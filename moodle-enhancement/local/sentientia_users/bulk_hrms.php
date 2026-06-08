<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
//
// W1-6 (2026-05-16) — HRMS 24-column bulk-import page.

require_once(__DIR__ . '/../../config.php');
require_once($CFG->libdir . '/formslib.php');

require_login();
$context = \context_system::instance();
require_capability('local/sentientia_users:create', $context);

$PAGE->set_context($context);
$PAGE->set_url(new moodle_url('/local/sentientia_users/bulk_hrms.php'));
$PAGE->set_pagelayout('standard');
$PAGE->set_title(get_string('hrms_pagetitle', 'local_sentientia_users'));
$PAGE->set_heading(get_string('hrms_pageheading', 'local_sentientia_users'));
$PAGE->navbar->add(get_string('manage_users', 'local_sentientia_users'),
    new moodle_url('/local/sentientia_users/index.php'));
$PAGE->navbar->add(get_string('hrms_breadcrumb', 'local_sentientia_users'));

$mform = new \local_sentientia_users\form\bulk_hrms_form();

if ($mform->is_cancelled()) {
    redirect(new moodle_url('/local/sentientia_users/index.php'));
}

if ($data = $mform->get_data()) {
    // Read uploaded CSV content into memory. (The 24-col Darwinbox format
    // is ~250 bytes per row; even 10K employees ≈ 2.5MB which is fine in
    // memory. If we hit > 10K we'll move to streaming.)
    $content = $mform->get_file_content('csvfile');
    $filename = $mform->get_new_filename('csvfile') ?: 'hrms.csv';

    if ($content === false || $content === '') {
        echo $OUTPUT->header();
        echo $OUTPUT->notification(
            get_string('hrms_empty_csv', 'local_sentientia_users'), 'error');
        $mform->display();
        echo $OUTPUT->footer();
        exit;
    }

    // Run the import. Returns the sync_runs row id.
    @set_time_limit(60 * 30);   // 30 minutes hard cap
    raise_memory_limit(MEMORY_HUGE);

    $run_id = \local_sentientia_users\hrms_importer::import_csv(
        $content, (int) $USER->id, $filename, 'web');

    // Redirect to the run-detail page with success notice.
    redirect(
        new moodle_url('/local/sentientia_users/sync_run_detail.php',
            ['id' => $run_id]),
        get_string('hrms_import_done', 'local_sentientia_users'),
        null,
        \core\output\notification::NOTIFY_SUCCESS
    );
}

echo $OUTPUT->header();
echo $OUTPUT->heading(get_string('hrms_pageheading', 'local_sentientia_users'));

// Quick links: back to user list, sync run history.
echo html_writer::start_div('mb-3 d-flex gap-2');
echo html_writer::link(
    new moodle_url('/local/sentientia_users/index.php'),
    '← ' . get_string('back_to_users', 'local_sentientia_users'),
    ['class' => 'btn btn-outline-secondary btn-sm']
);
echo html_writer::link(
    new moodle_url('/local/sentientia_users/sync_runs.php'),
    get_string('hrms_view_history', 'local_sentientia_users'),
    ['class' => 'btn btn-outline-primary btn-sm']
);
echo html_writer::end_div();

$mform->display();

echo $OUTPUT->footer();
