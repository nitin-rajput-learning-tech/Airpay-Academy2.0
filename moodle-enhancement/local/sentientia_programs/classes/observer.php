<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

namespace local_sentientia_programs;

defined('MOODLE_INTERNAL') || die();

/**
 * W1-9 (2026-05-15) — event observer.
 *
 * Hooks into `\core\event\course_completed` to detect whether the course
 * completion brings the user to full program-complete state for any program
 * containing that course. If yes, stores the completion and emits
 * `program_completed`.
 *
 * Algorithm:
 *   1. Find every program that has the just-completed course on any level.
 *   2. For each such program the user is enrolled in and has not completed,
 *      check if the user has now finished every `completion_required = 1`
 *      level (any ONE of them when the program's completion_required is 0).
 *   3. If yes AND we haven't already emitted `program_completed` for this
 *      user × program in the past 24h (dedupe via cache), store the
 *      completion (status 2, timecompleted) and emit it.
 *
 * Cache dedupe prevents spamming the event when a user re-completes the
 * same final-level course (e.g., recompletion → re-complete same day).
 *
 * ADR-032 (2026-09-30), what changed:
 *   - A learner with no enrolment in the program is skipped. Before, the
 *     event fired for anyone who completed the last course, enrolled or not.
 *   - A learner whose enrolment is already completed is skipped: the state is
 *     never downgraded and the event is not fired a second time.
 *   - The completion is stored (status 2, timecompleted, last level as the
 *     current one). Before it only fired the event and the roster never showed
 *     the learner as completed.
 *   - A program with completion_required = 0 is complete when any required
 *     level is (BizLMS "OR" tracking). A level with no courses is not a
 *     required level.
 *
 * The BizLMS import never reaches this code: it writes no course completion
 * and fires no event.
 *
 * @package local_sentientia_programs
 */
class observer {

    public static function course_completed(\core\event\course_completed $event): void {
        global $DB;
        try {
            $userid   = (int) $event->relateduserid;
            $courseid = (int) $event->courseid;
            if ($userid <= 1 || $courseid <= 0) {
                return;
            }

            // Find programs containing this course on any level.
            $programids = $DB->get_fieldset_sql(
                "SELECT DISTINCT pl.programid
                   FROM {local_sentientia_programs_levels} pl
                   JOIN {local_sentientia_programs_courses} pc
                     ON pc.levelid = pl.id
                  WHERE pc.courseid = :cid",
                ['cid' => $courseid]
            );

            foreach ($programids as $pid) {
                self::maybe_fire_program_completed($userid, (int) $pid);
            }
        } catch (\Throwable $e) {
            debugging('local_sentientia_programs observer (course_completed) failed: '
                . $e->getMessage(), DEBUG_DEVELOPER);
        }
    }

    /**
     * Complete the learner's program enrolment and fire `program_completed`
     * when the program is now done.
     *
     * @param int $userid
     * @param int $programid
     */
    private static function maybe_fire_program_completed(int $userid, int $programid): void {
        global $DB;

        $program = $DB->get_record('local_sentientia_programs', ['id' => $programid], 'id, completion_required');
        if (!$program) {
            return;
        }
        // Not enrolled: not in the program. Already completed: nothing to do, nothing to downgrade.
        $enrolment = $DB->get_record('local_sentientia_programs_users',
            ['programid' => $programid, 'userid' => $userid], 'id, status');
        if (!$enrolment || (int) $enrolment->status === program_manager::ENROL_COMPLETED) {
            return;
        }

        // Dedupe cache.
        $cache = \cache::make_from_params(
            \cache_store::MODE_APPLICATION,
            'local_sentientia_programs',
            'program_complete_dedupe'
        );
        $key = "{$userid}:{$programid}";
        if ($cache->get($key)) {
            return;
        }

        $state = program_manager::get_user_program_state($programid, $userid);
        $required = 0;
        $done = 0;
        $lastlevel = null;
        foreach ($state['levels'] as $lvl) {
            if (!empty($lvl['empty'])) {
                continue;
            }
            $lastlevel = (int) $lvl['id'];
            if (!empty($lvl['completion_required'])) {
                $required++;
                if (!empty($lvl['completed'])) {
                    $done++;
                }
            }
        }

        // No required level: nothing can complete the program.
        if ($required === 0) {
            return;
        }
        $complete = ((int) $program->completion_required === 0) ? ($done >= 1) : ($done === $required);
        if (!$complete) {
            return;
        }

        $now = time();
        $DB->update_record('local_sentientia_programs_users', (object) [
            'id'             => (int) $enrolment->id,
            'status'         => program_manager::ENROL_COMPLETED,
            'timecompleted'  => $now,
            'currentlevelid' => $lastlevel,
            'timemodified'   => $now,
        ]);

        event\program_completed::create([
            'context'       => \context_system::instance(),
            'objectid'      => $programid,
            'relateduserid' => $userid,
            'other'         => ['programid' => $programid],
        ])->trigger();

        // Mark dedupe for 24h.
        $cache->set($key, 1);
    }
}
