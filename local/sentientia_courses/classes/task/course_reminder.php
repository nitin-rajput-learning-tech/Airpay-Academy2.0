<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

namespace local_sentientia_courses\task;

defined('MOODLE_INTERNAL') || die();

/**
 * P1 #28 (2026-05-20) — daily learner deadline reminder.
 *
 * Closes audit item #14 from
 * parity-audit-2026-05-15/sentientia_courses.md.
 *
 * Scans every active enrolment in every course with a non-zero
 * `open_coursecompletiondays` (the field P1 #21 restored to the
 * edit_course form). For each (user, course) pair:
 *
 *   1. Compute the deadline = enrolment.timestart + days × 86400.
 *   2. Skip if the user has already completed the course.
 *   3. Skip if `now > deadline` (we're past the deadline — different
 *      surface; could become P1 #15's "overdue" digest).
 *   4. Compute days_remaining = ceil((deadline - now) / 86400).
 *   5. If days_remaining ∈ configured buckets (e.g. {7, 3, 1}) AND
 *      we haven't already nudged this user for this bucket + deadline,
 *      send a Moodle notification and write a `_remind_sent` row.
 *
 * Idempotency: the `idx_user_course_bucket` unique index on
 * (userid, courseid, days_before_deadline, deadline_ts) means rerunning
 * the task on the same day is a no-op; the INSERT throws on conflict
 * and we swallow it. Including `deadline_ts` in the dedupe key means
 * if a learner is unenrolled and re-enrolled (so the deadline shifts
 * forward), they correctly get a fresh cycle of nudges.
 *
 * Disabled by default — admin opts in via Site admin ▶ Server ▶
 * Scheduled tasks. Default schedule: 09:00 daily (so reminders hit
 * inboxes during work hours).
 *
 * @package local_sentientia_courses
 */
class course_reminder extends \core\task\scheduled_task {

    public function get_name(): string {
        return get_string('task_course_reminder', 'local_sentientia_courses');
    }

    public function execute(): void {
        global $DB, $CFG;

        if (!(int) get_config('local_sentientia_courses', 'reminder_enabled')) {
            mtrace('local_sentientia_courses course_reminder: disabled '
                . '(reminder_enabled = 0)');
            return;
        }

        $buckets = $this->parse_buckets((string) get_config(
            'local_sentientia_courses', 'reminder_days_before') ?: '7,3,1');
        if (empty($buckets)) {
            mtrace('local_sentientia_courses course_reminder: no valid buckets configured');
            return;
        }
        sort($buckets);  // ascending so 1-day-out wins over 7-day-out
                          // if a learner is somehow caught in both windows

        $max = max(1, min(5000, (int) get_config(
            'local_sentientia_courses', 'reminder_max_per_run') ?: 500));

        mtrace('local_sentientia_courses course_reminder: starting. '
            . 'buckets=[' . implode(',', $buckets) . '] max=' . $max);

        $now = time();
        $widest_bucket = max($buckets);

        // Find every (user, course) pair where:
        //   - course has open_coursecompletiondays > 0 (deadline enabled)
        //   - user is actively enrolled (enrolment.status = 0 = ENROL_USER_ACTIVE,
        //     and enrol method is enabled)
        //   - user has not completed the course
        //   - user is not deleted/suspended
        //   - the computed deadline is within the widest configured bucket
        //
        // We over-fetch by one day on each side to handle clock skew
        // and partial-day rounding.
        $window_start = $now - 86400;  // 1 day grace for "today"
        $window_end   = $now + ($widest_bucket + 1) * 86400;

        $sql = "SELECT u.id AS userid,
                        c.id AS courseid,
                        c.fullname,
                        c.open_coursecompletiondays AS days_to_deadline,
                        ue.timestart
                  FROM {course} c
                  JOIN {enrol} e             ON e.courseid = c.id AND e.status = 0
                  JOIN {user_enrolments} ue  ON ue.enrolid = e.id AND ue.status = 0
                  JOIN {user} u              ON u.id = ue.userid
                                            AND u.deleted = 0
                                            AND u.suspended = 0
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

        $sent_count = 0;
        $skipped_count = 0;

        foreach ($rs as $row) {
            $deadline_ts = (int) $row->timestart
                + ((int) $row->days_to_deadline * 86400);
            $secs_remaining = $deadline_ts - $now;
            if ($secs_remaining <= 0) {
                continue;  // past deadline — not our problem here
            }
            $days_remaining = (int) ceil($secs_remaining / 86400);

            // Match the user to ONE bucket — the smallest that's still
            // ≥ days_remaining. So a learner at 4 days out hits the
            // "3-day" bucket only when they cross under 3, not at 4.
            $bucket = null;
            foreach ($buckets as $b) {
                if ($days_remaining <= $b) {
                    $bucket = $b;
                    break;
                }
            }
            if ($bucket === null) {
                continue;
            }

            if ($this->send_one_reminder(
                    (int) $row->userid, (int) $row->courseid,
                    (string) $row->fullname,
                    $bucket, $deadline_ts, $days_remaining)) {
                $sent_count++;
            } else {
                $skipped_count++;
            }
        }
        $rs->close();

        set_config('reminder_last_run', $now, 'local_sentientia_courses');
        set_config('reminder_last_sent', $sent_count, 'local_sentientia_courses');

        mtrace('local_sentientia_courses course_reminder: finished. '
            . "sent=$sent_count skipped_dedupe=$skipped_count");
    }

