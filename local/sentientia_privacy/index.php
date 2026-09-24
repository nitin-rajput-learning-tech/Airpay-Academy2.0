<?php
/**
 * DPDP Privacy Self-Service & Administration.
 *
 * ACCESS RULES:
 * - Siteadmin: Full admin panel — all requests, tenant DPDP configuration
 * - External tenant admin (DPDP-enabled tenant): Admin panel scoped to own tenant
 * - External/Public tenant user: Self-service data download + account deletion
 * - Internal employee (Airpay tenant 1): Policy notice ONLY — no download/deletion
 *   (governed by employment data retention laws, not consumer DPDP rights)
 *
 * @package    local_sentientia_privacy
 */

require_once(__DIR__ . '/../../config.php');
require_login();

$PAGE->set_url(new moodle_url('/local/sentientia_privacy/index.php'));
$PAGE->set_context(context_system::instance());
$PAGE->set_title(get_string('myprivacy', 'local_sentientia_privacy'));
$PAGE->set_pagelayout('standard');

$action = optional_param('action', '', PARAM_ALPHA);
$tab    = optional_param('tab', '', PARAM_ALPHA);
$userid = $USER->id;
$manager = \local_sentientia_privacy\privacy_manager::class;

// ════════════════════════════════════════════════════════════════
// ADMIN VIEW — siteadmins see request management panel
// ════════════════════════════════════════════════════════════════
// NOT TENANT-SCOPED: the request list below is every request on the site. That is
// right for a site admin and wrong for anyone else. local/sentientia_privacy:manage
// is declared with no archetypes and nobody holds it; granting it to a tenant admin
// would show them every tenant's erasure requests (names, emails, reasons). Scope
// the query with tenant::path_descendant_filter() before granting it to anyone.
if (is_siteadmin() || has_capability('local/sentientia_privacy:manage', context_system::instance())) {
    $PAGE->set_heading(get_string('pluginname', 'local_sentientia_privacy') . ' — Administration');
    $panelurl = new moodle_url('/local/sentientia_privacy/index.php');

    // Handle admin actions. Both act only on a request that is still pending, so a
    // replayed or stale link cannot re-run an erasure or overturn a decision.
    if (($action === 'approve' || $action === 'reject') && confirm_sesskey()) {
        $reqid = required_param('reqid', PARAM_INT);
        global $DB;
        $request = $DB->get_record('local_privacy_requests', ['id' => $reqid], '*', MUST_EXIST);
        if ($request->status !== 'pending') {
            redirect($panelurl, get_string('requestnotpending', 'local_sentientia_privacy'),
                null, \core\output\notification::NOTIFY_WARNING);
        }
    }
    if ($action === 'approve' && confirm_sesskey()) {
        // process_deletion() requires the approving admin's id; this call used to
        // omit it, so every Approve click died with an ArgumentCountError.
        $manager::process_deletion($reqid, (int) $USER->id);
        // It can finish 'partial' (some data could not be erased), and saying
        // "processed successfully" then would be the false success it now reports.
        $status = $DB->get_field('local_privacy_requests', 'status', ['id' => $reqid]);
        if ($status === 'completed') {
            redirect($panelurl, get_string('erasurecompleted', 'local_sentientia_privacy'),
                null, \core\output\notification::NOTIFY_SUCCESS);
        }
        redirect($panelurl, get_string('erasurepartial', 'local_sentientia_privacy'),
            null, \core\output\notification::NOTIFY_ERROR);
    }
    if ($action === 'reject' && confirm_sesskey()) {
        $DB->set_field('local_privacy_requests', 'status', 'rejected', ['id' => $reqid]);
        $DB->set_field('local_privacy_requests', 'timeprocessed', time(), ['id' => $reqid]);
        redirect($panelurl, 'Request rejected.', null, \core\output\notification::NOTIFY_WARNING);
    }

    // Get all requests across all users.
    global $DB;
    $allrequests = $DB->get_records_sql(
        "SELECT pr.*, u.firstname, u.lastname, u.email, u.open_path
           FROM {local_privacy_requests} pr
           JOIN {user} u ON u.id = pr.userid
       ORDER BY pr.timecreated DESC"
    );

    // Each status is counted explicitly. 'Rejected' used to be total minus the
    // others, so every 'partial' (incomplete erasure) and 'processing' request was
    // shown to the DPO as rejected.
    $pending = 0;
    $completed = 0;
    $partial = 0;
    $rejected = 0;
    $total = count($allrequests);
    $rows = [];
    foreach ($allrequests as $r) {
        if ($r->status === 'pending') { $pending++; }
        if ($r->status === 'completed') { $completed++; }
        if ($r->status === 'partial') { $partial++; }
        if ($r->status === 'rejected') { $rejected++; }
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
            'is_partial'  => ($r->status === 'partial'),
            // The note names what could not be erased; the DPO needs it to finish
            // the request by hand. Escaped by the template's {{admin_notes}}, not
            // here - it quotes provider exception text, and s() twice mangles it.
            'admin_notes' => ($r->status === 'partial') ? (string) ($r->admin_notes ?? '') : '',
            'created'     => userdate($r->timecreated, '%d %b %Y %I:%M %p'),
            'processed'   => $r->timeprocessed ? userdate($r->timeprocessed, '%d %b %Y %I:%M %p') : '-',
        ];
    }

    // Phase B0+ — stat_card-compatible KPI tiles.
    $kpi_tiles = [
        [
            'label' => 'Total Requests',
            'value' => number_format($total),
            'icon'  => 'inbox',
            'color' => 'primary',
        ],
        [
            'label' => 'Pending',
            'value' => number_format($pending),
            'icon'  => 'clock-o',
            // Pending DPDP requests have a 72h / 30d SLA — flag when > 0.
            'color' => $pending > 0 ? 'warning' : 'primary',
        ],
        [
            'label' => 'Completed',
            'value' => number_format($completed),
            'icon'  => 'check-circle',
            'color' => 'success',
        ],
        [
            'label' => get_string('incomplete', 'local_sentientia_privacy'),
            'value' => number_format($partial),
            'icon'  => 'exclamation-triangle',
            // Each one is a data subject told nothing yet whose data may survive.
            'color' => $partial > 0 ? 'danger' : 'primary',
        ],
        [
            'label' => 'Rejected',
            'value' => number_format($rejected),
            'icon'  => 'times-circle',
            'color' => $rejected > 0 ? 'danger' : 'primary',
        ],
    ];

    $admindata = [
        'sesskey'      => sesskey(),
        'baseurl'      => (new moodle_url('/local/sentientia_privacy/index.php'))->out(false),
        'total'        => $total,
        'pending'      => $pending,
        'completed'    => $completed,
        'partial'      => $partial,
        'rejected'     => $rejected,
        'kpi_tiles'    => $kpi_tiles,
        'has_kpi_tiles' => !empty($kpi_tiles),
        'requests'     => $rows,
        'has_requests' => !empty($rows),
    ];

    echo $OUTPUT->header();
    echo $OUTPUT->render_from_template('local_sentientia_privacy/admin_panel', $admindata);
    echo $OUTPUT->footer();
    die();
}

