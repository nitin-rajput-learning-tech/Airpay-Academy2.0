<?php
// Airpay Skills — admin management (categories + skills).
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

// Counts for KPIs.
$total_cats        = \local_airpay_skills\skills_manager::count_categories();
$total_skills      = \local_airpay_skills\skills_manager::count_skills();
$total_role_maps   = \local_airpay_skills\skills_manager::count_role_mappings();

// Load all categories with skill counts.
$cats_with_counts = $DB->get_records_sql(
    "SELECT c.*, (SELECT COUNT(*) FROM {local_airpay_skills} s WHERE s.categoryid = c.id) AS skillcount
       FROM {local_airpay_skill_cats} c
   ORDER BY c.sort_order ASC, c.name ASC");

$cat_rows = [];
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
}

// Load all skills with category names.
$skills_with_cats = $DB->get_records_sql(
    "SELECT s.*, c.name AS category_name, c.color AS category_color
       FROM {local_airpay_skills} s
       JOIN {local_airpay_skill_cats} c ON c.id = s.categoryid
   ORDER BY c.sort_order ASC, s.sort_order ASC, s.name ASC", [], 0, 100);

$level_labels = \local_airpay_skills\skills_manager::LEVELS;
$skill_rows = [];
foreach ($skills_with_cats as $s) {
    $skill_rows[] = [
        'id'           => $s->id,
        'name'         => format_string($s->name),
        'description'  => format_string($s->description ?? ''),
        'category'     => format_string($s->category_name),
        'category_color' => s($s->category_color ?? '#0066A7'),
        'max_level'    => (int) $s->max_level,
        'max_level_label' => $level_labels[$s->max_level] ?? '—',
        'sort_order'   => (int) $s->sort_order,
    ];
}

$data = [
    'total_categories' => $total_cats,
    'total_skills'     => $total_skills,
    'total_role_maps'  => $total_role_maps,
    'categories'       => $cat_rows,
    'has_categories'   => !empty($cat_rows),
    'skills'           => $skill_rows,
    'has_skills'       => !empty($skill_rows),
];

echo $OUTPUT->header();
echo $OUTPUT->render_from_template('local_airpay_skills/manage', $data);
echo $OUTPUT->footer();
