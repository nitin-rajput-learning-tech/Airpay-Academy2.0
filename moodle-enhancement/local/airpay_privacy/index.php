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
$userid = $USER->id;
$manager = \local_airpay_privacy\privacy_manager::class;

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
