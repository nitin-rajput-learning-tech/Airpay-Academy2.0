<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

/**
 * Learner "My programs" page (ADR-032 code fix 1): the certification programs the signed-in learner is
 * enrolled in, level by level, including the history the BizLMS import carried.
 *
 * Behind the flag sentientia.programs.learner.enabled (default OFF). It shows only the learner's own
 * enrolments, only in active programs of their own tenant (see learner_view).
 *
 * @package    local_sentientia_programs
 * @copyright  2026 Airpay Payment Services
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../config.php');

require_login();
if (isguestuser()) {
    throw new \moodle_exception('noguest');
}

if (!\local_sentientia_programs\learner_view::enabled()) {
    throw new \moodle_exception('error_learner_page_off', 'local_sentientia_programs');
}

$context = context_system::instance();
$PAGE->set_context($context);
$PAGE->set_url(new moodle_url('/local/sentientia_programs/myprograms.php'));
$PAGE->set_title(get_string('myprograms', 'local_sentientia_programs'));
$PAGE->set_heading(get_string('myprograms', 'local_sentientia_programs'));
$PAGE->set_pagelayout('standard');
$PAGE->set_secondary_navigation(false);

$data = \local_sentientia_programs\learner_view::page_data((int) $USER->id);

echo $OUTPUT->header();
echo $OUTPUT->render_from_template('local_sentientia_programs/myprograms', $data);
echo $OUTPUT->footer();
