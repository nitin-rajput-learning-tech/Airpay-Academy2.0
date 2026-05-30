<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

namespace local_sentientia_recommendations;

defined('MOODLE_INTERNAL') || die();

/**
 * Persistence + retrieval layer for AI course recommendations.
 *
 * All writes go through this class so:
 *   - costcenterid + customerid are always populated from the actor
 *   - timecreated + timemodified are always set
 *   - status transitions follow the defined lifecycle
 *   - batchid groups all rows from a single Claude call
 *
 * Phase H.0 status lifecycle:
 *
 *   RECOMMENDATION:
 *     active → dismissed | enrolled | expired
 *
 *     active    — visible in the dashboard block
 *     dismissed — learner clicked "not interested"
 *     enrolled  — learner enrolled in this course (cron updates)
 *     expired   — newer batch superseded this row
 *
 * @package local_sentientia_recommendations
 */
class recommendation_engine {

    public const TABLE = 'local_sentientia_rec_log';

    public const STATUS_ACTIVE    = 'active';
    public const STATUS_DISMISSED = 'dismissed';
    public const STATUS_ENROLLED  = 'enrolled';
    public const STATUS_EXPIRED   = 'expired';

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
        // ADR-018 Wave 2: tenant root via the Sentientia seam.
        return \local_sentientia_core\tenant_identity::root_for_user($u);
    }

    /**
     * Build a learner-profile object from the user record + completion history.
     *
     * The output object is the input to {@see prompt_builder::build_user_message}.
     *
     * @param int $userid
     * @return \stdClass {role, tenant, skills, completed}
     */
    public static function build_profile(int $userid): \stdClass {
        global $DB;

        $profile = new \stdClass();
        $profile->role      = 'learner';
        $profile->tenant    = 'unknown';
        $profile->skills    = [];
        $profile->completed = [];

        $user = $DB->get_record('user', ['id' => $userid], 'id, open_path', IGNORE_MISSING);
        if (!$user) {
            return $profile;
        }
        $tenantroot = self::tenant_root_for($user);
        $profile->tenant = (string)$tenantroot;

        // Completed course IDs — capped at MAX_HISTORY_ITEMS by the prompt
        // builder, so we don't need to cap here, but we do bound the query.
        try {
            $rows = $DB->get_records_select(
                'course_completions',
                'userid = :uid AND timecompleted > 0',
                ['uid' => $userid],
                'timecompleted DESC',
                'id, course, timecompleted',
                0,
                prompt_builder::MAX_HISTORY_ITEMS
            );
            foreach ($rows as $r) {
                $profile->completed[] = (int)$r->course;
            }
        } catch (\Throwable $e) {
            // Table missing in unit-test sandbox — leave completed empty.
        }

        return $profile;
    }

    /**
     * Build a candidate-course list (a learner's visible catalog) with
     * already-completed courses filtered out.
     *
     * @param \stdClass $profile  Learner profile (uses ->completed)
     * @param int       $maxitems Hard cap on candidates returned
     * @return array Array of objects with ->id, ->fullname, ->shortname, ->summary
     */
    public static function build_candidate_list(\stdClass $profile, int $maxitems = 100): array {
        global $DB;

        $maxitems = max(1, min(prompt_builder::MAX_CANDIDATE_COURSES, $maxitems));
        $completed = isset($profile->completed) && is_array($profile->completed)
            ? array_map('intval', $profile->completed) : [];

        $candidates = $DB->get_records_select(
            'course',
            'visible = 1 AND id > 1',
            null,
            'fullname ASC',
            'id, fullname, shortname, summary',
            0,
            $maxitems
        );

        // Filter out completed courses.
        $out = [];
        foreach ($candidates as $c) {
            if (in_array((int)$c->id, $completed, true)) {
                continue;
            }
            $out[] = $c;
        }
        return $out;
    }

    /**
     * Persist a generated batch.
     *
     * Marks any previously-active batch for the user as `expired`, then
     * inserts one row per parsed recommendation with status `active`.
     *
     * On parser-zero (Claude returned but no usable recommendations):
     * nothing is persisted — the caller logs the failure.
     *
     * @param int         $userid
     * @param \stdClass[] $parsed       Output of response_parser::parse() — each item has ->course_id, ->score, ->reasoning
     * @param int         $tokensin
     * @param int         $tokensout
     * @param string      $mode         'mock' | 'live'
     * @param string      $model
     * @return string  The batchid used for this batch ('' if nothing persisted)
     */
    public static function persist_batch(
        int $userid,
        array $parsed,
        int $tokensin,
        int $tokensout,
        string $mode,
        string $model = anthropic_client::DEFAULT_MODEL
    ): string {
        global $DB;

        if (empty($parsed)) {
            return '';
        }

        $user = $DB->get_record('user', ['id' => $userid], 'id, open_path', IGNORE_MISSING);
        $tenantroot = $user ? self::tenant_root_for($user) : 0;

        // Generate a stable batchid (uuid-ish). uniqid alone is not unique
        // across processes — combine with random_bytes for safety.
        $batchid = self::generate_batchid();
        $now = time();

        // Pro-rata tokens across rows (rough but useful for cost analytics).
        $count = count($parsed);
        $tokens_in_each  = (int)floor($tokensin / max(1, $count));
        $tokens_out_each = (int)floor($tokensout / max(1, $count));

        $txn = $DB->start_delegated_transaction();
        try {
            // Mark prior active batches as expired.
            $DB->execute(
                "UPDATE {" . self::TABLE . "}
                    SET status = :exp, timemodified = :now
                  WHERE userid = :uid AND status = :act",
                [
                    'exp' => self::STATUS_EXPIRED,
                    'now' => $now,
                    'uid' => $userid,
                    'act' => self::STATUS_ACTIVE,
                ]
            );

            $rank = 1;
            foreach ($parsed as $item) {
                $cid   = isset($item->course_id) ? (int)$item->course_id : 0;
                $score = isset($item->score)     ? max(0, min(100, (int)$item->score)) : 0;
                $reason = isset($item->reasoning) ? (string)$item->reasoning : null;
                if ($cid <= 0) {
                    continue;
                }

                $row = new \stdClass();
                $row->userid         = $userid;
                $row->courseid       = $cid;
                $row->customerid     = 1;  // Phase 1: hardcoded Airpay.
                $row->costcenterid   = $tenantroot;
                $row->batchid        = $batchid;
                $row->model          = $model;
                $row->prompt_version = prompt_builder::VERSION;
                $row->score          = $score;
                $row->rank_order     = $rank++;
                $row->reasoning      = $reason;
                $row->mode           = $mode === 'live' ? 'live' : 'mock';
                $row->tokens_in      = $tokens_in_each;
                $row->tokens_out     = $tokens_out_each;
                $row->status         = self::STATUS_ACTIVE;
                $row->generated_at   = $now;
                $row->timecreated    = $now;
                $row->timemodified   = $now;
                $DB->insert_record(self::TABLE, $row);
            }

            $txn->allow_commit();
        } catch (\Throwable $e) {
            $txn->rollback($e);
            return '';
        }

        return $batchid;
    }

    /**
     * Load the most recent active recommendation batch for a learner.
     *
     * Returns an empty array when no active batch exists.
     *
     * @param int $userid
     * @param int $limit  Cap on rows returned (block typically asks for 3-5)
     * @return \stdClass[] Rows from the recommendations_log table, ordered by rank_order
     */
    public static function latest_for_user(int $userid, int $limit = 5): array {
        global $DB;
        $limit = max(1, min(prompt_builder::MAX_RECOMMENDATIONS, $limit));

        // Find the most recent active batchid for this user.
        $row = $DB->get_record_sql(
            "SELECT batchid, MAX(generated_at) AS gen
               FROM {" . self::TABLE . "}
              WHERE userid = :uid AND status = :st
           GROUP BY batchid
           ORDER BY gen DESC",
            ['uid' => $userid, 'st' => self::STATUS_ACTIVE],
            IGNORE_MULTIPLE
        );
        if (!$row || empty($row->batchid)) {
            return [];
        }

        $rows = $DB->get_records(
            self::TABLE,
            ['userid' => $userid, 'batchid' => $row->batchid, 'status' => self::STATUS_ACTIVE],
            'rank_order ASC',
            '*',
            0,
            $limit
        );

        return array_values($rows);
    }

    /**
     * Flip a single recommendation row to dismissed / enrolled / expired.
     *
     * @param int    $recid
     * @param int    $userid    Ownership check — caller must be the learner
     * @param string $newstatus
     * @return bool true on success
     */
    public static function update_status(int $recid, int $userid, string $newstatus): bool {
        global $DB;

        $allowed = [self::STATUS_DISMISSED, self::STATUS_ENROLLED, self::STATUS_EXPIRED, self::STATUS_ACTIVE];
        if (!in_array($newstatus, $allowed, true)) {
            throw new \coding_exception("Invalid recommendation status: {$newstatus}");
        }

        $row = $DB->get_record(self::TABLE, ['id' => $recid], 'id, userid', IGNORE_MISSING);
        if (!$row) {
            return false;
        }
        if ((int)$row->userid !== $userid) {
            // Ownership mismatch — refuse silently to prevent enumeration.
            return false;
        }

        $upd = new \stdClass();
        $upd->id           = $recid;
        $upd->status       = $newstatus;
        $upd->timemodified = time();
        $DB->update_record(self::TABLE, $upd);
        return true;
    }

    /**
     * Sum tokens consumed by recommendation generation for the entire
     * customer, today. Drives the per-customer daily cost cap.
     *
     * @param int $customerid
     * @return int
     */
    public static function tokens_used_today_for_customer(int $customerid): int {
        global $DB;
        $today = strtotime('today');
        return (int)$DB->get_field_sql(
            "SELECT COALESCE(SUM(tokens_in + tokens_out), 0)
               FROM {" . self::TABLE . "}
              WHERE customerid = :cid AND timecreated >= :since",
            ['cid' => $customerid, 'since' => $today]
        );
    }

    /**
     * Sum tokens consumed by recommendation generation for a single user,
     * today. Useful for per-user diagnostics and admin debug.
     *
     * @param int $userid
     * @return int
     */
    public static function tokens_used_today_for_user(int $userid): int {
        global $DB;
        $today = strtotime('today');
        return (int)$DB->get_field_sql(
            "SELECT COALESCE(SUM(tokens_in + tokens_out), 0)
               FROM {" . self::TABLE . "}
              WHERE userid = :uid AND timecreated >= :since",
            ['uid' => $userid, 'since' => $today]
        );
    }

    /**
     * Generate a stable, opaque batchid string (32 hex chars).
     *
     * @return string
     */
    private static function generate_batchid(): string {
        try {
            return bin2hex(random_bytes(16));
        } catch (\Throwable $e) {
            // Fallback — should never happen on a modern PHP install.
            return md5(uniqid('rec', true));
        }
    }
}
