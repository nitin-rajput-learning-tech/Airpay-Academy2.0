<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

namespace local_sentientia_aiquiz;

defined('MOODLE_INTERNAL') || die();

/**
 * Persistence layer for AI-quiz drafts and their questions.
 *
 * All writes go through this class so:
 *   - costcenterid + customerid are always populated from the actor
 *   - timecreated + timemodified are always set
 *   - status transitions follow the defined lifecycle
 *
 * Phase G.0 status lifecycle:
 *
 *   DRAFT:
 *     pending → generated → approved → pushed
 *                        ↘ rejected
 *                        ↘ failed (on API error)
 *
 *   QUESTION:
 *     generated → approved | edited | rejected
 *
 * @package local_sentientia_aiquiz
 */
class draft_manager {

    public const DRAFT_TABLE    = 'local_sentientia_aiquiz_draft';
    public const QUESTION_TABLE = 'local_sentientia_aiquiz_question';

    public const STATUS_PENDING   = 'pending';
    public const STATUS_GENERATED = 'generated';
    public const STATUS_APPROVED  = 'approved';
    public const STATUS_PUSHED    = 'pushed';
    public const STATUS_REJECTED  = 'rejected';
    public const STATUS_FAILED    = 'failed';

    public const Q_STATUS_GENERATED = 'generated';
    public const Q_STATUS_APPROVED  = 'approved';
    public const Q_STATUS_EDITED    = 'edited';
    public const Q_STATUS_REJECTED  = 'rejected';

    /**
     * Resolve the BizLMS tenant root (1, 77, 177, ...) from a user's open_path.
     *
     * Uses the production-compatible pattern from CLAUDE.md §rules/database.md.
     *
     * @param \stdClass|null $user A user record with `open_path` populated. NULL = global $USER.
     * @return int
     */
    public static function tenant_root_for(?\stdClass $user = null): int {
        global $USER;
        $u = $user ?? $USER;
        $path = isset($u->open_path) ? (string)$u->open_path : '';
        $parts = explode('/', trim($path, '/'));
        return (int)($parts[0] ?? 0);
    }

    /**
     * Create a pending draft row before the API call goes out.
     *
     * Phase G.1 (2026-05-25) — the resolved prompt_version is now a
     * caller-supplied argument so Hindi / customer-template drafts get
     * the right version tag recorded against them. Callers that don't
     * specify a version inherit the Phase G.0 default ('v1').
     *
     * @param int    $ownerid
     * @param int    $courseid       0 = not yet bound to a course
     * @param string $title
     * @param string $sourcetext
     * @param string $model
     * @param int    $numrequested
     * @param string $prompt_version Recorded on the draft row. One of:
     *                                'v1' | 'v2-hindi' | 'custom:v1' |
     *                                'custom:v2-hindi'. Defaults to 'v1'.
     * @return int Draft id
     */
    public static function create_pending(
        int $ownerid,
        int $courseid,
        string $title,
        string $sourcetext,
        string $model,
        int $numrequested,
        string $prompt_version = prompt_builder::VERSION_V1
    ): int {
        global $DB;

        $owner = $DB->get_record('user', ['id' => $ownerid], 'id, open_path', MUST_EXIST);
        $now = time();

        // Defensive: never persist an empty or oversize prompt_version
        // tag — the column is CHAR(32) so we cap at that length.
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
        $record->title          = $title === '' ? 'Untitled draft' : $title;
        $record->sourcetext     = $sourcetext;
        $record->model          = $model;
        $record->prompt_version = $version;
        $record->num_requested  = $numrequested;
        $record->num_generated  = 0;
        $record->tokens_in      = 0;
        $record->tokens_out     = 0;
        $record->status         = self::STATUS_PENDING;
        $record->error_detail   = null;
        $record->reviewed_by    = null;
        $record->reviewed_at    = null;
        $record->pushed_quizid  = null;
        $record->generated_at   = 0;
        $record->timecreated    = $now;
        $record->timemodified   = $now;

        return $DB->insert_record(self::DRAFT_TABLE, $record);
    }

