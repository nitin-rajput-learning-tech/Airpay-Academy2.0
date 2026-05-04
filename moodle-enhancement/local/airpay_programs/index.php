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

$can_manage = is_siteadmin()
    || has_capability('local/airpay_programs:manage', $context)
    || has_capability('local/airpay_programs:update', $context);
$can_delete = is_siteadmin() || has_capability('local/airpay_programs:delete', $context);

// Resolve table.
$dbman = $DB->get_manager();
$programs = [];
$total = 0;
$active = 0;
$draft = 0;

$table = null;
if ($dbman->table_exists('local_airpay_programs')) {
    $table = 'local_airpay_programs';
} else if ($dbman->table_exists('local_program')) {
    $table = 'local_program';
}

if ($table) {
    $total = $DB->count_records($table);
    try {
        $active = $DB->count_records_select($table, "status = 1");
        $draft = $DB->count_records_select($table, "status = 0");
    } catch (\Throwable $e) { /* ignore */ }

    $levels_table = $dbman->table_exists('local_airpay_programs_levels')
        ? 'local_airpay_programs_levels' : '';
    $users_table = $dbman->table_exists('local_airpay_programs_users')
        ? 'local_airpay_programs_users' : '';

    if ($levels_table) {
        $sql = "SELECT p.*,
                   (SELECT COUNT(*) FROM {{$levels_table}} l WHERE l.programid = p.id) AS levelcount";
        if ($users_table) {
            $sql .= ",\n                   (SELECT COUNT(*) FROM {{$users_table}} pu WHERE pu.programid = p.id) AS enrolled";
        }
        $sql .= "\n              FROM {{$table}} p ORDER BY p.id DESC";
        $programs = $DB->get_records_sql($sql, [], 0, 50);
    } else {
        $programs = $DB->get_records($table, null, 'id DESC', '*', 0, 50);
    }
}

$rows = [];
foreach ($programs as $p) {
    $status = (int) ($p->status ?? 0);
    $statuslabel = match ($status) {
        0 => 'Draft',
        1 => 'Active',
        2 => 'Archived',
        default => 'Unknown',
    };
    $statuscss = match ($status) {
        0 => 'badge-secondary',
        1 => 'badge-success',
        2 => 'badge-warning',
        default => 'badge-secondary',
    };

    $rows[] = [
        'id'          => $p->id,
        'name'        => format_string($p->name ?? 'Program #' . $p->id),
        'description' => format_string($p->description ?? ''),
        'levelcount'  => (int) ($p->levelcount ?? 0),
        'enrolled'    => (int) ($p->enrolled ?? 0),
        'status'      => $status,
        'is_draft'    => ($status === 0),
        'is_active'   => ($status === 1),
        'is_archived' => ($status === 2),
        'statuslabel' => $statuslabel,
        'statuscss'   => $statuscss,
        'can_delete'  => $can_delete,
    ];
}

$data = [
    'total'      => $total,
    'active'     => $active,
    'draft'      => $draft,
    'programs'   => $rows,
    'has_data'   => !empty($rows),
    'can_manage' => $can_manage,
    'can_delete' => $can_delete,
];

echo $OUTPUT->header();
echo $OUTPUT->render_from_template('local_airpay_programs/manage', $data);
echo $OUTPUT->footer();
