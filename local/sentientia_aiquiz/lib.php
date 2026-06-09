<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

/**
 * Sentientia LMS AI Quiz — public lib helpers.
 *
 * Phase G.0 (MVP) — only the helpers used by other plugins or by core
 * Moodle hooks belong here. Generation logic + parser live in
 * classes/anthropic_client.php + classes/response_parser.php
 * + classes/prompt_builder.php so they can be unit-tested in isolation.
 *
 * @package local_sentientia_aiquiz
 */

defined('MOODLE_INTERNAL') || die();

/**
 * Extend the global navigation with the AI Quiz authoring link for users
 * with the `local/sentientia_aiquiz:generate` capability.
 *
 * Phase G.0 keeps the link silent when the feature flag is OFF — same
 * pattern used by every other Sentientia LMS plugin.
 *
 * @param global_navigation $nav
 */
function local_sentientia_aiquiz_extend_navigation(global_navigation $nav): void {
    global $USER;

    if (empty($USER->id) || isguestuser()) {
        return;
    }

    if (class_exists('\\local_sentientia_platform\\feature_flags')
            && !\local_sentientia_platform\feature_flags::is_enabled('sentientia.aiquiz.enabled')) {
        return;
    }

    $context = \context_system::instance();
    if (!has_capability('local/sentientia_aiquiz:generate', $context)) {
        return;
    }

    $url = new \moodle_url('/local/sentientia_aiquiz/generate.php');
    $node = $nav->add(
        get_string('nav_generate', 'local_sentientia_aiquiz'),
        $url,
        \navigation_node::TYPE_CUSTOM,
        null,
        'local_sentientia_aiquiz_generate'
    );
    $node->showinflatnavigation = false;
}