    /**
     * Persist generation results into a draft + its questions in one txn.
     *
     * On success: status flips to `generated`, num_generated set,
     * tokens recorded, and each question inserted with sortorder 1..N.
     *
     * On parser-zero (Claude returned but no usable questions): status
     * flips to `failed` with error_detail = 'parser_no_questions'.
     *
     * @param int          $draftid
     * @param \stdClass[]  $questions   Output of response_parser::parse()
     * @param int          $tokensin
     * @param int          $tokensout
     * @param string       $mode        'mock' | 'live'
     * @return void
     */
    public static function persist_questions(
        int $draftid,
        array $questions,
        int $tokensin,
        int $tokensout,
        string $mode
    ): void {
        global $DB;

        $txn = $DB->start_delegated_transaction();
        try {
            $draft = $DB->get_record(self::DRAFT_TABLE, ['id' => $draftid], '*', MUST_EXIST);
            $now = time();

            $draft->tokens_in     = $tokensin;
            $draft->tokens_out    = $tokensout;
            $draft->generated_at  = $now;
            $draft->timemodified  = $now;
            $draft->num_generated = count($questions);

            if (count($questions) === 0) {
                $draft->status = self::STATUS_FAILED;
                $draft->error_detail = "parser_no_questions (mode={$mode})";
                $DB->update_record(self::DRAFT_TABLE, $draft);
                $txn->allow_commit();
                return;
            }

            $draft->status = self::STATUS_GENERATED;
            $draft->error_detail = null;
            $DB->update_record(self::DRAFT_TABLE, $draft);

            $sortorder = 1;
            foreach ($questions as $q) {
                $row = new \stdClass();
                $row->draftid       = $draftid;
                $row->qtype         = isset($q->qtype) ? (string)$q->qtype : 'multichoice';
                $row->qtext         = isset($q->qtext) ? (string)$q->qtext : '';
                $row->qoptions_json = isset($q->qoptions_json) ? (string)$q->qoptions_json : null;
                $row->qanswer      = isset($q->qanswer) ? (string)$q->qanswer : '';
                $row->qexplanation  = isset($q->qexplanation) ? (string)$q->qexplanation : null;
                $row->sortorder     = $sortorder++;
                $row->status        = self::Q_STATUS_GENERATED;
                $row->reviewer_note = null;
                $row->timecreated   = $now;
                $row->timemodified  = $now;
                $DB->insert_record(self::QUESTION_TABLE, $row);
            }

            $txn->allow_commit();
        } catch (\Throwable $e) {
            $txn->rollback($e);
        }
    }

    /**
     * Mark a draft as failed (no questions persisted).
     *
     * @param int    $draftid
     * @param string $errordetail Short tag — NEVER include API key text
     * @return void
     */
    public static function mark_failed(int $draftid, string $errordetail): void {
        global $DB;
        $now = time();
        $update = new \stdClass();
        $update->id           = $draftid;
        $update->status       = self::STATUS_FAILED;
        $update->error_detail = substr($errordetail, 0, 1000);
        $update->generated_at = $now;
        $update->timemodified = $now;
        $DB->update_record(self::DRAFT_TABLE, $update);
    }

    /**
     * Update a single question's review status. Used by review.php's per-question controls.
     *
     * Allowed transitions: from generated → approved | edited | rejected
     * Re-flipping is also allowed (approved → edited, edited → rejected, etc.)
     *
     * @param int    $questionid
     * @param string $newstatus    One of Q_STATUS_*
     * @param array  $updates      Optional ['qtext'=>..., 'qoptions_json'=>..., 'qanswer'=>..., 'qexplanation'=>..., 'reviewer_note'=>...]
     * @return void
     */
    public static function review_question(int $questionid, string $newstatus, array $updates = []): void {
        global $DB;

        $allowed = [
            self::Q_STATUS_APPROVED,
            self::Q_STATUS_EDITED,
            self::Q_STATUS_REJECTED,
            self::Q_STATUS_GENERATED, // un-review
        ];
        if (!in_array($newstatus, $allowed, true)) {
            throw new \coding_exception("Invalid question status: {$newstatus}");
        }

        $now = time();
        $update = new \stdClass();
        $update->id           = $questionid;
        $update->status       = $newstatus;
        $update->timemodified = $now;

        foreach (['qtext', 'qoptions_json', 'qanswer', 'qexplanation', 'reviewer_note'] as $field) {
            if (array_key_exists($field, $updates)) {
                $update->{$field} = $updates[$field];
            }
        }
        $DB->update_record(self::QUESTION_TABLE, $update);
    }

