<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

namespace local_sentientia_skillsai;

defined('MOODLE_INTERNAL') || die();

/**
 * Persistence layer for extraction jobs, candidate skills, the human-review
 * gate, and promotion into the per-tenant canonical taxonomy.
 *
 * All writes go through this class so:
 *   - costcenterid + customerid are always populated from the actor
 *   - timecreated + timemodified are always set
 *   - status transitions follow the defined lifecycle
 *   - NO candidate becomes canonical taxonomy without passing the review
 *     gate (status approved|edited) and an explicit promote() call
 *
 * Lifecycle:
 *   JOB:       pending → extracted → reviewed
 *                                 ↘ failed (on API/parser error)
 *   CANDIDATE: proposed → approved | edited | rejected
 *              (only approved|edited are promotable; promote sets taxonomyid)
 *
 * @package local_sentientia_skillsai
 */
class taxonomy_manager {

    public const JOB_TABLE       = 'local_sentientia_skai_job';
    public const CAND_TABLE      = 'local_sentientia_skai_cand';
    public const TAXONOMY_TABLE  = 'local_sentientia_skai_taxonomy';

    public const STATUS_PENDING   = 'pending';
    public const STATUS_EXTRACTED = 'extracted';
    public const STATUS_REVIEWED  = 'reviewed';
    public const STATUS_FAILED    = 'failed';

    public const C_PROPOSED = 'proposed';
    public const C_APPROVED = 'approved';
    public const C_EDITED   = 'edited';
    public const C_REJECTED = 'rejected';

    public const TAX_ACTIVE  = 'active';
    public const TAX_RETIRED = 'retired';

    /** Valid source-kind tags for a job. */
    public const SOURCE_KINDS = ['scorm', 'narration', 'sop', 'manual'];

    /**
     * Resolve the BizLMS tenant root from a user's open_path.
     *
     * @param \stdClass|null $user
     * @return int
     */
    public static function tenant_root_for(?\stdClass $user = null): int {
        global $USER;
        $u = $user ?? $USER;
        $path = isset($u->open_path) ? (string)$u->open_path : '';
        $parts = explode('/', trim($path, '/'));
        return (int)($parts[0] ?? 0);
    }

    // ──────────────────────────────────────────────────────────────────
    //  JOBS
    // ──────────────────────────────────────────────────────────────────

    /**
     * Create a pending extraction job before the API call goes out.
     *
     * @param int    $ownerid
     * @param int    $courseid       0 = not bound to a course
     * @param string $title
     * @param string $sourcekind     one of SOURCE_KINDS
     * @param string $sourcetext
     * @param string $model
     * @param string $prompt_version
     * @return int Job id
     */
    public static function create_pending(
        int $ownerid,
        int $courseid,
        string $title,
        string $sourcekind,
        string $sourcetext,
        string $model,
        string $prompt_version = prompt_builder::VERSION_V1
    ): int {
        global $DB;

        $owner = $DB->get_record('user', ['id' => $ownerid], 'id, open_path', MUST_EXIST);
        $now = time();

        $kind = in_array($sourcekind, self::SOURCE_KINDS, true) ? $sourcekind : 'manual';

        $version = trim($prompt_version);
        if ($version === '') {
            $version = prompt_builder::VERSION_V1;
        }
        if (strlen($version) > 32) {
            $version = substr($version, 0, 32);
        }

        $record = new \stdClass();
        $record->courseid       = $courseid;
        $record->ownerid        = $ownerid;
        $record->customerid     = 1;  // Phase 1: hardcoded Airpay.
        $record->costcenterid   = self::tenant_root_for($owner);
        $record->title          = $title === '' ? 'Untitled extraction' : $title;
        $record->sourcekind     = $kind;
        $record->sourcetext     = $sourcetext;
        $record->model          = $model;
        $record->prompt_version = $version;
        $record->num_extracted  = 0;
        $record->tokens_in      = 0;
        $record->tokens_out     = 0;
        $record->status         = self::STATUS_PENDING;
        $record->error_detail   = null;
        $record->reviewed_by    = null;
        $record->reviewed_at    = null;
        $record->extracted_at   = 0;
        $record->timecreated    = $now;
        $record->timemodified   = $now;

        return $DB->insert_record(self::JOB_TABLE, $record);
    }

