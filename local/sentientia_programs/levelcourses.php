<?php
// Airpay Certification Programs — per-level course management page (G-03).
//
// Standalone sub-page reached from the program view's Levels tab. Lists
// courses assigned to a single level with an "Add Courses" modal and
// per-row unassign action.
//
// @package    local_sentientia_programs
// @copyright  2026 Airpay Payment Services
// @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

require_once(__DIR__ . '/../../config.php');

$levelid = required_param('levelid', PARAM_INT);

require_login();

$context = context_system::instance();
require_capability('local/sentientia_programs:view', $context);

// Tenant scope (ADR-031) — same guard as view.php; it fails closed for a
// viewer with no tenant and for a program with no path.
[$level, $program] = \local_sentientia_programs\program_manager::require_level_access($levelid);

$can_update = is_siteadmin() || has_capability('local/sentientia_programs:update', $context)
    || has_capability('local/sentientia_programs:manage', $context);

$page_url = new moodle_url('/local/sentientia_programs/levelcourses.php',
    ['levelid' => $levelid]);
$PAGE->set_context($context);
$PAGE->set_url($page_url);
$PAGE->set_title(get_string('manage_level_courses', 'local_sentientia_programs', $level->name));
$PAGE->set_heading(format_string($program->name));
$PAGE->set_pagelayout('standard');
$PAGE->set_secondary_navigation(false);

$course_count = \local_sentientia_programs\program_manager::count_level_courses($levelid);

$courses_columns = [
    ['key' => 'position',  'label' => '#',          'sortable' => true,  'sortkey' => 'sortorder'],
    ['key' => 'name',      'label' => 'Course',     'sortable' => true,  'sortkey' => 'fullname', 'format' => 'html'],
    ['key' => 'shortname', 'label' => 'Short name', 'sortable' => true,  'sortkey' => 'shortname'],
    ['key' => 'mandatory', 'label' => 'Type',       'sortable' => false, 'format' => 'html'],
];

$data = [
    'levelid'             => $levelid,
    'programid'           => (int) $level->programid,
    'level_name'          => format_string($level->name),
    'level_position'      => (int) $level->sortorder + 1,
    'level_required'      => ((int) $level->completion_required) === 1,
    'program_name'        => format_string($program->name),
    'course_count'        => $course_count,
    'back_url'            => (new moodle_url('/local/sentientia_programs/view.php',
        ['id' => (int) $level->programid, 'tab' => 'levels']))->out(false),
    'page_heading'        => get_string('manage_level_courses', 'local_sentientia_programs',
                                         format_string($level->name)),
    'can_update'          => $can_update,

    // Same NO-s() rule for JSON in data attributes (G-02 lesson).
    'courses_columns_json' => json_encode($courses_columns),
    'extra_args_json'      => json_encode(['levelid' => $levelid]),
];

echo $OUTPUT->header();
echo $OUTPUT->render_from_template('local_sentientia_programs/levelcourses', $data);
echo $OUTPUT->footer();
