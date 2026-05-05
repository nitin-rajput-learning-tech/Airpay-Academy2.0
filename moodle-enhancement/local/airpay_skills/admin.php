<?php
// Airpay Skills — admin (datatable-driven for skills, static for categories).
//
// @package    local_airpay_skills
// @copyright  2026 Airpay Payment Services
// @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

require_once(__DIR__ . '/../../config.php');
require_login();

$context = context_system::instance();
require_capability('local/airpay_skills:manage', $context);

$PAGE->set_context($context);
$PAGE->set_url(new moodle_url('/local/airpay_skills/admin.php'));
$PAGE->set_title('Skills Management');
$PAGE->set_heading('Skills Management');
$PAGE->set_pagelayout('standard');
$PAGE->set_secondary_navigation(false);

$total_cats      = \local_airpay_skills\skills_manager::count_categories();
$total_skills    = \local_airpay_skills\skills_manager::count_skills();
$total_role_maps = \local_airpay_skills\skills_manager::count_role_mappings();

// Categories — small dataset, render server-side.
$cats_with_counts = $DB->get_records_sql(
    "SELECT c.*, (SELECT COUNT(*) FROM {local_airpay_skills} s WHERE s.categoryid = c.id) AS skillcount
       FROM {local_airpay_skill_cats} c
   ORDER BY c.sort_order ASC, c.name ASC");
$cat_rows = [];
$cat_options_for_filter = [];
foreach ($cats_with_counts as $c) {
    $cat_rows[] = [
        'id'         => $c->id,
        'name'       => format_string($c->name),
        'description' => format_string($c->description ?? ''),
        'icon'       => s($c->icon ?? 'fa-cogs'),
        'color'      => s($c->color ?? '#0066A7'),
        'sort_order' => (int) ($c->sort_order ?? 0),
        'skillcount' => (int) $c->skillcount,
    ];
    $cat_options_for_filter[] = [
        'id'   => (int) $c->id,
        'name' => format_string($c->name),
    ];
}

$columns = [
    ['key' => 'name',      'label' => 'Skill',      'sortable' => true,  'sortkey' => 'name'],
    ['key' => 'category',  'label' => 'Category',   'sortable' => false],
    ['key' => 'max_level', 'label' => 'Max Level',  'sortable' => true,  'sortkey' => 'max_level'],
    ['key' => 'sort',      'label' => 'Sort',       'sortable' => true,  'sortkey' => 'sort_order'],
    ['key' => 'created',   'label' => 'Created',    'sortable' => true,  'sortkey' => 'timecreated'],
];

$data = [
    'total_categories' => number_format($total_cats),
    'total_skills'     => number_format($total_skills),
    'total_role_maps'  => number_format($total_role_maps),
    'categories'       => $cat_rows,
    'has_categories'   => !empty($cat_rows),
    'cat_options'      => $cat_options_for_filter,
    'has_cat_options'  => !empty($cat_options_for_filter),
    'columns_json'     => s(json_encode($columns)),
];

echo $OUTPUT->header();
echo $OUTPUT->render_from_template('local_airpay_skills/manage', $data);
echo $OUTPUT->footer();
