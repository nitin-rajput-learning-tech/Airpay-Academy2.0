<?php
// Airpay Certification Programs — multi-level certification management.
//
// @package    local_airpay_programs
// @copyright  2026 Airpay Payment Services
// @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

require_once(__DIR__ . '/../../config.php');
require_login();

$context = context_system::instance();
require_capability('local/airpay_programs:view', $context);

$PAGE->set_context($context);
$PAGE->set_url(new moodle_url('/local/airpay_programs/index.php'));
$PAGE->set_title('Certification Programs');
$PAGE->set_heading('Certification Programs');
$PAGE->set_pagelayout('standard');
$PAGE->set_secondary_navigation(false);

$can_manage = has_capability('local/airpay_programs:manage', $context);

// Check for data.
$dbman = $DB->get_manager();
$programs = [];
$total = 0;

$table = null;
if ($dbman->table_exists('local_airpay_programs') && $DB->count_records('local_airpay_programs') > 0) {
    $table = 'local_airpay_programs';
} else if ($dbman->table_exists('local_program')) {
    $table = 'local_program';
}

if ($table) {
    $total = $DB->count_records($table);
    $programs = $DB->get_records($table, null, 'id DESC', '*', 0, 25);
}

$rows = [];
foreach ($programs as $p) {
    $rows[] = [
        'id'   => $p->id,
        'name' => format_string($p->name ?? $p->fullname ?? 'Program #' . $p->id),
        'status' => match((int)($p->status ?? 0)) { 0 => 'Draft', 1 => 'New', 2 => 'Active', 3 => 'Hold', 4 => 'Completed', 5 => 'Cancelled', default => 'Unknown' },
        'statuscss' => match((int)($p->status ?? 0)) { 2 => 'badge-success', 4 => 'badge-primary', default => 'badge-secondary' },
    ];
}

$data = [
    'total'     => $total,
    'programs'  => $rows,
    'has_data'  => !empty($rows),
    'can_manage' => $can_manage,
];

echo $OUTPUT->header();
echo $OUTPUT->render_from_template('local_airpay_programs/manage', $data);
echo $OUTPUT->footer();
