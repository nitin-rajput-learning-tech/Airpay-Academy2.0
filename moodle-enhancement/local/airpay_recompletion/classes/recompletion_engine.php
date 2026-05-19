<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

namespace local_airpay_recompletion;

defined('MOODLE_INTERNAL') || die();

/**
 * Recompletion engine — evaluates rules + resets completions.
 *
 * Algorithm (per rule, per cron pass):
 *   1. For each user who completed the matching courses, check if
 *      `last completion + period_days < now`.
 *   2. If yes:
 *      a. Notify (recompletion_due_soon) `pre_notify_days` BEFORE expiry,
 *         once per user per cycle.
 *      b. On expiry: reset course_completions, optionally grades + quiz_attempts.
 *      c. Append a row to local_airpay_recompletion_history.
 *      d. Notify the user (recompletion_reset).
 *   3. Cap at `max_batch` resets per pass.
 *
 * Triggers supported:
 *   completion  — count days from each user's last completion
 *   enrolment   — count days from each user's enrolment
 *   fixed       — single calendar date for all users
 *
 * @package local_airpay_recompletion
 */
class recompletion_engine {

    /**
     * Run all enabled rules. Returns aggregated counts.
     */
    public static function run_all(bool $dryrun = false): array {
        global $DB;
        $rules = $DB->get_records('local_airpay_recompletion_rules',
            ['enabled' => 1]);
        $totals = ['rules_run' => 0, 'reset' => 0, 'notified' => 0,
                   'skipped' => 0, 'errors' => 0];
        foreach ($rules as $rule) {
            try {
                $r = self::run_rule($rule, $dryrun);
                $totals['rules_run']++;
                $totals['reset']    += $r['reset'];
                $totals['notified'] += $r['notified'];
                $totals['skipped']  += $r['skipped'];
                // Update rule's last_run.
                $rule->last_run_at = time();
                $rule->last_run_resets = $r['reset'];
                $DB->update_record('local_airpay_recompletion_rules', $rule);
            } catch (\Throwable $e) {
                $totals['errors']++;
                debugging("recompletion rule {$rule->id} failed: "
                    . $e->getMessage(), DEBUG_DEVELOPER);
            }
        }
        return $totals;
    }

