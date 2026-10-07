<?php
// Airpay Training Evaluations — question builder.
//
// @package    local_sentientia_evaluation
// @copyright  2026 Airpay Payment Services
// @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

require_once(__DIR__ . '/../../config.php');
require_login();

$context = context_system::instance();
require_capability('local/sentientia_evaluation:manage', $context);

$evaluationid = required_param('id', PARAM_INT);
$evaluation = \local_sentientia_evaluation\evaluation_manager::get($evaluationid);
if (!$evaluation) {
    throw new moodle_exception('invalidevaluation', 'local_sentientia_evaluation');
}
// ADR-031: only an evaluation in the caller's tenant.
\local_sentientia_evaluation\evaluation_manager::require_evaluation_access($evaluation);

$PAGE->set_context($context);
$PAGE->set_url(new moodle_url('/local/sentientia_evaluation/questions.php', ['id' => $evaluationid]));
// set_title(), set_heading() and the navbar apply format_string() themselves, so they are given the raw name
// (format_string() here escaped it twice: "Tom & Jerry" showed as "Tom &amp; Jerry").
$PAGE->set_title('Questions — ' . $evaluation->name);
$PAGE->set_heading($evaluation->name);
$PAGE->set_pagelayout('standard');
$PAGE->set_secondary_navigation(false);
$PAGE->navbar->add(get_string('pluginname', 'local_sentientia_evaluation'),
    new moodle_url('/local/sentientia_evaluation/index.php'));
$PAGE->navbar->add($evaluation->name);

// Load questions.
$questions = \local_sentientia_evaluation\evaluation_manager::get_questions($evaluationid);

$type_labels = \local_sentientia_evaluation\evaluation_manager::QUESTION_TYPES;
// Short labels for table.
$short_types = [
    'rating'      => 'Rating (1-5)',
    'nps'         => 'NPS (0-10)',
    'yesno'       => 'Yes / No',
    'multichoice' => 'Multiple choice',
    'multichoice_multi' => get_string('questiontype_multichoice_multi_short', 'local_sentientia_evaluation'),
    'numeric'     => get_string('questiontype_numeric_short', 'local_sentientia_evaluation'),
    'text'        => 'Free text',
];

$rows = [];
$position = 0;
foreach ($questions as $q) {
    $position++;
    // Only the two choice types keep a list of options; a number question keeps {min, max} in the same column.
    $ischoice = in_array($q->questiontype, ['multichoice', 'multichoice_multi'], true);
    $opts = $ischoice ? \local_sentientia_evaluation\evaluation_manager::decode_options($q->options ?? null) : [];
    $bounds = '';
    if ($q->questiontype === 'numeric') {
        $b = \local_sentientia_evaluation\evaluation_manager::decode_numeric_bounds($q->options ?? null);
        if ($b['min'] !== null && $b['max'] !== null) {
            $bounds = get_string('responses_numeric_range', 'local_sentientia_evaluation', (object) $b);
        } else if ($b['min'] !== null) {
            $bounds = get_string('questions_numeric_atleast', 'local_sentientia_evaluation', $b['min']);
        } else if ($b['max'] !== null) {
            $bounds = get_string('questions_numeric_atmost', 'local_sentientia_evaluation', $b['max']);
        }
    }
    $rows[] = [
        'id'            => $q->id,
        'sortorder'     => (int) $q->sortorder,
        // 1..n in form order (it printed the question id + 1: get_questions() is keyed by id).
        'position'      => $position,
        'questiontext'  => format_string($q->questiontext, true, ['escape' => false]),
        'questiontype'  => $q->questiontype,
        'typelabel'     => $short_types[$q->questiontype] ?? $q->questiontype,
        'required'      => (bool) $q->required,
        'is_anonymous'  => (int) ($q->anonymous ?? 0) === 1,
        'is_multichoice' => ($q->questiontype === 'multichoice'),
        'options'       => array_map(static fn($o): string => format_string((string) $o, true, ['escape' => false]), $opts),
        'has_options'   => !empty($opts),
        'bounds_text'   => $bounds,
    ];
}

// A form the BizLMS import brought over is a record of what was asked and answered: evaluation_manager refuses to
// add, edit, delete or reorder its questions, so the page offers none of those controls for it.
$readonly = \local_sentientia_evaluation\evaluation_manager::is_imported($evaluationid);

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
        '/local/sentientia_evaluation/export_template.php',
        ['id' => $evaluation->id]))->out(false),
    'evalname'     => \local_sentientia_evaluation\evaluation_manager::display_text($evaluation->name),
    'evaldesc'     => \local_sentientia_evaluation\evaluation_manager::display_text($evaluation->description ?? ''),
    'status_banner_css'   => $status_banner['css'],
    'status_banner_icon'  => $status_banner['icon'],
    'status_banner_label' => $status_banner['label'],
    'readonly'      => $readonly,
    'readonly_notice' => $readonly ? get_string('imported_readonly_notice', 'local_sentientia_evaluation') : '',
    'questions'     => $rows,
    'has_questions' => !empty($rows),
    'qcount'        => count($rows),
    'rcount'        => \local_sentientia_evaluation\evaluation_manager::count_responses($evaluationid),
    'backurl'       => (new moodle_url('/local/sentientia_evaluation/index.php'))->out(false),
];

echo $OUTPUT->header();
echo $OUTPUT->render_from_template('local_sentientia_evaluation/questions', $data);
echo $OUTPUT->footer();
