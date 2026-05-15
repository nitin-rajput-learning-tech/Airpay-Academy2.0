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

// Phase B0+ — stat_card-compatible tiles.
$kpi_tiles = [
    [
        'label' => 'Total Classrooms',
        'value' => number_format($total),
        'icon'  => 'calendar',
        'color' => 'primary',
    ],
    [
        'label' => 'Active',
        'value' => number_format($active),
        'icon'  => 'play-circle',
        'color' => 'success',
    ],
    [
        'label' => 'Completed',
        'value' => number_format($completed),
        'icon'  => 'check-circle',
        'color' => 'info',
    ],
];

$data = [
    'total_count'     => number_format($total),
    'active_count'    => number_format($active),
    'completed_count' => number_format($completed),
    'kpi_tiles'       => $kpi_tiles,
    'has_kpi_tiles'   => !empty($kpi_tiles),
    'can_create'      => $can_create,
    'columns_json'    => s(json_encode($columns)),
    // W1-1 BizLMS parity: 5-level org hierarchy cascade.
    'cascade_group'   => 'classroom-filter',
];

$PAGE->requires->js_call_amd('theme_airpayux/org_cascade', 'init');

echo $OUTPUT->header();
echo $OUTPUT->render_from_template('local_airpay_classroom/manage', $data);
echo $OUTPUT->footer();
