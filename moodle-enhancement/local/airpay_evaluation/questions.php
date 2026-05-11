<?php
// Airpay Training Evaluations — question builder.
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
$PAGE->set_url(new moodle_url('/local/airpay_evaluation/questions.php', ['id' => $evaluationid]));
$PAGE->set_title('Questions — ' . format_string($evaluation->name));
$PAGE->set_heading(format_string($evaluation->name));
$PAGE->set_pagelayout('standard');
$PAGE->set_secondary_navigation(false);
$PAGE->navbar->add(get_string('pluginname', 'local_airpay_evaluation'),
    new moodle_url('/local/airpay_evaluation/index.php'));
$PAGE->navbar->add(format_string($evaluation->name));

// Load questions.
$questions = \local_airpay_evaluation\evaluation_manager::get_questions($evaluationid);

$type_labels = \local_airpay_evaluation\evaluation_manager::QUESTION_TYPES;
// Short labels for table.
$short_types = [
    'rating'      => 'Rating (1-5)',
    'nps'         => 'NPS (0-10)',
    'yesno'       => 'Yes / No',
    'multichoice' => 'Multiple choice',
    'text'        => 'Free text',
];

$rows = [];
foreach ($questions as $i => $q) {
    $opts = \local_airpay_evaluation\evaluation_manager::decode_options($q->options ?? null);
    $rows[] = [
        'id'            => $q->id,
        'sortorder'     => (int) $q->sortorder,
        'position'      => $i + 1,
        'questiontext'  => format_string($q->questiontext),
        'questiontype'  => $q->questiontype,
        'typelabel'     => $short_types[$q->questiontype] ?? $q->questiontype,
        'required'      => (bool) $q->required,
        'is_anonymous'  => (int) ($q->anonymous ?? 0) === 1,
        'is_multichoice' => ($q->questiontype === 'multichoice'),
        'options'       => array_map('format_string', $opts),
        'has_options'   => !empty($opts),
    ];
}

// Status banner styling.
$status_banner = match ((int) $evaluation->status) {
    0 => ['css' => 'alert-secondary', 'icon' => 'fa-pencil', 'label' => 'This evaluation is in DRAFT — it won\'t collect responses until published.'],
    1 => ['css' => 'alert-success',  'icon' => 'fa-check-circle', 'label' => 'This evaluation is ACTIVE and collecting responses.'],
    2 => ['css' => 'alert-warning',  'icon' => 'fa-archive', 'label' => 'This evaluation is ARCHIVED — past responses preserved, no new submissions.'],
    default => ['css' => 'alert-secondary', 'icon' => 'fa-question', 'label' => ''],
};

$data = [
    'evaluationid' => $evaluation->id,
    // UAT fix 2026-05-09: hardcoded /local/... → moodle_url for non-root installs.
    'export_template_url' => (new moodle_url(
        '/local/airpay_evaluation/export_template.php',
        ['id' => $evaluation->id]))->out(false),
    'evalname'     => format_string($evaluation->name),
    'evaldesc'     => format_string($evaluation->description ?? ''),
    'status_banner_css'   => $status_banner['css'],
    'status_banner_icon'  => $status_banner['icon'],
    'status_banner_label' => $status_banner['label'],
    'questions'     => $rows,
    'has_questions' => !empty($rows),
    'qcount'        => count($rows),
    'rcount'        => \local_airpay_evaluation\evaluation_manager::count_responses($evaluationid),
    'backurl'       => (new moodle_url('/local/airpay_evaluation/index.php'))->out(false),
];

echo $OUTPUT->header();
echo $OUTPUT->render_from_template('local_airpay_evaluation/questions', $data);
echo $OUTPUT->footer();
