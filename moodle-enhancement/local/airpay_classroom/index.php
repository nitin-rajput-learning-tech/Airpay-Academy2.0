<?php
// Airpay Classroom Training — redirects to BizLMS during transition.

require_once(__DIR__ . '/../../config.php');
require_login();

// If BizLMS classroom management still exists, redirect there.
if (file_exists($CFG->dirroot . '/local/classroom/index.php')) {
    redirect(new moodle_url('/local/classroom/index.php'));
}

$context = context_system::instance();
$PAGE->set_context($context);
$PAGE->set_url(new moodle_url('/local/airpay_classroom/index.php'));
$PAGE->set_title(get_string('pluginname', 'local_airpay_classroom'));
$PAGE->set_heading(get_string('pluginname', 'local_airpay_classroom'));
$PAGE->set_pagelayout('admin');

echo $OUTPUT->header();
echo $OUTPUT->heading(get_string('pluginname', 'local_airpay_classroom'));

$count = \local_airpay_classroom\session_manager::count_classrooms();
echo html_writer::tag('p', "Total classroom sessions: {$count}");
echo html_writer::link(new moodle_url('/my/dashboard.php'), 'Back to Dashboard', ['class' => 'btn btn-primary']);

echo $OUTPUT->footer();
