<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

/**
 * A learner's own evaluation history (ADR-032, mapping doc section 18, "Code fixes" 6).
 *
 * BizLMS showed a learner the evaluations they had completed. After the BizLMS import that history is in
 * Sentientia's tables; this page gives a learner their own rows back. It is behind the default-OFF flag
 * sentientia.evaluation.learner_history: with the flag OFF the page answers as if it did not exist, and
 * nothing links to it. Whether the flag is ON for a customer is the customer owner's call, made after the page
 * has been reviewed (docs/cutover/bizlms-import-decisions.json, framework.reader_flags_airpay_at_cutover).
 *
 * @package    local_sentientia_evaluation
 * @copyright  2026 Airpay Payment Services
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../config.php');
require_login();

$context = context_system::instance();
require_capability('local/sentientia_evaluation:respond', $context);

if (!\local_sentientia_platform\feature_flags::is_enabled('sentientia.evaluation.learner_history')) {
    throw new moodle_exception('my_evaluations_unavailable', 'local_sentientia_evaluation');
}

$PAGE->set_context($context);
$PAGE->set_url(new moodle_url('/local/sentientia_evaluation/my_evaluations.php'));
$PAGE->set_pagelayout('standard');
$PAGE->set_title(get_string('my_evaluations_title', 'local_sentientia_evaluation'));
$PAGE->set_heading(get_string('my_evaluations_title', 'local_sentientia_evaluation'));

$rows = [];
$known = [
    \local_sentientia_evaluation\learner_history::STATUS_RESPONDED,
    \local_sentientia_evaluation\learner_history::STATUS_ASSIGNED,
    \local_sentientia_evaluation\learner_history::STATUS_EXPIRED,
];
foreach (\local_sentientia_evaluation\learner_history::for_user((int) $USER->id) as $entry) {
    // Any status the page has no word for is shown as waiting, never as an error.
    $status = in_array($entry->status, $known, true) ? $entry->status : \local_sentientia_evaluation\learner_history::STATUS_ASSIGNED;
    $rows[] = [
        'name' => format_string($entry->name),
        'status_' . $status => true,
        'status_label' => get_string('my_evaluations_status_' . $status, 'local_sentientia_evaluation'),
        // The day for an evaluation whose respondents are protected, the minute otherwise - the same rule
        // the admin pages apply, so this page cannot be used to read a time they withhold.
        'date' => $entry->time > 0
            ? \local_sentientia_evaluation\evaluation_manager::submitted_label((int) $entry->time, (bool) $entry->anonymous)
            : '',
        'has_date' => $entry->time > 0,
        'anonymous' => (bool) $entry->anonymous,
        'imported' => (bool) $entry->imported,
    ];
}

echo $OUTPUT->header();
echo $OUTPUT->render_from_template('local_sentientia_evaluation/my_evaluations', [
    'rows' => $rows,
    'has_rows' => !empty($rows),
]);
echo $OUTPUT->footer();
