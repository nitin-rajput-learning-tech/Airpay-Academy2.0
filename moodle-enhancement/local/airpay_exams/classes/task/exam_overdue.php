<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

namespace local_airpay_exams\task;

defined('MOODLE_INTERNAL') || die();

/**
 * P1 #34 (2026-05-20) — daily exam overdue manager-escalation task.
 *
 * Sister of P1 #33's `exam_reminder`. Same architecture as P1 #29's
 * airpay_courses overdue task: positive buckets = pre-deadline
 * (reminder), negative buckets = post-deadline (escalation). Reuses
 * `local_airpay_exams_remind_sent` table with the negative values.
 *
 * Recipient is the learner's `user.open_supervisorid`. Learners with
 * no supervisor are filtered out at the SQL JOIN.
 *
 * Disabled by default + two-step opt-in (overdue_enabled config +
 * scheduled-task enable).
 *
 * @package local_airpay_exams
 */
class exam_overdue extends \core\task\scheduled_task {

    public function get_name(): string {
        return get_string('task_exam_overdue', 'local_airpay_exams');
    }

    public function execute(): void {
        global $DB;

        if (!(int) get_config('local_airpay_exams', 'overdue_enabled')) {
            mtrace('local_airpay_exams exam_overdue: disabled '
                . '(overdue_enabled = 0)');
            return;
        }

        $buckets = $this->parse_buckets((string) get_config(
            'local_airpay_exams', 'overdue_days_after') ?: '1,7,14');
        if (empty($buckets)) {
            mtrace('local_airpay_exams exam_overdue: no valid buckets');
            return;
        }
        sort($buckets);
        $max = max(1, min(5000, (int) get_config(
            'local_airpay_exams', 'overdue_max_per_run') ?: 500));

        mtrace('local_airpay_exams exam_overdue: starting. '
            . 'buckets=[' . implode(',', $buckets) . '] max=' . $max);

        $now = time();
        $widest = max($buckets);
        $win_start = $now - ($widest + 1) * 86400;
        $win_end   = $now;

        // Same shape as exam_reminder.php but the window is "past
        // deadline" + supervisor must exist.
        $sql = "SELECT u.id AS userid,
                        u.firstname, u.lastname,
                        u.open_supervisorid,
                        e.id AS examid,
                        e.name AS examname,
                        q.timeclose,
                        c.fullname AS coursename,
                        s.id AS supervisorid
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
                                            AND u.open_supervisorid > 0
                  JOIN {user} s              ON s.id = u.open_supervisorid
                                            AND s.deleted = 0
                                            AND s.suspended = 0
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
            $secs_past = $now - $deadline_ts;
            if ($secs_past <= 0) continue;
            $days_past = (int) ceil($secs_past / 86400);

            $bucket = null;
            foreach ($buckets as $b) {
                if ($days_past <= $b) {
                    $bucket = $b;
                    break;
                }
            }
            if ($bucket === null) continue;

            if ($this->send_one_escalation($row, $bucket,
                    $deadline_ts, $days_past)) {
                $sent++;
            } else {
                $skipped++;
            }
        }
        $rs->close();

        set_config('overdue_last_run', $now, 'local_airpay_exams');
        set_config('overdue_last_sent', $sent, 'local_airpay_exams');

        mtrace('local_airpay_exams exam_overdue: finished. '
            . "sent=$sent skipped_dedupe=$skipped");
    }

    private function send_one_escalation(\stdClass $row, int $bucket,
                                          int $deadline_ts,
                                          int $days_past): bool {
        global $DB;
        $bucket_signed = -1 * $bucket;

        if ($DB->record_exists('local_airpay_exams_remind_sent', [
            'userid'   => (int) $row->userid,
            'examid'   => (int) $row->examid,
            'days_before_deadline' => $bucket_signed,
            'deadline_ts'          => $deadline_ts,
        ])) {
            return false;
        }

        $supervisor = \core_user::get_user((int) $row->supervisorid);
        if (!$supervisor) return false;

        $a = (object) [
            'learner_name'  => trim((string) $row->firstname . ' ' . $row->lastname),
            'exam_name'     => format_string($row->examname),
            'coursename'    => format_string($row->coursename),
            'days_past'     => $days_past,
            'deadline'      => userdate($deadline_ts, '%d %b %Y'),
            'exam_url'      => (new \moodle_url('/local/airpay_exams/view.php',
                ['id' => (int) $row->examid]))->out(false),
            'learner_profile_url' => (new \moodle_url('/user/profile.php',
                ['id' => (int) $row->userid]))->out(false),
        ];

        $msg = new \core\message\message();
        $msg->component   = 'local_airpay_exams';
        $msg->name        = 'exam_overdue_supervisor';
        $msg->userfrom    = \core_user::get_noreply_user();
        $msg->userto      = $supervisor;
        $msg->subject     = get_string('overdue_subject',
            'local_airpay_exams', $a);
        $msg->fullmessage = get_string('overdue_body_plain',
            'local_airpay_exams', $a);
        $msg->fullmessageformat = FORMAT_PLAIN;
        $msg->fullmessagehtml   = get_string('overdue_body_html',
            'local_airpay_exams', $a);
        $msg->smallmessage  = get_string('overdue_small',
            'local_airpay_exams', $a);
        $msg->notification    = 1;
        $msg->contexturl      = $a->exam_url;
        $msg->contexturlname  = format_string($row->examname);

        \message_send($msg);

        try {
            $DB->insert_record('local_airpay_exams_remind_sent', (object) [
                'userid'   => (int) $row->userid,
                'examid'   => (int) $row->examid,
                'days_before_deadline' => $bucket_signed,
                'deadline_ts'          => $deadline_ts,
                'timesent'             => time(),
            ]);
        } catch (\dml_write_exception $e) {
            mtrace('  dedupe race for user ' . $row->userid
                . ' / exam ' . $row->examid
                . ' / bucket ' . $bucket_signed . ' — swallowing');
        }

        return true;
    }

    private function parse_buckets(string $raw): array {
        $parts = preg_split('/[,\s]+/', trim($raw));
        $out = [];
        foreach ($parts as $p) {
            if ($p === '' || !ctype_digit($p)) continue;
            $n = (int) $p;
            if ($n > 0 && $n <= 365) $out[$n] = true;
        }
        return array_keys($out);
    }
}
