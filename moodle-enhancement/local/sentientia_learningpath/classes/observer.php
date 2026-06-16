<?php
namespace local_sentientia_learningpath;

defined('MOODLE_INTERNAL') || die();

/**
 * Event observer for local_sentientia_learningpath.
 *
 * P0.2 (2026-06-16) — Adaptive Learning Journeys.
 *
 * Listens to mod_quiz\event\attempt_submitted to trigger the adaptive
 * journey engine after every finalised quiz attempt.
 *
 * The observer is registered in db/events.php. It is a no-op when the
 * feature flag sentientia.learningpath.adaptive.enabled is OFF — the
 * flag gate lives inside journey_engine::evaluate().
 *
 * @package local_sentientia_learningpath
 */
class observer {

    /**
     * Handle a finalised quiz attempt.
     *
     * @param \mod_quiz\event\attempt_submitted $event
     * @return void
     */
    public static function quiz_attempt_submitted(
        \core\event\base $event
    ): void {
        // Re-use the base type signature so this compiles even when
        // mod_quiz\event\attempt_submitted is on older Moodle versions.
        $userid   = (int) $event->userid;
        $courseid = (int) $event->courseid;

        if ($userid <= 0 || $courseid <= 0) {
            return;
        }

        // Feature flag + path membership checked inside evaluate().
        try {
            adaptive\journey_engine::evaluate($userid, $courseid);
        } catch (\Throwable $e) {
            // Never let an observer failure break the quiz submission page.
            debugging(
                'local_sentientia_learningpath observer: journey_engine::evaluate threw: '
                . $e->getMessage(),
                DEBUG_DEVELOPER
            );
        }
    }
}
