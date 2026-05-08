<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
//
// Phase F.2 (2026-05-08) — admin curation page for featured courses.

require_once(__DIR__ . '/../../config.php');

require_login();
$context = context_system::instance();
require_capability('local/airpay_courses:manage', $context);

$PAGE->set_url(new moodle_url('/local/airpay_courses/featured.php'));
$PAGE->set_context($context);
$PAGE->set_pagelayout('standard');
$PAGE->set_title('Featured courses');
$PAGE->set_heading('Featured courses');
$PAGE->navbar->add('Manage courses',
    new moodle_url('/local/airpay_courses/index.php'));
$PAGE->navbar->add('Featured');

global $DB;

// Tenant options for the picker (admins of one tenant only see their own).
$tenant_options = [['value' => 0, 'label' => 'All tenants',
    'selected' => true]];
if (is_siteadmin()) {
    $orgs = $DB->get_records_sql(
        "SELECT id, name FROM {local_airpay_org}
          WHERE id IN (1, 77, 177)
       ORDER BY id ASC");
    foreach ($orgs as $o) {
        $tenant_options[] = [
            'value'    => (int) $o->id,
            'label'    => format_string($o->name),
            'selected' => false,
        ];
    }
}

// All visible courses for the picker.
$courses_for_picker = $DB->get_records_sql(
    "SELECT id, fullname, shortname FROM {course}
      WHERE id <> :siteid AND visible = 1
   ORDER BY fullname ASC", ['siteid' => SITEID], 0, 500);
$course_options = [];
foreach ($courses_for_picker as $c) {
    $course_options[] = [
        'id'        => (int) $c->id,
        'fullname'  => format_string($c->fullname),
        'shortname' => format_string($c->shortname),
    ];
}

$rows = \local_airpay_courses\featured_manager::list_all();

$data = [
    'sesskey'         => sesskey(),
    'tenant_options'  => $tenant_options,
    'course_options'  => $course_options,
    'has_courses'     => !empty($course_options),
    'rows'            => $rows,
    'has_rows'        => !empty($rows),
    'manage_url'      => (new moodle_url('/local/airpay_courses/index.php'))->out(false),
];

echo $OUTPUT->header();
echo $OUTPUT->render_from_template('local_airpay_courses/featured', $data);
$PAGE->requires->js_call_amd('local_airpay_courses/featured', 'init', []);
echo $OUTPUT->footer();