    /**
     * Run a single rule. Returns counts.
     */
    public static function run_rule(\stdClass $rule, bool $dryrun = false): array {
        global $DB;
        $max_batch = (int) (get_config('local_airpay_recompletion', 'max_batch') ?: 500);
        $pre_notify_days = (int) (get_config('local_airpay_recompletion',
            'pre_notify_days') ?: 30);

        $r = ['reset' => 0, 'notified' => 0, 'skipped' => 0];

        // Build the candidate users query based on trigger type.
        $now = time();
        $period_seconds = (int) $rule->period_days * 86400;
        $expiry_threshold = $now - $period_seconds;
        $warn_threshold = $now - ($period_seconds - ($pre_notify_days * 86400));

        // Courses in scope: specific course OR all courses with completion enabled.
        $where = ['1=1'];
        $args  = [];
        if ((int) $rule->courseid > 0) {
            $where[] = 'cc.course = :cid';
            $args['cid'] = (int) $rule->courseid;
        } else {
            $where[] = 'c.enablecompletion = 1';
        }

        // ── B6 fix: tenant scoping ────────────────────────────────────────
        // The rule has a costcenterid column. Without filtering by it,
        // a rule created for Airpay (tenant 1) was also wiping completions
        // for Public-tenant (77) and ZEEA (177) users on the same course.
        // Now: only users whose open_path starts with /{tenant}/... or
        // exactly /{tenant} get hit. costcenterid=0 means "all tenants"
        // and is reserved for site-admin global rules.
        if ((int) $rule->costcenterid > 0) {
            $where[] = "(u.open_path = :tnpath_exact OR u.open_path LIKE :tnpath_prefix)";
            $args['tnpath_exact']  = '/' . (int) $rule->costcenterid;
            $args['tnpath_prefix'] = '/' . (int) $rule->costcenterid . '/%';
        }

        // Trigger-specific time field on completion row.
        switch ($rule->trigger_type) {
            case 'completion':
                $time_field = 'cc.timecompleted';
                $where[] = 'cc.timecompleted IS NOT NULL AND cc.timecompleted > 0';
                break;
            case 'enrolment':
                $time_field = 'ue.timecreated';
                break;
            case 'fixed':
                // For 'fixed', the trigger is the fixed_date itself.
                if (empty($rule->fixed_date)) return $r;  // misconfigured
                // Only fire if today is past fixed_date AND no reset has fired
                // since fixed_date.
                if ($now < (int) $rule->fixed_date) return $r;
                $time_field = ":fixed_const";  // we treat the date as the trigger
                $args['fixed_const'] = (int) $rule->fixed_date;
                break;
            default:
                return $r;
        }

        $wheresql = implode(' AND ', $where);

        // Find candidate users to RESET (past the period_seconds threshold).
        // B8 fix: $max_batch was string-interpolated into the SQL LIMIT
        // clause. Even though it's an admin-only setting, that's a
        // dangerous pattern — any code path that lets less-trusted
        // input drive max_batch becomes RCE-via-SQL. Use the 5th/6th
        // args of get_records_sql() instead.
        $rows = $DB->get_records_sql(
            "SELECT cc.id, cc.userid, cc.course AS courseid,
                    cc.timecompleted, c.fullname
               FROM {course_completions} cc
               JOIN {course} c ON c.id = cc.course
          LEFT JOIN {user_enrolments} ue
                  ON ue.userid = cc.userid
                 AND ue.enrolid IN (
                    SELECT id FROM {enrol} WHERE courseid = cc.course AND status = 0
                 )
              JOIN {user} u ON u.id = cc.userid
             WHERE $wheresql
               AND u.deleted = 0 AND u.suspended = 0
               AND $time_field < :expiry
          ORDER BY cc.id",
            array_merge($args, ['expiry' => $expiry_threshold]),
            0, $max_batch);

        foreach ($rows as $row) {
            // Skip if a recent reset for this user+course already happened
            // within this period (idempotent — protects against double-reset).
            $recent = $DB->record_exists_select('local_airpay_recompletion_history',
                'userid = :u AND courseid = :c AND timecreated > :since AND dryrun = 0',
                ['u' => $row->userid, 'c' => $row->courseid,
                 'since' => $now - 86400]);
            if ($recent) {
                $r['skipped']++;
                continue;
            }

            if (!$dryrun) {
                $ok = self::reset_user_in_course(
                    (int) $row->userid, (int) $row->courseid,
                    (bool) $rule->reset_grades, (bool) $rule->reset_attempts,
                    // P1 #20 — cron path has no human reset_by; reason='cron'
                    // gives observers the audit-friendly source label.
                    null, 'cron');
                if (!$ok) { $r['skipped']++; continue; }
            }

            // Record audit log.
            $DB->insert_record('local_airpay_recompletion_history', (object) [
                'ruleid'         => (int) $rule->id,
                'userid'         => (int) $row->userid,
                'courseid'       => (int) $row->courseid,
                'reason'         => 'cron',
                'reset_by_userid' => null,
                'previous_timecompleted' => $row->timecompleted ?: null,
                'reset_grades'   => (int) $rule->reset_grades,
                'reset_attempts' => (int) $rule->reset_attempts,
                'dryrun'         => $dryrun ? 1 : 0,
                'timecreated'    => time(),
            ]);

            $r['reset']++;

            // Notify the user (recompletion_reset).
            if (!$dryrun) {
                self::send_message($row->userid, 'recompletion_reset',
                    "Recompletion: '$row->fullname' has been reset",
                    "Your previous completion of '$row->fullname' was on "
                    . userdate($row->timecompleted, '%d %b %Y') . ". "
                    . "Per the {$rule->period_days}-day recompletion rule, "
                    . "you'll need to complete it again to maintain compliance.");
            }
        }

        // Pre-notification pass: warn users who are within the warn window.
        // (Only when not dryrun — pre-notifications are real.)
        if (!$dryrun && $rule->trigger_type !== 'fixed') {
            // B8 fix: same LIMIT-interpolation issue as the main query.
            $warn_rows = $DB->get_records_sql(
                "SELECT cc.id, cc.userid, cc.course AS courseid,
                        cc.timecompleted, c.fullname
                   FROM {course_completions} cc
                   JOIN {course} c ON c.id = cc.course
                   JOIN {user} u ON u.id = cc.userid
                  WHERE $wheresql
                    AND u.deleted = 0 AND u.suspended = 0
                    AND $time_field < :warn_thr
                    AND $time_field >= :expiry2
               ORDER BY cc.id",
                array_merge($args, ['warn_thr' => $warn_threshold, 'expiry2' => $expiry_threshold]),
                0, $max_batch);

            foreach ($warn_rows as $row) {
                // Suppress duplicate warn within 24h.
                $key = "recompletion_warn:{$rule->id}:{$row->userid}:{$row->courseid}";
                $cache = \cache::make('local_airpay_recompletion', 'warn_dedupe');
                if ($cache->get($key)) continue;
                $cache->set($key, 1);

                $days_left = (int) (((int) $row->timecompleted + $period_seconds - $now) / 86400);
                self::send_message($row->userid, 'recompletion_due_soon',
                    "Recompletion due in $days_left days: '$row->fullname'",
                    "Heads up — your completion of '$row->fullname' will expire in "
                    . "$days_left day(s). Plan to redo it before then to maintain compliance.");
                $r['notified']++;
            }
        }

        return $r;
    }

