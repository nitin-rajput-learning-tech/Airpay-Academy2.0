<?php
/**
 * Scheduled task: Compliance deadline check.
 * Runs daily. Finds employees approaching mandatory course deadlines
 * and sends notifications to them and their managers.
 *
 * @package    local_sentientia_lifecycle
 * @copyright  2026 Airpay Payment Services
 */

namespace local_sentientia_lifecycle\task;

defined('MOODLE_INTERNAL') || die();

class compliance_check extends \core\task\scheduled_task {

    public function get_name() {
        return 'Airpay Compliance Deadline Check';
    }

    public function execute() {
        global $DB;

        $now = time();
        $warningdays = 7; // Notify when < 7 days remaining
        $warningcutoff = $now + ($warningdays * 86400);

        mtrace('Compliance check: scanning for approaching deadlines...');

        // Find all mandatory courses with deadlines approaching in next 7 days.
        $atriskcourses = $DB->get_records_sql(
            "SELECT c.id, c.fullname, c.shortname, c.enddate
               FROM {course} c
              WHERE c.enddate > :now AND c.enddate < :cutoff
                AND c.visible = 1 AND c.id > 1",
            ['now' => $now, 'cutoff' => $warningcutoff]
        );

        if (empty($atriskcourses)) {
            mtrace('Compliance check: no approaching deadlines found.');
            return;
        }

        $notified = 0;
        $managernotified = 0;

        foreach ($atriskcourses as $course) {
            $daysremaining = max(0, round(($course->enddate - $now) / 86400));

            // Find enrolled users who haven't completed.
            $incompletusers = $DB->get_records_sql(
                "SELECT u.id, u.firstname, u.lastname, u.email, u.open_supervisorid
                   FROM {user} u
                   JOIN {enrol} e ON e.courseid = :cid
                   JOIN {user_enrolments} ue ON ue.enrolid = e.id AND ue.userid = u.id
                  WHERE u.deleted = 0 AND u.suspended = 0
                    AND u.id NOT IN (
                        SELECT cc.userid FROM {course_completions} cc
                         WHERE cc.course = :cid2 AND cc.timecompleted IS NOT NULL
                    )",
                ['cid' => $course->id, 'cid2' => $course->id]
            );

            foreach ($incompletusers as $user) {
                // Send notification to the employee.
                $this->send_deadline_notification($user, $course, $daysremaining);
                $notified++;

                // Send notification to manager if exists.
                if (!empty($user->open_supervisorid)) {
                    $manager = $DB->get_record('user', ['id' => $user->open_supervisorid, 'deleted' => 0]);
                    if ($manager) {
                        $this->send_manager_alert($manager, $user, $course, $daysremaining);
                        $managernotified++;
                    }
                }
            }
        }

        mtrace("Compliance check: sent $notified employee notifications, $managernotified manager alerts.");

        // Send Teams notification if enabled.
        if (class_exists('\local_airpay_integrations\teams_notifier')) {
            foreach ($atriskcourses as $course) {
                $overduecount = $DB->count_records_sql(
                    "SELECT COUNT(u.id)
                       FROM {user} u
                       JOIN {enrol} e ON e.courseid = :cid
                       JOIN {user_enrolments} ue ON ue.enrolid = e.id AND ue.userid = u.id
                      WHERE u.deleted = 0 AND u.suspended = 0
                        AND u.id NOT IN (
                            SELECT cc.userid FROM {course_completions} cc
                             WHERE cc.course = :cid2 AND cc.timecompleted IS NOT NULL
                        )",
                    ['cid' => $course->id, 'cid2' => $course->id]
                );
                if ($overduecount > 0) {
                    \local_airpay_integrations\teams_notifier::notify_compliance_overdue(
                        format_string($course->fullname), $overduecount
                    );
                }
            }
        }
    }

    /**
     * Send deadline notification to employee via Moodle messaging.
     */
    private function send_deadline_notification($user, $course, int $daysremaining) {
        $message = new \core\message\message();
        $message->component = 'local_sentientia_lifecycle';
        $message->name = 'compliance_deadline';
        $message->userfrom = \core_user::get_noreply_user();
        $message->userto = $user;
        $message->subject = "⏰ {$daysremaining} days left: " . format_string($course->fullname);
        $message->fullmessage = "Your mandatory training \"{$course->fullname}\" is due in {$daysremaining} days. "
            . "Please complete it before the deadline to stay compliant.";
        $message->fullmessageformat = FORMAT_PLAIN;
        $message->fullmessagehtml = "<p>Your mandatory training <strong>" . format_string($course->fullname) . "</strong> "
            . "is due in <strong>{$daysremaining} days</strong>.</p>"
            . "<p>Please complete it before the deadline to stay compliant.</p>"
            . '<p><a href="' . (new \moodle_url('/course/view.php', ['id' => $course->id]))->out() . '">Go to Course</a></p>';
        $message->smallmessage = "{$daysremaining} days left for " . format_string($course->shortname);
        $message->notification = 1;

        try {
            message_send($message);
        } catch (\Exception $e) {
            mtrace('Compliance notification failed for user ' . $user->id . ': ' . $e->getMessage());
        }
    }

    /**
     * Send alert to manager about team member's approaching deadline.
     */
    private function send_manager_alert($manager, $employee, $course, int $daysremaining) {
        $message = new \core\message\message();
        $message->component = 'local_sentientia_lifecycle';
        $message->name = 'compliance_deadline';
        $message->userfrom = \core_user::get_noreply_user();
        $message->userto = $manager;
        $message->subject = "🚨 Team alert: {$employee->firstname} has {$daysremaining} days for " . format_string($course->shortname);
        $message->fullmessage = "{$employee->firstname} {$employee->lastname} has {$daysremaining} days remaining "
            . "to complete mandatory training: " . format_string($course->fullname);
        $message->fullmessageformat = FORMAT_PLAIN;
        $message->fullmessagehtml = "<p><strong>{$employee->firstname} {$employee->lastname}</strong> has "
            . "<strong>{$daysremaining} days</strong> remaining to complete:</p>"
            . "<p><strong>" . format_string($course->fullname) . "</strong></p>";
        $message->smallmessage = "{$employee->firstname} — {$daysremaining}d left for " . format_string($course->shortname);
        $message->notification = 1;

        try {
            message_send($message);
        } catch (\Exception $e) {
            mtrace('Manager alert failed for manager ' . $manager->id . ': ' . $e->getMessage());
        }
    }
}
