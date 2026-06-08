<?php
// Airpay Training Evaluations — admin (datatable-driven).
//
// @package    local_sentientia_evaluation
// @copyright  2026 Airpay Payment Services
// @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

require_once(__DIR__ . '/../../config.php');
require_login();

$context = context_system::instance();
require_capability('local/sentientia_evaluation:manage', $context);

$PAGE->set_context($context);
$PAGE->set_url(new moodle_url('/local/sentientia_evaluation/index.php'));
$PAGE->set_title('Training Evaluations');
$PAGE->set_heading('Training Evaluations');
$PAGE->set_pagelayout('standard');
$PAGE->set_secondary_navigation(false);

// W1-1: org cascade filter (5 cascading selects via list_children WS).
$PAGE->requires->js_call_amd('theme_sentientia/org_cascade', 'init');

$can_manage = is_siteadmin() || has_capability('local/sentientia_evaluation:manage', $context);

$dbman = $DB->get_manager();
$total = $active = $draft = $total_responses = 0;

if ($dbman->table_exists('local_sentientia_evaluation')) {
    $total  = (int) \local_sentientia_evaluation\evaluation_manager::count_evaluations();
    $active = (int) \local_sentientia_evaluation\evaluation_manager::count_evaluations(
        \local_sentientia_evaluation\evaluation_manager::STATUS_ACTIVE);
    $draft  = (int) \local_sentientia_evaluation\evaluation_manager::count_evaluations(
        \local_sentientia_evaluation\evaluation_manager::STATUS_DRAFT);
    $total_responses = (int) \local_sentientia_evaluation\evaluation_manager::count_responses();
}

$columns = [
    ['key' => 'name',        'label' => 'Evaluation',   'sortable' => true,  'sortkey' => 'name'],
    ['key' => 'kirkpatrick', 'label' => 'Level',        'sortable' => true,  'sortkey' => 'kirkpatrick_level'],
    ['key' => 'qcount',      'label' => 'Questions',    'sortable' => false],
    ['key' => 'rcount',      'label' => 'Responses',    'sortable' => false],
    ['key' => 'modified',    'label' => 'Modified',     'sortable' => true,  'sortkey' => 'timemodified'],
    ['key' => 'statuslabel', 'label' => 'Status',       'sortable' => true,  'sortkey' => 'status', 'format' => 'badge'],
];

// Phase B0+ — stat_card-compatible tiles.
$kpi_tiles = [
    [
        'label' => 'Total Forms',
        'value' => number_format($total),
        'icon'  => 'list-alt',
        'color' => 'primary',
    ],
    [
        'label' => 'Active',
        'value' => number_format($active),
        'icon'  => 'play-circle',
        'color' => 'success',
    ],
    [
        'label' => 'Drafts',
        'value' => number_format($draft),
        'icon'  => 'pencil',
        'color' => 'info',
    ],
    [
        'label' => 'Responses',
        'value' => number_format($total_responses),
        'icon'  => 'comments',
        'color' => 'accent',
    ],
];

$data = [
    'total_count'     => number_format($total),
    'active_count'    => number_format($active),
    'draft_count'     => number_format($draft),
    'total_responses' => number_format($total_responses),
    'kpi_tiles'       => $kpi_tiles,
    'has_kpi_tiles'   => !empty($kpi_tiles),
    'can_manage'      => $can_manage,
    'cascade_group'   => 'evaluation-filter',  // W1-1: scopes org cascade events.
    'columns_json'    => s(json_encode($columns)),
    'analysis_url'    => (new moodle_url('/local/sentientia_evaluation/analysis.php'))->out(false),
    // UAT-T4 fix 2026-05-09: was a hardcoded /local/... path that broke
    // on installs where Moodle isn't rooted at /. Route through moodle_url.
    'import_template_url' => (new moodle_url('/local/sentientia_evaluation/import_template.php'))->out(false),
];

echo $OUTPUT->header();
echo $OUTPUT->render_from_template('local_sentientia_evaluation/manage', $data);
echo $OUTPUT->footer();
