<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

/**
 * Sprint D — Airpay Super Admin pending-requests inbox.
 *
 * Shows pending course-share requests from receiving tenants, with
 * Approve / Reject buttons. Approving fires both
 * request_manager::approve_request and (via cascade)
 * sharing_manager::share_course, so the borrowed course appears in
 * the requesting tenant's catalog immediately.
 *
 * Access: local/airpay_courses:approve_request (siteadmin only by default).
 *
 * @package local_airpay_courses
 */

require_once(__DIR__ . '/../../config.php');

require_login();

$context = context_system::instance();
$PAGE->set_context($context);
require_capability('local/airpay_courses:approve_request', $context);

// Phase A0 (2026-05-14): Switchboard gate.
if (!\local_airpay_core\feature_flags::is_enabled('commerce.crossTenantRequest.enabled')) {
    throw new \moodle_exception('featuredisabled', 'local_airpay_core', '',
        'commerce.crossTenantRequest.enabled');
}

global $DB, $OUTPUT;

// POST — Approve / Reject action.
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_sesskey();
    $action = required_param('action', PARAM_ALPHA);
    $requestid = required_param('requestid', PARAM_INT);

    if ($action === 'approve') {
        \local_airpay_courses\request_manager::approve_request($requestid);
        $msg = get_string('request_approved', 'local_airpay_courses');
    } else if ($action === 'reject') {
        $reason = optional_param('reason', '', PARAM_TEXT);
        \local_airpay_courses\request_manager::reject_request($requestid, $reason);
        $msg = get_string('request_rejected', 'local_airpay_courses');
    } else {
        throw new \moodle_exception('invalidparameter', 'local_airpay_courses');
    }

    redirect(
        new moodle_url('/local/airpay_courses/manage_requests.php'),
        $msg,
        2,
        \core\output\notification::NOTIFY_SUCCESS
    );
}

// Render pending requests.
$pending = \local_airpay_courses\request_manager::list_pending_requests();
$known   = \local_airpay_courses\sharing_manager::known_tenants();
$tenant_names = [];
foreach ($known as $t) {
    $tenant_names[(int) $t->id] = $t->name;
}

$rows = [];
foreach ($pending as $r) {
    $rows[] = [
        'id'              => (int) $r->id,
        'courseid'        => (int) $r->courseid,
        'coursename'      => format_string($r->coursename),
        'courseshort'     => format_string($r->courseshort),
        'requester_name'  => format_string("$r->firstname $r->lastname"),
        'requester_email' => format_string($r->email),
        'tenant_id'       => (int) $r->requesting_tenant,
        'tenant_name'     => $tenant_names[(int) $r->requesting_tenant]
            ?? ('Tenant ' . $r->requesting_tenant),
        'requested_human' => userdate((int) $r->timecreated, '%d %b %Y %H:%M'),
    ];
}

$PAGE->set_url(new moodle_url('/local/airpay_courses/manage_requests.php'));
$PAGE->set_title('Course-share requests');
$PAGE->set_heading('Course-share requests');
$PAGE->set_pagelayout('admin');

$data = [
    'pending'    => $rows,
    'has_pending' => !empty($rows),
    'count'      => count($rows),
    'sesskey'    => sesskey(),
    'post_url'   => (new moodle_url('/local/airpay_courses/manage_requests.php'))->out(false),
];

echo $OUTPUT->header();
echo $OUTPUT->render_from_template('local_airpay_courses/manage_requests', $data);
echo $OUTPUT->footer();
