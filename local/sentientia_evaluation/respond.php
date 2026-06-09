<?php
// Learner-facing evaluation response page.
//
// @package    local_sentientia_evaluation
// @copyright  2026 Airpay Payment Services
// @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

require_once(__DIR__ . '/../../config.php');
require_login();

$context = context_system::instance();
require_capability('local/sentientia_evaluation:respond', $context);

$evaluationid = required_param('id', PARAM_INT);
$courseid     = optional_param('courseid', 0, PARAM_INT);
$programid    = optional_param('programid', 0, PARAM_INT);
$classroomid  = optional_param('classroomid', 0, PARAM_INT);

$evaluation = \local_sentientia_evaluation\evaluation_manager::get($evaluationid);
if (!$evaluation) {
    throw new moodle_exception('invalidevaluation', 'local_sentientia_evaluation');
}

// Block access to non-active evaluations (unless admin).
$is_admin = is_siteadmin() || has_capability('local/sentientia_evaluation:manage', $context);
if ((int) $evaluation->status !== \local_sentientia_evaluation\evaluation_manager::STATUS_ACTIVE && !$is_admin) {
    throw new moodle_exception('evaluationnotactive', 'local_sentientia_evaluation');
}

// P1 #17 — outside the configured availability window? Show a friendly
// banner instead of throwing a fatal. Admins still get through so they
// can preview pre/post window.
$window_status = null;  // null = open, otherwise = ['kind' => 'notyetopen'|'closed', 'when' => 'human-readable']
if (!$is_admin) {
    $now   = time();
    $open  = (int) ($evaluation->timeopen  ?? 0);
    $close = (int) ($evaluation->timeclose ?? 0);
    if ($open > 0 && $now < $open) {
        $window_status = ['kind' => 'notyetopen', 'when' => userdate($open)];
    } else if ($close > 0 && $now >= $close) {
        $window_status = ['kind' => 'closed', 'when' => userdate($close)];
    }
}

$PAGE->set_context($context);
$PAGE->set_url(new moodle_url('/local/sentientia_evaluation/respond.php', ['id' => $evaluationid]));
$PAGE->set_title(format_string($evaluation->name));
$PAGE->set_heading(format_string($evaluation->name));
$PAGE->set_pagelayout('standard');
$PAGE->set_secondary_navigation(false);

global $USER;

// Already responded? Show "thank you" instead of the form.
$already_responded = \local_sentientia_evaluation\evaluation_manager::has_user_responded(
    $evaluationid, (int) $USER->id);

$questions = \local_sentientia_evaluation\evaluation_manager::get_questions($evaluationid);

