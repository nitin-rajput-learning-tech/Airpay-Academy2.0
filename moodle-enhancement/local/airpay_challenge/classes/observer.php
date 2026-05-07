<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

namespace local_airpay_challenge;

defined('MOODLE_INTERNAL') || die();

/**
 * Event observer.
 *
 * The fast-path: when Moodle marks a course completion for a user,
 * immediately re-evaluate any of that user's in-progress challenge
 * attempts. The 15-min recompute task is the catch-up safety net.
 *
 * @package local_airpay_challenge
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
            debugging('local_airpay_challenge observer error: ' . $e->getMessage(),
                DEBUG_DEVELOPER);
        }
    }
}
