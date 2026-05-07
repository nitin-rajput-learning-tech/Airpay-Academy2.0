<?php
// Admin response viewer — aggregate stats per question with filter form (G-05).
//
// @package    local_airpay_evaluation
// @copyright  2026 Airpay Payment Services
// @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

require_once(__DIR__ . '/../../config.php');
require_login();

$context = context_system::instance();
require_capability('local/airpay_evaluation:manage', $context);

$evaluationid = required_param('id', PARAM_INT);

// Filter params (G-05).
$date_from   = optional_param('date_from',   '', PARAM_RAW);
$date_to     = optional_param('date_to',     '', PARAM_RAW);
$courseid    = optional_param('courseid',    0,  PARAM_INT);
$programid   = optional_param('programid',   0,  PARAM_INT);
$classroomid = optional_param('classroomid', 0,  PARAM_INT);

$evaluation = \local_airpay_evaluation\evaluation_manager::get($evaluationid);
if (!$evaluation) {
    throw new moodle_exception('invalidevaluation', 'local_airpay_evaluation');
}

$PAGE->set_context($context);
$PAGE->set_url(new moodle_url('/local/airpay_evaluation/responses.php', ['id' => $evaluationid]));
$PAGE->set_title('Responses — ' . format_string($evaluation->name));
$PAGE->set_heading('Responses — ' . format_string($evaluation->name));
$PAGE->set_pagelayout('standard');
$PAGE->set_secondary_navigation(false);
$PAGE->navbar->add(get_string('pluginname', 'local_airpay_evaluation'),
    new moodle_url('/local/airpay_evaluation/index.php'));
$PAGE->navbar->add('Responses');

$questions = \local_airpay_evaluation\evaluation_manager::get_questions($evaluationid);

// Build the filter array (date strings → unix ts).
$filters = ['evaluationid' => $evaluationid];
$has_filter = false;
if (!empty($date_from)) {
    $ts = strtotime($date_from);
    if ($ts !== false) { $filters['date_from'] = $ts; $has_filter = true; }
}
if (!empty($date_to)) {
    $ts = strtotime($date_to . ' 23:59:59');
    if ($ts !== false) { $filters['date_to'] = $ts; $has_filter = true; }
}
if ($courseid    > 0) { $filters['courseid']    = $courseid;    $has_filter = true; }
if ($programid   > 0) { $filters['programid']   = $programid;   $has_filter = true; }
if ($classroomid > 0) { $filters['classroomid'] = $classroomid; $has_filter = true; }

// When no filter is set, use the cheaper unfiltered stats (it's a simpler query).
if ($has_filter) {
    $filtered = \local_airpay_evaluation\evaluation_manager::get_response_stats_filtered(
        $evaluationid, $filters);
    $stats           = $filtered['questions'];
    $total_responses = $filtered['response_count'];
} else {
    $stats           = \local_airpay_evaluation\evaluation_manager::get_response_stats($evaluationid);
    $total_responses = \local_airpay_evaluation\evaluation_manager::count_responses($evaluationid);
}

