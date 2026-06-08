<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

/**
 * Daily payment sums — finance reconciliation report.
 *
 * @package local_sentientia_cart
 */

require_once(__DIR__ . '/../../config.php');
require_login();

global $OUTPUT, $PAGE;

$ctx = context_system::instance();
$PAGE->set_context($ctx);
$PAGE->set_url(new moodle_url('/local/sentientia_cart/daily_sums.php'));
$PAGE->set_pagelayout('admin');
$PAGE->set_title(get_string('exportreport_daily', 'local_sentientia_cart'));
$PAGE->set_heading(get_string('exportreport_daily', 'local_sentientia_cart'));
require_capability('local/sentientia_cart:viewallorders', $ctx);

$from = optional_param('from', date('Y-m-d', strtotime('-30 days')), PARAM_TEXT);
$to   = optional_param('to',   date('Y-m-d'), PARAM_TEXT);

$data = [
    'from'    => $from,
    'to'      => $to,
    'export_url' => (new moodle_url('/local/sentientia_cart/daily_sums_csv.php',
        ['from' => $from, 'to' => $to]))->out(false),
];

echo $OUTPUT->header();
echo $OUTPUT->render_from_template('local_sentientia_cart/daily_sums', $data);
echo $OUTPUT->footer();
