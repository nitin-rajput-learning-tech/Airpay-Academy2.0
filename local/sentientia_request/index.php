<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

/**
 * My course requests — list of requests placed by the current user.
 *
 * @package local_sentientia_request
 */

require_once(__DIR__ . '/../../config.php');
require_login();

global $OUTPUT, $PAGE;

$ctx = context_system::instance();
$PAGE->set_context($ctx);
$PAGE->set_url(new moodle_url('/local/sentientia_request/index.php'));
$PAGE->set_pagelayout('standard');
$PAGE->set_title(get_string('myrequests', 'local_sentientia_request'));
$PAGE->set_heading(get_string('myrequests', 'local_sentientia_request'));
require_capability('local/sentientia_request:request', $ctx);

$columns = [
    ['key' => 'course_name', 'label' => 'Course',     'sortable' => false],
    ['key' => 'reason',      'label' => 'Reason',     'sortable' => false],
    ['key' => 'status_badge', 'label' => 'Status',    'sortable' => true, 'sortkey' => 'status', 'format' => 'badge'],
    ['key' => 'placed_on',   'label' => 'Placed',     'sortable' => true, 'sortkey' => 'timecreated'],
    ['key' => 'decided_on',  'label' => 'Decided',    'sortable' => true, 'sortkey' => 'timedecided'],
    ['key' => 'decision_note', 'label' => 'Note',     'sortable' => false],
    ['key' => 'actions',     'label' => '',           'sortable' => false, 'format' => 'html'],
];

$data = [
    // Bug fix 2026-05-22 (Goal A audit Bug #6): was `s(json_encode(...))`
    // which double-escapes the JSON when Mustache's `{{ columns_json }}`
    // auto-escapes it again. The browser then sees `&amp;quot;` instead
    // of `&quot;` in the data attribute → JSON.parse() chokes at
    // position 2 → Datatable.init() throws → "Loading..." never resolves.
    // Pass raw JSON; Mustache will escape exactly once for the attribute,
    // the browser unescapes once on dataset read.
    'columns_json'   => json_encode($columns),
    'pending_url'    => has_capability('local/sentientia_request:approve', $ctx)
        ? (new moodle_url('/local/sentientia_request/approvals.php'))->out(false) : '',
    'has_pending_cap' => has_capability('local/sentientia_request:approve', $ctx),
];

echo $OUTPUT->header();
echo $OUTPUT->render_from_template('local_sentientia_request/my_requests', $data);
echo $OUTPUT->footer();
