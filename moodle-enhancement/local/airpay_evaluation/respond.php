<?php
// Learner-facing evaluation response page.
//
// @package    local_airpay_evaluation
// @copyright  2026 Airpay Payment Services
// @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

require_once(__DIR__ . '/../../config.php');
require_login();

$context = context_system::instance();
require_capability('local/airpay_evaluation:respond', $context);

$evaluationid = required_param('id', PARAM_INT);
$courseid     = optional_param('courseid', 0, PARAM_INT);
$programid    = optional_param('programid', 0, PARAM_INT);
$classroomid  = optional_param('classroomid', 0, PARAM_INT);

$evaluation = \local_airpay_evaluation\evaluation_manager::get($evaluationid);
if (!$evaluation) {
    throw new moodle_exception('invalidevaluation', 'local_airpay_evaluation');
}

// Block access to non-active evaluations (unless admin).
$is_admin = is_siteadmin() || has_capability('local/airpay_evaluation:manage', $context);
if ((int) $evaluation->status !== \local_airpay_evaluation\evaluation_manager::STATUS_ACTIVE && !$is_admin) {
    throw new moodle_exception('evaluationnotactive', 'local_airpay_evaluation');
}

$PAGE->set_context($context);
$PAGE->set_url(new moodle_url('/local/airpay_evaluation/respond.php', ['id' => $evaluationid]));
$PAGE->set_title(format_string($evaluation->name));
$PAGE->set_heading(format_string($evaluation->name));
$PAGE->set_pagelayout('standard');
$PAGE->set_secondary_navigation(false);

global $USER;

// Already responded? Show "thank you" instead of the form.
$already_responded = \local_airpay_evaluation\evaluation_manager::has_user_responded(
    $evaluationid, (int) $USER->id);

$questions = \local_airpay_evaluation\evaluation_manager::get_questions($evaluationid);

// Build template data with type-specific UI metadata.
$question_rows = [];
foreach ($questions as $i => $q) {
    $opts = \local_airpay_evaluation\evaluation_manager::decode_options($q->options ?? null);

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

    $question_rows[] = [
        'id'              => $q->id,
        'position'        => $i + 1,
        'questiontext'    => format_string($q->questiontext),
        'questiontype'    => $q->questiontype,
        'is_rating'       => ($q->questiontype === 'rating'),
        'is_nps'          => ($q->questiontype === 'nps'),
        'is_yesno'        => ($q->questiontype === 'yesno'),
        'is_multichoice'  => ($q->questiontype === 'multichoice'),
        'is_text'         => ($q->questiontype === 'text'),
        'required'        => (bool) $q->required,
        'options'         => $option_rows,
        'rating_scale'    => $rating_scale,
        'nps_scale'       => $nps_scale,
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
];

echo $OUTPUT->header();
echo $OUTPUT->render_from_template('local_airpay_evaluation/respond', $data);
echo $OUTPUT->footer();
