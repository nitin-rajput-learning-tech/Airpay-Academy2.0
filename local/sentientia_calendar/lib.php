<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

/**
 * Library callbacks for local_sentientia_calendar.
 *
 * Hooks into the user-preferences navigation tree so the "Calendar
 * subscription" item appears under each user's profile/preferences
 * page when the master feature flag is on.
 *
 * @package local_sentientia_calendar
 */

defined('MOODLE_INTERNAL') || die();

/**
 * Add a "Calendar subscription" node to the user preferences sidebar.
 *
 * Hooked from core's user navigation builder via the standard
 * local_*_extend_navigation_user_settings callback name.
 *
 * @param navigation_node $usernode      The user settings root node
 * @param \stdClass       $user          The user whose menu is being built
 * @param \context_user   $usercontext   User context
 * @param \stdClass       $course        Surrounding course (for in-course profile views)
 * @param \context_course $coursecontext Course context
 * @return void
 */
function local_sentientia_calendar_extend_navigation_user_settings(
    navigation_node $usernode,
    \stdClass $user,
    \context_user $usercontext,
    \stdClass $course,
    \context_course $coursecontext
): void {
    global $USER;

    // Only show the node on the user's own profile — admins editing
    // someone else's profile shouldn't see (or be tempted to use) it.
    if ((int) $user->id !== (int) $USER->id) {
        return;
    }

    // Respect the master feature flag.
    if (class_exists('\\local_sentientia_platform\\feature_flags')) {
        if (!\local_sentientia_platform\feature_flags::is_enabled('sentientia.calendar_sync.enabled')) {
            return;
        }
    }

    // Capability gate — the same one index.php enforces.
    if (!has_capability('local/sentientia_calendar:subscribe', $usercontext)) {
        return;
    }

    $url = new \moodle_url('/local/sentientia_calendar/index.php');
    $label = get_string('nav_label', 'local_sentientia_calendar');

    $node = navigation_node::create(
        $label,
        $url,
        navigation_node::TYPE_SETTING,
        null,
        'sentientia_calendar_subscribe',
        new \pix_icon('i/calendar', '')
    );

    $usernode->add_node($node);
}
