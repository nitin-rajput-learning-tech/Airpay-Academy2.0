<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

require_once(__DIR__ . '/../../config.php');

require_login();
$context = context_system::instance();
// Bug fix 2026-05-22 (Goal A audit): require_capability rejected
// supervisors-via-open_supervisorid who don't hold the Moodle manager
// archetype. See \local_sentientia_manager\team_manager::can_manage().
\local_sentientia_manager\team_manager::require_manage();

$PAGE->set_url('/local/sentientia_manager/requests.php');
$PAGE->set_context($context);
$PAGE->set_pagelayout('standard');
// V15 (2026-11-18): translated page title (was hard-coded English).
$PAGE->set_title(get_string('enrolmentrequests', 'local_sentientia_manager'));
$PAGE->set_heading(get_string('enrolmentrequests', 'local_sentientia_manager'));

// B7 (surface walk 2026-11-18): a learner's enrolment request is stored by
// local_sentientia_request (the catalogue's Request enrolment button, the
// BizLMS import) and routed to their line manager, who decides it on that
// plugin's Pending approvals page. This page lists local_sentientia_mgr_requests,
// which nothing writes (approval_manager::create_request() has no caller), so
// it said "No records found" while Pending approvals listed the same
// manager's requests. A manager with no rows here is sent to Pending
// approvals; one with rows keeps this list, under a link to it.
$approvalsurl = null;
$approvalscount = 0;
if (class_exists('\local_sentientia_request\request_manager')
        && get_capability_info('local/sentientia_request:approve')
        && has_capability('local/sentientia_request:approve', $context)) {
    $approvalsurl = new moodle_url('/local/sentientia_request/approvals.php');
    $approvalscount = \local_sentientia_request\request_manager::pending_count_for_approver((int) $USER->id);
}
$haslegacy = $DB->record_exists('local_sentientia_mgr_requests', ['managerid' => (int) $USER->id]);
if ($approvalsurl !== null && !$haslegacy) {
    redirect($approvalsurl);
}

$columns = [
    ['key' => 'when',       'label' => 'When',     'sortable' => false],
    ['key' => 'username',   'label' => 'Requester','sortable' => false],
    ['key' => 'coursename', 'label' => 'Course',   'sortable' => false],
    ['key' => 'reason',     'label' => 'Reason',   'sortable' => false],
    ['key' => 'status',     'label' => 'Status',   'sortable' => false],
    ['key' => 'actions',    'label' => '',         'sortable' => false, 'format' => 'html'],
];

$data = [
    'columns_json'    => json_encode($columns),
    'extra_args_json' => json_encode(['status' => 'pending']),
    'index_url'       => (new moodle_url('/local/sentientia_manager/index.php'))->out(false),
    'allocations_url' => (new moodle_url('/local/sentientia_manager/allocations.php'))->out(false),
    'approvals_url'   => $approvalsurl ? $approvalsurl->out(false) : '',
    'approvals_count' => $approvalscount,
];

echo $OUTPUT->header();
echo $OUTPUT->render_from_template('local_sentientia_manager/requests', $data);
$PAGE->requires->js_call_amd('local_sentientia_manager/manager_actions', 'init',
    [['page' => 'requests']]);
$PAGE->requires->js_call_amd('theme_sentientia/datatable', 'init', []);
echo $OUTPUT->footer();
