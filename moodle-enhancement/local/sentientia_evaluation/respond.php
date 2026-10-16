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
// ADR-031: :manage previews only an evaluation in the manager's own tenant
// (it used to preview any tenant's draft or archived form), and anyone else
// answers only a global evaluation or one of their own tenant.
$is_admin = has_capability('local/sentientia_evaluation:manage', $context)
    && \local_sentientia_evaluation\evaluation_manager::can_manage_evaluation($evaluation);
if (!$is_admin && !\local_sentientia_evaluation\evaluation_manager::can_respond($evaluation, $USER)) {
    throw new moodle_exception('error_outoftenant', 'local_sentientia_platform');
}
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
// set_title() and set_heading() apply format_string() themselves, so they are given the raw name.
$PAGE->set_title($evaluation->name);
$PAGE->set_heading($evaluation->name);
$PAGE->set_pagelayout('standard');
$PAGE->set_secondary_navigation(false);

global $USER;

// Already responded? Show "thank you" instead of the form.
$already_responded = \local_sentientia_evaluation\evaluation_manager::has_user_responded(
    $evaluationid, (int) $USER->id);

$questions = \local_sentientia_evaluation\evaluation_manager::get_questions($evaluationid);

// Build template data with type-specific UI metadata. The rows are built in the manager so they can be tested: the
// position counts 1..n (it printed the question id + 1), text and options are not escaped twice, and a number
// question's bounds are not offered as options to tick.
$question_rows = \local_sentientia_evaluation\evaluation_manager::respond_question_rows($questions);

$kp_labels = [
    1 => 'Reaction',
    2 => 'Learning',
    3 => 'Behaviour',
    4 => 'Results',
];

$data = [
    'evaluationid'      => $evaluation->id,
    // Printed with {{ }}, which escapes once: filtered here, not escaped as well.
    'name'              => \local_sentientia_evaluation\evaluation_manager::display_text($evaluation->name),
    'description'       => \local_sentientia_evaluation\evaluation_manager::display_text($evaluation->description ?? ''),
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
