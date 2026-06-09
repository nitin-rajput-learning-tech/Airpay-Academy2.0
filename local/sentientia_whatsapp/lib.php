<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

/**
 * Library callbacks for local_sentientia_whatsapp.
 *
 * Currently registers a "Communication preferences" link in the user
 * profile so learners can find the opt-in page without admin help.
 *
 * @package local_sentientia_whatsapp
 */

defined('MOODLE_INTERNAL') || die();

/**
 * Inject a link into the user-profile page so learners can manage
 * their WhatsApp/SMS preferences from their own profile.
 *
 * Standard Moodle callback name — Moodle calls this automatically
 * when rendering /user/profile.php.
 *
 * @param \core_user\output\myprofile\tree $tree
 * @param \stdClass $user
 * @param bool $iscurrentuser
 * @param \stdClass|null $course
 */
function local_sentientia_whatsapp_myprofile_navigation(
    \core_user\output\myprofile\tree $tree,
    $user,
    $iscurrentuser,
    $course
): void {
    // Only let users edit their OWN preferences. Admins managing others
    // can use bulk tools; per-user preferences are private.
    if (!$iscurrentuser) {
        return;
    }

    $url = new moodle_url('/local/sentientia_whatsapp/preferences.php');
    $node = new \core_user\output\myprofile\node(
        'contact',                                  // category
        'sentientia_whatsapp_prefs',                    // name
        get_string('preferences_nav', 'local_sentientia_whatsapp'),
        null,
        $url
    );
    $tree->add_node($node);
}
