<?php
/**
 * Scheduled task: Process notification rules and send queued notifications.
 *
 * Runs hourly. Evaluates each enabled rule, finds matching users/courses,
 * and sends notifications via the unified sender.
 *
 * Respects $CFG->noemailever — logs as 'suppressed' on local dev.
 *
 * @package    local_sentientia_emails
 * @copyright  2026 Airpay Payment Services
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_sentientia_emails\task;

defined('MOODLE_INTERNAL') || die();

class process_rules extends \core\task\scheduled_task {

    public function get_name(): string {
        return 'Process notification rules';
    }

    public function execute(): void {
        global $DB, $CFG;

        mtrace('Starting notification rule processing...');

        $rules = $DB->get_records('local_sentientia_email_rules', ['enabled' => 1], 'priority DESC');
        mtrace('Found ' . count($rules) . ' enabled rules.');

        $sent = 0;
        $suppressed = 0;
        $errors = 0;

        foreach ($rules as $rule) {
            mtrace("Processing rule: {$rule->rule_name} (type: {$rule->rule_type})");

            try {
                $results = $this->evaluate_rule($rule);
                foreach ($results as $r) {
                    if ($r['status'] === 'sent') { $sent++; }
                    elseif ($r['status'] === 'suppressed') { $suppressed++; }
                    else { $errors++; }
                }
            } catch (\Exception $e) {
                mtrace("  ERROR: " . $e->getMessage());
                $errors++;
            }
        }

        mtrace("Rule processing complete. Sent: {$sent}, Suppressed: {$suppressed}, Errors: {$errors}");
    }

    /**
     * Evaluate a single rule and send notifications for matching users.
     *
     * @param object $rule
     * @return array of result arrays from notification_sender::send()
     */
    private function evaluate_rule(object $rule): array {
        global $DB;

        $allresults = [];

        switch ($rule->rule_type) {
            case 'course_not_started':
                $allresults = $this->process_course_not_started($rule);
                break;

            case 'deadline_approaching':
                $allresults = $this->process_deadline_approaching($rule);
                break;

            case 'streak_broken':
                $allresults = $this->process_streak_broken($rule);
                break;

            case 'manager_nudge':
                $allresults = $this->process_manager_nudge($rule);
                break;

            case 'course_incomplete':
                // Sprint B: ramping reminders (1-3-7-14-21 days etc.)
                $allresults = $this->process_course_incomplete($rule);
                break;

            case 'course_completed':
                // Sprint B: the course-completed email is driven by the
                // observer in \local_sentientia_emails\observer, not by cron.
                // We skip it here but trace so an admin doesn't think
                // the rule is silently broken.
                mtrace("  course_completed rule: driven by event observer, "
                    . "not the cron processor (skipped).");
                break;

            default:
                mtrace("  Skipping unsupported rule type: {$rule->rule_type}");
                break;
        }

        return $allresults;
    }

    /**
     * Find users enrolled in courses with 0% progress after X days.
     */
    private function process_course_not_started(object $rule): array {
        global $DB;

        $triggerdays = (int)($rule->trigger_days ?? 10);
        $cutoff = time() - ($triggerdays * 86400);

        $users = $DB->get_records_sql(
            "SELECT DISTINCT ue.userid, u.firstname, u.lastname, u.email, u.open_path,
                    c.id AS courseid, c.fullname AS coursename
               FROM {user_enrolments} ue
               JOIN {enrol} e ON e.id = ue.enrolid
               JOIN {course} c ON c.id = e.courseid
               JOIN {user} u ON u.id = ue.userid
          LEFT JOIN {course_completions} cc ON cc.userid = ue.userid AND cc.course = c.id
              WHERE ue.timestart > 0 AND ue.timestart < :cutoff
                AND u.deleted = 0 AND u.suspended = 0
                AND (cc.id IS NULL OR cc.timecompleted IS NULL)
                AND NOT EXISTS (
                    SELECT 1 FROM {local_sentientia_email_log} l
                    WHERE l.userid = u.id AND l.courseid = c.id
                      AND l.template_key = :tkey AND l.timecreated > :dedup
                )
           ORDER BY ue.timestart ASC
              LIMIT 100",
            ['cutoff' => $cutoff, 'tkey' => $rule->template_key, 'dedup' => time() - 86400 * 7]
        );

        mtrace("  course_not_started: " . count($users) . " users matched.");

        $results = [];
        foreach ($users as $user) {
            $context = [
                'firstname'     => format_string($user->firstname),
                'course_name'   => format_string($user->coursename),
                'course_url'    => (new \moodle_url('/course/view.php', ['id' => $user->courseid]))->out(false),
                'days_since'    => $triggerdays,
                'enrolled_date' => userdate($cutoff, '%d %B %Y'),
                'subject'       => 'You have not started: ' . format_string($user->coursename),
            ];
            $sendresults = \local_sentientia_emails\notification_sender::send($rule, $user, $context, $user->courseid);
            $results = array_merge($results, $sendresults);
        }

        return $results;
    }

    /**
     * Find courses with deadlines approaching in X days.
     */
    private function process_deadline_approaching(object $rule): array {
        global $DB;

        $triggerdays = abs((int)($rule->trigger_days ?? 7));
        $targetdate = time() + ($triggerdays * 86400);
        $windowstart = $targetdate - 43200; // 12 hour window.
        $windowend = $targetdate + 43200;

        $users = $DB->get_records_sql(
            "SELECT DISTINCT ue.userid, u.firstname, u.lastname, u.email, u.open_path,
                    c.id AS courseid, c.fullname AS coursename, c.enddate
               FROM {user_enrolments} ue
               JOIN {enrol} e ON e.id = ue.enrolid
               JOIN {course} c ON c.id = e.courseid AND c.enddate > 0
               JOIN {user} u ON u.id = ue.userid
          LEFT JOIN {course_completions} cc ON cc.userid = ue.userid AND cc.course = c.id
              WHERE c.enddate BETWEEN :wstart AND :wend
                AND u.deleted = 0 AND u.suspended = 0
                AND (cc.id IS NULL OR cc.timecompleted IS NULL)
                AND NOT EXISTS (
                    SELECT 1 FROM {local_sentientia_email_log} l
                    WHERE l.userid = u.id AND l.courseid = c.id
                      AND l.template_key = :tkey AND l.timecreated > :dedup
                )
              LIMIT 100",
            ['wstart' => $windowstart, 'wend' => $windowend,
             'tkey' => $rule->template_key, 'dedup' => time() - 86400]
        );

        mtrace("  deadline_approaching: " . count($users) . " users matched.");

        $results = [];
        foreach ($users as $user) {
            $context = [
                'firstname'     => format_string($user->firstname),
                'course_name'   => format_string($user->coursename),
                'course_url'    => (new \moodle_url('/course/view.php', ['id' => $user->courseid]))->out(false),
                'deadline_date' => userdate($user->enddate, '%d %B %Y'),
                'deadline_days' => $triggerdays,
                'subject'       => "Deadline in {$triggerdays} days: " . format_string($user->coursename),
            ];
            $sendresults = \local_sentientia_emails\notification_sender::send($rule, $user, $context, $user->courseid);
            $results = array_merge($results, $sendresults);
        }

        return $results;
    }

    /**
     * Sprint B — ramping reminder cadence for enrolled-but-incomplete users.
     *
     * The rule's `cadence_days_json` column holds an ordered array of
     * day-offsets from the user's enrolment date, e.g. [1, 3, 7, 14, 21].
     * Each cron run fires this method; for every (user, course) pair in
     * scope we compute `days_since_enrolment` and send IF AND ONLY IF:
     *
     *   - the user has NOT completed the course yet
     *     (auto_stop_on_completion is the rule's setting, default 1)
     *   - today's day-offset is in the cadence array
     *   - we haven't already sent a reminder on this same day-offset
     *     (the log is the source of truth — keyed by user × course ×
     *      template × day)
     *   - the user hasn't already received max_reminders_per_user
     *     emails for this course (0 = unlimited)
     *
     * Why "day-offset matching" instead of plain dedup-window
     * -------------------------------------------------------
     * The existing course_not_started rule uses a 7-day dedup window:
     * "if I sent within 7 days, skip". That works for a SINGLE recurring
     * fire but not for ramping cadence — by day 7 we'd already have
     * sent on day 1 and day 3 (within the window) and would silently
     * skip the day-7 reminder. The day-offset check sidesteps this.
     *
     * @param object $rule
     * @return array
     */
    private function process_course_incomplete(object $rule): array {
        global $DB;

        // Parse the cadence. Day-2 (2026-05-14) — fallback order:
        //   1. The rule row's own `cadence_days_json` (per-rule override)
        //   2. The plugin-wide default from settings.php
        //   3. Hard-coded baseline [1, 3, 7, 14, 21]
        $cadence = [];
        if (!empty($rule->cadence_days_json)) {
            $decoded = json_decode($rule->cadence_days_json, true);
            if (is_array($decoded)) {
                $cadence = array_map('intval', $decoded);
            }
        }
        if (empty($cadence)) {
            $admin_default = get_config('local_sentientia_emails',
                'default_cadence_days_json');
            if (!empty($admin_default)) {
                $decoded = json_decode($admin_default, true);
                if (is_array($decoded)) {
                    $cadence = array_map('intval', $decoded);
                }
            }
        }
        if (empty($cadence)) {
            $cadence = [1, 3, 7, 14, 21];
        }
        // Defensive: filter out non-positive values silently and cap
        // the array length at 10. A misconfigured cadence like
        // [-1, 0, 99999] becomes [99999] — still wrong but won't crash.
        $cadence = array_values(array_filter($cadence, fn($d) => $d > 0));
        $cadence = array_slice($cadence, 0, 10);
        if (empty($cadence)) {
            $cadence = [1, 3, 7, 14, 21];
        }
        $max_offset = (int) max($cadence);

        // Cap + auto-stop also fall back through rule → admin setting
        // → hard-coded baseline.
        $cap = (int) ($rule->max_reminders_per_user
            ?? get_config('local_sentientia_emails', 'default_max_reminders')
            ?? 0);
        $auto_stop = (int) ($rule->auto_stop_on_completion
            ?? get_config('local_sentientia_emails', 'default_auto_stop')
            ?? 1);

        // Find candidates: enrolled in any course, enrolment within the
        // max cadence window. We intentionally don't filter by exact
        // day-offset in SQL — that's done in PHP because cadence is
        // per-rule and tiny. SQL just narrows the candidate pool.
        $now = time();
        $oldest_enrol = $now - ($max_offset + 1) * 86400;

        $params = ['oldest' => $oldest_enrol];
        $auto_stop_join = '';
        $auto_stop_where = '';
        if ($auto_stop) {
            // Exclude users who have completed the course.
            $auto_stop_where = " AND (cc.id IS NULL OR cc.timecompleted IS NULL OR cc.timecompleted = 0)";
        }

        $candidates = $DB->get_records_sql(
            "SELECT DISTINCT ue.userid, u.firstname, u.lastname, u.email, u.open_path,
                    c.id AS courseid, c.fullname AS coursename, ue.timestart
               FROM {user_enrolments} ue
               JOIN {enrol} e ON e.id = ue.enrolid
               JOIN {course} c ON c.id = e.courseid
               JOIN {user} u ON u.id = ue.userid
          LEFT JOIN {course_completions} cc ON cc.userid = u.id AND cc.course = c.id
              WHERE ue.timestart > :oldest
                AND ue.timestart > 0
                AND u.deleted = 0 AND u.suspended = 0
                $auto_stop_where
           ORDER BY ue.timestart ASC
              LIMIT 500",
            $params
        );

        mtrace("  course_incomplete: " . count($candidates)
            . " candidate(s); cadence=[" . implode(',', $cadence) . "]"
            . ", cap=$cap, auto_stop=$auto_stop");

        $results = [];
        $today_floor = strtotime('today');  // start of today, server local TZ

        foreach ($candidates as $cand) {
            $days_since = (int) floor(($today_floor - (int) $cand->timestart) / 86400);
            if ($days_since <= 0 || !in_array($days_since, $cadence, true)) {
                continue;
            }

            // Cap check — count log rows for this user × course × template.
            if ($cap > 0) {
                $sent_count = $DB->count_records_select(
                    'local_sentientia_email_log',
                    "userid = :uid AND courseid = :cid AND template_key = :tkey
                       AND status IN ('sent', 'suppressed_completion')",
                    ['uid' => $cand->userid, 'cid' => $cand->courseid,
                     'tkey' => $rule->template_key]
                );
                if ($sent_count >= $cap) {
                    continue;
                }
            }

            // Dedup on TODAY — never two reminders to the same user × course
            // × template within the same calendar day, even if cron fires
            // multiple times.
            $already_today = $DB->record_exists_select(
                'local_sentientia_email_log',
                "userid = :uid AND courseid = :cid AND template_key = :tkey
                   AND timecreated >= :today",
                ['uid' => $cand->userid, 'cid' => $cand->courseid,
                 'tkey' => $rule->template_key, 'today' => $today_floor]
            );
            if ($already_today) {
                continue;
            }

            $context = [
                'firstname'    => format_string($cand->firstname),
                'course_name'  => format_string($cand->coursename),
                'course_url'   => (new \moodle_url('/course/view.php',
                    ['id' => $cand->courseid]))->out(false),
                'days_since'   => $days_since,
                'enrolled_date' => userdate((int) $cand->timestart, '%d %B %Y'),
                'subject'      => "Reminder: continue your course "
                    . format_string($cand->coursename),
            ];
            $sendresults = \local_sentientia_emails\notification_sender::send(
                $rule, $cand, $context, (int) $cand->courseid);
            $results = array_merge($results, $sendresults);
        }

        return $results;
    }

    /**
     * Find users who broke their login streak.
     */
    private function process_streak_broken(object $rule): array {
        // Placeholder — requires gamification streak data.
        mtrace("  streak_broken: Not yet implemented (requires gamification data).");
        return [];
    }

    /**
     * Find managers with 3+ team members having overdue courses.
     */
    private function process_manager_nudge(object $rule): array {
        // Placeholder — requires compliance snapshot data.
        mtrace("  manager_nudge: Not yet implemented (requires compliance snapshot).");
        return [];
    }
}
