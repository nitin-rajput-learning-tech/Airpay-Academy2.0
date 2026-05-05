<?php
// Airpay Classroom Training (ILT) — admin (datatable-driven).
//
// @package    local_airpay_classroom
// @copyright  2026 Airpay Payment Services
// @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

require_once(__DIR__ . '/../../config.php');
require_login();

$context = context_system::instance();
require_capability('local/airpay_classroom:view', $context);

$PAGE->set_context($context);
$PAGE->set_url(new moodle_url('/local/airpay_classroom/index.php'));
$PAGE->set_title('Classroom Training');
$PAGE->set_heading('Classroom Training');
$PAGE->set_pagelayout('standard');
$PAGE->set_secondary_navigation(false);

$can_create = is_siteadmin() || has_capability('local/airpay_classroom:create', $context)
    || has_capability('local/airpay_classroom:manage', $context);

$dbman = $DB->get_manager();
$total = 0;
$active = 0;
$completed = 0;
if ($dbman->table_exists('local_airpay_classroom')) {
    $total     = (int) $DB->count_records('local_airpay_classroom');
    $active    = (int) $DB->count_records('local_airpay_classroom', ['status' => 1]);
    $completed = (int) $DB->count_records('local_airpay_classroom', ['status' => 2]);
}

$columns = [
    ['key' => 'name',        'label' => 'Classroom',   'sortable' => true,  'sortkey' => 'name', 'format' => 'html'],
    ['key' => 'location',    'label' => 'Location',    'sortable' => true,  'sortkey' => 'location'],
    ['key' => 'capacity',    'label' => 'Capacity',    'sortable' => true,  'sortkey' => 'capacity'],
    ['key' => 'created',     'label' => 'Created',     'sortable' => true,  'sortkey' => 'timecreated'],
    ['key' => 'statuslabel', 'label' => 'Status',      'sortable' => true,  'sortkey' => 'status', 'format' => 'badge'],
];

$data = [
    'total_count'     => number_format($total),
    'active_count'    => number_format($active),
    'completed_count' => number_format($completed),
    'can_create'      => $can_create,
    'columns_json'    => s(json_encode($columns)),
];

echo $OUTPUT->header();
echo $OUTPUT->render_from_template('local_airpay_classroom/manage', $data);
echo $OUTPUT->footer();
