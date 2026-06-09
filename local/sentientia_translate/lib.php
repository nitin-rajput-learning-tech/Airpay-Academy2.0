<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

/**
 * Sentientia LMS AI Translation — public lib helpers.
 *
 * Phase T.0 (MVP) — only the helpers used by other plugins or by core
 * Moodle hooks belong here. Translation logic lives in classes/ so it can
 * be unit-tested in isolation.
 *
 * @package local_sentientia_translate
 */

defined('MOODLE_INTERNAL') || die();

/**
 * Extend the global navigation with the translate link for users with the
 * `local/sentientia_translate:translate` capability.
 *
 * Phase T.0 keeps the link silent when the feature flag is OFF — same
 * pattern used by every other Sentientia LMS plugin.
 *
 * @param global_navigation $nav
 */
function local_sentientia_translate_extend_navigation(global_navigation $nav): void {
    global $USER;

    if (empty($USER->id) || isguestuser()) {
        return;
    }

    if (class_exists('\\local_sentientia_platform\\feature_flags')
            && !\local_sentientia_platform\feature_flags::is_enabled('sentientia.translate.enabled')) {
        return;
    }

    $context = \context_system::instance();
    if (!has_capability('local/sentientia_translate:translate', $context)) {
        return;
    }

    $url = new \moodle_url('/local/sentientia_translate/translate.php');
    $node = $nav->add(
        get_string('nav_translate', 'local_sentientia_translate'),
        $url,
        \navigation_node::TYPE_CUSTOM,
        null,
        'local_sentientia_translate_translate'
    );
    $node->showinflatnavigation = false;
}
