<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

/**
 * Sentientia LMS Skills Intelligence — public lib helpers.
 *
 * Only navigation hooks live here; extraction / parsing / gap logic live in
 * classes/ so they can be unit-tested in isolation.
 *
 * @package local_sentientia_skillsai
 */

defined('MOODLE_INTERNAL') || die();

/**
 * Extend global navigation with the Skills Intelligence links.
 *
 * Each link is silent unless the master feature flag is ON and the user
 * holds the relevant capability — same pattern as every other Sentientia
 * plugin.
 *
 * @param global_navigation $nav
 */
function local_sentientia_skillsai_extend_navigation(global_navigation $nav): void {
    global $USER;

    if (empty($USER->id) || isguestuser()) {
        return;
    }

    if (!class_exists('\\local_sentientia_platform\\feature_flags')
            || !\local_sentientia_platform\feature_flags::is_enabled('sentientia.skillsai.enabled')) {
        return;
    }

    $context = \context_system::instance();

    // Extract / taxonomy authoring.
    if (has_capability('local/sentientia_skillsai:extract', $context)) {
        $node = $nav->add(
            get_string('nav_extract', 'local_sentientia_skillsai'),
            new \moodle_url('/local/sentientia_skillsai/extract.php'),
            \navigation_node::TYPE_CUSTOM,
            null,
            'local_sentientia_skillsai_extract'
        );
        $node->showinflatnavigation = false;
    }

    // Gap feed — only when the gap engine flag is also ON.
    if (\local_sentientia_platform\feature_flags::is_enabled('sentientia.skillsai.gap_engine')
            && has_capability('local/sentientia_skillsai:viewgaps', $context)) {
        $node = $nav->add(
            get_string('nav_gaps', 'local_sentientia_skillsai'),
            new \moodle_url('/local/sentientia_skillsai/gaps.php'),
            \navigation_node::TYPE_CUSTOM,
            null,
            'local_sentientia_skillsai_gaps'
        );
        $node->showinflatnavigation = false;
    }
}
