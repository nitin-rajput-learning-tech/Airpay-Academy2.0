<?php
/**
 * DPDP Self-Service — Users can download data or request account deletion.
 * Available to ALL users, but deletion self-service is for Public tenant only.
 *
 * @package    local_airpay_privacy
 */

require_once(__DIR__ . '/../../config.php');
require_login();

$PAGE->set_url(new moodle_url('/local/airpay_privacy/index.php'));
$PAGE->set_context(context_system::instance());
$PAGE->set_title(get_string('myprivacy', 'local_airpay_privacy'));
$PAGE->set_pagelayout('standard');

$action = optional_param('action', '', PARAM_ALPHA);
$tab    = optional_param('tab', '', PARAM_ALPHA);
$userid = $USER->id;
$manager = \local_airpay_privacy\privacy_manager::class;

// ════════════════════════════════════════════════════════════════
// ADMIN VIEW — siteadmins see request management panel
// ════════════════════════════════════════════════════════════════
if (is_siteadmin() || has_capability('local/airpay_privacy:manage', context_system::instance())) {
    $PAGE->set_heading(get_string('pluginname', 'local_airpay_privacy') . ' — Administration');

    // Handle admin actions.
    if ($action === 'approve' && confirm_sesskey()) {
        $reqid = required_param('reqid', PARAM_INT);
        $manager::process_deletion($reqid);
        redirect(new moodle_url('/local/airpay_privacy/index.php'),
            'Request processed successfully.', null, \core\output\notification::NOTIFY_SUCCESS);
    }
    if ($action === 'reject' && confirm_sesskey()) {
        $reqid = required_param('reqid', PARAM_INT);
        global $DB;
        $DB->set_field('local_privacy_requests', 'status', 'rejected', ['id' => $reqid]);
        $DB->set_field('local_privacy_requests', 'timeprocessed', time(), ['id' => $reqid]);
        redirect(new moodle_url('/local/airpay_privacy/index.php'),
            'Request rejected.', null, \core\output\notification::NOTIFY_WARNING);
    }

    // Get all requests across all users.
    global $DB;
    $allrequests = $DB->get_records_sql(
        "SELECT pr.*, u.firstname, u.lastname, u.email, u.open_path
           FROM {local_privacy_requests} pr
           JOIN {user} u ON u.id = pr.userid
       ORDER BY pr.timecreated DESC"
    );

    $pending = 0;
    $completed = 0;
    $total = count($allrequests);
    $rows = [];
    foreach ($allrequests as $r) {
        if ($r->status === 'pending') { $pending++; }
        if ($r->status === 'completed') { $completed++; }
        $parts = explode('/', trim($r->open_path ?? '', '/'));
        $tenantid = (int)($parts[0] ?? 0);
        $rows[] = [
            'id'          => $r->id,
            'user_name'   => format_string($r->firstname . ' ' . $r->lastname),
            'user_email'  => s($r->email),
            'tenant_id'   => $tenantid,
            'type'        => ucfirst(str_replace('_', ' ', $r->request_type)),
            'type_delete'  => ($r->request_type === 'account_delete'),
            'type_download' => ($r->request_type === 'data_download'),
            'reason'      => s($r->reason ?? ''),
            'status'      => ucfirst($r->status),
            'is_pending'  => ($r->status === 'pending'),
            'is_completed' => ($r->status === 'completed'),
            'is_rejected' => ($r->status === 'rejected'),
            'created'     => userdate($r->timecreated, '%d %b %Y %I:%M %p'),
            'processed'   => $r->timeprocessed ? userdate($r->timeprocessed, '%d %b %Y %I:%M %p') : '-',
        ];
    }

    $admindata = [
        'sesskey'    => sesskey(),
        'baseurl'    => (new moodle_url('/local/airpay_privacy/index.php'))->out(false),
        'total'      => $total,
        'pending'    => $pending,
        'completed'  => $completed,
        'rejected'   => $total - $pending - $completed,
        'requests'   => $rows,
        'has_requests' => !empty($rows),
    ];

    echo $OUTPUT->header();
    echo $OUTPUT->render_from_template('local_airpay_privacy/admin_panel', $admindata);
    echo $OUTPUT->footer();
    die();
}

// ════════════════════════════════════════════════════════════════
// USER SELF-SERVICE VIEW — regular users
// ════════════════════════════════════════════════════════════════

// Handle form submissions.
if ($action === 'download' && confirm_sesskey()) {
    $requestid = $manager::request_data_download($userid);
    // Process immediately for download requests.
    $manager::process_download($requestid);
    redirect(new moodle_url('/local/airpay_privacy/index.php'),
        get_string('downloadrequested', 'local_airpay_privacy'), null, \core\output\notification::NOTIFY_SUCCESS);
}

if ($action === 'delete' && confirm_sesskey()) {
    $reason = required_param('reason', PARAM_TEXT);
    $manager::request_account_deletion($userid, $reason);
    redirect(new moodle_url('/local/airpay_privacy/index.php'),
        get_string('deleterequested', 'local_airpay_privacy'), null, \core\output\notification::NOTIFY_WARNING);
}

// Get existing requests.
$requests = $manager::get_user_requests($userid);
$is_public = $manager::is_public_tenant($userid);

$formatted_requests = [];
foreach ($requests as $r) {
    $formatted_requests[] = [
        'id'         => $r->id,
        'type'       => ucfirst(str_replace('_', ' ', $r->request_type)),
        'status'     => ucfirst($r->status),
        'status_class' => match($r->status) {
            'completed' => 'success', 'pending' => 'warning',
            'processing' => 'info', 'rejected' => 'danger', default => 'secondary'
        },
        'created'    => userdate($r->timecreated, '%d %b %Y %I:%M %p'),
        'processed'  => $r->timeprocessed ? userdate($r->timeprocessed, '%d %b %Y %I:%M %p') : '-',
        'has_download' => ($r->request_type === 'data_download' && $r->status === 'completed' &&
                           $r->timeexpires && $r->timeexpires > time()),
        'download_id' => $r->id,
    ];
}

$data = [
    'sesskey'          => sesskey(),
    'is_public_tenant' => $is_public,
    'requests'         => $formatted_requests,
    'has_requests'     => !empty($formatted_requests),
    'firstname'        => format_string($USER->firstname),
    'baseurl'          => (new moodle_url('/local/airpay_privacy/index.php'))->out(false),
];

echo $OUTPUT->header();
echo $OUTPUT->render_from_template('local_airpay_privacy/self_service', $data);
echo $OUTPUT->footer();
