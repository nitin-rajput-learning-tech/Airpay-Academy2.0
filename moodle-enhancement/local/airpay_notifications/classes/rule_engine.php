<?php
/**
 * Rule Engine — evaluates notification rules and sends alerts.
 *
 * Called by scheduled task (cron) to check all active rules.
 *
 * @package    local_airpay_notifications
 * @copyright  2026 Airpay Payment Services
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_airpay_notifications;

defined('MOODLE_INTERNAL') || die();

class rule_engine {

    /**
     * Process all active notification rules.
     * Called by scheduled task every hour.
     */
    public static function process_all(): array {
        global $DB;

        $rules = $DB->get_records('local_airpay_notif_rules', ['enabled' => 1]);
        $stats = ['processed' => 0, 'sent' => 0, 'skipped' => 0];

        foreach ($rules as $rule) {
            $result = self::process_rule($rule);
            $stats['processed']++;
            $stats['sent'] += $result['sent'];
            $stats['skipped'] += $result['skipped'];
        }

        return $stats;
    }

    /**
     * Process a single rule — find matching users and send notifications.
     */
    public static function process_rule(\stdClass $rule): array {
        $result = ['sent' => 0, 'skipped' => 0];

        switch ($rule->rule_type) {
            case 'deadline_approaching':
                $result = self::rule_deadline_approaching($rule);
                break;
            case 'course_not_started':
                $result = self::rule_course_not_started($rule);
                break;
            case 'streak_broken':
                $result = self::rule_streak_broken($rule);
                break;
            case 'manager_nudge':
                $result = self::rule_manager_nudge($rule);
                break;
            case 'new_course':
                $result = self::rule_new_course($rule);
                break;
        }

        return $result;
    }

    /**
     * Rule: Deadline approaching — notify learners X days before course enddate.
     */
    private static function rule_deadline_approaching(\stdClass $rule): array {
        global $DB;
        $result = ['sent' => 0, 'skipped' => 0];

        $target_date = time() + ($rule->trigger_days * 86400);
        $window_start = $target_date;
        $window_end = $target_date + 86400; // 1-day window.

        // Find courses with enddate in the trigger window.
        $courses = $DB->get_records_select('course',
            'enddate >= :start AND enddate < :end AND visible = 1 AND id > 1',
            ['start' => $window_start, 'end' => $window_end]);

        foreach ($courses as $course) {
            // Find enrolled users who haven't completed.
            $users = $DB->get_records_sql(
                "SELECT DISTINCT u.id, u.firstname, u.lastname, u.email
                   FROM {user_enrolments} ue
                   JOIN {enrol} e ON e.id = ue.enrolid
                   JOIN {user} u ON u.id = ue.userid
              LEFT JOIN {course_completions} cc ON cc.userid = u.id AND cc.course = e.courseid
                  WHERE e.courseid = :cid
                    AND u.deleted = 0 AND u.suspended = 0
                    AND (cc.timecompleted IS NULL)",
                ['cid' => $course->id]);

            foreach ($users as $user) {
                $sent = self::send($rule, $user->id, $course->id,
                    'Deadline approaching: ' . format_string($course->fullname),
                    'Your course "' . format_string($course->fullname) . '" is due in ' .
                    $rule->trigger_days . ' days. Complete it before ' .
                    userdate($course->enddate, '%d %b %Y') . '.');
                $sent ? $result['sent']++ : $result['skipped']++;
            }
        }

        return $result;
    }

    /**
     * Rule: Course not started — notify learners who enrolled X days ago but 0% progress.
     */
    private static function rule_course_not_started(\stdClass $rule): array {
        global $DB;
        $result = ['sent' => 0, 'skipped' => 0];

        $cutoff = time() - ($rule->trigger_days * 86400);

        $users = $DB->get_records_sql(
            "SELECT DISTINCT ue.userid, u.firstname, u.lastname, c.id as courseid, c.fullname
               FROM {user_enrolments} ue
               JOIN {enrol} e ON e.id = ue.enrolid
               JOIN {course} c ON c.id = e.courseid
               JOIN {user} u ON u.id = ue.userid
          LEFT JOIN {course_completions} cc ON cc.userid = ue.userid AND cc.course = c.id
              WHERE ue.timestart > 0 AND ue.timestart <= :cutoff
                AND u.deleted = 0 AND u.suspended = 0
                AND c.visible = 1
                AND (cc.id IS NULL OR cc.timecompleted IS NULL)
              LIMIT 100",
            ['cutoff' => $cutoff]);

        foreach ($users as $row) {
            // Check if there's any activity at all.
            $hasactivity = $DB->record_exists_select('logstore_standard_log',
                "userid = :uid AND courseid = :cid AND timecreated > :since",
                ['uid' => $row->userid, 'cid' => $row->courseid, 'since' => $cutoff]);

            if (!$hasactivity) {
                $sent = self::send($rule, $row->userid, $row->courseid,
                    'Ready to start? ' . format_string($row->fullname),
                    'You enrolled in "' . format_string($row->fullname) .
                    '" but haven\'t started yet. Jump in today!');
                $sent ? $result['sent']++ : $result['skipped']++;
            }
        }

        return $result;
    }

    /**
     * Rule: Streak broken — notify users who had an active streak but missed login.
     */
    private static function rule_streak_broken(\stdClass $rule): array {
        global $DB;
        $result = ['sent' => 0, 'skipped' => 0];

        if (!$DB->get_manager()->table_exists('local_airpay_streaks')) {
            return $result;
        }

        $yesterday = date('Y-m-d', strtotime('-1 day'));
        $twodaysago = date('Y-m-d', strtotime('-2 days'));

        // Users whose last login was 2+ days ago but had a streak >= 3.
        $users = $DB->get_records_sql(
            "SELECT s.userid, s.current_streak, s.longest_streak, u.firstname
               FROM {local_airpay_streaks} s
               JOIN {user} u ON u.id = s.userid
              WHERE s.last_login_date <= :cutoff
                AND s.current_streak >= 3
                AND u.deleted = 0 AND u.suspended = 0
              LIMIT 50",
            ['cutoff' => $twodaysago]);

        foreach ($users as $row) {
            $sent = self::send($rule, $row->userid, null,
                'Your ' . $row->current_streak . '-day streak is at risk!',
                'Hey ' . format_string($row->firstname) . ', you had a ' .
                $row->current_streak . '-day learning streak going. ' .
                'Log in today to keep it alive!');
            $sent ? $result['sent']++ : $result['skipped']++;
        }

        return $result;
    }

    /**
     * Rule: Manager nudge — alert managers when team members have overdue courses.
     */
    private static function rule_manager_nudge(\stdClass $rule): array {
        global $DB;
        $result = ['sent' => 0, 'skipped' => 0];

        $now = time();
        // Find managers with 3+ team members who have overdue courses.
        $managers = $DB->get_records_sql(
            "SELECT u.open_supervisorid as managerid,
                    COUNT(DISTINCT u.id) as overdue_count,
                    mgr.firstname as mgr_firstname, mgr.email as mgr_email
               FROM {user} u
               JOIN {user_enrolments} ue ON ue.userid = u.id
               JOIN {enrol} e ON e.id = ue.enrolid
               JOIN {course} c ON c.id = e.courseid
               JOIN {user} mgr ON mgr.id = u.open_supervisorid
          LEFT JOIN {course_completions} cc ON cc.userid = u.id AND cc.course = c.id
              WHERE c.enddate > 0 AND c.enddate < :now
                AND (cc.timecompleted IS NULL)
                AND u.deleted = 0 AND u.suspended = 0
                AND u.open_supervisorid > 0
                AND mgr.deleted = 0
           GROUP BY u.open_supervisorid, mgr.firstname, mgr.email
             HAVING overdue_count >= 3
              LIMIT 50",
            ['now' => $now]);

        foreach ($managers as $mgr) {
            $sent = self::send($rule, $mgr->managerid, null,
                $mgr->overdue_count . ' team members have overdue courses',
                'Hi ' . format_string($mgr->mgr_firstname) . ', ' .
                $mgr->overdue_count . ' of your team members have overdue mandatory courses. ' .
                'Check your dashboard to see who needs a nudge.');
            $sent ? $result['sent']++ : $result['skipped']++;
        }

        return $result;
    }

    /**
     * Rule: New course available — notify learners when a course is created in their category.
     */
    private static function rule_new_course(\stdClass $rule): array {
        global $DB;
        $result = ['sent' => 0, 'skipped' => 0];

        $since = time() - (24 * 3600); // Last 24 hours.

        $newcourses = $DB->get_records_select('course',
            'timecreated > :since AND visible = 1 AND id > 1',
            ['since' => $since], '', 'id, fullname, open_path');

        foreach ($newcourses as $course) {
            if (empty($course->open_path)) {
                continue;
            }

            // Notify users in the same tenant.
            $parts = explode('/', trim($course->open_path, '/'));
            $orgpath = '/' . ($parts[0] ?? '');

            $users = $DB->get_records_sql(
                "SELECT id, firstname FROM {user}
                  WHERE deleted = 0 AND suspended = 0
                    AND open_path LIKE :pathprefix
                  LIMIT 100",
                ['pathprefix' => $orgpath . '%']);

            foreach ($users as $user) {
                $sent = self::send($rule, $user->id, $course->id,
                    'New course: ' . format_string($course->fullname),
                    'A new course "' . format_string($course->fullname) .
                    '" is now available. Check it out in the catalog!');
                $sent ? $result['sent']++ : $result['skipped']++;
            }
        }

        return $result;
    }

    /**
     * Send a notification — checks for duplicates, respects user preferences.
     *
     * @return bool True if sent, false if skipped (duplicate or preference).
     */
    private static function send(\stdClass $rule, int $userid, ?int $courseid,
                                  string $subject, string $message): bool {
        global $DB;

        // Prevent duplicate: same rule + user + course within 24 hours.
        // Use transaction to prevent race condition with parallel cron.
        $cid = $courseid ?? 0;
        $since = time() - 86400;
        $transaction = $DB->start_delegated_transaction();
        try {
            $exists = $DB->record_exists_select('local_airpay_notif_log',
                'ruleid = :rid AND userid = :uid AND courseid = :cid AND timecreated > :since',
                ['rid' => $rule->id, 'uid' => $userid, 'cid' => $cid, 'since' => $since]);
            if ($exists) {
                $transaction->allow_commit();
                return false;
            }
            // Insert the log record immediately to claim the slot before sending.
            $logrecord = (object)[
                'ruleid'      => $rule->id,
                'userid'      => $userid,
                'courseid'    => $cid,
                'channel'     => $rule->channel,
                'status'      => 'sending',
                'subject'     => $subject,
                'timecreated' => time(),
            ];
            $logid = $DB->insert_record('local_airpay_notif_log', $logrecord);
            $transaction->allow_commit();
        } catch (\Throwable $e) {
            try { $transaction->rollback($e); } catch (\Throwable $ignored) {}
            return false; // Another process already claimed this notification.
        }

        // Check user preferences.
        $prefs = $DB->get_record('local_airpay_notif_prefs', ['userid' => $userid]);
        $channel = $rule->channel;
        if ($prefs) {
            if ($channel === 'inapp' && !$prefs->channel_inapp) {
                return false;
            }
            if ($channel === 'email' && !$prefs->channel_email) {
                return false;
            }
        }

        // Log the notification.
        $DB->insert_record('local_airpay_notif_log', (object)[
            'ruleid'      => $rule->id,
            'userid'      => $userid,
            'courseid'    => $courseid,
            'channel'     => $channel,
            'subject'     => $subject,
            'message'     => $message,
            'status'      => 'sent',
            'timecreated' => time(),
        ]);

        // Render branded HTML using email template system (if available).
        $html = '';
        $templatekey = $rule->template ?? '';
        if (!empty($templatekey) && class_exists('\\local_airpay_emails\\email_renderer')) {
            $user = $DB->get_record('user', ['id' => $userid], 'id, firstname, lastname, email, open_path');
            if ($user) {
                $tplcontext = [
                    'firstname'   => format_string($user->firstname),
                    'lastname'    => format_string($user->lastname),
                    'fullname'    => format_string($user->firstname . ' ' . $user->lastname),
                    'course_name' => $courseid ? ($DB->get_field('course', 'fullname', ['id' => $courseid]) ?? '') : '',
                    'course_url'  => $courseid ? (new \moodle_url('/course/view.php', ['id' => $courseid]))->out(false) : '',
                    'dashboard_url' => (new \moodle_url('/my/dashboard.php'))->out(false),
                    'subject'     => $subject,
                ];
                try {
                    $html = \local_airpay_emails\email_renderer::render(
                        'local_airpay_emails/' . $templatekey, $tplcontext, $userid
                    );
                } catch (\Exception $e) {
                    debugging('Notification template render fallback: ' . $e->getMessage());
                }
            }
        }
        if (empty($html)) {
            $html = '<p>' . s($message) . '</p>';
        }

        // Send via Moodle messaging (in-app + email if template rendered).
        if ($channel === 'inapp' || $channel === 'email') {
            $eventdata = new \core\message\message();
            $eventdata->component         = 'local_airpay_notifications';
            $eventdata->name              = 'smart_alert';
            $eventdata->userfrom          = \core_user::get_noreply_user();
            $eventdata->userto            = $userid;
            $eventdata->subject           = $subject;
            $eventdata->fullmessage       = html_to_text($html);
            $eventdata->fullmessageformat = FORMAT_HTML;
            $eventdata->fullmessagehtml   = $html;
            $eventdata->smallmessage      = $subject;
            $eventdata->notification      = 1;

            if ($courseid) {
                $eventdata->contexturl     = new \moodle_url('/course/view.php', ['id' => $courseid]);
                $eventdata->contexturlname = $subject;
            }

            try {
                message_send($eventdata);
            } catch (\Exception $e) {
                debugging('Notification send failed: ' . $e->getMessage());
            }
        }

        return true;
    }
}
