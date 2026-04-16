<?php
// Airpay Online Exams — redirects to BizLMS during transition.

require_once(__DIR__ . '/../../config.php');
require_login();

// If BizLMS exam management still exists, redirect there.
if (file_exists($CFG->dirroot . '/local/onlineexams/index.php')) {
    redirect(new moodle_url('/local/onlineexams/index.php'));
}

$context = context_system::instance();
$PAGE->set_context($context);
$PAGE->set_url(new moodle_url('/local/airpay_exams/index.php'));
$PAGE->set_title(get_string('pluginname', 'local_airpay_exams'));
$PAGE->set_heading(get_string('pluginname', 'local_airpay_exams'));
$PAGE->set_pagelayout('admin');

echo $OUTPUT->header();
echo $OUTPUT->heading(get_string('pluginname', 'local_airpay_exams'));

$count = \local_airpay_exams\exam_manager::count_exams();
echo html_writer::tag('p', "Total exams: {$count}");
echo html_writer::link(new moodle_url('/my/dashboard.php'), 'Back to Dashboard', ['class' => 'btn btn-primary']);

echo $OUTPUT->footer();
