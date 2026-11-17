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

    /**
     * Gates the "compliance deadlines approaching" section (see db/feature_flags.php).
     * Default OFF: until 2026-11-16 the section read a table that does not
     * exist, so no digest has ever carried it.
     */
    public const DEADLINES_FLAG = 'sentientia.notifications.digest_deadlines.enabled';

    /** @var bool[] DEADLINES_FLAG resolved per tenant root, once per run. */
    private array $deadlineson = [];

    /** @var bool[] table_exists() per table name, once per run (build_digest() runs per learner). */
    private array $tablespresent = [];

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
        // Reads local_sentientia_compliance_report's snapshot: one row per user
        // and course (unique userid+courseid), rebuilt hourly by its
        // refresh_snapshot task. Until 2026-11-16 this read a table no plugin
        // creates (local_sentientia_compliance_snapshot, column deadline), so
        // the section never ran. The snapshot is never pruned, so a row only
        // counts while:
        //  - its course is still an active compliance course for the learner's
        //    current tenant (the rule rebuild_snapshot() applies);
        //  - the learner still has an ACTIVE enrolment in it: participation
        //    active (ue.status 0), method enabled (e.status 0), started, not
        //    ended (the rule local_sentientia_courses' enrolments_importer
        //    uses; core's enrol_get_all_users_courses() differs only in
        //    timestart < now). Neither the rebuild nor section 1 checks this:
        //    both accept any enrolment row;
        //  - no completion was recorded since the last rebuild;
        //  - the learner is not exempted. An exemption is live while
        //    is_active = 1 and expiry_date is NULL (permanent) or still ahead:
        //    the rule rebuild_snapshot() applies. courseid 0 means "excluded
        //    from ALL compliance tracking" (compliance_engine::exclude_user(),
        //    the report's Excluded Users list). Since compliance_report 1.0.7
        //    rebuild_snapshot() marks those rows exempted too; this check
        //    covers the gap until the next hourly rebuild, and sites still
        //    on an older compliance_report.
        // EXISTS / NOT EXISTS, never a JOIN, so a course on the list twice
        // (costcenterid 0 and the tenant) or several enrolments in it cannot
        // repeat a line. Every such row is also an incomplete visible course
        // section 1 sees, so this section never makes a digest go to a learner
        // who would not get one anyway; it only adds lines.
        if ($this->deadlines_enabled_for($user)
                && $this->table_present('local_compliance_snapshot')
                && $this->table_present('local_compliance_courses')
                && $this->table_present('local_compliance_exemptions')) {
            $now = time();
            $deadlines = $DB->get_records_sql(
                "SELECT cs.id, cs.courseid, c.fullname, cs.deadline_date
                   FROM {local_compliance_snapshot} cs
                   JOIN {course} c ON c.id = cs.courseid
                  WHERE cs.userid = :uid
                    AND cs.status IN (:notstarted, :inprogress)
                    AND cs.deadline_date > :now
                    AND cs.deadline_date < :soon
                    AND c.visible = 1
                    AND EXISTS (SELECT 1
                                  FROM {local_compliance_courses} mc
                                 WHERE mc.courseid = cs.courseid
                                   AND mc.is_active = 1
                                   AND (mc.costcenterid = 0 OR mc.costcenterid = :tenant))
                    AND EXISTS (SELECT 1
                                  FROM {user_enrolments} ue
                                  JOIN {enrol} e ON e.id = ue.enrolid
                                 WHERE e.courseid = cs.courseid
                                   AND ue.userid = cs.userid
                                   AND ue.status = :ueactive
                                   AND e.status = :eenabled
                                   AND ue.timestart <= :enrolstart
                                   AND (ue.timeend = 0 OR ue.timeend > :enrolend))
                    AND NOT EXISTS (SELECT 1
                                      FROM {course_completions} cc
                                     WHERE cc.course = cs.courseid
                                       AND cc.userid = cs.userid
                                       AND cc.timecompleted IS NOT NULL)
                    AND NOT EXISTS (SELECT 1
                                      FROM {local_compliance_exemptions} ex
                                     WHERE ex.userid = cs.userid
                                       AND (ex.courseid = cs.courseid OR ex.courseid = 0)
                                       AND ex.is_active = 1
                                       AND (ex.expiry_date IS NULL OR ex.expiry_date > :exemptnow))
               ORDER BY cs.deadline_date ASC, cs.id ASC",
                [
                    'uid'        => $userid,
                    'notstarted' => 'not_started',
                    'inprogress' => 'in_progress',
                    'now'        => $now,
                    'soon'       => $now + (7 * 86400),
                    'tenant'     => \local_sentientia_platform\tenant::root_for_user($user),
                    'ueactive'   => ENROL_USER_ACTIVE,
                    'eenabled'   => ENROL_INSTANCE_ENABLED,
                    'enrolstart' => $now,
                    'enrolend'   => $now,
                    'exemptnow'  => $now,
                ], 0, 2);

            foreach ($deadlines as $d) {
                $days = max(1, (int) round(($d->deadline_date - $now) / 86400));
                $items[] = [
                    'icon' => '⚠️',
                    'text' => 'Due in ' . $days . 'd: ' . format_string($d->fullname),
                    'url'  => $CFG->wwwroot . '/course/view.php?id=' . $d->courseid,
                ];
            }
        }

        // 3. Streak status.
        // local_sentientia_streaks belongs to local_sentientia_gamification,
        // which this plugin does not depend on (version.php). Without that
        // plugin get_record() throws, and execute() does not catch around
        // build_digest(), so the whole run stopped at the first learner.
        // Guarded like section 2 (2026-11-16); where gamification is installed
        // nothing changes.
        if ($this->table_present('local_sentientia_streaks')) {
            $streak = $DB->get_record('local_sentientia_streaks', ['userid' => $userid]);
            if ($streak && $streak->current_streak > 0) {
                $items[] = [
                    'icon' => '🔥',
                    'text' => $streak->current_streak . '-day streak! Keep it going today.',
                    'url'  => $CFG->wwwroot . '/my/',
                ];
            }
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

    /**
     * Does DEADLINES_FLAG resolve ON for this learner's tenant?
     *
     * A learner with no tenant never gets the section (fail closed, as the
     * smart rules do, ADR-031). Resolved once per tenant root per run.
     *
     * @param \stdClass $user The learner, carrying open_path.
     * @return bool
     */
    private function deadlines_enabled_for(\stdClass $user): bool {
        $root = \local_sentientia_platform\tenant::root_for_user($user);
        if ($root <= 0) {
            return false;
        }
        if (!array_key_exists($root, $this->deadlineson)) {
            $customer = class_exists('\local_sentientia_core\tenant_registry')
                ? (int) \local_sentientia_core\tenant_registry::customer_of($root) : 0;
            $this->deadlineson[$root] = \local_sentientia_platform\feature_flags::is_enabled_for(
                self::DEADLINES_FLAG, $customer, $root);
        }
        return $this->deadlineson[$root];
    }

    /**
     * Does $table exist? Asked once per table per run: build_digest() runs
     * once per learner, and the tables of plugins this one does not depend on
     * (compliance report, gamification) may be absent.
     *
     * @param string $table Table name without prefix.
     * @return bool
     */
    private function table_present(string $table): bool {
        global $DB;
        if (!array_key_exists($table, $this->tablespresent)) {
            $this->tablespresent[$table] = $DB->get_manager()->table_exists($table);
        }
        return $this->tablespresent[$table];
    }
}
