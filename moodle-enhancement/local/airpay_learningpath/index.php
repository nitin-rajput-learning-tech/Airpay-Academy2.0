<?php
// Airpay Learning Paths — ordered course sequences.
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

$can_manage = is_siteadmin()
    || has_capability('local/airpay_learningpath:manage', $context)
    || has_capability('local/airpay_learningpath:update', $context);
$can_delete = is_siteadmin() || has_capability('local/airpay_learningpath:delete', $context);

// Resolve table.
$dbman = $DB->get_manager();
$paths = [];
$total = 0;
$active = 0;

$table = null;
if ($dbman->table_exists('local_airpay_learningpath')) {
    $table = 'local_airpay_learningpath';
} else if ($dbman->table_exists('local_learningplan')) {
    $table = 'local_learningplan';
}

if ($table) {
    $total = $DB->count_records($table);
    try {
        $active = $DB->count_records_select($table, "status = 1");
    } catch (\Throwable $e) { $active = $total; }

    // Load paths with course count via subquery.
    $courses_table = $dbman->table_exists('local_airpay_learningpath_courses')
        ? 'local_airpay_learningpath_courses' : '';

    if ($courses_table) {
        $paths = $DB->get_records_sql(
            "SELECT p.*, (SELECT COUNT(*) FROM {{$courses_table}} pc WHERE pc.pathid = p.id) AS coursecount
               FROM {{$table}} p ORDER BY p.id DESC", [], 0, 50);
    } else {
        $paths = $DB->get_records($table, null, 'id DESC', '*', 0, 50);
    }
}

$rows = [];
foreach ($paths as $p) {
    $status = (int) ($p->status ?? 1);
    $rows[] = [
        'id'          => $p->id,
        'name'        => format_string($p->name ?? 'Learning Path #' . $p->id),
        'description' => format_string($p->description ?? ''),
        'coursecount' => (int) ($p->coursecount ?? 0),
        'status'      => $status,
        'is_active'   => ($status === 1),
        'statuslabel' => $status === 1 ? 'Active' : 'Archived',
        'statuscss'   => $status === 1 ? 'badge-success' : 'badge-secondary',
        'can_delete'  => $can_delete,
    ];
}

$data = [
    'total'      => $total,
    'active'     => $active,
    'archived'   => $total - $active,
    'paths'      => $rows,
    'has_data'   => !empty($rows),
    'can_manage' => $can_manage,
    'can_delete' => $can_delete,
];

echo $OUTPUT->header();
echo $OUTPUT->render_from_template('local_airpay_learningpath/manage', $data);
echo $OUTPUT->footer();
