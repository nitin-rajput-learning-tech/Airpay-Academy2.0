<?php
// Airpay Classroom Training (ILT) — session management.
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

$can_manage = has_capability('local/airpay_classroom:manage', $context);

// Check for session data — try legacy table, then Airpay table.
$dbman = $DB->get_manager();
$classrooms = [];
$total = 0;
$active = 0;
$completed = 0;

$table = null;
if ($dbman->table_exists('local_airpay_classroom') && $DB->count_records('local_airpay_classroom') > 0) {
    $table = 'local_airpay_classroom';
} else if ($dbman->table_exists('local_classroom')) {
    $table = 'local_classroom';
}

if ($table) {
    $total = $DB->count_records($table);
    // Status fields may not exist on all table schemas — use try/catch.
    try {
        $active = $DB->count_records_select($table, "status IN (1, 2)");
    } catch (\Throwable $e) { $active = 0; }
    try {
        $completed = $DB->count_records_select($table, "status = 4");
    } catch (\Throwable $e) { $completed = 0; }

    // Enrolled count — only include sub-query if users table exists.
    $users_table = $dbman->table_exists('local_classroom_users') ? 'local_classroom_users'
        : ($dbman->table_exists('local_airpay_classroom_users') ? 'local_airpay_classroom_users' : '');

    if ($users_table) {
        $classrooms = $DB->get_records_sql(
            "SELECT cl.*, (SELECT COUNT(*) FROM {{$users_table}} clu WHERE clu.classroomid = cl.id) AS enrolled
               FROM {{$table}} cl ORDER BY cl.id DESC", [], 0, 25);
    } else {
        $classrooms = $DB->get_records($table, null, 'id DESC', '*', 0, 25);
    }
}

$rows = [];
foreach ($classrooms as $cl) {
    $rows[] = [
        'id'         => $cl->id,
        'name'       => format_string($cl->name ?? $cl->fullname ?? 'Classroom #' . $cl->id),
        'status'     => $cl->status ?? 0,
        'statuslabel' => match((int)($cl->status ?? 0)) { 0 => 'Draft', 1 => 'New', 2 => 'Active', 3 => 'Hold', 4 => 'Completed', 5 => 'Cancelled', default => 'Unknown' },
        'statuscss'  => match((int)($cl->status ?? 0)) { 2 => 'badge-success', 4 => 'badge-primary', 3 => 'badge-warning', 5 => 'badge-danger', default => 'badge-secondary' },
        'enrolled'   => (int)($cl->enrolled ?? 0),
        'startdate'  => !empty($cl->startdate) ? userdate($cl->startdate, '%d %b %Y') : '—',
        'enddate'    => !empty($cl->enddate) ? userdate($cl->enddate, '%d %b %Y') : '—',
    ];
}

$data = [
    'total'      => $total,
    'active'     => $active,
    'completed'  => $completed,
    'classrooms' => $rows,
    'has_data'   => !empty($rows),
    'can_manage' => $can_manage,
];

echo $OUTPUT->header();
echo $OUTPUT->render_from_template('local_airpay_classroom/manage', $data);
echo $OUTPUT->footer();
