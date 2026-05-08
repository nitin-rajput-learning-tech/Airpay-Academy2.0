<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

require_once(__DIR__ . '/../../config.php');

require_login();
$context = context_system::instance();
require_capability('local/airpay_manager:view', $context);

$PAGE->set_url('/local/airpay_manager/allocations.php');
$PAGE->set_context($context);
$PAGE->set_pagelayout('admin');
$PAGE->set_title('Course Allocations');
$PAGE->set_heading('Course Allocations');

$columns = [
    ['key' => 'username',   'label' => 'Direct report', 'sortable' => false],
    ['key' => 'coursename', 'label' => 'Course',        'sortable' => false],
    ['key' => 'due_label',  'label' => 'Due',           'sortable' => false],
    ['key' => 'status',     'label' => 'Status',        'sortable' => false],
    ['key' => 'note',       'label' => 'Note',          'sortable' => false],
    ['key' => 'actions',    'label' => '',              'sortable' => false, 'format' => 'html'],
];

$can_allocate = has_capability('local/airpay_manager:allocate', $context);

$data = [
    'columns_json'    => json_encode($columns),
    'extra_args_json' => json_encode(['status' => 'all']),
    'index_url'       => (new moodle_url('/local/airpay_manager/index.php'))->out(false),
    'requests_url'    => (new moodle_url('/local/airpay_manager/requests.php'))->out(false),
    'exporturl'       => (new moodle_url('/local/airpay_manager/exportcsv.php',
                            ['sesskey' => sesskey()]))->out(false),
    'can_allocate'    => $can_allocate,
];

echo $OUTPUT->header();
echo $OUTPUT->render_from_template('local_airpay_manager/allocations', $data);
$PAGE->requires->js_call_amd('local_airpay_manager/manager_actions', 'init',
    [['page' => 'allocations']]);
$PAGE->requires->js_call_amd('theme_airpayux/datatable', 'init', []);
echo $OUTPUT->footer();
