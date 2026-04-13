<?php
/**
 * Scheduled task: Process notification rules and send queued notifications.
 *
 * Runs hourly. Evaluates each enabled rule, finds matching users/courses,
 * and sends notifications via the unified sender.
 *
 * Respects $CFG->noemailever — logs as 'suppressed' on local dev.
 *
 * @package    local_airpay_emails
 * @copyright  2026 Airpay Payment Services
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_airpay_emails\task;

defined('MOODLE_INTERNAL') || die();

class process_rules extends \core\task\scheduled_task {

    public function get_name(): string {
        return 'Process notification rules';
    }

    public function execute(): void {
        global $DB, $CFG;

        mtrace('Starting notification rule processing...');

        $rules = $DB->get_records('local_airpay_email_rules', ['enabled' => 1], 'priority DESC');
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
                    SELECT 1 FROM {local_airpay_email_log} l
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
            $sendresults = \local_airpay_emails\notification_sender::send($rule, $user, $context, $user->courseid);
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
                    SELECT 1 FROM {local_airpay_email_log} l
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
            $sendresults = \local_airpay_emails\notification_sender::send($rule, $user, $context, $user->courseid);
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
