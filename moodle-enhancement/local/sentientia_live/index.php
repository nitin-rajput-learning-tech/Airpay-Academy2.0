<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

/**
 * Sentientia LMS Live engagement — Phase E.0 landing placeholder.
 *
 * Phase E.0 only ships the schema + capabilities + privacy + ADR.
 * No real UI yet. This file gates access to /local/sentientia_live/
 * so the URL doesn't 404 — instead it shows a "coming soon" notice.
 *
 * Phase E.1 will replace this with the full trainer dashboard.
 *
 * @package local_sentientia_live
 */

require(__DIR__ . '/../../config.php');

require_login();
$context = \context_system::instance();

// Gate on the master flag. Phase E.0 default: OFF.
if (class_exists('\\local_airpay_core\\feature_flags')) {
    if (!\local_airpay_core\feature_flags::is_enabled('live.enabled')) {
        throw new \moodle_exception('errorfeatureoff', 'local_sentientia_live');
    }
}

$PAGE->set_url('/local/sentientia_live/index.php');
$PAGE->set_context($context);
$PAGE->set_pagelayout('standard');
$PAGE->set_title(get_string('pluginname', 'local_sentientia_live'));
$PAGE->set_heading(get_string('pluginname', 'local_sentientia_live'));

echo $OUTPUT->header();
echo $OUTPUT->heading(get_string('pluginname', 'local_sentientia_live'));

echo \html_writer::tag('div',
    \html_writer::tag('strong', 'Phase E.0 — Foundation') .
    '<br>' .
    'The Live engagement feature is being built incrementally. ' .
    'Phase E.0 ships the database schema and capability framework. ' .
    'Trainer + audience UIs land in Phases E.1 and E.2.',
    ['class' => 'alert alert-info']);

echo $OUTPUT->footer();
