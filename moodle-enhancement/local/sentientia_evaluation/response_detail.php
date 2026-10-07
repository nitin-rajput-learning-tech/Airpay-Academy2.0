<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

/**
 * Per-response drill-down — Phase 4 B.6.
 *
 * Shows one respondent's full answers for one evaluation, including
 * comparison to all other respondents (avg / distribution per question).
 *
 * Behind the default-OFF flag sentientia.evaluation.response_drilldown and local/sentientia_evaluation:manage (EV-06):
 * the page used to ask for ":view", which no plugin declares, so nobody could open it.
 *
 * @package local_sentientia_evaluation
 */

require_once(__DIR__ . '/../../config.php');
require_login();

global $DB, $OUTPUT, $PAGE;

// EV-06: the capability and the flag come first, so that nothing is read for a caller who may not be here. OFF
// answers "not available", as if the page did not exist.
\local_sentientia_evaluation\evaluation_manager::require_response_drilldown();

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
// set_heading() runs format_string() on what it is given, so it takes the raw name (it leaves an "&" that already
// starts an entity alone, so formatting it first made no difference to its output).
$PAGE->set_heading('Response detail — ' . $evaluation->name);
// ADR-031: one respondent's answers only for an evaluation in the caller's tenant.
\local_sentientia_evaluation\evaluation_manager::require_evaluation_access($evaluation);
// An invited user's pending shell row (timesubmitted 0) is an invitation, not a response: refuse it, so it is never
// shown as their answer.
\local_sentientia_evaluation\evaluation_manager::require_submitted_response($response);

// Anonymous check — if evaluation is anonymous, don't reveal userid.
// 2026-09-25: sticky (evaluation_manager::identity_protected()) - anonymous
// now, answered anonymously before, or with an anonymous question, whose
// answer is on this very page. The submission time is then shown to the day.
$is_anonymous_eval = \local_sentientia_evaluation\evaluation_manager::identity_protected($evaluation);

// Who answered, named through fullname() like the response list and the CSV (the site's name format applies to all
// three); nobody on a protected evaluation. Built in the manager so it can be tested.
$respondent = \local_sentientia_evaluation\evaluation_manager::response_detail_respondent($response, $is_anonymous_eval);

// The respondent's answers, each beside how everybody else answered. Built in the manager so it can be tested:
// it reads response_data by the bare question id and a choice question's options as a plain list (this page read
// 'q<id>' keys and an options['choices'] list that nothing writes, so every answer showed as missing).
$detail = \local_sentientia_evaluation\evaluation_manager::response_detail_rows($evaluation, $response);
$q_rows = $detail['questions'];

$data = [
    'response_id'   => (int) $response->id,
    'eval_name'     => \local_sentientia_evaluation\evaluation_manager::display_text($evaluation->name),
    'eval_id'       => (int) $evaluation->id,
    'submitted_at'  => $is_anonymous_eval
        ? \local_sentientia_evaluation\evaluation_manager::submitted_label(
            (int) $response->timesubmitted, true)
        : userdate($response->timesubmitted),
    'kirkpatrick'   => (string) ($evaluation->kirkpatrick_level ?? '—'),

    'is_anonymous'  => $is_anonymous_eval,
    'user_name'     => $respondent['user_name'],
    'user_email'    => $respondent['user_email'],
    'employee_id'   => $respondent['employee_id'],

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
