<?php
// Airpay Training Evaluations — admin (datatable-driven).
//
// @package    local_airpay_evaluation
// @copyright  2026 Airpay Payment Services
// @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

require_once(__DIR__ . '/../../config.php');
require_login();

$context = context_system::instance();
require_capability('local/airpay_evaluation:manage', $context);

$PAGE->set_context($context);
$PAGE->set_url(new moodle_url('/local/airpay_evaluation/index.php'));
$PAGE->set_title('Training Evaluations');
$PAGE->set_heading('Training Evaluations');
$PAGE->set_pagelayout('standard');
$PAGE->set_secondary_navigation(false);

$can_manage = is_siteadmin() || has_capability('local/airpay_evaluation:manage', $context);

$dbman = $DB->get_manager();
$total = $active = $draft = $total_responses = 0;

if ($dbman->table_exists('local_airpay_evaluation')) {
    $total  = (int) \local_airpay_evaluation\evaluation_manager::count_evaluations();
    $active = (int) \local_airpay_evaluation\evaluation_manager::count_evaluations(
        \local_airpay_evaluation\evaluation_manager::STATUS_ACTIVE);
    $draft  = (int) \local_airpay_evaluation\evaluation_manager::count_evaluations(
        \local_airpay_evaluation\evaluation_manager::STATUS_DRAFT);
    $total_responses = (int) \local_airpay_evaluation\evaluation_manager::count_responses();
}

$columns = [
    ['key' => 'name',        'label' => 'Evaluation',   'sortable' => true,  'sortkey' => 'name'],
    ['key' => 'kirkpatrick', 'label' => 'Level',        'sortable' => true,  'sortkey' => 'kirkpatrick_level'],
    ['key' => 'qcount',      'label' => 'Questions',    'sortable' => false],
    ['key' => 'rcount',      'label' => 'Responses',    'sortable' => false],
    ['key' => 'modified',    'label' => 'Modified',     'sortable' => true,  'sortkey' => 'timemodified'],
    ['key' => 'statuslabel', 'label' => 'Status',       'sortable' => true,  'sortkey' => 'status', 'format' => 'badge'],
];

$data = [
    'total_count'     => number_format($total),
    'active_count'    => number_format($active),
    'draft_count'     => number_format($draft),
    'total_responses' => number_format($total_responses),
    'can_manage'      => $can_manage,
    'columns_json'    => s(json_encode($columns)),
];

echo $OUTPUT->header();
echo $OUTPUT->render_from_template('local_airpay_evaluation/manage', $data);
echo $OUTPUT->footer();
