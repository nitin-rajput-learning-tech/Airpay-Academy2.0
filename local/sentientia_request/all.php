<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

/**
 * All requests — tenant-wide admin view.
 *
 * @package local_sentientia_request
 */

require_once(__DIR__ . '/../../config.php');
require_login();

global $OUTPUT, $PAGE;

$ctx = context_system::instance();
$PAGE->set_context($ctx);
$PAGE->set_url(new moodle_url('/local/sentientia_request/all.php'));
$PAGE->set_pagelayout('admin');
$PAGE->set_title(get_string('allrequests', 'local_sentientia_request'));
$PAGE->set_heading(get_string('allrequests', 'local_sentientia_request'));
require_capability('local/sentientia_request:viewall', $ctx);

$columns = [
    ['key' => 'placed_on',      'label' => 'Placed',    'sortable' => true, 'sortkey' => 'timecreated'],
    ['key' => 'requester_name', 'label' => 'Requester', 'sortable' => false],
    ['key' => 'course_name',    'label' => 'Course',    'sortable' => false],
    ['key' => 'status_badge',   'label' => 'Status',    'sortable' => true, 'sortkey' => 'status', 'format' => 'badge'],
    ['key' => 'route',          'label' => 'Route',     'sortable' => false],
    ['key' => 'decided_on',     'label' => 'Decided',   'sortable' => true, 'sortkey' => 'timedecided'],
];

// WF-022: json_encode WITHOUT s() — `{{ columns_json }}` HTML-escapes for the
// attribute; s() here double-escaped and broke JSON.parse. (See my_requests.)
$data = [ 'columns_json' => json_encode($columns) ];

echo $OUTPUT->header();
echo $OUTPUT->render_from_template('local_sentientia_request/all_requests', $data);
echo $OUTPUT->footer();
