<?php
// Airpay Certification Programs — admin (datatable-driven).
//
// @package    local_sentientia_programs
// @copyright  2026 Airpay Payment Services
// @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

require_once(__DIR__ . '/../../config.php');
require_login();

$context = context_system::instance();
require_capability('local/sentientia_programs:view', $context);

$PAGE->set_context($context);
$PAGE->set_url(new moodle_url('/local/sentientia_programs/index.php'));
$PAGE->set_title('Certification Programs');
$PAGE->set_heading('Certification Programs');
$PAGE->set_pagelayout('standard');
$PAGE->set_secondary_navigation(false);

$can_create = is_siteadmin() || has_capability('local/sentientia_programs:create', $context)
    || has_capability('local/sentientia_programs:manage', $context);

$dbman = $DB->get_manager();
$total = $active = $completed = 0;
if ($dbman->table_exists('local_sentientia_programs')) {
    $total     = (int) $DB->count_records('local_sentientia_programs');
    $active    = (int) $DB->count_records('local_sentientia_programs', ['status' => 1]);
    $completed = (int) $DB->count_records('local_sentientia_programs', ['status' => 2]);
}

$columns = [
    ['key' => 'name',        'label' => 'Program',    'sortable' => true,  'sortkey' => 'name', 'format' => 'html'],
    ['key' => 'levels',      'label' => 'Levels',     'sortable' => false],
    ['key' => 'enrolled',    'label' => 'Enrolled',   'sortable' => false],
    ['key' => 'created',     'label' => 'Created',    'sortable' => true,  'sortkey' => 'timecreated'],
    ['key' => 'statuslabel', 'label' => 'Status',     'sortable' => true,  'sortkey' => 'status', 'format' => 'badge'],
];

// Phase B0+ — stat_card-compatible tiles.
$kpi_tiles = [
    [
        'label' => 'Total Programs',
        'value' => number_format($total),
        'icon'  => 'graduation-cap',
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
    'cascade_group'   => 'programs-filter',
];

$PAGE->requires->js_call_amd('theme_sentientia/org_cascade', 'init');

echo $OUTPUT->header();
echo $OUTPUT->render_from_template('local_sentientia_programs/manage', $data);
echo $OUTPUT->footer();
