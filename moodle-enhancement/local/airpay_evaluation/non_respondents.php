<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
//
// P1 #38 (2026-05-20) — admin page showing assigned-but-not-responded
// learners for an evaluation. Closes audit item #20 from
// parity-audit-2026-05-15/airpay_evaluation.md.
//
// Reads the local_airpay_evaluation_assign table P1 #37 created;
// renders a list of pending learners with email + assignment source
// metadata so the admin can chase them.

require_once(__DIR__ . '/../../config.php');
require_login();

$evaluationid = required_param('id', PARAM_INT);
$tab          = optional_param('tab', 'pending', PARAM_ALPHA);
if (!in_array($tab, ['pending', 'responded'], true)) {
    $tab = 'pending';
}

$context = context_system::instance();
require_capability('local/airpay_evaluation:manage', $context);

$evaluation = \local_airpay_evaluation\evaluation_manager::get($evaluationid);
if (!$evaluation) {
    throw new moodle_exception('invalidevaluation', 'local_airpay_evaluation');
}

$PAGE->set_context($context);
$PAGE->set_url(new moodle_url('/local/airpay_evaluation/non_respondents.php',
    ['id' => $evaluationid, 'tab' => $tab]));
$PAGE->set_pagelayout('admin');
$PAGE->set_title(get_string('non_respondents_title',
    'local_airpay_evaluation', format_string($evaluation->name)));
$PAGE->set_heading(get_string('non_respondents_heading',
    'local_airpay_evaluation', format_string($evaluation->name)));

$status = ($tab === 'responded') ? 'responded' : 'assigned';
$rows = \local_airpay_evaluation\evaluation_manager::list_assignments(
    $evaluationid, $status);

// Counts for tab badges.
$count_pending = count(\local_airpay_evaluation\evaluation_manager::list_assignments(
    $evaluationid, 'assigned'));
$count_responded = count(\local_airpay_evaluation\evaluation_manager::list_assignments(
    $evaluationid, 'responded'));

// Build the table rows for the template.
$trigger_labels = [
    'manual'             => get_string('non_respondents_trigger_manual',
        'local_airpay_evaluation'),
    'course_completion'  => get_string('non_respondents_trigger_course',
        'local_airpay_evaluation'),
    'program_completion' => get_string('non_respondents_trigger_program',
        'local_airpay_evaluation'),
    'classroom_end'      => get_string('non_respondents_trigger_classroom',
        'local_airpay_evaluation'),
];

$table_rows = [];
foreach ($rows as $r) {
    $table_rows[] = [
        'userid'       => (int) $r->userid,
        'fullname'     => trim($r->firstname . ' ' . $r->lastname),
        'email'        => (string) $r->email,
        'trigger'      => $trigger_labels[$r->trigger_event] ?? $r->trigger_event,
        'source_id'    => (int) ($r->source_id ?? 0),
        'has_source'   => (int) ($r->source_id ?? 0) > 0,
        'due_at'       => $r->due_at ? userdate($r->due_at, '%d %b %Y') : '',
        'has_due'      => !empty($r->due_at),
        'is_overdue'   => !empty($r->due_at) && time() > (int) $r->due_at,
        'responded_at' => $r->responded_at
            ? userdate($r->responded_at, '%d %b %Y %H:%M') : '',
        'assigned_at'  => userdate($r->timecreated, '%d %b %Y'),
        'profile_url'  => (new moodle_url('/user/profile.php',
            ['id' => (int) $r->userid]))->out(false),
    ];
}

$data = [
    'evaluationid'    => (int) $evaluation->id,
    'name'            => format_string($evaluation->name),
    'count_pending'   => $count_pending,
    'count_responded' => $count_responded,
    'tab_pending_active'   => $tab === 'pending',
    'tab_responded_active' => $tab === 'responded',
    'tab_pending_url'   => (new moodle_url('/local/airpay_evaluation/non_respondents.php',
        ['id' => $evaluationid, 'tab' => 'pending']))->out(false),
    'tab_responded_url' => (new moodle_url('/local/airpay_evaluation/non_respondents.php',
        ['id' => $evaluationid, 'tab' => 'responded']))->out(false),
    'back_url' => (new moodle_url('/local/airpay_evaluation/index.php'))->out(false),
    'rows'     => $table_rows,
    'has_rows' => !empty($table_rows),
    'is_pending_tab'   => $tab === 'pending',
    'is_responded_tab' => $tab === 'responded',
];

// P1 #40 — wire the bulk-assign modal trigger.
$PAGE->requires->js_call_amd(
    'local_airpay_evaluation/non_respondents_actions', 'init');

echo $OUTPUT->header();
echo $OUTPUT->render_from_template(
    'local_airpay_evaluation/non_respondents', $data);
echo $OUTPUT->footer();
