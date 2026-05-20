<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

namespace local_airpay_exams\task;

defined('MOODLE_INTERNAL') || die();

/**
 * P1 #33 (2026-05-20) — daily exam deadline reminder.
 *
 * Closes audit item #16 from
 * parity-audit-2026-05-15/airpay_exams.md.
 *
 * Sister task to P1 #28's `local_airpay_courses\task\course_reminder`.
 * Differences:
 *   • Subject is a `local_airpay_exams` row (which wraps a Moodle
 *     `quiz`), not a `course` row.
 *   • Deadline source is `quiz.timeclose` (a fixed calendar timestamp
 *     set by the admin on the quiz), NOT a relative
 *     `enrolment.timestart + days` calculation. The exam is "due by
 *     midnight on 2026-06-30" rather than "due 30 days after you
 *     started".
 *   • "Completion" = the user has at least one `quiz_attempts` row
 *     with state='finished' AND a non-null grade (we check `sumgrades
 *     IS NOT NULL` to filter out abandoned attempts the cron job
 *     hasn't graded yet).
 *
 * Same bucketing semantics: smallest bucket ≥ days_remaining wins, so
 * a learner 5 days out gets the '7' bucket only when they cross from
 * 7→5; the '3' bucket fires at the 3-day mark; the '1' bucket on the
 * day before close.
 *
 * Idempotency: unique (userid, examid, days_before_deadline, deadline_ts).
 * Including deadline_ts means an admin re-opening the quiz (new
 * timeclose) resets the dedupe state and the user gets a fresh cycle.
 *
 * Disabled by default + two-step opt-in (`reminder_enabled` config +
 * scheduled-task enable in the admin UI).
 *
 * @package local_airpay_exams
 */
class exam_reminder extends \core\task\scheduled_task {

    public function get_name(): string {
        return get_string('task_exam_reminder', 'local_airpay_exams');
    }

    public function execute(): void {
        global $DB;

        if (!(int) get_config('local_airpay_exams', 'reminder_enabled')) {
            mtrace('local_airpay_exams exam_reminder: disabled '
                . '(reminder_enabled = 0)');
            return;
        }

        $buckets = $this->parse_buckets((string) get_config(
            'local_airpay_exams', 'reminder_days_before') ?: '7,3,1');
        if (empty($buckets)) {
            mtrace('local_airpay_exams exam_reminder: no valid buckets');
            return;
        }
        sort($buckets);

        $max = max(1, min(5000, (int) get_config(
            'local_airpay_exams', 'reminder_max_per_run') ?: 500));

        mtrace('local_airpay_exams exam_reminder: starting. '
            . 'buckets=[' . implode(',', $buckets) . '] max=' . $max);

        $now = time();
        $widest = max($buckets);
        $win_start = $now - 86400;                          // 1 day grace
        $win_end   = $now + ($widest + 1) * 86400;

        // Find (active enrolment × airpay exam × wrapping quiz with
        // timeclose × not-yet-completed) tuples whose deadline falls
        // within the widest bucket window.
        //
        // `cm` join is needed because the quiz could be in a course
        // the user isn't enrolled in — Moodle's standard "enrolled
        // via course_modules" check. We pre-filter on quiz.timeclose > 0
        // to avoid scanning quizzes with no deadline.
        $sql = "SELECT u.id AS userid,
                        e.id AS examid,
                        e.name AS examname,
                        q.id AS quizid,
                        q.timeclose,
                        c.id AS courseid,
                        c.fullname AS coursename
                  FROM {local_airpay_exams} e
                  JOIN {quiz} q              ON q.id = e.quizid
                                            AND q.timeclose > 0
                  JOIN {course} c            ON c.id = q.course
                  JOIN {enrol} en            ON en.courseid = c.id
                                            AND en.status = 0
                  JOIN {user_enrolments} ue  ON ue.enrolid = en.id
                                            AND ue.status = 0
                  JOIN {user} u              ON u.id = ue.userid
                                            AND u.deleted = 0
                                            AND u.suspended = 0
             LEFT JOIN {quiz_attempts} qa    ON qa.quiz = q.id
                                            AND qa.userid = u.id
                                            AND qa.state = 'finished'
                                            AND qa.sumgrades IS NOT NULL
                 WHERE e.status = 1
                   AND e.visible = 1
                   AND qa.id IS NULL
                   AND q.timeclose BETWEEN :winstart AND :winend
              ORDER BY q.timeclose ASC";

        $rs = $DB->get_recordset_sql($sql, [
            'winstart' => $win_start,
            'winend'   => $win_end,
        ], 0, $max);

        $sent = 0;
        $skipped = 0;

        foreach ($rs as $row) {
            $deadline_ts = (int) $row->timeclose;
            $secs_remaining = $deadline_ts - $now;
            if ($secs_remaining <= 0) continue;  // past deadline
            $days_remaining = (int) ceil($secs_remaining / 86400);

            $bucket = null;
            foreach ($buckets as $b) {
                if ($days_remaining <= $b) {
                    $bucket = $b;
                    break;
                }
            }
            if ($bucket === null) continue;

            if ($this->send_one_reminder($row, $bucket,
                    $deadline_ts, $days_remaining)) {
                $sent++;
            } else {
                $skipped++;
            }
        }
        $rs->close();

        set_config('reminder_last_run', $now, 'local_airpay_exams');
        set_config('reminder_last_sent', $sent, 'local_airpay_exams');

        mtrace('local_airpay_exams exam_reminder: finished. '
            . "sent=$sent skipped_dedupe=$skipped");
    }

