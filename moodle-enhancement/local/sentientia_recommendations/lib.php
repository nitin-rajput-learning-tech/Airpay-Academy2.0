<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

/**
 * Sentientia LMS AI Recommendations — public lib helpers.
 *
 * Phase H.0 (MVP) — only the helpers used by other plugins or by core
 * Moodle hooks belong here. Generation + persistence + parsing live in
 * classes/ so they can be unit-tested in isolation.
 *
 * @package local_sentientia_recommendations
 */

defined('MOODLE_INTERNAL') || die();

/**
 * Extend the global navigation with the recommendations generate link for
 * users with the `local/sentientia_recommendations:generate` capability.
 *
 * Phase H.0 keeps the link silent when the feature flag is OFF — same
 * pattern used by every other Sentientia LMS plugin.
 *
 * @param global_navigation $nav
 */
function local_sentientia_recommendations_extend_navigation(global_navigation $nav): void {
    global $USER;

    if (empty($USER->id) || isguestuser()) {
        return;
    }

    if (class_exists('\\local_airpay_core\\feature_flags')
            && !\local_airpay_core\feature_flags::is_enabled('sentientia.recommendations.enabled')) {
        return;
    }

    $context = \context_system::instance();
    if (!has_capability('local/sentientia_recommendations:generate', $context)) {
        return;
    }

    $url = new \moodle_url('/local/sentientia_recommendations/generate.php');
    $node = $nav->add(
        get_string('nav_generate', 'local_sentientia_recommendations'),
        $url,
        \navigation_node::TYPE_CUSTOM,
        null,
        'local_sentientia_recommendations_generate'
    );
    $node->showinflatnavigation = false;
}
