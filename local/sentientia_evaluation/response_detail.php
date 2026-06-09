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

// Anonymous check — if evaluation is anonymous, don't reveal userid.
$is_anonymous_eval = (int) ($evaluation->anonymous ?? 0) === 1;
$user = null;
if (!$is_anonymous_eval && $response->userid) {
    $user = $DB->get_record('user', ['id' => $response->userid],
        'firstname, lastname, email, open_employeeid');
}

// Parse the response data.
$answers = json_decode($response->response_data ?: '{}', true) ?: [];

// Load all questions in order.
$questions = $DB->get_records('local_sentientia_evaluation_questions',
    ['evaluationid' => $evaluation->id], 'sortorder ASC');

// Aggregate stats per question for comparison.
$all_responses = $DB->get_records('local_sentientia_evaluation_responses',
    ['evaluationid' => $evaluation->id]);

$qstats = [];  // qid => ['count' => N, 'avg' => x, 'distribution' => {...}]
foreach ($questions as $q) {
    $vals = [];
    foreach ($all_responses as $r) {
        $data = json_decode($r->response_data ?: '{}', true) ?: [];
        $key = 'q' . $q->id;
        if (isset($data[$key]) && $data[$key] !== '') {
            $vals[] = $data[$key];
        }
    }
    $qstats[$q->id] = [
        'count'  => count($vals),
        'values' => $vals,
    ];
    if ($q->questiontype === 'rating' || $q->questiontype === 'numeric') {
        $nums = array_filter($vals, 'is_numeric');
        $qstats[$q->id]['avg'] = $nums ? round(array_sum($nums) / count($nums), 2) : 0;
    }
}

// Build display data.
$q_rows = [];
foreach ($questions as $q) {
    $key = 'q' . $q->id;
    $my_answer = $answers[$key] ?? '(no answer)';
    $opts = json_decode($q->options ?: '{}', true) ?: [];
    $stats = $qstats[$q->id];

    $row = [
        'qid'           => (int) $q->id,
        'type'          => $q->questiontype,
        'text'          => format_string($q->questiontext),
        'required'      => (bool) $q->required,
        'anonymous'     => (bool) $q->anonymous,
        'my_answer'     => is_array($my_answer) ? implode(', ', $my_answer) : (string) $my_answer,
        'has_my_answer' => $my_answer !== '(no answer)',
        'response_count' => $stats['count'],
    ];

    // Type-specific display.
    if ($q->questiontype === 'rating') {
        $row['max_rating'] = (int) ($opts['max'] ?? 5);
        $row['avg'] = $stats['avg'] ?? 0;
        // Build distribution histogram 1..max.
        $hist = [];
        for ($i = 1; $i <= $row['max_rating']; $i++) {
            $count = count(array_filter($stats['values'], fn($v) => (int) $v === $i));
            $hist[] = [
                'level' => $i,
                'count' => $count,
                'pct'   => $stats['count'] > 0
                    ? round(100 * $count / $stats['count'], 1) : 0,
                'is_my_choice' => (int) $row['my_answer'] === $i,
            ];
        }
        $row['histogram'] = $hist;
    } else if ($q->questiontype === 'choice' || $q->questiontype === 'multichoice') {
        $choices = $opts['choices'] ?? [];
        $hist = [];
        foreach ($choices as $choice) {
            $count = count(array_filter($stats['values'],
                fn($v) => is_array($v) ? in_array($choice, $v) : (string) $v === (string) $choice));
            $hist[] = [
                'label' => $choice,
                'count' => $count,
                'pct'   => $stats['count'] > 0
                    ? round(100 * $count / $stats['count'], 1) : 0,
            ];
        }
        $row['histogram'] = $hist;
    }

    $q_rows[] = $row;
}

$data = [
    'response_id'   => (int) $response->id,
    'eval_name'     => format_string($evaluation->name),
    'eval_id'       => (int) $evaluation->id,
    'submitted_at'  => userdate($response->timesubmitted),
    'kirkpatrick'   => (string) ($evaluation->kirkpatrick_level ?? '—'),

    'is_anonymous'  => $is_anonymous_eval,
    'user_name'     => $user ? trim($user->firstname . ' ' . $user->lastname) : '(anonymous)',
    'user_email'    => $user ? (string) $user->email : '',
    'employee_id'   => $user ? (string) ($user->open_employeeid ?? '') : '',

    'questions'     => $q_rows,
    'question_count' => count($questions),
    'total_responses' => count($all_responses),

    'back_url'      => (new moodle_url('/local/sentientia_evaluation/responses.php',
        ['id' => $evaluation->id]))->out(false),
    'analysis_url'  => (new moodle_url('/local/sentientia_evaluation/analysis.php',
        ['id' => $evaluation->id]))->out(false),
];

echo $OUTPUT->header();
echo $OUTPUT->render_from_template('local_sentientia_evaluation/response_detail', $data);
echo $OUTPUT->footer();