    /**
     * Mark the parent draft as fully reviewed.
     *
     * Sets status=approved if at least one question is in approved/edited;
     * otherwise rejected. Records reviewer_id + reviewed_at.
     *
     * @param int $draftid
     * @param int $reviewerid
     * @return string New draft status
     */
    public static function finalise_review(int $draftid, int $reviewerid): string {
        global $DB;
        $now = time();

        $usable = $DB->count_records_select(
            self::QUESTION_TABLE,
            'draftid = :did AND status IN (:s1, :s2)',
            ['did' => $draftid, 's1' => self::Q_STATUS_APPROVED, 's2' => self::Q_STATUS_EDITED]
        );

        $newstatus = $usable > 0 ? self::STATUS_APPROVED : self::STATUS_REJECTED;

        $update = new \stdClass();
        $update->id           = $draftid;
        $update->status       = $newstatus;
        $update->reviewed_by  = $reviewerid;
        $update->reviewed_at  = $now;
        $update->timemodified = $now;
        $DB->update_record(self::DRAFT_TABLE, $update);

        return $newstatus;
    }

    /**
     * Mark draft as pushed once its approved questions have been written
     * into a real mod_quiz activity.
     *
     * @param int $draftid
     * @param int $quizid mod_quiz.id
     * @return void
     */
    public static function mark_pushed(int $draftid, int $quizid): void {
        global $DB;
        $now = time();
        $update = new \stdClass();
        $update->id            = $draftid;
        $update->status        = self::STATUS_PUSHED;
        $update->pushed_quizid = $quizid;
        $update->timemodified  = $now;
        $DB->update_record(self::DRAFT_TABLE, $update);
    }

    /**
     * Load a draft with its questions, scoped to the actor's customer + tenant.
     *
     * Returns null if the draft doesn't exist OR the actor lacks access
     * (different customer/tenant AND no manage_all cap).
     *
     * @param int           $draftid
     * @param \stdClass     $actor   User attempting access (typically $USER)
     * @param bool          $manageall  Whether actor has :manage_all cap
     * @return \stdClass|null Object with ->draft + ->questions
     */
    public static function load_for_actor(int $draftid, \stdClass $actor, bool $manageall): ?\stdClass {
        global $DB;
        $draft = $DB->get_record(self::DRAFT_TABLE, ['id' => $draftid]);
        if (!$draft) {
            return null;
        }

        $actorroot = self::tenant_root_for($actor);
        if (!$manageall) {
            if ($draft->ownerid !== (int)$actor->id
                && $draft->costcenterid !== $actorroot) {
                return null;
            }
        }

        $questions = $DB->get_records(self::QUESTION_TABLE, ['draftid' => $draftid], 'sortorder ASC');
        $out = new \stdClass();
        $out->draft = $draft;
        $out->questions = array_values($questions);
        return $out;
    }

    /**
     * List recent drafts owned by an actor (or all drafts if manage_all).
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
                self::DRAFT_TABLE, [], 'timecreated DESC', '*', 0, $limit));
        }
        $tenant = self::tenant_root_for($actor);
        return array_values($DB->get_records_sql(
            "SELECT * FROM {" . self::DRAFT_TABLE . "}
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
               FROM {" . self::DRAFT_TABLE . "}
              WHERE ownerid = :uid AND timecreated >= :since",
            ['uid' => $userid, 'since' => $today]
        );
    }
}
