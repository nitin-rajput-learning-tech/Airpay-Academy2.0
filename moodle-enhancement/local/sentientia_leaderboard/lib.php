<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

/**
 * Plugin library — public function surface invoked by Moodle core.
 *
 * Phase L.0 surfaces:
 *   - local_sentientia_leaderboard_user_preferences() — registers the
 *     "Hide me from public leaderboards" preference so it appears on
 *     /user/preferences.php under the standard core_user::PREFERENCE_USERS
 *     category.
 *   - local_sentientia_leaderboard_extend_navigation_user_settings() —
 *     adds a "Leaderboard preferences" link inside the user's profile
 *     page so the preference is discoverable.
 *
 * @package local_sentientia_leaderboard
 */

defined('MOODLE_INTERNAL') || die();

/**
 * Register the leaderboard opt-out user preference.
 *
 * Hooked into core_user via the user_preference_allowed_admin_actions /
 * Moodle's user_preferences callback discovery.
 *
 * @return array
 */
function local_sentientia_leaderboard_user_preferences(): array {
    return [
        // 0 = listed publicly. 1 = opted out (hidden from learner-facing
        // leaderboards). Underlying storage in core user_preferences;
        // the optout_manager class also writes to its own table to keep
        // the privacy export / set + the ranking_engine read paths
        // tied to a single source of truth.
        'local_sentientia_leaderboard_optout' => [
            'type'    => PARAM_BOOL,
            'null'    => NULL_NOT_ALLOWED,
            'default' => 0,
            'choices' => [0, 1],
            'permissioncallback' => function ($user, $preferencename) {
                global $USER;
                // Only the user themselves (or a site admin) can flip it.
                return ((int) $user->id === (int) $USER->id) || is_siteadmin();
            },
        ],
    ];
}

/**
 * Extend the user-settings navigation node — adds a leaderboard prefs
 * link inside Profile → Preferences.
 *
 * @param \navigation_node $navigation
 * @param \stdClass $user
 * @param \context_user $usercontext
 * @param \stdClass $course
 * @param \context_course $coursecontext
 */
function local_sentientia_leaderboard_extend_navigation_user_settings(
        \navigation_node $navigation, \stdClass $user,
        \context_user $usercontext, \stdClass $course,
        \context_course $coursecontext): void {

    global $USER;
    // Only show on the user's own preferences page — admin viewing
    // another user lands on a different navigation surface.
    if ((int) $user->id !== (int) $USER->id && !is_siteadmin()) {
        return;
    }

    // Master flag — skip if leaderboards are off entirely.
    if (class_exists('\\local_airpay_core\\feature_flags')) {
        if (!\local_airpay_core\feature_flags::is_enabled(
                'sentientia.leaderboards.enabled')) {
            return;
        }
        if (!\local_airpay_core\feature_flags::is_enabled(
                'sentientia.leaderboards.optout.enabled')) {
            return;
        }
    }

    $url = new \moodle_url('/local/sentientia_leaderboard/preferences.php',
        ['userid' => $user->id]);
    $navigation->add(
        get_string('preference_optout', 'local_sentientia_leaderboard'),
        $url,
        \navigation_node::TYPE_SETTING,
        null,
        'local_sentientia_leaderboard_pref',
        new \pix_icon('i/preferences', '')
    );
}
