<?php
// Admin response viewer — aggregate stats per question.
//
// @package    local_airpay_evaluation
// @copyright  2026 Airpay Payment Services
// @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

require_once(__DIR__ . '/../../config.php');
require_login();

$context = context_system::instance();
require_capability('local/airpay_evaluation:manage', $context);

$evaluationid = required_param('id', PARAM_INT);
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
$stats = \local_airpay_evaluation\evaluation_manager::get_response_stats($evaluationid);
$total_responses = \local_airpay_evaluation\evaluation_manager::count_responses($evaluationid);

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
        // Distribution as bar chart data.
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

$data = [
    'evaluationid'    => $evaluation->id,
    'name'            => format_string($evaluation->name),
    'description'     => format_string($evaluation->description ?? ''),
    'is_anonymous'    => (bool) $evaluation->anonymous,
    'total_responses' => $total_responses,
    'has_responses'   => ($total_responses > 0),
    'questions'       => $question_rows,
    'has_questions'   => !empty($question_rows),
    'backurl'         => (new moodle_url('/local/airpay_evaluation/index.php'))->out(false),
];

echo $OUTPUT->header();
echo $OUTPUT->render_from_template('local_airpay_evaluation/responses', $data);
echo $OUTPUT->footer();
