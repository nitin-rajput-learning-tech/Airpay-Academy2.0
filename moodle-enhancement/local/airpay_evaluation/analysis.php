<?php
// Airpay Evaluations — cross-evaluation analysis dashboard (G-05).
//
// Aggregates responses across ALL evaluations grouped by Kirkpatrick
// level (1=Reaction, 2=Learning, 3=Behaviour, 4=Results). Optional
// date-range + context filters narrow the response set.
//
// URL: /local/airpay_evaluation/analysis.php
//      [?date_from=YYYY-MM-DD][&date_to=YYYY-MM-DD]
//      [&courseid=N][&programid=N][&classroomid=N]
//
// @package    local_airpay_evaluation
// @copyright  2026 Airpay Payment Services
// @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

require_once(__DIR__ . '/../../config.php');

require_login();

$context = context_system::instance();
require_capability('local/airpay_evaluation:manage', $context);

$date_from   = optional_param('date_from',   '', PARAM_RAW);
$date_to     = optional_param('date_to',     '', PARAM_RAW);
$courseid    = optional_param('courseid',    0,  PARAM_INT);
$programid   = optional_param('programid',   0,  PARAM_INT);
$classroomid = optional_param('classroomid', 0,  PARAM_INT);

$PAGE->set_context($context);
$PAGE->set_url(new moodle_url('/local/airpay_evaluation/analysis.php'));
$PAGE->set_title('Evaluation Analysis');
$PAGE->set_heading('Evaluation Analysis');
$PAGE->set_pagelayout('standard');
$PAGE->set_secondary_navigation(false);
$PAGE->navbar->add(get_string('pluginname', 'local_airpay_evaluation'),
    new moodle_url('/local/airpay_evaluation/index.php'));
$PAGE->navbar->add('Analysis');

$filters = [];
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

$summary = \local_airpay_evaluation\evaluation_manager::get_kirkpatrick_summary($filters);

// Build template-friendly per-level cards.
$levels = [];
$total_responses = 0;
$total_evaluations = 0;
foreach ($summary as $level => $row) {
    $total_responses   += (int) $row['response_count'];
    $total_evaluations += (int) $row['evaluation_count'];

    // Color-code NPS: ≥50 success, 0-49 warning, <0 danger.
    $nps_class = $row['nps_score'] >= 50 ? 'text-success'
               : ($row['nps_score'] >= 0 ? 'text-warning' : 'text-danger');
    // Same idea for rating: ≥4 success, ≥3 warning, <3 danger.
    $rating_class = $row['avg_rating'] >= 4 ? 'text-success'
                  : ($row['avg_rating'] >= 3 ? 'text-warning' : 'text-danger');

    $levels[] = [
        'level'             => $row['level'],
        'level_label'       => $row['level_label'],
        'evaluation_count'  => $row['evaluation_count'],
        'response_count'    => $row['response_count'],
        'avg_rating'        => $row['avg_rating'],
        'avg_rating_pct'    => round(($row['avg_rating'] / 5) * 100),
        'rating_count'      => $row['rating_count'],
        'has_rating'        => $row['rating_count'] > 0,
        'rating_class'      => $rating_class,
        'avg_nps'           => $row['avg_nps'],
        'nps_count'         => $row['nps_count'],
        'has_nps'           => $row['nps_count'] > 0,
        'nps_score'         => $row['nps_score'],
        'nps_class'         => $nps_class,
        'nps_promoters'     => $row['nps_promoters'],
        'nps_detractors'    => $row['nps_detractors'],
    ];
}

$reset_url = (new moodle_url('/local/airpay_evaluation/analysis.php'))->out(false);

$data = [
    'levels'              => $levels,
    'total_responses'     => $total_responses,
    'total_evaluations'   => $total_evaluations,
    'has_data'            => $total_responses > 0,
    'backurl'             => (new moodle_url('/local/airpay_evaluation/index.php'))->out(false),

    // Filter form context.
    'filter_action_url'   => (new moodle_url('/local/airpay_evaluation/analysis.php'))->out(false),
    'filter_date_from'    => s($date_from),
    'filter_date_to'      => s($date_to),
    'filter_courseid'     => $courseid > 0 ? $courseid : '',
    'filter_programid'    => $programid > 0 ? $programid : '',
    'filter_classroomid'  => $classroomid > 0 ? $classroomid : '',
    'has_filter'          => $has_filter,
    'reset_url'           => $reset_url,
];

echo $OUTPUT->header();
echo $OUTPUT->render_from_template('local_airpay_evaluation/analysis', $data);
echo $OUTPUT->footer();
