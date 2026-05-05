<?php
// Airpay Online Exams — admin exam management (datatable-driven).
//
// Exams are wrappers around Moodle quiz activities. The wrapper adds
// tenant scoping, custom passing grades, and dashboard reporting on
// top of the underlying quiz module.
//
// @package    local_airpay_exams
// @copyright  2026 Airpay Payment Services
// @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

require_once(__DIR__ . '/../../config.php');
require_login();

$context = context_system::instance();
require_capability('local/airpay_exams:view', $context);

$PAGE->set_context($context);
$PAGE->set_url(new moodle_url('/local/airpay_exams/index.php'));
$PAGE->set_title('Online Exams');
$PAGE->set_heading('Online Exams');
$PAGE->set_pagelayout('standard');
$PAGE->set_secondary_navigation(false);

$can_create = is_siteadmin() || has_capability('local/airpay_exams:manage', $context);

$dbman = $DB->get_manager();
$total  = 0;
$active = 0;
if ($dbman->table_exists('local_airpay_exams')) {
    $total  = (int) $DB->count_records('local_airpay_exams');
    $active = (int) $DB->count_records('local_airpay_exams', ['status' => 1]);
}

$columns = [
    ['key' => 'name',         'label' => 'Exam Name',     'sortable' => true,  'sortkey' => 'name'],
    ['key' => 'duration',     'label' => 'Duration',      'sortable' => false],
    ['key' => 'passinggrade', 'label' => 'Pass Grade',    'sortable' => true,  'sortkey' => 'passinggrade'],
    ['key' => 'attempts',     'label' => 'Attempts',      'sortable' => false],
    ['key' => 'created',      'label' => 'Created',       'sortable' => true,  'sortkey' => 'timecreated'],
    ['key' => 'statuslabel',  'label' => 'Status',        'sortable' => true,  'sortkey' => 'status', 'format' => 'badge'],
];

$data = [
    'total_count'   => number_format($total),
    'active_count'  => number_format($active),
    'inactive_count' => number_format($total - $active),
    'can_create'    => $can_create,
    'columns_json'  => s(json_encode($columns)),
];

echo $OUTPUT->header();
echo $OUTPUT->render_from_template('local_airpay_exams/manage', $data);
echo $OUTPUT->footer();
