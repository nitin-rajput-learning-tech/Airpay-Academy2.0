<?php
// Admin response viewer — aggregate stats per question with filter form (G-05).
//
// @package    local_sentientia_evaluation
// @copyright  2026 Airpay Payment Services
// @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

require_once(__DIR__ . '/../../config.php');
require_login();

$context = context_system::instance();
require_capability('local/sentientia_evaluation:manage', $context);

$evaluationid = required_param('id', PARAM_INT);

// Filter params (G-05).
$date_from   = optional_param('date_from',   '', PARAM_RAW);
$date_to     = optional_param('date_to',     '', PARAM_RAW);
$courseid    = optional_param('courseid',    0,  PARAM_INT);
$programid   = optional_param('programid',   0,  PARAM_INT);
$classroomid = optional_param('classroomid', 0,  PARAM_INT);

$evaluation = \local_sentientia_evaluation\evaluation_manager::get($evaluationid);
if (!$evaluation) {
    throw new moodle_exception('invalidevaluation', 'local_sentientia_evaluation');
}
// ADR-031: per-question results and free-text answers only for an
// evaluation in the caller's tenant.
\local_sentientia_evaluation\evaluation_manager::require_evaluation_access($evaluation);

$PAGE->set_context($context);
$PAGE->set_url(new moodle_url('/local/sentientia_evaluation/responses.php', ['id' => $evaluationid]));
$PAGE->set_title('Responses — ' . format_string($evaluation->name));
$PAGE->set_heading('Responses — ' . format_string($evaluation->name));
$PAGE->set_pagelayout('standard');
$PAGE->set_secondary_navigation(false);
$PAGE->navbar->add(get_string('pluginname', 'local_sentientia_evaluation'),
    new moodle_url('/local/sentientia_evaluation/index.php'));
$PAGE->navbar->add('Responses');

$questions = \local_sentientia_evaluation\evaluation_manager::get_questions($evaluationid);

// Build the filter array (date strings → unix ts).
// 2026-09-25: snapped to whole days - a time in date_from used to filter to
// the minute, narrowing an anonymous answer down to its submission time.
$days = \local_sentientia_evaluation\evaluation_manager::response_filter_days($date_from, $date_to);
$filters = ['evaluationid' => $evaluationid] + $days;
$has_filter = !empty($days);
if ($courseid    > 0) { $filters['courseid']    = $courseid;    $has_filter = true; }
if ($programid   > 0) { $filters['programid']   = $programid;   $has_filter = true; }
if ($classroomid > 0) { $filters['classroomid'] = $classroomid; $has_filter = true; }

// When no filter is set, use the cheaper unfiltered stats (it's a simpler query).
if ($has_filter) {
    $filtered = \local_sentientia_evaluation\evaluation_manager::get_response_stats_filtered(
        $evaluationid, $filters);
    $stats           = $filtered['questions'];
    $total_responses = $filtered['response_count'];
} else {
    $stats           = \local_sentientia_evaluation\evaluation_manager::get_response_stats($evaluationid);
    $total_responses = \local_sentientia_evaluation\evaluation_manager::count_responses($evaluationid);
}

// Build template data per question with type-aware presentation (rating, NPS, yes/no, multiple choice - one or
// several answers -, number and free text). The rows are built in the manager so they can be tested.
$question_rows = \local_sentientia_evaluation\evaluation_manager::response_question_rows($questions, $stats);

// Build the export URL preserving filters.
$export_params = ['id' => $evaluationid];
if (!empty($date_from))   { $export_params['date_from']   = $date_from; }
if (!empty($date_to))     { $export_params['date_to']     = $date_to; }
if ($courseid    > 0)     { $export_params['courseid']    = $courseid; }
if ($programid   > 0)     { $export_params['programid']   = $programid; }
if ($classroomid > 0)     { $export_params['classroomid'] = $classroomid; }
$export_url = (new moodle_url('/local/sentientia_evaluation/exportcsv.php', $export_params))->out(false);

// Reset URL clears all filters.
$reset_url = (new moodle_url('/local/sentientia_evaluation/responses.php',
    ['id' => $evaluationid]))->out(false);

$data = [
    'evaluationid'    => $evaluation->id,
    'name'            => format_string($evaluation->name),
    'description'     => format_string($evaluation->description ?? ''),
    'is_anonymous'    => (bool) $evaluation->anonymous,
    'kirkpatrick_label' => \local_sentientia_evaluation\evaluation_manager::KIRKPATRICK_LEVELS[(int) $evaluation->kirkpatrick_level] ?? '',
    'total_responses' => $total_responses,
    'has_responses'   => ($total_responses > 0),
    'questions'       => $question_rows,
    'has_questions'   => !empty($question_rows),
    'backurl'         => (new moodle_url('/local/sentientia_evaluation/index.php'))->out(false),
    'export_url'      => $export_url,
    'reset_url'       => $reset_url,

    // Filter form context.
    'filter_action_url' => (new moodle_url('/local/sentientia_evaluation/responses.php',
        ['id' => $evaluationid]))->out(false),
    'filter_date_from' => s($date_from),
    'filter_date_to'   => s($date_to),
    'filter_courseid'  => $courseid > 0 ? $courseid : '',
    'filter_programid' => $programid > 0 ? $programid : '',
    'filter_classroomid' => $classroomid > 0 ? $classroomid : '',
    'has_filter'       => $has_filter,
];

echo $OUTPUT->header();
echo $OUTPUT->render_from_template('local_sentientia_evaluation/responses', $data);
echo $OUTPUT->footer();