    /**
     * Persist extraction results into a job + its candidate skills (one txn).
     *
     * @param int         $jobid
     * @param \stdClass[] $skills   Output of response_parser::parse()
     * @param int         $tokensin
     * @param int         $tokensout
     * @param string      $mode     'mock' | 'live'
     * @return void
     */
    public static function persist_candidates(
        int $jobid,
        array $skills,
        int $tokensin,
        int $tokensout,
        string $mode
    ): void {
        global $DB;

        $txn = $DB->start_delegated_transaction();
        try {
            $job = $DB->get_record(self::JOB_TABLE, ['id' => $jobid], '*', MUST_EXIST);
            $now = time();

            $job->tokens_in    = $tokensin;
            $job->tokens_out   = $tokensout;
            $job->extracted_at = $now;
            $job->timemodified = $now;
            $job->num_extracted = count($skills);

            if (count($skills) === 0) {
                $job->status = self::STATUS_FAILED;
                $job->error_detail = "parser_no_skills (mode={$mode})";
                $DB->update_record(self::JOB_TABLE, $job);
                $txn->allow_commit();
                return;
            }

            $job->status = self::STATUS_EXTRACTED;
            $job->error_detail = null;
            $DB->update_record(self::JOB_TABLE, $job);

            $sortorder = 1;
            foreach ($skills as $s) {
                $row = new \stdClass();
                $row->jobid              = $jobid;
                $row->costcenterid       = (int)$job->costcenterid;
                $row->skillname          = isset($s->name) ? (string)$s->name : '';
                $row->skilldescription   = isset($s->description) ? (string)$s->description : null;
                $row->suggested_category = isset($s->category) ? (string)$s->category : '';
                $row->suggested_level    = isset($s->level) ? (int)$s->level : 1;
                $row->confidence         = isset($s->confidence) ? (float)$s->confidence : 0;
                $row->evidence           = isset($s->evidence) ? (string)$s->evidence : null;
                $row->status             = self::C_PROPOSED;
                $row->reviewer_note      = null;
                $row->taxonomyid         = null;
                $row->sortorder          = $sortorder++;
                $row->timecreated        = $now;
                $row->timemodified       = $now;
                $DB->insert_record(self::CAND_TABLE, $row);
            }

            $txn->allow_commit();
        } catch (\Throwable $e) {
            $txn->rollback($e);
        }
    }

    /**
     * Mark a job as failed (no candidates persisted).
     *
     * @param int    $jobid
     * @param string $errordetail Short tag — NEVER includes API key text
     * @return void
     */
    public static function mark_failed(int $jobid, string $errordetail): void {
        global $DB;
        $now = time();
        $update = new \stdClass();
        $update->id           = $jobid;
        $update->status       = self::STATUS_FAILED;
        $update->error_detail = substr($errordetail, 0, 1000);
        $update->extracted_at = $now;
        $update->timemodified = $now;
        $DB->update_record(self::JOB_TABLE, $update);
    }

    // ──────────────────────────────────────────────────────────────────
    //  CANDIDATE REVIEW GATE
    // ──────────────────────────────────────────────────────────────────

    /**
     * Update a single candidate's review verdict. Used by the review UI.
     *
     * Allowed verdicts: approved | edited | rejected | proposed (un-review).
     * Optional $updates may edit the candidate fields the reviewer changed.
     *
     * @param int    $candidateid
     * @param string $newstatus
     * @param array  $updates  ['skillname'=>, 'skilldescription'=>, 'suggested_category'=>, 'suggested_level'=>, 'reviewer_note'=>]
     * @return void
     */
    public static function review_candidate(int $candidateid, string $newstatus, array $updates = []): void {
        global $DB;

        $allowed = [self::C_APPROVED, self::C_EDITED, self::C_REJECTED, self::C_PROPOSED];
        if (!in_array($newstatus, $allowed, true)) {
            throw new \coding_exception("Invalid candidate status: {$newstatus}");
        }

        $now = time();
        $update = new \stdClass();
        $update->id           = $candidateid;
        $update->status       = $newstatus;
        $update->timemodified = $now;

        foreach (['skillname', 'skilldescription', 'suggested_category', 'reviewer_note'] as $field) {
            if (array_key_exists($field, $updates)) {
                $update->{$field} = $updates[$field];
            }
        }
        if (array_key_exists('suggested_level', $updates)) {
            $lvl = (int)$updates['suggested_level'];
            $update->suggested_level = max(1, min(5, $lvl));
        }
        $DB->update_record(self::CAND_TABLE, $update);
    }

