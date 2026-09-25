<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
//
// Phase F.2 (2026-05-08) — admin curation page for featured courses.

require_once(__DIR__ . '/../../config.php');

require_login();
$context = context_system::instance();
require_capability('local/sentientia_courses:manage', $context);

$PAGE->set_url(new moodle_url('/local/sentientia_courses/featured.php'));
$PAGE->set_context($context);
$PAGE->set_pagelayout('standard');
$PAGE->set_title('Featured courses');
$PAGE->set_heading('Featured courses');
$PAGE->navbar->add('Manage courses',
    new moodle_url('/local/sentientia_courses/index.php'));
$PAGE->navbar->add('Featured');

global $DB;

// ADR-031 (2026-09-25): null = cross-tenant curator (every list, including
// the global "All tenants" one); otherwise the curator's tenant root. A scoped
// curator with no tenant is refused (error_outoftenant). Until this date every
// :manage holder was offered "All tenants", picked from every tenant's courses
// and saw every tenant's featured rows.
$curationroot = \local_sentientia_courses\featured_manager::curation_root();

// Tenant options for the picker (admins of one tenant only see their own).
if ($curationroot !== null) {
    $ownorg = $DB->get_record('local_sentientia_org', ['id' => $curationroot], 'id, fullname');
    $tenant_options = [['value' => $curationroot,
        'label' => $ownorg ? format_string($ownorg->fullname) : (string) $curationroot,
        'selected' => true]];
} else {
    $tenant_options = [['value' => 0, 'label' => 'All tenants',
        'selected' => true]];
    // Bug-fix 2026-05-09 (UAT-T1.1.c): local_sentientia_org has 'fullname',
    // not 'name'. Caught by UAT — the original query 500'd the whole
    // page, hiding the admin form.
    $orgs = $DB->get_records_sql(
        "SELECT id, fullname FROM {local_sentientia_org}
          WHERE id IN (1, 77, 177)
       ORDER BY id ASC");
    foreach ($orgs as $o) {
        $tenant_options[] = [
            'value'    => (int) $o->id,
            'label'    => format_string($o->fullname),
            'selected' => false,
        ];
    }
}

// Visible courses for the picker - ADR-031: the same scope as the Manage
// Courses table (own tree + legacy NULL-path courses; 1=1 cross-tenant).
[$pickersql, $pickerargs] = \local_sentientia_courses\course_manager::manage_scope_sql('c');
$courses_for_picker = $DB->get_records_sql(
    "SELECT c.id, c.fullname, c.shortname FROM {course} c
      WHERE c.id <> :siteid AND c.visible = 1 AND {$pickersql}
   ORDER BY c.fullname ASC", ['siteid' => SITEID] + $pickerargs, 0, 500);
$course_options = [];
foreach ($courses_for_picker as $c) {
    $course_options[] = [
        'id'        => (int) $c->id,
        'fullname'  => format_string($c->fullname),
        'shortname' => format_string($c->shortname),
    ];
}

$rows = $curationroot === null
    ? \local_sentientia_courses\featured_manager::list_all()
    : \local_sentientia_courses\featured_manager::list_all($curationroot);

$data = [
    'sesskey'         => sesskey(),
    'tenant_options'  => $tenant_options,
    'course_options'  => $course_options,
    'has_courses'     => !empty($course_options),
    'rows'            => $rows,
    'has_rows'        => !empty($rows),
    'manage_url'      => (new moodle_url('/local/sentientia_courses/index.php'))->out(false),
];

echo $OUTPUT->header();
echo $OUTPUT->render_from_template('local_sentientia_courses/featured', $data);
$PAGE->requires->js_call_amd('local_sentientia_courses/featured', 'init', []);
echo $OUTPUT->footer();