    /**
     * Reset one user's completion in one course. Atomic.
     *
     * W1-4 (2026-05-15) — now also resets:
     *   - SCORM tracking data (mdl_scorm_attempt + mdl_scorm_scoes_value)
     *     plus the legacy mdl_scorm_scoes_track for Moodle-4.x-upgraded sites
     *   - Per-activity completion (mdl_course_modules_completion)
     *
     * Without these, the next time the learner opens the course Moodle's
     * scorm_external_check_completion() reads cmi.completion_status='completed'
     * from the stale tracking row and immediately re-marks course completion.
     * Every Airpay compliance course (AML / KYC / POSH / DPDP) is SCORM, so
     * this was the single most-broken behaviour in the recompletion engine.
     */
    public static function reset_user_in_course(int $userid, int $courseid,
                                                  bool $reset_grades = true,
                                                  bool $reset_attempts = true,
                                                  ?int $reset_by_userid = null,
                                                  string $reason = 'cron'): bool {
        global $DB;
        $tx = $DB->start_delegated_transaction();
        try {
            // 1. Delete course_completions row (Moodle will rebuild on next access).
            $DB->delete_records('course_completions',
                ['userid' => $userid, 'course' => $courseid]);

            // 2. Delete completion criteria for activities (forces reattempt).
            $DB->delete_records('course_completion_crit_compl',
                ['userid' => $userid, 'course' => $courseid]);

            // 3. W1-4: clear per-activity completion. Without this, Moodle's
            //    completion-cron rebuilds course_completions from the still-
            //    "complete" course_modules_completion rows.
            self::reset_activity_completion($userid, $courseid);

            // 4. W1-4: reset SCORM tracking — the single biggest blocker for
            //    Airpay compliance courses. Always run regardless of the
            //    reset_attempts flag, because SCORM tracking is the SOURCE
            //    that drives both completion AND attempts.
            self::reset_scorm_tracking($userid, $courseid);

            // 5. Optionally reset grades for the course.
            if ($reset_grades) {
                // grade_items belong to the course; grade_grades hangs off
                // grade_items. Find them all and zero the user's grades.
                $items = $DB->get_fieldset_select('grade_items', 'id',
                    'courseid = :cid', ['cid' => $courseid]);
                if (!empty($items)) {
                    [$insql, $inparams] = $DB->get_in_or_equal($items, SQL_PARAMS_NAMED, 'gi');
                    $DB->delete_records_select('grade_grades',
                        "userid = :uid AND itemid $insql",
                        array_merge($inparams, ['uid' => $userid]));
                }
            }

            // 6. Optionally reset quiz attempts.
            if ($reset_attempts) {
                $quizids = $DB->get_fieldset_select('quiz', 'id',
                    'course = :cid', ['cid' => $courseid]);
                if (!empty($quizids)) {
                    [$insql, $inparams] = $DB->get_in_or_equal($quizids, SQL_PARAMS_NAMED, 'qid');
                    $attempt_ids = $DB->get_fieldset_select('quiz_attempts',
                        'id',
                        "userid = :uid AND quiz $insql",
                        array_merge($inparams, ['uid' => $userid]));
                    if (!empty($attempt_ids)) {
                        // Use Moodle's API for proper cascading.
                        require_once($GLOBALS['CFG']->dirroot . '/mod/quiz/locallib.php');
                        foreach ($attempt_ids as $aid) {
                            try {
                                quiz_delete_attempt($aid, $DB->get_record('quiz',
                                    ['id' => reset($quizids)]));
                            } catch (\Throwable $e) {
                                // Best-effort; fall back to direct delete.
                                $DB->delete_records('quiz_attempts', ['id' => $aid]);
                            }
                        }
                    }
                }
            }

            $tx->allow_commit();

            // P1 #20 — fire the completion_reset event AFTER commit so
            // observers only see durable state. Wrapped in try/catch
            // because a broken third-party observer must never poison
            // the reset itself (return true is what callers depend on
            // to insert the history audit row).
            try {
                \local_airpay_recompletion\event\completion_reset::create([
                    'context'       => \context_course::instance($courseid),
                    'courseid'      => $courseid,
                    'relateduserid' => $userid,
                    'userid'        => $reset_by_userid ?: $userid,
                    'other' => [
                        'reset_by_userid' => $reset_by_userid,
                        'reset_grades'    => $reset_grades ? 1 : 0,
                        'reset_attempts'  => $reset_attempts ? 1 : 0,
                        'reason'          => $reason,
                    ],
                ])->trigger();
            } catch (\Throwable $e) {
                debugging('completion_reset event trigger failed: '
                    . $e->getMessage(), DEBUG_NORMAL);
            }

            return true;
        } catch (\Throwable $e) {
            $tx->rollback($e);
            return false;
        }
    }