    /**
     * Promote an approved/edited candidate into the canonical per-tenant
     * taxonomy. This is the audited bridge from AI output to canonical
     * data — only callable on a candidate whose review verdict is
     * approved or edited.
     *
     * Idempotent on (tenant, skill name): if a taxonomy node already exists
     * for the tenant + name, the candidate is linked to it rather than
     * creating a duplicate.
     *
     * @param int $candidateid
     * @param int $approverid Reviewer performing the promotion
     * @return int Taxonomy node id
     * @throws \moodle_exception when the candidate is not in an approvable state
     */
    public static function promote_candidate(int $candidateid, int $approverid): int {
        global $DB;

        $txn = $DB->start_delegated_transaction();
        try {
            $cand = $DB->get_record(self::CAND_TABLE, ['id' => $candidateid], '*', MUST_EXIST);

            if (!in_array($cand->status, [self::C_APPROVED, self::C_EDITED], true)) {
                throw new \moodle_exception('err_candidate_not_approved', 'local_sentientia_skillsai');
            }

            // Already promoted? Return the existing node.
            if (!empty($cand->taxonomyid)) {
                $txn->allow_commit();
                return (int)$cand->taxonomyid;
            }

            $job = $DB->get_record(self::JOB_TABLE, ['id' => $cand->jobid], '*', MUST_EXIST);
            $tenant = (int)$cand->costcenterid;
            $name = trim((string)$cand->skillname);
            $now = time();

            // Idempotent on (tenant, name).
            $existing = $DB->get_record(self::TAXONOMY_TABLE, [
                'costcenterid' => $tenant,
                'name'         => $name,
            ]);

            if ($existing) {
                $taxonomyid = (int)$existing->id;
            } else {
                $node = new \stdClass();
                $node->customerid        = (int)$job->customerid;
                $node->costcenterid      = $tenant;
                $node->name              = $name;
                $node->description       = $cand->skilldescription;
                $node->category          = (string)$cand->suggested_category;
                $node->max_level         = 5;
                $node->origin_candidateid = $candidateid;
                $node->approved_by       = $approverid;
                $node->linked_skillid    = null;
                $node->status            = self::TAX_ACTIVE;
                $node->timecreated       = $now;
                $node->timemodified      = $now;
                $taxonomyid = $DB->insert_record(self::TAXONOMY_TABLE, $node);
            }

            // Link the candidate to the node.
            $DB->update_record(self::CAND_TABLE, (object)[
                'id'           => $candidateid,
                'taxonomyid'   => $taxonomyid,
                'timemodified' => $now,
            ]);

            $txn->allow_commit();
            return $taxonomyid;
        } catch (\Throwable $e) {
            $txn->rollback($e);
            return 0; // unreachable — rollback rethrows.
        }
    }

    /**
     * Finalise a job's review: mark it reviewed + record reviewer.
     *
     * @param int $jobid
     * @param int $reviewerid
     * @return void
     */
    public static function finalise_review(int $jobid, int $reviewerid): void {
        global $DB;
        $now = time();
        $update = new \stdClass();
        $update->id           = $jobid;
        $update->status       = self::STATUS_REVIEWED;
        $update->reviewed_by  = $reviewerid;
        $update->reviewed_at  = $now;
        $update->timemodified = $now;
        $DB->update_record(self::JOB_TABLE, $update);
    }

