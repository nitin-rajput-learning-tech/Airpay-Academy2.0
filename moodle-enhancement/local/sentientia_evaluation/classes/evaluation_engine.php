<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

namespace local_sentientia_evaluation;

defined('MOODLE_INTERNAL') || die();

/**
 * W1-5 (2026-05-15) — evaluation trigger engine.
 *
 * Responsibilities:
 *   - `on_trigger_event()` — fan a single trigger event out to all matching
 *     active evaluations, enqueuing a row per (eval, user, item).
 *   - `on_classroom_end()` — fan a classroom-end event out to all classroom
 *     attendees (whereas course/program completion targets a single user).
 *   - `process_due_triggers()` — drain the queue, called from the scheduled
 *     task; sends Moodle notifications + inserts response-shell rows.
 *
 * Tenant scoping: an evaluation with `costcenterid > 0` only matches users
 * whose `open_path` starts with that tenant's path. `costcenterid = 0` means
 * "all tenants" and is reserved for site-admin globals.
 *
 * @package local_sentientia_evaluation
 */
class evaluation_engine {

    private const TRIGGER_TABLE  = 'local_sentientia_evaluation_triggers';
    private const EVAL_TABLE     = 'local_sentientia_evaluation';
    private const RESPONSE_TABLE = 'local_sentientia_evaluation_responses';

    /** Status values for the trigger queue. */
    public const STATUS_PENDING   = 0;
    public const STATUS_FIRED     = 1;
    public const STATUS_CANCELLED = 2;
    public const STATUS_SKIPPED   = 3;

    /**
     * Handle a trigger event for a single user × item pair (course or program).
     *
     * Queries `local_sentientia_evaluation` for ACTIVE rows whose `trigger_event`
     * matches and whose tenant scope includes the user; enqueues one row per
     * match. Existing pending rows (same eval, user, item) are skipped via
     * the UNIQUE constraint — repeated event emission is safe.
     *
     * @param string $trigger_event  One of TRIGGER_EVENTS keys
     * @param int    $userid
     * @param int    $itemid         courseid OR programid depending on event
     * @param int    $event_time     UNIX timestamp the event fired
     */
    public static function on_trigger_event(string $trigger_event, int $userid,
                                             int $itemid, int $event_time): void {
        global $DB;

        if ($userid <= 1 || $itemid <= 0) {
            return;
        }
        if (!array_key_exists($trigger_event, evaluation_manager::TRIGGER_EVENTS)) {
            return;
        }

        // Pull the user's tenant path so we can filter evaluations by scope.
        $userpath = $DB->get_field('user', 'open_path', ['id' => $userid]) ?: '';

        $evaluations = $DB->get_records(self::EVAL_TABLE, [
            'trigger_event' => $trigger_event,
            'status'        => evaluation_manager::STATUS_ACTIVE,
        ]);

        foreach ($evaluations as $eval) {
            if (!self::is_user_in_eval_scope($eval, $userpath)) {
                continue;
            }
            self::enqueue_trigger((int) $eval->id, $userid, $itemid,
                $trigger_event, $event_time, (int) $eval->days_after);
        }
    }

    /**
     * Handle a classroom-end event by fanning out to every confirmed attendee.
     *
     * Unlike course/program completion which fires per-user, a classroom
     * session ends for ALL attendees at once. The engine reads
     * `local_airpay_classroom_users` to find the cohort.
     *
     * @param int $classroomid
     * @param int $event_time
     */
    public static function on_classroom_end(int $classroomid, int $event_time): void {
        global $DB;

        if ($classroomid <= 0) {
            return;
        }

        $dbman = $DB->get_manager();
        if (!$dbman->table_exists('local_airpay_classroom_users')) {
            return;  // airpay_classroom not installed — nothing to fan out to.
        }

        $userids = $DB->get_fieldset_select('local_airpay_classroom_users',
            'userid', 'classroomid = :cid', ['cid' => $classroomid]);

        foreach ($userids as $uid) {
            self::on_trigger_event('classroom_end', (int) $uid,
                $classroomid, $event_time);
        }
    }

    /**
     * Insert a row in the trigger queue if one doesn't already exist for
     * (eval, user, item). Returns the trigger row ID or 0 if skipped/duped.
     */
    private static function enqueue_trigger(int $evaluationid, int $userid,
                                              int $itemid, string $trigger_event,
                                              int $event_time, int $days_after): int {
        global $DB;

        // Skip if a non-cancelled trigger already exists for this combo.
        $existing = $DB->get_record(self::TRIGGER_TABLE, [
            'evaluationid' => $evaluationid,
            'userid'       => $userid,
            'itemid'       => $itemid,
        ]);
        if ($existing) {
            return 0;
        }

        $fire_after = $event_time + max(0, $days_after) * 86400;

        try {
            return (int) $DB->insert_record(self::TRIGGER_TABLE, (object) [
                'evaluationid'  => $evaluationid,
                'userid'        => $userid,
                'itemid'        => $itemid,
                'trigger_event' => $trigger_event,
                'fire_after'    => $fire_after,
                'status'        => self::STATUS_PENDING,
                'timecreated'   => time(),
            ]);
        } catch (\dml_write_exception $e) {
            // Race-window dup — another observer call inserted between our
            // SELECT and INSERT. Safe to ignore; the queued row will fire.
            return 0;
        }
    }

