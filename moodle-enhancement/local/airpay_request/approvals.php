<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

/**
 * Pending approvals — list of requests waiting on the current user.
 *
 * @package local_airpay_request
 */

require_once(__DIR__ . '/../../config.php');
require_login();

global $OUTPUT, $PAGE;

$ctx = context_system::instance();
$PAGE->set_context($ctx);
$PAGE->set_url(new moodle_url('/local/airpay_request/approvals.php'));
$PAGE->set_pagelayout('admin');
$PAGE->set_title(get_string('pendingapprovals', 'local_airpay_request'));
$PAGE->set_heading(get_string('pendingapprovals', 'local_airpay_request'));
require_capability('local/airpay_request:approve', $ctx);

$columns = [
    ['key' => 'requester_name', 'label' => 'Requester', 'sortable' => false],
    ['key' => 'course_name',    'label' => 'Course',    'sortable' => false],
    ['key' => 'reason',         'label' => 'Reason',    'sortable' => false],
    ['key' => 'due_badge',      'label' => 'SLA',       'sortable' => true, 'sortkey' => 'timedue', 'format' => 'badge'],
    ['key' => 'actions',        'label' => '',          'sortable' => false, 'format' => 'html'],
];

$data = [
    'columns_json' => s(json_encode($columns)),
    'allrequests_url' => has_capability('local/airpay_request:viewall', $ctx)
        ? (new moodle_url('/local/airpay_request/all.php'))->out(false) : '',
    'has_viewall_cap' => has_capability('local/airpay_request:viewall', $ctx),
];

echo $OUTPUT->header();
echo $OUTPUT->render_from_template('local_airpay_request/pending', $data);
echo $OUTPUT->footer();
