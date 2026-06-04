<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

namespace local_airpay_courses\task;

defined('MOODLE_INTERNAL') || die();

/**
 * P1 #29 (2026-05-20) — daily overdue manager-escalation task.
 *
 * Closes audit item #15 from
 * parity-audit-2026-05-15/airpay_courses.md.
 *
 * Sibling of P1 #28's `course_reminder`. Where the reminder task fires
 * BEFORE the deadline (positive buckets like 7, 3, 1), this task fires
 * AFTER it (1, 7, 14 days past). Recipient is the learner's
 * `user.open_supervisorid` — NOT the learner themselves. The point is
 * to close the loop in the corporate context: when a learner ignores
 * their pre-deadline nudges, their manager finds out and can intervene.
 *
 * Schema choice — we reuse P1 #28's `local_airpay_courses_remind_sent`
 * table for the audit trail and de-dupe, storing negative values in
 * `days_before_deadline` to mark post-deadline rows. This means a
 * single SQL query against the table answers compliance's "show me
 * every nudge/escalation that happened around Alice's POSH deadline"
 * with no schema fork. Sign convention:
 *   positive N → reminder fired N days BEFORE the deadline (P1 #28)
 *   negative N → escalation fired |N| days AFTER the deadline (this task)
 *
 * Idempotency: same unique index on (userid, courseid,
 * days_before_deadline, deadline_ts) as P1 #28. Re-running the task on
 * the same day is a no-op.
 *
 * Edge case — learners with no `open_supervisorid` (typical for the
 * Public tenant) are skipped: nobody to escalate to. They still get
 * their pre-deadline reminders from P1 #28.
 *
 * Disabled by default + opt-in via `overdue_enabled` config, same
 * two-step gate as P1 #28.
 *
 * @package local_airpay_courses
 */
class course_overdue extends \core\task\scheduled_task {

    public function get_name(): string {
        return get_string('task_course_overdue', 'local_airpay_courses');
    }

    public function execute(): void {
        global $DB;

        if (!(int) get_config('local_airpay_courses', 'overdue_enabled')) {
            mtrace('local_airpay_courses course_overdue: disabled '
                . '(overdue_enabled = 0)');
            return;
        }

        $buckets = $this->parse_buckets((string) get_config(
            'local_airpay_courses', 'overdue_days_after') ?: '1,7,14');
        if (empty($buckets)) {
            mtrace('local_airpay_courses course_overdue: no valid buckets');
            return;
        }
        sort($buckets);  // ascending — '1-day-overdue' wins before '7-day-overdue'

        $max = max(1, min(5000, (int) get_config(
            'local_airpay_courses', 'overdue_max_per_run') ?: 500));

        mtrace('local_airpay_courses course_overdue: starting. '
            . 'buckets=[' . implode(',', $buckets) . '] max=' . $max);

        $now = time();
        $widest = max($buckets);

        // Find (active enrolment × course-with-deadline × supervisor-set)
        // tuples where the deadline passed within the last `widest` days.
        // We join `user` twice — once for the learner (must be non-deleted,
        // have a supervisor) and once for the supervisor (must be non-deleted).
        $window_start = $now - ($widest + 1) * 86400;
        $window_end   = $now;

        $sql = "SELECT u.id AS userid,
                        u.firstname, u.lastname,
                        u.open_supervisorid,
                        c.id AS courseid,
                        c.fullname,
                        c.open_coursecompletiondays AS days_to_deadline,
                        ue.timestart,
                        s.id AS supervisorid,
                        s.firstname AS sup_first,
                        s.lastname  AS sup_last
                  FROM {course} c
                  JOIN {enrol} e             ON e.courseid = c.id AND e.status = 0
                  JOIN {user_enrolments} ue  ON ue.enrolid = e.id AND ue.status = 0
                  JOIN {user} u              ON u.id = ue.userid
                                            AND u.deleted = 0
                                            AND u.suspended = 0
                                            AND u.open_supervisorid > 0
                  JOIN {user} s              ON s.id = u.open_supervisorid
                                            AND s.deleted = 0
                                            AND s.suspended = 0
             LEFT JOIN {course_completions} cc ON cc.userid = u.id
                                              AND cc.course = c.id
                                              AND cc.timecompleted > 0
                 WHERE c.id > 1
                   AND c.visible = 1
                   AND c.open_coursecompletiondays > 0
                   AND cc.id IS NULL
                   AND ue.timestart > 0
                   AND (ue.timestart + c.open_coursecompletiondays * 86400)
                       BETWEEN :winstart AND :winend
              ORDER BY ue.timestart ASC";

        $rs = $DB->get_recordset_sql($sql, [
            'winstart' => $window_start,
            'winend'   => $window_end,
        ], 0, $max);

        $sent = 0;
        $skipped = 0;

        foreach ($rs as $row) {
            $deadline_ts = (int) $row->timestart
                + ((int) $row->days_to_deadline * 86400);
            $secs_past = $now - $deadline_ts;
            if ($secs_past <= 0) {
                continue;  // not actually past deadline (clock skew)
            }
            $days_past = (int) ceil($secs_past / 86400);

            // Match the SMALLEST bucket >= days_past (mirror of the
            // pre-deadline task's logic). So a learner 5 days overdue
            // hits the '7' bucket only when they cross 7 days, not at 5.
            $bucket = null;
            foreach ($buckets as $b) {
                if ($days_past <= $b) {
                    $bucket = $b;
                    break;
                }
            }
            if ($bucket === null) {
                continue;  // past the widest bucket — stop nagging
            }

            if ($this->send_one_escalation($row, $bucket,
                    $deadline_ts, $days_past)) {
                $sent++;
            } else {
                $skipped++;
            }
        }
        $rs->close();

        set_config('overdue_last_run', $now, 'local_airpay_courses');
        set_config('overdue_last_sent', $sent, 'local_airpay_courses');

        mtrace('local_airpay_courses course_overdue: finished. '
            . "sent=$sent skipped_dedupe=$skipped");
    }

