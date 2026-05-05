<?php
// Airpay Course Management — admin (datatable-driven).
//
// @package    local_airpay_courses
// @copyright  2026 Airpay Payment Services
// @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

require_once(__DIR__ . '/../../config.php');
require_login();

$context = context_system::instance();
require_capability('local/airpay_courses:view', $context);

$PAGE->set_context($context);
$PAGE->set_url(new moodle_url('/local/airpay_courses/index.php'));
$PAGE->set_title(get_string('pluginname', 'local_airpay_courses'));
$PAGE->set_heading(get_string('pluginname', 'local_airpay_courses'));
$PAGE->set_pagelayout('standard');
$PAGE->set_secondary_navigation(false);

$can_create = has_capability('local/airpay_courses:create', $context);

// KPI counts.
$total_count   = (int) $DB->count_records_select('course', 'id > 1');
$visible_count = (int) $DB->count_records_select('course', 'id > 1 AND visible = 1');
$hidden_count  = $total_count - $visible_count;

// Category options.
$categories = $DB->get_records('course_categories', null, 'sortorder ASC', 'id, name, depth');
$cat_options = [];
foreach ($categories as $c) {
    $cat_options[] = [
        'id'   => $c->id,
        'name' => str_repeat('— ', max(0, $c->depth - 1)) . format_string($c->name),
    ];
}

// Datatable columns.
$columns = [
    ['key' => 'fullname',    'label' => 'Course Name', 'sortable' => true,  'sortkey' => 'fullname',   'format' => 'html'],
    ['key' => 'shortname',   'label' => 'Code',        'sortable' => true,  'sortkey' => 'shortname'],
    ['key' => 'catname',     'label' => 'Category',    'sortable' => false],
    ['key' => 'enrolled',    'label' => 'Enrolled',    'sortable' => false],
    ['key' => 'created',     'label' => 'Created',     'sortable' => true,  'sortkey' => 'timecreated'],
    ['key' => 'statuslabel', 'label' => 'Visibility',  'sortable' => true,  'sortkey' => 'visible', 'format' => 'badge'],
];

$data = [
    'total_count'    => number_format($total_count),
    'visible_count'  => number_format($visible_count),
    'hidden_count'   => number_format($hidden_count),
    'can_create'     => $can_create,
    'cat_options'    => $cat_options,
    'has_cat_options' => !empty($cat_options),
    'columns_json'   => s(json_encode($columns)),
];

echo $OUTPUT->header();
echo $OUTPUT->render_from_template('local_airpay_courses/manage', $data);
echo $OUTPUT->footer();
