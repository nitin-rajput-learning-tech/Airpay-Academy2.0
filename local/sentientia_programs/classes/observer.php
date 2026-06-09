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
 * containing that course. If yes, emits `program_completed`.
 *
 * Algorithm:
 *   1. Find every program that has the just-completed course on any level.
 *   2. For each such program, check if the user has now finished every
 *      `completion_required = 1` level.
 *   3. If yes AND we haven't already emitted `program_completed` for this
 *      user × program in the past 24h (dedupe via cache), emit it.
 *
 * Cache dedupe prevents spamming the event when a user re-completes the
 * same final-level course (e.g., recompletion → re-complete same day).
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
     * Fire `program_completed` if the user has completed every required level
     * of the given program AND we haven't already fired it for this combo
     * in the past 24h.
     */
    private static function maybe_fire_program_completed(int $userid, int $programid): void {
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
        $total_required = 0;
        $completed_required = 0;
        foreach ($state['levels'] as $lvl) {
            if (!empty($lvl['completion_required'])) {
                $total_required++;
                if (!empty($lvl['completed'])) {
                    $completed_required++;
                }
            }
        }

        // If there are no required levels OR not all of them are done yet,
        // don't fire.
        if ($total_required === 0 || $completed_required < $total_required) {
            return;
        }

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
