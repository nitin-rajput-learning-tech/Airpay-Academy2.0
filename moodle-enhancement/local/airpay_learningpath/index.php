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

$can_manage = has_capability('local/airpay_learningpath:manage', $context);

// Check for data.
$dbman = $DB->get_manager();
$paths = [];
$total = 0;

$table = null;
if ($dbman->table_exists('local_airpay_learningpath') && $DB->count_records('local_airpay_learningpath') > 0) {
    $table = 'local_airpay_learningpath';
} else if ($dbman->table_exists('local_learningplan')) {
    $table = 'local_learningplan';
}

if ($table) {
    $total = $DB->count_records($table);
    $paths = $DB->get_records($table, null, 'id DESC', '*', 0, 25);
}

$rows = [];
foreach ($paths as $p) {
    $rows[] = [
        'id'   => $p->id,
        'name' => format_string($p->name ?? $p->fullname ?? 'Learning Path #' . $p->id),
        'description' => format_string($p->description ?? ''),
    ];
}

$data = [
    'total'     => $total,
    'paths'     => $rows,
    'has_data'  => !empty($rows),
    'can_manage' => $can_manage,
];

echo $OUTPUT->header();
echo $OUTPUT->render_from_template('local_airpay_learningpath/manage', $data);
echo $OUTPUT->footer();
