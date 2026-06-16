<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

/**
 * Sentientia LMS GenAI Authoring Studio — public lib helpers.
 *
 * Only callbacks consumed by core Moodle hooks live here. Generation,
 * parsing, persistence, question-type validation, TTS, and localization live
 * in classes/* so they can be unit-tested in isolation.
 *
 * @package local_sentientia_authoring
 */

defined('MOODLE_INTERNAL') || die();

/**
 * Extend global navigation with the Authoring Studio link for users holding
 * the :generate capability — only when the master flag is ON.
 *
 * @param global_navigation $nav
 */
function local_sentientia_authoring_extend_navigation(global_navigation $nav): void {
    global $USER;

    if (empty($USER->id) || isguestuser()) {
        return;
    }
    if (class_exists('\\local_sentientia_platform\\feature_flags')
            && !\local_sentientia_platform\feature_flags::is_enabled('sentientia.authoring.enabled')) {
        return;
    }
    $context = \context_system::instance();
    if (!has_capability('local/sentientia_authoring:generate', $context)) {
        return;
    }
    $url = new \moodle_url('/local/sentientia_authoring/studio.php');
    $node = $nav->add(
        get_string('nav_studio', 'local_sentientia_authoring'),
        $url,
        \navigation_node::TYPE_CUSTOM,
        null,
        'local_sentientia_authoring_studio'
    );
    $node->showinflatnavigation = false;
}