    /**
     * Send one reminder + record the audit row. Returns true if the
     * reminder was actually sent (false on dedupe skip).
     */
    private function send_one_reminder(int $userid, int $courseid,
                                         string $coursename, int $bucket,
                                         int $deadline_ts,
                                         int $days_remaining): bool {
        global $DB;

        // Pre-check the unique index — cheaper than catching the
        // exception, and `record_exists()` is well-indexed for this
        // shape (the unique index leads on userid+courseid+bucket+
        // deadline_ts in that order).
        if ($DB->record_exists('local_sentientia_courses_remind_sent', [
            'userid'   => $userid,
            'courseid' => $courseid,
            'days_before_deadline' => $bucket,
            'deadline_ts'          => $deadline_ts,
        ])) {
            return false;
        }

        $user = \core_user::get_user($userid);
        if (!$user) {
            return false;
        }

        $a = (object) [
            'fullname'       => format_string($coursename),
            'days_remaining' => $days_remaining,
            'deadline'       => userdate($deadline_ts, '%d %b %Y'),
            'course_url'     => (new \moodle_url('/course/view.php',
                ['id' => $courseid]))->out(false),
        ];

        $msg = new \core\message\message();
        $msg->component   = 'local_sentientia_courses';
        $msg->name        = 'course_reminder';
        $msg->userfrom    = \core_user::get_noreply_user();
        $msg->userto      = $user;
        $msg->subject     = get_string('reminder_subject',
            'local_sentientia_courses', $a);
        $msg->fullmessage = get_string('reminder_body_plain',
            'local_sentientia_courses', $a);
        $msg->fullmessageformat = FORMAT_PLAIN;
        $msg->fullmessagehtml   = get_string('reminder_body_html',
            'local_sentientia_courses', $a);
        $msg->smallmessage  = get_string('reminder_small',
            'local_sentientia_courses', $a);
        $msg->notification  = 1;
        $msg->contexturl    = $a->course_url;
        $msg->contexturlname = format_string($coursename);

        \message_send($msg);

        // Phase B.3.a — also fire a Web Push via the shared bridge.
        // Soft-coupled to local_sentientia_pwa via class_exists. Push is
        // opt-IN (absence of a subscription IS the opt-out), so we don't
        // honor email opt-out preferences here.
        if (class_exists('\\local_sentientia_pwa\\notification_bridge')) {
            \local_sentientia_pwa\notification_bridge::also_push(
                $user,
                'sentientia.pwa.push.reminders',
                get_string('reminder_push_title', 'local_sentientia_courses', $a),
                get_string('reminder_push_body',  'local_sentientia_courses', $a),
                $a->course_url,
                'sentientia-reminder-course-' . md5($user->id . ':' . $a->course_url . ':' . $deadline_ts),
                false   // require_interaction
            );
        }

        // Stream C / C.1.b (2026-05-21) — also fire WhatsApp/SMS via the
        // sentientia_whatsapp bridge. Picks template_key based on bucket
        // (deadline_7d / _3d / _1d). Soft-coupled — no-op if plugin
        // missing, user not opted in, no mobile number, template not
        // DLT-approved, or master flag off.
        if (class_exists('\\local_sentientia_whatsapp\\notification_bridge')) {
            $tpl_key = \local_sentientia_whatsapp\notification_bridge::pick_deadline_template($days_remaining);
            \local_sentientia_whatsapp\notification_bridge::also_send(
                $user,
                'engagement.whatsapp.reminders',
                $tpl_key,
                [
                    'firstname'   => $user->firstname ?? '',
                    'coursename'  => $coursename,
                    'duedate'     => $a->deadline,
                    'course_url'  => $a->course_url,
                ]
            );

            // Stream F / Wave E2 P4 (2026-05-25) — extra urgent "due
            // in <48h" surface, gated independently on
            // sentientia_whatsapp_content_notifications (default OFF) +
            // its own 6h throttle. Fires from the same cron walk so
            // we don't need a second scheduled task.
            $secs_remaining_now = $deadline_ts - time();
            if ($secs_remaining_now > 0 && $secs_remaining_now < 48 * 3600) {
                $hours_remaining = (int) ceil($secs_remaining_now / 3600);
                \local_sentientia_whatsapp\notification_bridge::send_course_due_soon(
                    $userid, $courseid, $hours_remaining);
            }
        }

        // Record the audit row AFTER send. The unique index serves as
        // the de-dupe key; we ignore conflicts (race with a parallel
        // cron firing — very unlikely but cheap to defend against).
        try {
            $DB->insert_record('local_sentientia_courses_remind_sent', (object) [
                'userid'   => $userid,
                'courseid' => $courseid,
                'days_before_deadline' => $bucket,
                'deadline_ts'          => $deadline_ts,
                'timesent'             => time(),
            ]);
        } catch (\dml_write_exception $e) {
            // Concurrent fire — the other cron beat us to the audit
            // row. The user got two notifications but that's better
            // than poisoning the run with an uncaught exception.
            mtrace('  dedupe race for user ' . $userid . ' / course '
                . $courseid . ' / bucket ' . $bucket . ' — swallowing');
        }

        return true;
    }

    /**
     * Parse a comma-separated bucket list. Returns sorted, unique,
     * positive ints. Tolerates whitespace.
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