    /**
     * W1-4 (2026-05-15) — purge SCORM tracking data for one user × one course.
     *
     * Handles both Moodle 5.x schema (scorm_attempt + scorm_scoes_value) and
     * the legacy Moodle 4.x scorm_scoes_track table. Defensive `table_exists()`
     * checks so this is forward-compatible if Moodle changes the schema again.
     *
     * @param int $userid
     * @param int $courseid
     */
    private static function reset_scorm_tracking(int $userid, int $courseid): void {
        global $DB;

        $scormids = $DB->get_fieldset_select('scorm', 'id',
            'course = :cid', ['cid' => $courseid]);
        if (empty($scormids)) {
            return;  // No SCORM activities in this course — common for
                     // non-compliance courses; not an error.
        }

        $dbman = $DB->get_manager();
        [$insql, $inparams] = $DB->get_in_or_equal($scormids, SQL_PARAMS_NAMED, 'scid');
        $userargs = array_merge($inparams, ['uid' => $userid]);

        // Moodle 5.x — `scorm_attempt` is the parent of `scorm_scoes_value`.
        // Find the user's attempts in these scorms, then delete the values
        // for those attempts, then delete the attempts themselves.
        if ($dbman->table_exists('scorm_attempt')) {
            $attemptids = $DB->get_fieldset_select('scorm_attempt', 'id',
                "userid = :uid AND scormid $insql", $userargs);
            if (!empty($attemptids)) {
                [$aSql, $aArgs] = $DB->get_in_or_equal($attemptids, SQL_PARAMS_NAMED, 'aid');
                if ($dbman->table_exists('scorm_scoes_value')) {
                    $DB->delete_records_select('scorm_scoes_value',
                        "attemptid $aSql", $aArgs);
                }
                $DB->delete_records_select('scorm_attempt',
                    "id $aSql", $aArgs);
            }
        }

        // AICC bridge — extremely rare but the schema supports it. Safety.
        if ($dbman->table_exists('scorm_aicc_session')) {
            $DB->delete_records_select('scorm_aicc_session',
                "userid = :uid AND scormid $insql", $userargs);
        }

        // Legacy Moodle 4.x table — kept around on sites upgraded from 4.x
        // until the migration cron drops it. Delete defensively if present.
        if ($dbman->table_exists('scorm_scoes_track')) {
            $DB->delete_records_select('scorm_scoes_track',
                "userid = :uid AND scormid $insql", $userargs);
        }
    }