// Build template data with type-specific UI metadata.
$question_rows = [];
foreach ($questions as $i => $q) {
    $opts = \local_sentientia_evaluation\evaluation_manager::decode_options($q->options ?? null);

    $option_rows = [];
    foreach ($opts as $idx => $opt) {
        $option_rows[] = [
            'value'  => $opt,
            'label'  => format_string($opt),
            'inputid' => 'q-' . $q->id . '-opt-' . $idx,
        ];
    }

    // Rating scale: 1-5 with descriptive labels.
    $rating_scale = [
        ['value' => 1, 'label' => 'Strongly Disagree'],
        ['value' => 2, 'label' => 'Disagree'],
        ['value' => 3, 'label' => 'Neutral'],
        ['value' => 4, 'label' => 'Agree'],
        ['value' => 5, 'label' => 'Strongly Agree'],
    ];

    // NPS scale: 0-10
    $nps_scale = [];
    for ($n = 0; $n <= 10; $n++) {
        $nps_scale[] = ['value' => $n, 'label' => (string) $n];
    }

    // P1 #18 — numeric bounds (decoded only when type=numeric).
    $num_bounds = ($q->questiontype === 'numeric')
        ? \local_sentientia_evaluation\evaluation_manager::decode_numeric_bounds($q->options ?? null)
        : ['min' => null, 'max' => null];

    $question_rows[] = [
        'id'              => $q->id,
        'position'        => $i + 1,
        'questiontext'    => format_string($q->questiontext),
        'questiontype'    => $q->questiontype,
        'is_rating'       => ($q->questiontype === 'rating'),
        'is_nps'          => ($q->questiontype === 'nps'),
        'is_yesno'        => ($q->questiontype === 'yesno'),
        'is_multichoice'  => ($q->questiontype === 'multichoice'),
        // P1 #18 — both new types surface as flags + share option rows
        // for multichoice_multi.
        'is_multichoice_multi' => ($q->questiontype === 'multichoice_multi'),
        'is_numeric'      => ($q->questiontype === 'numeric'),
        'is_text'         => ($q->questiontype === 'text'),
        'required'        => (bool) $q->required,
        'options'         => $option_rows,
        'rating_scale'    => $rating_scale,
        'nps_scale'       => $nps_scale,
        // Mustache helpers — leave empty string when unset so the
        // template's `<input min/max>` attrs render as the constraint
        // only when present.
        'numeric_min'     => $num_bounds['min'] !== null ? (string) $num_bounds['min'] : '',
        'numeric_max'     => $num_bounds['max'] !== null ? (string) $num_bounds['max'] : '',
        'numeric_hint'    => $num_bounds['min'] !== null || $num_bounds['max'] !== null
            ? sprintf('Range: %s to %s',
                $num_bounds['min'] !== null ? $num_bounds['min'] : '−∞',
                $num_bounds['max'] !== null ? $num_bounds['max'] : '+∞')
            : '',
        // P1 #31 (2026-05-20) — dependency wire-up for client show/hide.
        // We emit raw values for the JS to consume; the server-side
        // visibility check happens again in submit_response so a
        // tampered client payload still can't bypass required-when-hidden.
        'has_dependency'   => (int) ($q->depends_on_qid ?? 0) > 0,
        'depends_on_qid'   => (int) ($q->depends_on_qid ?? 0),
        // depends_on_value is intentionally NOT format_string'd — JS
        // compares string-equality against the parent's raw answer,
        // which is the user's literal input. The template emits this
        // via `s()` so the attribute is HTML-escaped safely.
        'depends_on_value' => (string) ($q->depends_on_value ?? ''),
    ];
}

$kp_labels = [
    1 => 'Reaction',
    2 => 'Learning',
    3 => 'Behaviour',
    4 => 'Results',
];

$data = [
    'evaluationid'      => $evaluation->id,
    'name'              => format_string($evaluation->name),
    'description'       => format_string($evaluation->description ?? ''),
    'kirkpatrick_label' => $kp_labels[(int) $evaluation->kirkpatrick_level] ?? '',
    'is_anonymous'      => (bool) $evaluation->anonymous,
    'has_questions'     => !empty($question_rows),
    'questions'         => $question_rows,
    'already_responded' => $already_responded,
    'context_courseid'    => $courseid,
    'context_programid'   => $programid,
    'context_classroomid' => $classroomid,
    'backurl'           => (new moodle_url('/my/'))->out(false),
    // P1 #17 — window status (null when open). The template renders a
    // banner with the appropriate copy + hides the form.
    'window_locked'     => $window_status !== null,
    'window_notyetopen' => $window_status && $window_status['kind'] === 'notyetopen',
    'window_closed'     => $window_status && $window_status['kind'] === 'closed',
    'window_when'       => $window_status['when'] ?? '',
    // P1 #17 — pulse-mode hint shown above the form so a re-submitting
    // user understands why no "already responded" gate is firing.
    'is_pulse'          => (int) ($evaluation->multiple_submit ?? 0) === 1,
];

echo $OUTPUT->header();
echo $OUTPUT->render_from_template('local_sentientia_evaluation/respond', $data);
echo $OUTPUT->footer();
