<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
//
// W1-6 (2026-05-16) — drill-down for one HRMS bulk-import run: stats + every
// row's error/warning detail.

require_once(__DIR__ . '/../../config.php');

$run_id = required_param('id', PARAM_INT);

require_login();
$context = \context_system::instance();
require_capability('local/airpay_users:create', $context);

$run = $DB->get_record('local_airpay_users_sync_runs', ['id' => $run_id], '*', MUST_EXIST);

// Tenant scoping: non-siteadmin can only view runs in their tenant.
if (!is_siteadmin()) {
    $caller_path = (string) ($USER->open_path ?? '');
    $parts = explode('/', trim($caller_path, '/'));
    $tenant = isset($parts[0]) && ctype_digit($parts[0]) ? (int) $parts[0] : 0;
    if ($tenant === 0 || (int) $run->costcenterid !== $tenant) {
        throw new \moodle_exception('nopermissions', 'error',
            new moodle_url('/local/airpay_users/sync_runs.php'),
            'view this HRMS sync run');
    }
}

$PAGE->set_context($context);
$PAGE->set_url(new moodle_url('/local/airpay_users/sync_run_detail.php',
    ['id' => $run_id]));
$PAGE->set_pagelayout('standard');
$PAGE->set_title(get_string('hrms_run_detail_title', 'local_airpay_users', $run_id));
$PAGE->set_heading(get_string('hrms_run_detail_heading', 'local_airpay_users', $run_id));
$PAGE->navbar->add(get_string('manage_users', 'local_airpay_users'),
    new moodle_url('/local/airpay_users/index.php'));
$PAGE->navbar->add(get_string('hrms_history_breadcrumb', 'local_airpay_users'),
    new moodle_url('/local/airpay_users/sync_runs.php'));
$PAGE->navbar->add('#' . $run_id);

echo $OUTPUT->header();
echo $OUTPUT->heading(get_string('hrms_run_detail_heading', 'local_airpay_users', $run_id));

// Header card with run stats.
echo html_writer::start_div('card mb-3');
echo html_writer::start_div('card-body');
echo html_writer::tag('h5',
    s($run->filename ?: '(no filename)'),
    ['class' => 'card-title']);
echo html_writer::tag('p',
    'Source: <code>' . s($run->source) . '</code>'
    . ' &middot; '
    . userdate((int) $run->timecreated, '%d %b %Y, %H:%M:%S')
    . ' &middot; status <strong>' . s($run->status) . '</strong>'
    . ($run->error_summary
        ? '<br><span class="text-danger">' . s($run->error_summary) . '</span>'
        : ''),
    ['class' => 'text-muted small']);

// Stats row.
$stats = [
    ['Total rows',  (int) $run->totalrows,     'secondary'],
    ['Inserted',    (int) $run->insertedcount, 'success'],
    ['Updated',     (int) $run->updatedcount,  'info'],
    ['Skipped',     (int) $run->skippedcount,  'secondary'],
    ['Errors',      (int) $run->errorcount,    'danger'],
    ['Warnings',    (int) $run->warningcount,  'warning'],
    ['Suspended',   (int) $run->suspendedcount, 'dark'],
];
echo html_writer::start_div('d-flex flex-wrap gap-3 mt-3');
foreach ($stats as [$label, $value, $colour]) {
    echo html_writer::tag('div',
        html_writer::tag('div', $value,
            ['class' => 'fs-3 fw-bold text-' . $colour])
        . html_writer::tag('div', $label,
            ['class' => 'small text-muted']),
        ['class' => 'text-center', 'style' => 'min-width: 80px;']);
}
echo html_writer::end_div();
echo html_writer::end_div();
echo html_writer::end_div();

// Errors + warnings detail.
$errors = $DB->get_records('local_airpay_users_sync_errors',
    ['runid' => $run_id], 'severity ASC, csv_line_number ASC', '*', 0, 500);

if (empty($errors)) {
    echo $OUTPUT->notification(
        get_string('hrms_no_errors', 'local_airpay_users'), 'success');
} else {
    echo html_writer::tag('h5',
        get_string('hrms_error_log', 'local_airpay_users'),
        ['class' => 'mt-4 mb-3']);
    $rows = [];
    foreach ($errors as $e) {
        $sev_badge = $e->severity === 'warning'
            ? '<span class="badge bg-warning text-dark">Warning</span>'
            : '<span class="badge bg-danger">Error</span>';
        $rows[] = [
            (int) $e->csv_line_number,
            $sev_badge,
            s($e->email),
            s($e->employee_code),
            s($e->username),
            s($e->firstname . ' ' . $e->lastname),
            '<small>' . s($e->error_message) . '</small>',
            $e->mandatory_fields
                ? '<code class="text-danger small">' . s($e->mandatory_fields) . '</code>'
                : '',
        ];
    }
    $table = new html_table();
    $table->head = [
        get_string('hrms_col_line',     'local_airpay_users'),
        get_string('hrms_col_severity', 'local_airpay_users'),
        get_string('hrms_col_email',    'local_airpay_users'),
        get_string('hrms_col_empcode',  'local_airpay_users'),
        get_string('hrms_col_username', 'local_airpay_users'),
        get_string('hrms_col_name',     'local_airpay_users'),
        get_string('hrms_col_message',  'local_airpay_users'),
        get_string('hrms_col_missing',  'local_airpay_users'),
    ];
    $table->attributes['class'] = 'generaltable table-sm';
    $table->data = $rows;
    echo html_writer::table($table);
}

echo html_writer::start_div('mt-4');
echo html_writer::link(
    new moodle_url('/local/airpay_users/sync_runs.php'),
    '← ' . get_string('hrms_back_to_history', 'local_airpay_users'),
    ['class' => 'btn btn-outline-secondary']);
echo html_writer::end_div();

echo $OUTPUT->footer();
