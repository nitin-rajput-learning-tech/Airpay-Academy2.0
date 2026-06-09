<?php
/**
 * Daily Learning Digest — scheduled task.
 * Sends a personalized "here's what to learn today" notification to active learners.
 * Runs once daily at 8:30 AM (configurable).
 *
 * @package    local_sentientia_notifications
 * @copyright  2026 Airpay Payment Services
 */

namespace local_sentientia_notifications\task;

defined('MOODLE_INTERNAL') || die();

class daily_digest extends \core\task\scheduled_task {

    public function get_name(): string {
        return 'Airpay daily learning digest';
    }

    public function execute() {
        global $DB, $CFG;

        // Get all active, non-admin learners who logged in within last 14 days.
        $cutoff = time() - (14 * 86400);
        $learners = $DB->get_records_sql(
            "SELECT u.id, u.firstname, u.lastname, u.email, u.open_path
               FROM {user} u
              WHERE u.deleted = 0 AND u.suspended = 0
                AND u.lastlogin > :cutoff
                AND u.id > 2
           ORDER BY u.lastname",
            ['cutoff' => $cutoff], 0, 500 // Process in batches of 500.
        );

        $sent = 0;
        $noreply = \core_user::get_noreply_user();

        foreach ($learners as $learner) {
            // Skip admins.
            if (is_siteadmin($learner->id)) {
                continue;
            }

            // Check user preference: opt-out of digest.
            $optout = get_user_preferences('airpay_digest_optout', 0, $learner->id);
            if ($optout) {
                continue;
            }

            // Build personalized digest content.
            $digest = $this->build_digest($learner);
            if (empty($digest['items'])) {
                continue; // Nothing to learn — skip.
            }

            // Send as Moodle notification.
            $message = new \core\message\message();
            $message->component = 'local_sentientia_notifications';
            $message->name = 'smart_alert';
            $message->userfrom = $noreply;
            $message->userto = $learner;
            $message->subject = "Your Learning for Today — " . date('D, d M');
            $message->fullmessage = $digest['text'];
            $message->fullmessageformat = FORMAT_HTML;
            $message->fullmessagehtml = $digest['html'];
            $message->smallmessage = $digest['summary'];
            $message->notification = 1;

            try {
                message_send($message);
                $sent++;
            } catch (\Throwable $e) {
                mtrace("Digest failed for user {$learner->id}: " . $e->getMessage());
            }
        }

        mtrace("Daily digest: sent to {$sent} learners.");
    }

    /**
     * Build personalized digest for a learner.
     */
    private function build_digest(\stdClass $user): array {
        global $DB, $CFG;

        $items = [];
        $userid = $user->id;

        // 1. In-progress courses (resume learning).
        $inprogress = $DB->get_records_sql(
            "SELECT c.id, c.fullname
               FROM {course} c
               JOIN {enrol} e ON e.courseid = c.id
               JOIN {user_enrolments} ue ON ue.enrolid = e.id
          LEFT JOIN {course_completions} cc ON cc.course = c.id AND cc.userid = ue.userid
                    AND cc.timecompleted IS NOT NULL
              WHERE ue.userid = :uid AND c.visible = 1 AND c.id > 1 AND cc.id IS NULL
           ORDER BY ue.timemodified DESC",
            ['uid' => $userid], 0, 3);

        foreach ($inprogress as $course) {
            $items[] = [
                'icon' => '📚',
                'text' => 'Continue: ' . format_string($course->fullname),
                'url'  => $CFG->wwwroot . '/course/view.php?id=' . $course->id,
            ];
        }

        // 2. Compliance deadlines approaching (next 7 days).
        if ($DB->get_manager()->table_exists('local_sentientia_compliance_snapshot')) {
            $deadlines = $DB->get_records_sql(
                "SELECT cs.courseid, c.fullname, cs.deadline
                   FROM {local_sentientia_compliance_snapshot} cs
                   JOIN {course} c ON c.id = cs.courseid
                  WHERE cs.userid = :uid AND cs.status IN ('not_started', 'in_progress')
                    AND cs.deadline > 0 AND cs.deadline < :soon
               ORDER BY cs.deadline ASC",
                ['uid' => $userid, 'soon' => time() + (7 * 86400)], 0, 2);

            foreach ($deadlines as $d) {
                $days = max(1, round(($d->deadline - time()) / 86400));
                $items[] = [
                    'icon' => '⚠️',
                    'text' => 'Due in ' . $days . 'd: ' . format_string($d->fullname),
                    'url'  => $CFG->wwwroot . '/course/view.php?id=' . $d->courseid,
                ];
            }
        }

        // 3. Streak status.
        $streak = $DB->get_record('local_sentientia_streaks', ['userid' => $userid]);
        if ($streak && $streak->current_streak > 0) {
            $items[] = [
                'icon' => '🔥',
                'text' => $streak->current_streak . '-day streak! Keep it going today.',
                'url'  => $CFG->wwwroot . '/my/',
            ];
        }

        if (empty($items)) {
            return ['items' => []];
        }

        // Build text + HTML.
        $name = format_string($user->firstname);
        $summary = "Hi {$name}, you have " . count($items) . " learning items today.";

        $textlines = [$summary, ''];
        $htmllines = ["<p>Hi <strong>{$name}</strong>,</p>", '<ul>'];
        foreach ($items as $item) {
            $textlines[] = $item['icon'] . ' ' . $item['text'] . ' — ' . $item['url'];
            $htmllines[] = '<li>' . $item['icon'] . ' <a href="' . s($item['url']) . '">' . s($item['text']) . '</a></li>';
        }
        $htmllines[] = '</ul>';
        $htmllines[] = '<p style="color:#607286; font-size:12px;">You can opt out of daily digests in your notification preferences.</p>';

        return [
            'items'   => $items,
            'summary' => $summary,
            'text'    => implode("\n", $textlines),
            'html'    => implode("\n", $htmllines),
        ];
    }
}
