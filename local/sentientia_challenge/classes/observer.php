<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

namespace local_sentientia_challenge;

defined('MOODLE_INTERNAL') || die();

/**
 * Event observer.
 *
 * The fast-path: when Moodle marks a course completion for a user,
 * immediately re-evaluate any of that user's in-progress challenge
 * attempts. The 15-min recompute task is the catch-up safety net.
 *
 * @package local_sentientia_challenge
 */
class observer {

    public static function on_course_completed(\core\event\course_completed $event): void {
        $userid = (int) $event->relateduserid;
        if ($userid <= 0) return;

        // Wrap in a try so a bug here can never break course completion
        // for the user (which would be much worse than gamification lag).
        try {
            challenge_engine::reevaluate_user($userid);
        } catch (\Throwable $e) {
            debugging('local_sentientia_challenge observer error: ' . $e->getMessage(),
                DEBUG_DEVELOPER);
        }
    }

    /**
     * Phase 2 — when a user logs in, re-evaluate streak-typed challenges
     * for them. This keeps the streak counter fresh without waiting for
     * the 15-min cron.
     */
    public static function on_user_loggedin(\core\event\user_loggedin $event): void {
        $userid = (int) $event->userid;
        if ($userid <= 0) return;

        try {
            challenge_engine::reevaluate_user($userid);
        } catch (\Throwable $e) {
            debugging('local_sentientia_challenge streak observer error: ' . $e->getMessage(),
                DEBUG_DEVELOPER);
        }
    }

    /**
     * Phase 2 — when a quiz attempt is submitted, re-evaluate
     * quiz-score-typed challenges for that user.
     */
    public static function on_quiz_attempt_submitted(\mod_quiz\event\attempt_submitted $event): void {
        $userid = (int) $event->relateduserid;
        if ($userid <= 0) return;

        try {
            challenge_engine::reevaluate_user($userid);
        } catch (\Throwable $e) {
            debugging('local_sentientia_challenge quiz observer error: ' . $e->getMessage(),
                DEBUG_DEVELOPER);
        }
    }
}
