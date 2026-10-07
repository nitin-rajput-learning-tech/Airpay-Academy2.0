<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

/**
 * Individual responses list — Phase 4 B.6.
 *
 * Companion to the aggregate responses.php view. Lists each submission
 * separately with a link to the drill-down detail page.
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

$evaluationid = required_param('id', PARAM_INT);
$evaluation = $DB->get_record('local_sentientia_evaluation',
    ['id' => $evaluationid], '*', MUST_EXIST);

$ctx = context_system::instance();
$PAGE->set_context($ctx);
$PAGE->set_url(new moodle_url('/local/sentientia_evaluation/response_list.php',
    ['id' => $evaluationid]));
$PAGE->set_pagelayout('admin');
// set_title() and set_heading() run format_string() on what they are given, so they take the raw name (it leaves an
// "&" that already starts an entity alone, so formatting it first made no difference to their output).
$PAGE->set_title('Responses — ' . $evaluation->name);
$PAGE->set_heading('Individual responses — ' . $evaluation->name);
// ADR-031: names, emails and employee ids only for an evaluation in the caller's tenant.
\local_sentientia_evaluation\evaluation_manager::require_evaluation_access($evaluation);

// 2026-09-25: sticky - anonymous now, answered anonymously before, or with an
// anonymous question (evaluation_manager::identity_protected()). Unticking
// the evaluation's flag used to bring names, emails and employee ids back
// here, each beside its minute-exact submission time; for these evaluations
// the time is shown to the day only.
$is_anonymous = \local_sentientia_evaluation\evaluation_manager::identity_protected($evaluation);

// A supervisor evaluation is answered by one person about another; the import keeps that person in
// subject_userid. The column appears only when some response has one, and never on a protected evaluation.
$show_subject = \local_sentientia_evaluation\evaluation_manager::shows_subject($evaluation, $is_anonymous);

// The submitted responses, newest first (a pending invitation is not one), with the person a supervisor evaluation
// is about named the way the CSV names them. Built in the manager so it can be tested.
$shape = \local_sentientia_evaluation\evaluation_manager::response_list_rows($evaluation, $is_anonymous, $show_subject);

echo $OUTPUT->header();
echo $OUTPUT->render_from_template('local_sentientia_evaluation/response_list', [
    'eval_name'     => \local_sentientia_evaluation\evaluation_manager::display_text($evaluation->name),
    'is_anonymous'  => $is_anonymous,
    'show_subject'  => $show_subject,
    'total'         => count($shape),
    'rows'          => $shape,
    'has_rows'      => !empty($shape),
    'aggregate_url' => (new moodle_url('/local/sentientia_evaluation/responses.php',
        ['id' => $evaluationid]))->out(false),
    'analysis_url'  => (new moodle_url('/local/sentientia_evaluation/analysis.php',
        ['id' => $evaluationid]))->out(false),
]);
echo $OUTPUT->footer();
