<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

/**
 * Per-response drill-down — Phase 4 B.6.
 *
 * Shows one respondent's full answers for one evaluation, including
 * comparison to all other respondents (avg / distribution per question).
 *
 * @package local_sentientia_evaluation
 */

require_once(__DIR__ . '/../../config.php');
require_login();

global $DB, $OUTPUT, $PAGE;

$id = required_param('id', PARAM_INT);  // response id
$response = $DB->get_record('local_sentientia_evaluation_responses',
    ['id' => $id], '*', MUST_EXIST);
$evaluation = $DB->get_record('local_sentientia_evaluation',
    ['id' => $response->evaluationid], '*', MUST_EXIST);

$ctx = context_system::instance();
$PAGE->set_context($ctx);
$PAGE->set_url(new moodle_url('/local/sentientia_evaluation/response_detail.php', ['id' => $id]));
$PAGE->set_pagelayout('admin');
$PAGE->set_title('Response detail');
$PAGE->set_heading('Response detail — ' . format_string($evaluation->name));
require_capability('local/sentientia_evaluation:view', $ctx);
// ADR-031: one respondent's answers only for an evaluation in the caller's
// tenant (in place before this page is ever re-enabled - :view is not
// declared, so today it is dead for everyone).
\local_sentientia_evaluation\evaluation_manager::require_evaluation_access($evaluation);

// Anonymous check — if evaluation is anonymous, don't reveal userid.
// 2026-09-25: sticky (evaluation_manager::identity_protected()) - anonymous
// now, answered anonymously before, or with an anonymous question, whose
// answer is on this very page. The submission time is then shown to the day.
$is_anonymous_eval = \local_sentientia_evaluation\evaluation_manager::identity_protected($evaluation);
$user = null;
if (!$is_anonymous_eval && $response->userid) {
    $user = $DB->get_record('user', ['id' => $response->userid],
        'firstname, lastname, email, open_employeeid');
}

// The respondent's answers, each beside how everybody else answered. Built in the manager so it can be tested:
// it reads response_data by the bare question id and a choice question's options as a plain list (this page read
// 'q<id>' keys and an options['choices'] list that nothing writes, so every answer showed as missing).
$detail = \local_sentientia_evaluation\evaluation_manager::response_detail_rows($evaluation, $response);
$q_rows = $detail['questions'];

$data = [
    'response_id'   => (int) $response->id,
    'eval_name'     => format_string($evaluation->name),
    'eval_id'       => (int) $evaluation->id,
    'submitted_at'  => $is_anonymous_eval
        ? \local_sentientia_evaluation\evaluation_manager::submitted_label(
            (int) $response->timesubmitted, true)
        : userdate($response->timesubmitted),
    'kirkpatrick'   => (string) ($evaluation->kirkpatrick_level ?? '—'),

    'is_anonymous'  => $is_anonymous_eval,
    'user_name'     => $user ? trim($user->firstname . ' ' . $user->lastname) : '(anonymous)',
    'user_email'    => $user ? (string) $user->email : '',
    'employee_id'   => $user ? (string) ($user->open_employeeid ?? '') : '',

    'questions'     => $q_rows,
    'question_count' => count($q_rows),
    'total_responses' => $detail['total_responses'],

    'back_url'      => (new moodle_url('/local/sentientia_evaluation/responses.php',
        ['id' => $evaluation->id]))->out(false),
    'analysis_url'  => (new moodle_url('/local/sentientia_evaluation/analysis.php',
        ['id' => $evaluation->id]))->out(false),
];

echo $OUTPUT->header();
echo $OUTPUT->render_from_template('local_sentientia_evaluation/response_detail', $data);
echo $OUTPUT->footer();