// Build template data per question with type-aware presentation.
$question_rows = [];
foreach ($questions as $i => $q) {
    $bucket = $stats[$q->id] ?? ['type' => $q->questiontype, 'count' => 0];

    $row = [
        'id'           => $q->id,
        'position'     => $i + 1,
        'questiontext' => format_string($q->questiontext),
        'questiontype' => $q->questiontype,
        'required'     => (bool) $q->required,
        'response_count' => $bucket['count'],
        'is_rating'    => ($q->questiontype === 'rating'),
        'is_nps'       => ($q->questiontype === 'nps'),
        'is_yesno'     => ($q->questiontype === 'yesno'),
        'is_multichoice' => ($q->questiontype === 'multichoice'),
        'is_text'      => ($q->questiontype === 'text'),
    ];

    if ($q->questiontype === 'rating' && $bucket['count'] > 0) {
        $row['avg'] = $bucket['avg'];
        $row['avg_pct'] = round(($bucket['avg'] / 5) * 100);
        $dist_rows = [];
        foreach ($bucket['distribution'] as $val => $count) {
            $pct = $bucket['count'] > 0 ? round(($count / $bucket['count']) * 100) : 0;
            $dist_rows[] = [
                'level' => $val,
                'count' => $count,
                'pct'   => $pct,
                'pct_label' => $count . ' (' . $pct . '%)',
            ];
        }
        $row['distribution'] = $dist_rows;
    }

    if ($q->questiontype === 'nps' && $bucket['count'] > 0) {
        $row['nps_score']  = $bucket['nps_score'];
        $row['avg']        = $bucket['avg'];
        $row['promoters']  = $bucket['promoters'];
        $row['passives']   = $bucket['passives'];
        $row['detractors'] = $bucket['detractors'];
        $total = max(1, $bucket['count']);
        $row['promoter_pct']  = round(($bucket['promoters']  / $total) * 100);
        $row['passive_pct']   = round(($bucket['passives']   / $total) * 100);
        $row['detractor_pct'] = round(($bucket['detractors'] / $total) * 100);
        $row['nps_class'] = $bucket['nps_score'] >= 50 ? 'text-success'
                          : ($bucket['nps_score'] >= 0 ? 'text-warning' : 'text-danger');
    }

    if ($q->questiontype === 'yesno' && $bucket['count'] > 0) {
        $row['yes']     = $bucket['yes'];
        $row['no']      = $bucket['no'];
        $row['yes_pct'] = $bucket['yes_pct'];
        $row['no_pct']  = 100 - $bucket['yes_pct'];
    }

    if ($q->questiontype === 'multichoice' && $bucket['count'] > 0) {
        $dist_rows = [];
        foreach ($bucket['distribution'] as $opt => $count) {
            $pct = $bucket['count'] > 0 ? round(($count / $bucket['count']) * 100) : 0;
            $dist_rows[] = [
                'option' => format_string($opt),
                'count'  => $count,
                'pct'    => $pct,
            ];
        }
        $row['distribution'] = $dist_rows;
    }

    if ($q->questiontype === 'text' && !empty($bucket['samples'])) {
        $row['samples'] = array_map(function ($s) {
            return ['text' => format_string($s)];
        }, $bucket['samples']);
        $row['has_samples'] = true;
    }

    $question_rows[] = $row;
}

// Build the export URL preserving filters.
$export_params = ['id' => $evaluationid];
if (!empty($date_from))   { $export_params['date_from']   = $date_from; }
if (!empty($date_to))     { $export_params['date_to']     = $date_to; }
if ($courseid    > 0)     { $export_params['courseid']    = $courseid; }
if ($programid   > 0)     { $export_params['programid']   = $programid; }
if ($classroomid > 0)     { $export_params['classroomid'] = $classroomid; }
$export_url = (new moodle_url('/local/airpay_evaluation/exportcsv.php', $export_params))->out(false);

// Reset URL clears all filters.
$reset_url = (new moodle_url('/local/airpay_evaluation/responses.php',
    ['id' => $evaluationid]))->out(false);

$data = [
    'evaluationid'    => $evaluation->id,
    'name'            => format_string($evaluation->name),
    'description'     => format_string($evaluation->description ?? ''),
    'is_anonymous'    => (bool) $evaluation->anonymous,
    'kirkpatrick_label' => \local_airpay_evaluation\evaluation_manager::KIRKPATRICK_LEVELS[(int) $evaluation->kirkpatrick_level] ?? '',
    'total_responses' => $total_responses,
    'has_responses'   => ($total_responses > 0),
    'questions'       => $question_rows,
    'has_questions'   => !empty($question_rows),
    'backurl'         => (new moodle_url('/local/airpay_evaluation/index.php'))->out(false),
    'export_url'      => $export_url,
    'reset_url'       => $reset_url,

    // Filter form context.
    'filter_action_url' => (new moodle_url('/local/airpay_evaluation/responses.php',
        ['id' => $evaluationid]))->out(false),
    'filter_date_from' => s($date_from),
    'filter_date_to'   => s($date_to),
    'filter_courseid'  => $courseid > 0 ? $courseid : '',
    'filter_programid' => $programid > 0 ? $programid : '',
    'filter_classroomid' => $classroomid > 0 ? $classroomid : '',
    'has_filter'       => $has_filter,
];

echo $OUTPUT->header();
echo $OUTPUT->render_from_template('local_airpay_evaluation/responses', $data);
echo $OUTPUT->footer();
