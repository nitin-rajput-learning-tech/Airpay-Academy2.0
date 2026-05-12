<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

namespace local_airpay_classroom;

defined('MOODLE_INTERNAL') || die();

/**
 * Waitlist manager — queue for classroom enrolments when capacity is full.
 *
 * Status values:
 *   waiting   — in queue (default)
 *   promoted  — already moved to active enrolment
 *   removed   — left voluntarily or admin-removed
 *
 * Auto-promote: when an active enrolment is cancelled, the head of the
 * waiting queue (lowest position, status=waiting) gets promoted to active
 * enrolment + notified.
 *
 * @package local_airpay_classroom
 */
class waitlist_manager {

    /**
     * Join the waiting list for a classroom. Returns position (1 = head).
     *
     * @throws \moodle_exception if already in queue or already enrolled
     */
    public static function join(int $classroomid, int $userid): \stdClass {
        global $DB;

        // Already enrolled?
        if ($DB->record_exists('local_airpay_classroom_users',
            ['classroomid' => $classroomid, 'userid' => $userid])) {
            throw new \moodle_exception('alreadyenrolled', 'local_airpay_classroom');
        }

        // Already in queue?
        $existing = $DB->get_record('local_airpay_classroom_waitlist',
            ['classroomid' => $classroomid, 'userid' => $userid, 'status' => 'waiting']);
        if ($existing) {
            return $existing;
        }

        // Next position = max(position) + 1.
        $max_pos = (int) $DB->get_field_sql(
            "SELECT MAX(position) FROM {local_airpay_classroom_waitlist}
              WHERE classroomid = :cid AND status = 'waiting'",
            ['cid' => $classroomid]);

        $now = time();
        $row = (object) [
            'classroomid'  => $classroomid,
            'userid'       => $userid,
            'position'     => $max_pos + 1,
            'status'       => 'waiting',
            'timecreated'  => $now,
            'timemodified' => $now,
        ];
        $row->id = $DB->insert_record('local_airpay_classroom_waitlist', $row);
        return $row;
    }

    /**
     * Leave the waiting list (user-initiated).
     */
    public static function leave(int $waitlistid, int $userid): bool {
        global $DB;
        $row = $DB->get_record('local_airpay_classroom_waitlist',
            ['id' => $waitlistid], '*', MUST_EXIST);
        if ((int) $row->userid !== $userid) {
            throw new \moodle_exception('nopermissions', 'error', '',
                'remove someone else from waitlist');
        }
        $row->status      = 'removed';
        $row->removed_at  = time();
        $row->reason      = 'User left the waiting list';
        $row->timemodified = time();
        $DB->update_record('local_airpay_classroom_waitlist', $row);

        self::renumber_positions($row->classroomid);
        return true;
    }

    /** Admin-removes a user from the waiting list. */
    public static function admin_remove(int $waitlistid, string $reason = ''): bool {
        global $DB;
        $row = $DB->get_record('local_airpay_classroom_waitlist',
            ['id' => $waitlistid], '*', MUST_EXIST);
        $row->status      = 'removed';
        $row->removed_at  = time();
        $row->reason      = trim($reason) ?: 'Admin removal';
        $row->timemodified = time();
        $DB->update_record('local_airpay_classroom_waitlist', $row);
        self::renumber_positions($row->classroomid);
        return true;
    }

