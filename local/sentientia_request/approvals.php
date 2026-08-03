<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

/**
 * Pending approvals — list of requests waiting on the current user.
 *
 * @package local_sentientia_request
 */

require_once(__DIR__ . '/../../config.php');
require_login();

global $OUTPUT, $PAGE;

$ctx = context_system::instance();
$PAGE->set_context($ctx);
$PAGE->set_url(new moodle_url('/local/sentientia_request/approvals.php'));
$PAGE->set_pagelayout('admin');
$PAGE->set_title(get_string('pendingapprovals', 'local_sentientia_request'));
$PAGE->set_heading(get_string('pendingapprovals', 'local_sentientia_request'));
require_capability('local/sentientia_request:approve', $ctx);

$columns = [
    ['key' => 'requester_name', 'label' => 'Requester', 'sortable' => false],
    ['key' => 'course_name',    'label' => 'Course',    'sortable' => false],
    ['key' => 'reason',         'label' => 'Reason',    'sortable' => false],
    ['key' => 'due_badge',      'label' => 'SLA',       'sortable' => true, 'sortkey' => 'timedue', 'format' => 'badge'],
    ['key' => 'actions',        'label' => '',          'sortable' => false, 'format' => 'html'],
];

$data = [
    // WF-022: json_encode WITHOUT s() — the template's `{{ columns_json }}`
    // (double-stache) HTML-escapes it for the attribute. s() here double-escaped
    // (&quot; → &amp;quot;), so the browser handed JSON.parse literal `&quot;`
    // text and the datatable threw. Same Bug #6 fix already on my_requests.
    'columns_json' => json_encode($columns),
    'allrequests_url' => has_capability('local/sentientia_request:viewall', $ctx)
        ? (new moodle_url('/local/sentientia_request/all.php'))->out(false) : '',
    'has_viewall_cap' => has_capability('local/sentientia_request:viewall', $ctx),
];

echo $OUTPUT->header();
echo $OUTPUT->render_from_template('local_sentientia_request/pending', $data);
echo $OUTPUT->footer();
