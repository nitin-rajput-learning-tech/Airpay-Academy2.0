<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

/**
 * My classrooms - the classrooms a learner is on the roster of, with the sessions they were booked on, how
 * they were marked and whether they completed (ADR-032, classroom code fix 12).
 *
 * BizLMS showed a learner this history; the Sentientia classroom pages are for managers and trainers
 * (local/sentientia_classroom:view is not granted to learners), so after the BizLMS import the learner would
 * not see their own training. The page shows the learner's OWN rows only, so it needs no capability beyond
 * a login, and it is behind sentientia.classroom.import_history, default OFF: with the flag off the page does
 * not exist. The data is built by \local_sentientia_classroom\my_classrooms.
 *
 * @package    local_sentientia_classroom
 * @copyright  2026 Airpay Payment Services
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../config.php');

require_login();
if (isguestuser()) {
    throw new \moodle_exception('noguest');
}
if (!\local_sentientia_classroom\session_manager::history_enabled()) {
    throw new \moodle_exception('error_history_off', 'local_sentientia_classroom');
}

$context = context_system::instance();
$PAGE->set_context($context);
$PAGE->set_url(new moodle_url('/local/sentientia_classroom/my.php'));
$PAGE->set_title(get_string('myclassrooms', 'local_sentientia_classroom'));
$PAGE->set_heading(get_string('myclassrooms', 'local_sentientia_classroom'));
$PAGE->set_pagelayout('standard');

echo $OUTPUT->header();
echo $OUTPUT->render_from_template('local_sentientia_classroom/my',
    \local_sentientia_classroom\my_classrooms::context_for((int) $USER->id));
echo $OUTPUT->footer();
