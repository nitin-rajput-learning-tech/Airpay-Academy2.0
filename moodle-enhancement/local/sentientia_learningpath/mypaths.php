<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

/**
 * My learning paths: the learner's own page (ADR-032, mapping doc section 17, code fix 7).
 *
 * Behind the default-OFF flag sentientia.learningpath.learner_paths.enabled. A learner sees their own
 * enrolments in active paths of their own tenant, with progress and dates, including the history the
 * BizLMS import brought over. The admin surface (view.php, rosters, CSV) is unchanged and still
 * needs local/sentientia_learningpath:view.
 *
 * @package    local_sentientia_learningpath
 * @copyright  2026 Airpay Payment Services
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../config.php');

require_login();
if (isguestuser()) {
    redirect(get_login_url());
}

$context = context_system::instance();
$PAGE->set_context($context);
$PAGE->set_url(new moodle_url('/local/sentientia_learningpath/mypaths.php'));
$PAGE->set_title(get_string('mypaths_title', 'local_sentientia_learningpath'));
$PAGE->set_heading(get_string('mypaths_title', 'local_sentientia_learningpath'));
$PAGE->set_pagelayout('standard');
$PAGE->set_secondary_navigation(false);

// Gate: the flag is OFF by default and the import never turns it on.
if (!\local_sentientia_learningpath\learner_paths::enabled()) {
    throw new moodle_exception('learner_paths_off', 'local_sentientia_learningpath');
}

$data = \local_sentientia_learningpath\learner_paths::page_data();

echo $OUTPUT->header();
echo $OUTPUT->render_from_template('local_sentientia_learningpath/mypaths', $data);
echo $OUTPUT->footer();