    private function send_one_reminder(\stdClass $row, int $bucket,
                                         int $deadline_ts,
                                         int $days_remaining): bool {
        global $DB;

        if ($DB->record_exists('local_airpay_exams_remind_sent', [
            'userid'   => (int) $row->userid,
            'examid'   => (int) $row->examid,
            'days_before_deadline' => $bucket,
            'deadline_ts'          => $deadline_ts,
        ])) {
            return false;
        }

        $user = \core_user::get_user((int) $row->userid);
        if (!$user) return false;

        $a = (object) [
            'examname'       => format_string($row->examname),
            'coursename'     => format_string($row->coursename),
            'days_remaining' => $days_remaining,
            'deadline'       => userdate($deadline_ts, '%d %b %Y'),
            'exam_url'       => (new \moodle_url('/local/airpay_exams/view.php',
                ['id' => (int) $row->examid]))->out(false),
        ];

        $msg = new \core\message\message();
        $msg->component   = 'local_airpay_exams';
        $msg->name        = 'exam_reminder';
        $msg->userfrom    = \core_user::get_noreply_user();
        $msg->userto      = $user;
        $msg->subject     = get_string('reminder_subject',
            'local_airpay_exams', $a);
        $msg->fullmessage = get_string('reminder_body_plain',
            'local_airpay_exams', $a);
        $msg->fullmessageformat = FORMAT_PLAIN;
        $msg->fullmessagehtml   = get_string('reminder_body_html',
            'local_airpay_exams', $a);
        $msg->smallmessage    = get_string('reminder_small',
            'local_airpay_exams', $a);
        $msg->notification    = 1;
        $msg->contexturl      = $a->exam_url;
        $msg->contexturlname  = format_string($row->examname);

        \message_send($msg);

        try {
            $DB->insert_record('local_airpay_exams_remind_sent', (object) [
                'userid'   => (int) $row->userid,
                'examid'   => (int) $row->examid,
                'days_before_deadline' => $bucket,
                'deadline_ts'          => $deadline_ts,
                'timesent'             => time(),
            ]);
        } catch (\dml_write_exception $e) {
            mtrace('  dedupe race for user ' . $row->userid
                . ' / exam ' . $row->examid
                . ' / bucket ' . $bucket . ' — swallowing');
        }

        return true;
    }

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