// ════════════════════════════════════════════════════════════════
// TENANT-BASED ACCESS CONTROL
// ════════════════════════════════════════════════════════════════

// Determine user's tenant from open_path.
$parts = explode('/', trim($USER->open_path ?? '', '/'));
$user_tenant_id = (int)($parts[0] ?? 0);

// Get DPDP-enabled tenants (siteadmin configurable).
// Default: only external/Public tenant (77) has full DPDP self-service.
$dpdp_enabled_tenants = explode(',', get_config('local_sentientia_privacy', 'dpdp_tenants') ?: '77');
$dpdp_enabled_tenants = array_map('intval', array_filter($dpdp_enabled_tenants));

$is_dpdp_enabled = in_array($user_tenant_id, $dpdp_enabled_tenants, true);
$is_internal_employee = ($user_tenant_id == 1); // Airpay internal = tenant 1.

// ════════════════════════════════════════════════════════════════
// INTERNAL EMPLOYEE VIEW — DPDP policy notice only (no self-service)
// Employees are covered by employment data retention laws, not consumer DPDP rights.
// ════════════════════════════════════════════════════════════════
if ($is_internal_employee && !is_siteadmin() && !\local_sentientia_courses\course_manager::can_manage()) {
    $PAGE->set_heading(get_string('myprivacy', 'local_sentientia_privacy'));
    echo $OUTPUT->header();
    echo '<div class="airpay-privacy" style="max-width:800px; margin:0 auto;">';
    echo '<div class="airpay-privacy__header">';
    echo '<h2><i class="fa fa-shield"></i> ' . get_string('myprivacy', 'local_sentientia_privacy') . '</h2>';
    echo '</div>';

    echo '<div class="airpay-privacy__section">';
    echo '<h3><i class="fa fa-info-circle"></i> Your Data Privacy</h3>';
    echo '<p>As an Airpay employee, your personal data is processed and retained in accordance with applicable employment and data retention laws.</p>';
    echo '<ul>';
    echo '<li>Your employment data (name, email, department, designation) is maintained as per your employment contract.</li>';
    echo '<li>Your learning records (courses, completions, certificates) are retained for compliance and professional development purposes.</li>';
    echo '<li>You can view your profile data at any time via your <a href="' . (new moodle_url('/local/sentientia_users/profile.php'))->out() . '">Profile page</a>.</li>';
    echo '<li>For data correction requests, contact your HR team or the Data Protection Officer.</li>';
    echo '</ul>';
    echo '<div class="airpay-privacy__notice">';
    echo '<small><strong>Data Protection Officer:</strong> ' . s(get_config('local_sentientia_privacy', 'dpo_email') ?: 'academy@airpay.co.in') . '</small>';
    echo '</div>';
    echo '</div>';

    echo '<div class="airpay-privacy__section">';
    echo '<h3><i class="fa fa-file-text"></i> DPDP Act 2023 Notice</h3>';
    echo '<p>Airpay Payment Services processes your personal data as a Data Fiduciary under the Digital Personal Data Protection Act, 2023. ';
    echo 'As an employee, data processing is based on your employment contract (lawful purpose under Section 4). ';
    echo 'For the full privacy policy, see <a href="' . (new moodle_url('/local/sentientia_pages/index.php', ['page' => 'privacy']))->out() . '">Privacy Policy</a>.</p>';
    echo '</div>';

    echo '</div>';
    echo $OUTPUT->footer();
    die();
}

