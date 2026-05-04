<?php
// Airpay Classroom Training (ILT) — admin classroom management.
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

$can_manage = has_capability('local/airpay_classroom:manage', $context)
              || has_capability('local/airpay_classroom:update', $context);
$can_delete = is_siteadmin() || has_capability('local/airpay_classroom:delete', $context);

// ── Resolve table and load data ───────────────────────────────────────
$dbman = $DB->get_manager();
$classrooms = [];
$total = 0;
$active = 0;
$completed = 0;

// Prefer Airpay table; fall back to BizLMS legacy.
$table = null;
if ($dbman->table_exists('local_airpay_classroom')) {
    $table = 'local_airpay_classroom';
} else if ($dbman->table_exists('local_classroom')) {
    $table = 'local_classroom';
}

if ($table) {
    $total = $DB->count_records($table);
    // Status: 1=active, 0=cancelled, 2=completed (per install.xml).
    try {
        $active = $DB->count_records_select($table, "status = 1");
    } catch (\Throwable $e) { $active = 0; }
    try {
        $completed = $DB->count_records_select($table, "status = 2");
    } catch (\Throwable $e) { $completed = 0; }

    // Enrolled count — sub-query if users table exists.
    $users_table = $dbman->table_exists('local_airpay_classroom_users') ? 'local_airpay_classroom_users'
        : ($dbman->table_exists('local_classroom_users') ? 'local_classroom_users' : '');

    if ($users_table) {
        $classrooms = $DB->get_records_sql(
            "SELECT cl.*, (SELECT COUNT(*) FROM {{$users_table}} clu WHERE clu.classroomid = cl.id) AS enrolled
               FROM {{$table}} cl ORDER BY cl.id DESC", [], 0, 25);
    } else {
        $classrooms = $DB->get_records($table, null, 'id DESC', '*', 0, 25);
    }
}

// ── Build display rows ────────────────────────────────────────────────
$rows = [];
foreach ($classrooms as $cl) {
    $status = (int) ($cl->status ?? 1);
    $statuslabel = match ($status) {
        0 => 'Cancelled',
        1 => 'Active',
        2 => 'Completed',
        default => 'Unknown',
    };
    $statuscss = match ($status) {
        0 => 'badge-danger',
        1 => 'badge-success',
        2 => 'badge-primary',
        default => 'badge-secondary',
    };

    $rows[] = [
        'id'          => $cl->id,
        'name'        => format_string($cl->name ?? 'Classroom #' . $cl->id),
        'location'    => format_string($cl->location ?? '—'),
        'capacity'    => (int) ($cl->capacity ?? 0),
        'enrolled'    => (int) ($cl->enrolled ?? 0),
        'status'      => $status,
        'statuslabel' => $statuslabel,
        'statuscss'   => $statuscss,
        'is_active'   => ($status === 1),
        'can_delete'  => $can_delete,
    ];
}

$data = [
    'total'      => $total,
    'active'     => $active,
    'completed'  => $completed,
    'classrooms' => $rows,
    'has_data'   => !empty($rows),
    'can_manage' => $can_manage,
    'can_delete' => $can_delete,
];

echo $OUTPUT->header();
echo $OUTPUT->render_from_template('local_airpay_classroom/manage', $data);
echo $OUTPUT->footer();