    /**
     * Test whether a user's open_path is in scope of an evaluation's tenant.
     *
     * - costcenterid = 0 → all tenants (matches every user)
     * - costcenterid > 0 + eval has open_path → user path must equal or
     *   start with eval.open_path
     */
    private static function is_user_in_eval_scope(\stdClass $eval, string $userpath): bool {
        if ((int) $eval->costcenterid === 0) {
            return true;
        }
        if (empty($eval->open_path)) {
            // Inconsistent: tenant-bound but no path. Treat as "tenant-only"
            // matched via costcenterid prefix on the user path.
            $expected = '/' . (int) $eval->costcenterid;
            return $userpath === $expected
                || str_starts_with($userpath, $expected . '/');
        }
        $epath = rtrim($eval->open_path, '/');
        return $userpath === $epath || str_starts_with($userpath, $epath . '/');
    }

    /**
     * Drain due triggers — called from the scheduled task.
     *
     * For each pending row whose fire_after has passed:
     *   - if the evaluation is no longer active → mark SKIPPED
     *   - else create a response shell row (timesubmitted=0) so the user can
     *     find it from their dashboard, AND send a Moodle notification
     *   - mark trigger as FIRED with timefired = now
     *
     * Cap at `max_per_run` rows to avoid long cron passes.
     *
     * @param int $max_per_run
     * @return array  ['fired' => int, 'skipped' => int]
     */
    public static function process_due_triggers(int $max_per_run = 500): array {
        global $DB;

        $now = time();
        $rows = $DB->get_records_select(self::TRIGGER_TABLE,
            'status = :st AND fire_after <= :now',
            ['st' => self::STATUS_PENDING, 'now' => $now],
            'fire_after ASC, id ASC',
            '*',
            0, max(1, $max_per_run));

        $result = ['fired' => 0, 'skipped' => 0];

        foreach ($rows as $row) {
            $eval = $DB->get_record(self::EVAL_TABLE, ['id' => $row->evaluationid]);
            if (!$eval || (int) $eval->status !== evaluation_manager::STATUS_ACTIVE) {
                $DB->update_record(self::TRIGGER_TABLE, (object) [
                    'id'        => $row->id,
                    'status'    => self::STATUS_SKIPPED,
                    'timefired' => $now,
                ]);
                $result['skipped']++;
                continue;
            }

            // Don't double-create a response shell if one already exists.
            $has_response = $DB->record_exists(self::RESPONSE_TABLE, [
                'evaluationid' => $row->evaluationid,
                'userid'       => $row->userid,
            ]);

            if (!$has_response) {
                // Create a "pending response" shell — empty response_data,
                // timesubmitted=0 means the form hasn't been completed.
                $shell = (object) [
                    'evaluationid'  => (int) $row->evaluationid,
                    'userid'        => (int) $row->userid,
                    'response_data' => '{}',
                    'timesubmitted' => 0,
                ];
                switch ($row->trigger_event) {
                    case 'course_completion':   $shell->courseid    = (int) $row->itemid; break;
                    case 'program_completion':  $shell->programid   = (int) $row->itemid; break;
                    case 'classroom_end':       $shell->classroomid = (int) $row->itemid; break;
                }
                $DB->insert_record(self::RESPONSE_TABLE, $shell);
            }

            self::send_invite_notification((int) $row->userid, $eval);

            // P1 #37 (2026-05-20) — also record an assignment row so
            // compliance can see "who was assigned this evaluation
            // and hasn't responded yet". Idempotent via the UNIQUE
            // index on (evaluationid, userid, trigger_event, source_id);
            // safe to call multiple times.
            try {
                evaluation_manager::ensure_assignment(
                    (int) $row->evaluationid, (int) $row->userid,
                    (string) $row->trigger_event,
                    (int) ($row->itemid ?? 0),
                    null,  // auto-assigned, no acting admin
                    null   // no due_at on trigger-queue auto-assigns
                );
            } catch (\Throwable $e) {
                // The invite already went out; missing audit row is
                // a degraded-not-broken state. Log + continue.
                mtrace('  ensure_assignment failed for eval='
                    . $row->evaluationid . ' user=' . $row->userid
                    . ': ' . $e->getMessage());
            }

            $DB->update_record(self::TRIGGER_TABLE, (object) [
                'id'        => $row->id,
                'status'    => self::STATUS_FIRED,
                'timefired' => $now,
            ]);
            $result['fired']++;
        }

        return $result;
    }

    /**
     * Send a Moodle notification inviting the user to fill in the evaluation.
     */
    private static function send_invite_notification(int $userid, \stdClass $eval): void {
        global $DB, $CFG;
        $user = $DB->get_record('user', ['id' => $userid, 'deleted' => 0, 'suspended' => 0]);
        if (!$user) {
            return;
        }
        $url = new \moodle_url('/local/sentientia_evaluation/respond.php', [
            'evaluationid' => (int) $eval->id,
        ]);

        $msg = new \core\message\message();
        $msg->component         = 'local_sentientia_evaluation';
        $msg->name              = 'evaluation_invite';
        $msg->userfrom          = \core_user::get_noreply_user();
        $msg->userto            = $user;
        $msg->subject           = 'Please share your feedback: ' . format_string($eval->name);
        $msg->fullmessage       = 'You completed training that triggered the "'
            . format_string($eval->name) . '" feedback form. Please take 2 minutes '
            . 'to fill it in: ' . $url->out(false);
        $msg->fullmessageformat = FORMAT_PLAIN;
        $msg->fullmessagehtml   = '<p>You completed training that triggered the "<strong>'
            . format_string($eval->name) . '</strong>" feedback form.</p>'
            . '<p><a href="' . $url->out(false) . '">Click here to take 2 minutes to share your feedback.</a></p>';
        $msg->smallmessage      = 'Feedback requested: ' . format_string($eval->name);
        $msg->notification      = 1;
        $msg->contexturl        = $url->out(false);
        $msg->contexturlname    = 'Take the evaluation';
        message_send($msg);
    }
}