    // ──────────────────────────────────────────────────────────────────
    //  TAXONOMY CURATION
    // ──────────────────────────────────────────────────────────────────

    /**
     * Edit a canonical taxonomy node (name/description/category/level/status).
     *
     * @param int   $taxonomyid
     * @param array $updates ['name'=>, 'description'=>, 'category'=>, 'max_level'=>, 'status'=>]
     * @return void
     */
    public static function update_taxonomy(int $taxonomyid, array $updates): void {
        global $DB;
        $now = time();
        $update = new \stdClass();
        $update->id           = $taxonomyid;
        $update->timemodified = $now;

        foreach (['name', 'description', 'category'] as $field) {
            if (array_key_exists($field, $updates)) {
                $update->{$field} = $updates[$field];
            }
        }
        if (array_key_exists('max_level', $updates)) {
            $update->max_level = max(1, min(5, (int)$updates['max_level']));
        }
        if (array_key_exists('status', $updates)
                && in_array($updates['status'], [self::TAX_ACTIVE, self::TAX_RETIRED], true)) {
            $update->status = $updates['status'];
        }
        $DB->update_record(self::TAXONOMY_TABLE, $update);
    }

    /**
     * List the active canonical taxonomy for a tenant.
     *
     * @param int $costcenterid
     * @return \stdClass[]
     */
    public static function list_taxonomy(int $costcenterid): array {
        global $DB;
        return array_values($DB->get_records(self::TAXONOMY_TABLE, [
            'costcenterid' => $costcenterid,
            'status'       => self::TAX_ACTIVE,
        ], 'category ASC, name ASC'));
    }

    /**
     * Load a job + its candidates, scoped to the actor's tenant.
     *
     * Returns null if the job doesn't exist OR the actor lacks access
     * (different tenant AND not owner AND no manage_all cap).
     *
     * @param int       $jobid
     * @param \stdClass $actor
     * @param bool      $manageall
     * @return \stdClass|null Object with ->job + ->candidates
     */
    public static function load_for_actor(int $jobid, \stdClass $actor, bool $manageall): ?\stdClass {
        global $DB;
        $job = $DB->get_record(self::JOB_TABLE, ['id' => $jobid]);
        if (!$job) {
            return null;
        }

        $actorroot = self::tenant_root_for($actor);
        if (!$manageall) {
            if ((int)$job->ownerid !== (int)$actor->id
                && (int)$job->costcenterid !== $actorroot) {
                return null;
            }
        }

        $candidates = $DB->get_records(self::CAND_TABLE, ['jobid' => $jobid], 'sortorder ASC');
        $out = new \stdClass();
        $out->job = $job;
        $out->candidates = array_values($candidates);
        return $out;
    }

    /**
     * List recent jobs for an actor (or all jobs if manage_all).
     *
     * @param \stdClass $actor
     * @param bool      $manageall
     * @param int       $limit
     * @return \stdClass[]
     */
    public static function list_for_actor(\stdClass $actor, bool $manageall, int $limit = 50): array {
        global $DB;
        if ($manageall) {
            return array_values($DB->get_records(
                self::JOB_TABLE, [], 'timecreated DESC', '*', 0, $limit));
        }
        $tenant = self::tenant_root_for($actor);
        return array_values($DB->get_records_sql(
            "SELECT * FROM {" . self::JOB_TABLE . "}
              WHERE ownerid = :uid OR costcenterid = :cid
           ORDER BY timecreated DESC",
            ['uid' => (int)$actor->id, 'cid' => $tenant],
            0, $limit
        ));
    }

    /**
     * Sum tokens used by an actor today. Drives the per-user soft cap.
     *
     * @param int $userid
     * @return int
     */
    public static function tokens_used_today(int $userid): int {
        global $DB;
        $today = strtotime('today');
        return (int)$DB->get_field_sql(
            "SELECT COALESCE(SUM(tokens_in + tokens_out), 0)
               FROM {" . self::JOB_TABLE . "}
              WHERE ownerid = :uid AND timecreated >= :since",
            ['uid' => $userid, 'since' => $today]
        );
    }
}
