<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

/**
 * All requests — tenant-wide admin view.
 *
 * @package local_airpay_request
 */

require_once(__DIR__ . '/../../config.php');
require_login();

global $OUTPUT, $PAGE;

$ctx = context_system::instance();
$PAGE->set_context($ctx);
$PAGE->set_url(new moodle_url('/local/airpay_request/all.php'));
$PAGE->set_pagelayout('admin');
$PAGE->set_title(get_string('allrequests', 'local_airpay_request'));
$PAGE->set_heading(get_string('allrequests', 'local_airpay_request'));
require_capability('local/airpay_request:viewall', $ctx);

$columns = [
    ['key' => 'placed_on',      'label' => 'Placed',    'sortable' => true, 'sortkey' => 'timecreated'],
    ['key' => 'requester_name', 'label' => 'Requester', 'sortable' => false],
    ['key' => 'course_name',    'label' => 'Course',    'sortable' => false],
    ['key' => 'status_badge',   'label' => 'Status',    'sortable' => true, 'sortkey' => 'status', 'format' => 'badge'],
    ['key' => 'route',          'label' => 'Route',     'sortable' => false],
    ['key' => 'decided_on',     'label' => 'Decided',   'sortable' => true, 'sortkey' => 'timedecided'],
];

$data = [ 'columns_json' => s(json_encode($columns)) ];

echo $OUTPUT->header();
echo $OUTPUT->render_from_template('local_airpay_request/all_requests', $data);
echo $OUTPUT->footer();
