<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

/**
 * Trainer dashboard — Phase E.1.f.
 *
 * Landing page for trainers. Lists their own sessions across all states.
 * "Create new" button opens trainer/create.php. Per-row actions: edit /
 * run / end / delete.
 *
 * Capability: local/sentientia_live:create (which all
 * editingteacher+ archetypes have by default).
 *
 * @package local_sentientia_live
 */

require(__DIR__ . '/../../../config.php');

require_login();
$context = \context_system::instance();
require_capability('local/sentientia_live:create', $context);

// Master flag gate.
if (class_exists('\\local_airpay_core\\feature_flags')) {
    if (!\local_airpay_core\feature_flags::is_enabled('live.enabled')) {
        throw new \moodle_exception('errorfeatureoff', 'local_sentientia_live');
    }
}

$PAGE->set_url('/local/sentientia_live/trainer/index.php');
$PAGE->set_context($context);
$PAGE->set_pagelayout('standard');
$PAGE->set_title(get_string('trainer_dashboard_pagetitle', 'local_sentientia_live'));
$PAGE->set_heading(get_string('trainer_dashboard_heading', 'local_sentientia_live'));

echo $OUTPUT->header();

$dashboard = new \local_sentientia_live\output\trainer_dashboard(
    (int) $USER->id,
    (new \moodle_url('/local/sentientia_live/trainer/create.php'))->out(false)
);
echo $OUTPUT->render_from_template('local_sentientia_live/trainer_dashboard',
    $dashboard->export_for_template($OUTPUT));

echo $OUTPUT->footer();
