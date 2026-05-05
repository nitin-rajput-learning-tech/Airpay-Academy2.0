<?php
// Airpay Learning Paths — admin (datatable-driven).
//
// @package    local_airpay_learningpath
// @copyright  2026 Airpay Payment Services
// @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

require_once(__DIR__ . '/../../config.php');
require_login();

$context = context_system::instance();
require_capability('local/airpay_learningpath:view', $context);

$PAGE->set_context($context);
$PAGE->set_url(new moodle_url('/local/airpay_learningpath/index.php'));
$PAGE->set_title('Learning Paths');
$PAGE->set_heading('Learning Paths');
$PAGE->set_pagelayout('standard');
$PAGE->set_secondary_navigation(false);

$can_create = is_siteadmin() || has_capability('local/airpay_learningpath:create', $context)
    || has_capability('local/airpay_learningpath:manage', $context);

$dbman = $DB->get_manager();
$total = $active = $completed = 0;
if ($dbman->table_exists('local_airpay_learningpath')) {
    $total     = (int) $DB->count_records('local_airpay_learningpath');
    $active    = (int) $DB->count_records('local_airpay_learningpath', ['status' => 1]);
    $completed = (int) $DB->count_records('local_airpay_learningpath', ['status' => 2]);
}

$columns = [
    ['key' => 'name',        'label' => 'Path Name',  'sortable' => true,  'sortkey' => 'name'],
    ['key' => 'courses',     'label' => 'Courses',    'sortable' => false],
    ['key' => 'enrolled',    'label' => 'Enrolled',   'sortable' => false],
    ['key' => 'created',     'label' => 'Created',    'sortable' => true,  'sortkey' => 'timecreated'],
    ['key' => 'statuslabel', 'label' => 'Status',     'sortable' => true,  'sortkey' => 'status', 'format' => 'badge'],
];

$data = [
    'total_count'     => number_format($total),
    'active_count'    => number_format($active),
    'completed_count' => number_format($completed),
    'can_create'      => $can_create,
    'columns_json'    => s(json_encode($columns)),
];

echo $OUTPUT->header();
echo $OUTPUT->render_from_template('local_airpay_learningpath/manage', $data);
echo $OUTPUT->footer();