    /**
     * Auto-promote the head of the waiting list when an active enrolment
     * frees up.
     *
     * Called from the post-unenrol hook (see lib.php observer).
     *
     * Returns the promoted user's id, or 0 if nobody waiting.
     */
    public static function auto_promote(int $classroomid): int {
        global $DB;
        // Capacity check — only promote if there's actually a slot.
        $classroom = $DB->get_record('local_airpay_classroom',
            ['id' => $classroomid]);
        if (!$classroom) return 0;
        $current_enrolled = (int) $DB->count_records('local_airpay_classroom_users',
            ['classroomid' => $classroomid]);
        if ((int) $classroom->capacity > 0
            && $current_enrolled >= (int) $classroom->capacity) {
            return 0;
        }

        // Head of queue = lowest position, status=waiting.
        $head = $DB->get_record_sql(
            "SELECT * FROM {local_airpay_classroom_waitlist}
              WHERE classroomid = :cid AND status = 'waiting'
              ORDER BY position ASC, id ASC LIMIT 1",
            ['cid' => $classroomid]);
        if (!$head) return 0;

        $transaction = $DB->start_delegated_transaction();
        try {
            // 1. Enrol the user into the classroom.
            $DB->insert_record('local_airpay_classroom_users', (object) [
                'classroomid'  => $classroomid,
                'userid'       => $head->userid,
                'enrolledby'   => 0,  // 0 = auto-promote (audit signal)
                'timecreated'  => time(),
                'timemodified' => time(),
            ]);

            // 2. Update waitlist row.
            $head->status       = 'promoted';
            $head->promoted_at  = time();
            $head->timemodified = time();
            $DB->update_record('local_airpay_classroom_waitlist', $head);

            // 3. Renumber remaining waiters.
            self::renumber_positions($classroomid);

            // 4. Notify the user.
            self::notify_promotion($classroomid, (int) $head->userid);

            $transaction->allow_commit();
            return (int) $head->userid;
        } catch (\Throwable $e) {
            $transaction->rollback($e);
            return 0;
        }
    }

    /**
     * List waitlist entries for a classroom (admin view).
     */
    public static function list_waiting(int $classroomid): array {
        global $DB;
        $rows = $DB->get_records_sql(
            "SELECT w.id, w.userid, w.position, w.status, w.reason,
                    w.timecreated, w.promoted_at, w.removed_at,
                    u.firstname, u.lastname, u.email, u.open_employeeid
               FROM {local_airpay_classroom_waitlist} w
               JOIN {user} u ON u.id = w.userid
              WHERE w.classroomid = :cid
              ORDER BY
                CASE WHEN w.status = 'waiting' THEN 0 ELSE 1 END,
                w.position ASC, w.id ASC",
            ['cid' => $classroomid]);
        return array_values($rows);
    }

    /** Renumber waiting positions to be sequential 1..N after a removal. */
    private static function renumber_positions(int $classroomid): void {
        global $DB;
        $rows = $DB->get_records_select('local_airpay_classroom_waitlist',
            "classroomid = :cid AND status = 'waiting'",
            ['cid' => $classroomid], 'position ASC, id ASC');
        $pos = 1;
        foreach ($rows as $r) {
            if ((int) $r->position !== $pos) {
                $r->position = $pos;
                $r->timemodified = time();
                $DB->update_record('local_airpay_classroom_waitlist', $r);
            }
            $pos++;
        }
    }

    /** Send promotion notification via Moodle message_send. */
    private static function notify_promotion(int $classroomid, int $userid): void {
        global $DB;
        $classroom = $DB->get_record('local_airpay_classroom',
            ['id' => $classroomid], 'name');
        $user = $DB->get_record('user', ['id' => $userid], '*');
        if (!$user || !$classroom) return;

        $cname = format_string($classroom->name);
        $msg = new \core\message\message();
        $msg->component         = 'local_airpay_classroom';
        $msg->name              = 'waitlist_promoted';
        $msg->userfrom          = \core_user::get_noreply_user();
        $msg->userto            = $user;
        $msg->subject           = "You've been promoted from waitlist — $cname";
        $msg->fullmessage       = "Good news! A spot opened up in '$cname' and you've"
            . " been auto-promoted from the waiting list to active enrolment.";
        $msg->fullmessageformat = FORMAT_PLAIN;
        $msg->fullmessagehtml   = nl2br(s($msg->fullmessage));
        $msg->smallmessage      = $msg->subject;
        $msg->notification      = 1;
        message_send($msg);
    }
}