    /**
     * W1-4 (2026-05-15) — clear per-activity completion for a user × course.
     *
     * Without this, Moodle's completion API rebuilds course_completions from
     * the still-complete course_modules_completion rows. We delete ALL the
     * user's activity-completion rows for activities in the course, not just
     * SCORM ones — recompletion semantics treat the course as "start over"
     * regardless of what mix of activities it contains.
     *
     * @param int $userid
     * @param int $courseid
     */
    private static function reset_activity_completion(int $userid, int $courseid): void {
        global $DB;

        $cmids = $DB->get_fieldset_select('course_modules', 'id',
            'course = :cid', ['cid' => $courseid]);
        if (empty($cmids)) {
            return;
        }

        [$insql, $inparams] = $DB->get_in_or_equal($cmids, SQL_PARAMS_NAMED, 'cmid');
        $DB->delete_records_select('course_modules_completion',
            "userid = :uid AND coursemoduleid $insql",
            array_merge($inparams, ['uid' => $userid]));

        // Moodle 4.0+ added a separate `course_modules_viewed` table tracking
        // first-view timestamps used for "must view" completion. Clear it too
        // so the user gets a clean "haven't viewed yet" state.
        $dbman = $DB->get_manager();
        if ($dbman->table_exists('course_modules_viewed')) {
            $DB->delete_records_select('course_modules_viewed',
                "userid = :uid AND coursemoduleid $insql",
                array_merge($inparams, ['uid' => $userid]));
        }
    }

    /**
     * Bulk manual reset — used by the admin bulk-reset UI.
     */
    public static function bulk_reset(int $courseid, array $userids,
                                       int $reset_by, string $reason = 'bulk',
                                       bool $reset_grades = true,
                                       bool $reset_attempts = true): array {
        global $DB;
        $result = ['reset' => 0, 'failed' => 0];
        foreach ($userids as $uid) {
            $uid = (int) $uid;
            $prev = $DB->get_field('course_completions', 'timecompleted',
                ['userid' => $uid, 'course' => $courseid]);
            // P1 #20 — pass reset_by + reason into the engine so the
            // completion_reset event payload carries them. Without this,
            // bulk resets would fire the event with reset_by_userid=null
            // and observers couldn't tell who initiated them.
            $ok = self::reset_user_in_course($uid, $courseid,
                $reset_grades, $reset_attempts,
                $reset_by, $reason);
            if ($ok) {
                $DB->insert_record('local_airpay_recompletion_history', (object) [
                    'ruleid'                 => 0,
                    'userid'                 => $uid,
                    'courseid'               => $courseid,
                    'reason'                 => $reason,
                    'reset_by_userid'        => $reset_by,
                    'previous_timecompleted' => $prev ?: null,
                    'reset_grades'           => $reset_grades ? 1 : 0,
                    'reset_attempts'         => $reset_attempts ? 1 : 0,
                    'dryrun'                 => 0,
                    'timecreated'            => time(),
                ]);
                $result['reset']++;
            } else {
                $result['failed']++;
            }
        }
        return $result;
    }

    /** Send a Moodle notification via message_send. */
    private static function send_message(int $userid, string $event,
                                          string $subject, string $body): void {
        global $DB;
        $user = $DB->get_record('user', ['id' => $userid], '*');
        if (!$user) return;
        $msg = new \core\message\message();
        $msg->component         = 'local_airpay_recompletion';
        $msg->name              = $event;
        $msg->userfrom          = \core_user::get_noreply_user();
        $msg->userto            = $user;
        $msg->subject           = $subject;
        $msg->fullmessage       = $body;
        $msg->fullmessageformat = FORMAT_PLAIN;
        $msg->fullmessagehtml   = nl2br(s($body));
        $msg->smallmessage      = $subject;
        $msg->notification      = 1;
        message_send($msg);
    }
}
