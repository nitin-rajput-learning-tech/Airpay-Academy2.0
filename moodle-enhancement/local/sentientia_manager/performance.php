<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

/**
 * Team performance dashboard — Phase 4 B.10.
 *
 * @package local_sentientia_manager
 */

require_once(__DIR__ . '/../../config.php');
require_login();

global $OUTPUT, $PAGE;

$ctx = context_system::instance();
$PAGE->set_context($ctx);
$PAGE->set_url(new moodle_url('/local/sentientia_manager/performance.php'));
$PAGE->set_pagelayout('admin');
$PAGE->set_title('Team performance');
$PAGE->set_heading('Team performance');
// Bug fix 2026-05-22 (Goal A audit) — supervisor-or-capability check.
\local_sentientia_manager\team_manager::require_manage();

$period = optional_param('period', 30, PARAM_INT);

echo $OUTPUT->header();
echo $OUTPUT->render_from_template('local_sentientia_manager/performance', [
    'period_days' => $period,
    'periods' => [
        ['days' => 7,  'label' => '7 days',  'selected' => $period === 7],
        ['days' => 30, 'label' => '30 days', 'selected' => $period === 30],
        ['days' => 90, 'label' => '90 days', 'selected' => $period === 90],
    ],
]);
echo $OUTPUT->footer();
