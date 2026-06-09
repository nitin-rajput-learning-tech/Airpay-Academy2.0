<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
//
// Course-Skill Mapping admin page.
//
// Pick a course → see / edit which skills (and at what target level)
// course completion will credit. The observer in
// {@see local_sentientia_skills\observer::course_completed} consumes these
// rows when {@link \core\event\course_completed} fires.

require_once(__DIR__ . '/../../config.php');

require_login();
$context = context_system::instance();
require_capability('local/sentientia_skills:manage', $context);

$courseid = optional_param('courseid', 0, PARAM_INT);

$PAGE->set_url(new moodle_url('/local/sentientia_skills/course_mapping.php',
    $courseid ? ['courseid' => $courseid] : []));
$PAGE->set_context($context);
$PAGE->set_pagelayout('standard');
$PAGE->set_title('Course-Skill Mapping');
$PAGE->set_heading('Course-Skill Mapping');
$PAGE->navbar->add('Skills',
    new moodle_url('/local/sentientia_skills/admin.php'));
$PAGE->navbar->add('Course Mapping');

// All skills (for the picker dropdown).
global $DB;
$all_skills = $DB->get_records_sql(
    "SELECT s.id, s.name, s.max_level,
            c.name AS category_name, c.color AS category_color
       FROM {local_sentientia_skills} s
  LEFT JOIN {local_sentientia_skill_cats} c ON c.id = s.categoryid
   ORDER BY c.sort_order ASC, s.name ASC");
$skill_options = [];
foreach ($all_skills as $s) {
    $skill_options[] = [
        'id'             => (int) $s->id,
        'name'           => format_string($s->name),
        'max_level'      => (int) $s->max_level,
        'category_name'  => format_string((string) ($s->category_name ?? '')),
        'category_color' => s((string) ($s->category_color ?? '#0066A7')),
    ];
}

// Recent / popular courses for the initial picker (top 25 by skill count).
$top_courses_rows = $DB->get_records_sql(
    "SELECT c.id, c.fullname, c.shortname,
            (SELECT COUNT(*) FROM {local_sentientia_course_skills} cs
              WHERE cs.courseid = c.id) AS mapped_count
       FROM {course} c
      WHERE c.id <> :siteid AND c.visible = 1
   ORDER BY mapped_count DESC, c.fullname ASC",
    ['siteid' => SITEID], 0, 25);
$top_courses = [];
foreach ($top_courses_rows as $c) {
    $top_courses[] = [
        'id'           => (int) $c->id,
        'fullname'     => format_string($c->fullname),
        'shortname'    => format_string($c->shortname),
        'mapped_count' => (int) $c->mapped_count,
    ];
}

$selected_course = $courseid
    ? \local_sentientia_skills\skills_manager::get_course_summary($courseid) : null;
$mapped_rows = $courseid
    ? \local_sentientia_skills\skills_manager::list_course_skills($courseid) : [];

$data = [
    'has_course'      => !empty($selected_course),
    'course'          => $selected_course,
    'mapped_rows'     => $mapped_rows,
    'has_mapped_rows' => !empty($mapped_rows),
    'top_courses'     => $top_courses,
    'has_top_courses' => !empty($top_courses),
    'skill_options'   => $skill_options,
    'has_skills'      => !empty($skill_options),
    'admin_url'       => (new moodle_url('/local/sentientia_skills/admin.php'))->out(false),
    'sesskey'         => sesskey(),
];

echo $OUTPUT->header();
echo $OUTPUT->render_from_template('local_sentientia_skills/course_mapping', $data);
$PAGE->requires->js_call_amd('local_sentientia_skills/skill_actions', 'init',
    [['page' => 'course_mapping', 'courseid' => $courseid]]);
echo $OUTPUT->footer();
