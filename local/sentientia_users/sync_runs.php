<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
//
// W1-6 (2026-05-16) — list of past HRMS bulk-import runs.

require_once(__DIR__ . '/../../config.php');

require_login();
$context = \context_system::instance();
require_capability('local/sentientia_users:create', $context);

$PAGE->set_context($context);
$PAGE->set_url(new moodle_url('/local/sentientia_users/sync_runs.php'));
$PAGE->set_pagelayout('standard');
$PAGE->set_title(get_string('hrms_history_title', 'local_sentientia_users'));
$PAGE->set_heading(get_string('hrms_history_title', 'local_sentientia_users'));
$PAGE->navbar->add(get_string('manage_users', 'local_sentientia_users'),
    new moodle_url('/local/sentientia_users/index.php'));
$PAGE->navbar->add(get_string('hrms_history_breadcrumb', 'local_sentientia_users'));

// Tenant scoping — non-siteadmins see only their own runs.
$where = '1=1';
$params = [];
if (!is_siteadmin()) {
    $caller_path = (string) ($USER->open_path ?? '');
    $parts = explode('/', trim($caller_path, '/'));
    $tenant = isset($parts[0]) && ctype_digit($parts[0]) ? (int) $parts[0] : 0;
    if ($tenant > 0) {
        $where = 'costcenterid = :cc';
        $params['cc'] = $tenant;
    }
}

$runs = $DB->get_records_sql(
    "SELECT r.*, u.firstname, u.lastname, u.email AS user_email
       FROM {local_sentientia_users_sync_runs} r
  LEFT JOIN {user} u ON u.id = r.usercreated
      WHERE $where
   ORDER BY r.timecreated DESC",
    $params, 0, 100
);

echo $OUTPUT->header();
echo $OUTPUT->heading(get_string('hrms_history_title', 'local_sentientia_users'));

echo html_writer::start_div('mb-3 d-flex gap-2');
echo html_writer::link(
    new moodle_url('/local/sentientia_users/index.php'),
    '← ' . get_string('back_to_users', 'local_sentientia_users'),
    ['class' => 'btn btn-outline-secondary btn-sm']
);
echo html_writer::link(
    new moodle_url('/local/sentientia_users/bulk_hrms.php'),
    '+ ' . get_string('hrms_new_import', 'local_sentientia_users'),
    ['class' => 'btn btn-primary btn-sm']
);
echo html_writer::end_div();

if (empty($runs)) {
    echo $OUTPUT->notification(
        get_string('hrms_no_runs', 'local_sentientia_users'), 'info');
    echo $OUTPUT->footer();
    exit;
}

$rows = [];
foreach ($runs as $r) {
    $detail_url = new moodle_url('/local/sentientia_users/sync_run_detail.php',
        ['id' => $r->id]);
    $status_badge = match ($r->status) {
        'completed' => '<span class="badge bg-success">Completed</span>',
        'running'   => '<span class="badge bg-warning text-dark">Running</span>',
        'failed'    => '<span class="badge bg-danger">Failed</span>',
        default     => '<span class="badge bg-secondary">' . s($r->status) . '</span>',
    };
    $rows[] = [
        '<a href="' . s($detail_url->out(false)) . '">#' . (int) $r->id . '</a>',
        s($r->filename ?: '(no file)'),
        userdate((int) $r->timecreated, '%d %b %Y, %H:%M'),
        fullname($r),
        s($r->source),
        $status_badge,
        (int) $r->totalrows,
        (int) $r->insertedcount,
        (int) $r->updatedcount,
        (int) $r->errorcount,
        (int) $r->warningcount,
    ];
}

$table = new html_table();
$table->head = [
    get_string('hrms_col_id',         'local_sentientia_users'),
    get_string('hrms_col_filename',   'local_sentientia_users'),
    get_string('hrms_col_time',       'local_sentientia_users'),
    get_string('hrms_col_user',       'local_sentientia_users'),
    get_string('hrms_col_source',     'local_sentientia_users'),
    get_string('hrms_col_status',     'local_sentientia_users'),
    get_string('hrms_col_total',      'local_sentientia_users'),
    get_string('hrms_col_inserted',   'local_sentientia_users'),
    get_string('hrms_col_updated',    'local_sentientia_users'),
    get_string('hrms_col_errors',     'local_sentientia_users'),
    get_string('hrms_col_warnings',   'local_sentientia_users'),
];
$table->attributes['class'] = 'generaltable';
$table->data = $rows;
echo html_writer::table($table);

echo $OUTPUT->footer();