    private function send_one_escalation(\stdClass $row, int $bucket,
                                          int $deadline_ts,
                                          int $days_past): bool {
        global $DB;

        // Negative bucket = post-deadline marker. Reuses P1 #28's table.
        $bucket_signed = -1 * $bucket;

        if ($DB->record_exists('local_airpay_courses_remind_sent', [
            'userid'   => (int) $row->userid,
            'courseid' => (int) $row->courseid,
            'days_before_deadline' => $bucket_signed,
            'deadline_ts'          => $deadline_ts,
        ])) {
            return false;
        }

        $supervisor = \core_user::get_user((int) $row->supervisorid);
        if (!$supervisor) {
            return false;
        }

        $a = (object) [
            'learner_name'  => trim((string) $row->firstname . ' ' . $row->lastname),
            'course_name'   => format_string($row->fullname),
            'days_past'     => $days_past,
            'deadline'      => userdate($deadline_ts, '%d %b %Y'),
            'course_url'    => (new \moodle_url('/course/view.php',
                ['id' => $row->courseid]))->out(false),
            'learner_profile_url' => (new \moodle_url('/user/profile.php',
                ['id' => $row->userid]))->out(false),
        ];

        $msg = new \core\message\message();
        $msg->component   = 'local_airpay_courses';
        $msg->name        = 'course_overdue_supervisor';
        $msg->userfrom    = \core_user::get_noreply_user();
        $msg->userto      = $supervisor;
        $msg->subject     = get_string('overdue_subject',
            'local_airpay_courses', $a);
        $msg->fullmessage = get_string('overdue_body_plain',
            'local_airpay_courses', $a);
        $msg->fullmessageformat = FORMAT_PLAIN;
        $msg->fullmessagehtml   = get_string('overdue_body_html',
            'local_airpay_courses', $a);
        $msg->smallmessage  = get_string('overdue_small',
            'local_airpay_courses', $a);
        $msg->notification  = 1;
        $msg->contexturl    = $a->course_url;
        $msg->contexturlname = format_string($row->fullname);

        \message_send($msg);

        // Phase B.3.b — also push to the supervisor via the shared bridge.
        // Supervisor receives; learner is the subject of the message.
        if (class_exists('\\local_sentientia_pwa\\notification_bridge')) {
            \local_sentientia_pwa\notification_bridge::also_push(
                $supervisor,
                'sentientia.pwa.push.overdue',
                get_string('overdue_push_title', 'local_airpay_courses', $a),
                get_string('overdue_push_body',  'local_airpay_courses', $a),
                $a->learner_profile_url,
                'sentientia-overdue-' . md5($supervisor->id . ':' . $a->course_url . ':' . $row->userid),
                true   // require_interaction — manager should see this
            );
        }

        // Stream C / C.1.b — also WhatsApp/SMS to the supervisor.
        // team_overdue template — slightly awkward wording for a single-
        // learner alert, but reuses an existing seeded DLT template so we
        // avoid adding a new approval-pending row at this stage.
        if (class_exists('\\local_sentientia_whatsapp\\notification_bridge')) {
            \local_sentientia_whatsapp\notification_bridge::also_send(
                $supervisor,
                'engagement.whatsapp.overdue',
                'team_overdue',
                [
                    'firstname'     => $supervisor->firstname ?? '',
                    'overdue_count' => 1,
                    'manager_url'   => $a->learner_profile_url,
                ]
            );
        }

        try {
            $DB->insert_record('local_airpay_courses_remind_sent', (object) [
                'userid'   => (int) $row->userid,  // subject = the learner
                'courseid' => (int) $row->courseid,
                'days_before_deadline' => $bucket_signed,
                'deadline_ts'          => $deadline_ts,
                'timesent'             => time(),
            ]);
        } catch (\dml_write_exception $e) {
            mtrace('  dedupe race on overdue for user ' . $row->userid
                . ' / course ' . $row->courseid
                . ' / bucket ' . $bucket_signed . ' — swallowing');
        }

        return true;
    }

    /**
     * Same buckets parser as the reminder task — copy-paste because
     * scheduled tasks have minimal classloading overhead and sharing
     * via a static helper would require a new utility class.
     */
    private function parse_buckets(string $raw): array {
        $parts = preg_split('/[,\s]+/', trim($raw));
        $out = [];
        foreach ($parts as $p) {
            if ($p === '' || !ctype_digit($p)) continue;
            $n = (int) $p;
            if ($n > 0 && $n <= 365) {
                $out[$n] = true;
            }
        }
        return array_keys($out);
    }
}