// ════════════════════════════════════════════════════════════════
// NON-DPDP TENANT VIEW — policy notice only
// ════════════════════════════════════════════════════════════════
if (!$is_dpdp_enabled && !is_siteadmin() && !\local_sentientia_courses\course_manager::can_manage()) {
    $PAGE->set_heading(get_string('myprivacy', 'local_sentientia_privacy'));
    echo $OUTPUT->header();
    echo '<div class="airpay-privacy" style="max-width:800px; margin:0 auto;">';
    echo '<div class="airpay-privacy__header">';
    echo '<h2><i class="fa fa-shield"></i> ' . get_string('myprivacy', 'local_sentientia_privacy') . '</h2>';
    echo '</div>';
    echo '<div class="airpay-privacy__section">';
    echo '<h3><i class="fa fa-info-circle"></i> Data Privacy</h3>';
    echo '<p>Your data is managed in accordance with applicable data protection laws. ';
    echo 'For data access or correction requests, contact your administrator.</p>';
    echo '<p>For the full privacy policy, see <a href="' . (new moodle_url('/local/sentientia_pages/index.php', ['page' => 'privacy']))->out() . '">Privacy Policy</a>.</p>';
    echo '</div>';
    echo '</div>';
    echo $OUTPUT->footer();
    die();
}

// ════════════════════════════════════════════════════════════════
// DPDP-ENABLED TENANT USER — full self-service
// ════════════════════════════════════════════════════════════════
$PAGE->set_heading(get_string('myprivacy', 'local_sentientia_privacy'));

// Handle form submissions.
if ($action === 'download' && confirm_sesskey()) {
    $requestid = $manager::request_data_download($userid);
    $manager::process_download($requestid);
    redirect(new moodle_url('/local/sentientia_privacy/index.php'),
        get_string('downloadrequested', 'local_sentientia_privacy'), null, \core\output\notification::NOTIFY_SUCCESS);
}

if ($action === 'delete' && confirm_sesskey()) {
    $reason = required_param('reason', PARAM_TEXT);
    $manager::request_account_deletion($userid, $reason);
    redirect(new moodle_url('/local/sentientia_privacy/index.php'),
        get_string('deleterequested', 'local_sentientia_privacy'), null, \core\output\notification::NOTIFY_WARNING);
}

// Get existing requests.
$requests = $manager::get_user_requests($userid);

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
    'is_public_tenant' => true, // DPDP-enabled tenant — show full self-service including deletion.
    'requests'         => $formatted_requests,
    'has_requests'     => !empty($formatted_requests),
    'firstname'        => format_string($USER->firstname),
    'baseurl'          => (new moodle_url('/local/sentientia_privacy/index.php'))->out(false),
];

echo $OUTPUT->header();
echo $OUTPUT->render_from_template('local_sentientia_privacy/self_service', $data);
echo $OUTPUT->footer();
