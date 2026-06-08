<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

namespace local_sentientia_evaluation;

defined('MOODLE_INTERNAL') || die();

/**
 * W1-5 (2026-05-15) — event observer.
 *
 * One static handler per `trigger_event` listed in
 * `\local_sentientia_evaluation\evaluation_manager::TRIGGER_EVENTS`. Each handler
 * delegates to the engine which does the heavy lifting (matching evaluations,
 * enqueuing trigger rows, respecting `days_after` delay + tenant scope).
 *
 * Handlers are intentionally thin — Moodle's observer dispatcher catches
 * exceptions and writes them to the error log, so we want the smallest
 * possible critical section to keep the originating event flow intact.
 *
 * @package    local_sentientia_evaluation
 */
class observer {

    /**
     * `\core\event\course_completed` — Moodle-native.
     */
    public static function course_completed(\core\event\course_completed $event): void {
        try {
            evaluation_engine::on_trigger_event(
                'course_completion',
                (int) $event->relateduserid,
                (int) $event->courseid,
                (int) $event->timecreated
            );
        } catch (\Throwable $e) {
            debugging('local_sentientia_evaluation observer (course_completed) failed: '
                . $e->getMessage(), DEBUG_DEVELOPER);
        }
    }

    /**
     * `\local_airpay_programs\event\program_completed` — Airpay-emitted (W1-9 dependency).
     *
     * Expected event shape:
     *   - relateduserid = the learner
     *   - other['programid'] = the completed program
     *   - timecreated = completion timestamp
     */
    public static function program_completed(\core\event\base $event): void {
        try {
            $programid = (int) ($event->other['programid'] ?? $event->objectid ?? 0);
            evaluation_engine::on_trigger_event(
                'program_completion',
                (int) $event->relateduserid,
                $programid,
                (int) $event->timecreated
            );
        } catch (\Throwable $e) {
            debugging('local_sentientia_evaluation observer (program_completed) failed: '
                . $e->getMessage(), DEBUG_DEVELOPER);
        }
    }

    /**
     * `\local_airpay_classroom\event\session_completed` — Airpay-emitted (W1-9 dependency).
     *
     * Expected event shape:
     *   - other['classroomid'] = the classroom (parent of the session)
     *   - other['sessionid'] = the specific session that ended
     *   - For learners-of-the-session: the engine fans out via classroom_users.
     */
    public static function classroom_ended(\core\event\base $event): void {
        try {
            $classroomid = (int) ($event->other['classroomid'] ?? $event->objectid ?? 0);
            // For session_completed, there isn't ONE relateduserid — every
            // attendee gets a trigger. The engine fans out via classroom_users.
            evaluation_engine::on_classroom_end(
                $classroomid,
                (int) $event->timecreated
            );
        } catch (\Throwable $e) {
            debugging('local_sentientia_evaluation observer (classroom_ended) failed: '
                . $e->getMessage(), DEBUG_DEVELOPER);
        }
    }
}
