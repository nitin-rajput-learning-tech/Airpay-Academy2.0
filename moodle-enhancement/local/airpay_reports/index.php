<?php
// Airpay Reports — admin management page (datatable-driven).
//
// @package    local_airpay_reports
// @copyright  2026 Airpay Payment Services
// @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

require_once(__DIR__ . '/../../config.php');
require_login();

$context = context_system::instance();
require_capability('local/airpay_reports:view', $context);

$PAGE->set_context($context);
$PAGE->set_url(new moodle_url('/local/airpay_reports/index.php'));
$PAGE->set_title('Reports');
$PAGE->set_heading('Reports');
$PAGE->set_pagelayout('standard');
$PAGE->set_secondary_navigation(false);

$can_manage = is_siteadmin() || has_capability('local/airpay_reports:manage', $context);

$dbman = $DB->get_manager();
$total = $active = $archived = $total_runs = 0;
if ($dbman->table_exists('local_airpay_reports')) {
    $total    = (int) \local_airpay_reports\report_manager::count_reports();
    $active   = (int) \local_airpay_reports\report_manager::count_reports(
        \local_airpay_reports\report_manager::STATUS_ACTIVE);
    $archived = (int) \local_airpay_reports\report_manager::count_reports(
        \local_airpay_reports\report_manager::STATUS_ARCHIVED);
    $total_runs = (int) $DB->get_field_sql(
        "SELECT COALESCE(SUM(runcount), 0) FROM {local_airpay_reports}");
}

$columns = [
    ['key' => 'name',        'label' => 'Report Name', 'sortable' => true,  'sortkey' => 'name'],
    ['key' => 'type',        'label' => 'Type',        'sortable' => true,  'sortkey' => 'report_type'],
    ['key' => 'lastrun',     'label' => 'Last Run',    'sortable' => true,  'sortkey' => 'lastrun'],
    ['key' => 'runcount',    'label' => 'Runs',        'sortable' => true,  'sortkey' => 'runcount'],
    ['key' => 'statuslabel', 'label' => 'Status',      'sortable' => true,  'sortkey' => 'status', 'format' => 'badge'],
];

$data = [
    'total_count'    => number_format($total),
    'active_count'   => number_format($active),
    'archived_count' => number_format($archived),
    'total_runs'     => number_format($total_runs),
    'can_manage'     => $can_manage,
    'columns_json'   => s(json_encode($columns)),
];

echo $OUTPUT->header();
echo $OUTPUT->render_from_template('local_airpay_reports/manage', $data);
echo $OUTPUT->footer();
