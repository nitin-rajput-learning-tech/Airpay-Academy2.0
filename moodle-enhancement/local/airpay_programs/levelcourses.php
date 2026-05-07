<?php
// Airpay Certification Programs — per-level course management page (G-03).
//
// Standalone sub-page reached from the program view's Levels tab. Lists
// courses assigned to a single level with an "Add Courses" modal and
// per-row unassign action.
//
// @package    local_airpay_programs
// @copyright  2026 Airpay Payment Services
// @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

require_once(__DIR__ . '/../../config.php');

$levelid = required_param('levelid', PARAM_INT);

require_login();

$context = context_system::instance();
require_capability('local/airpay_programs:view', $context);

$level = $DB->get_record('local_airpay_programs_levels',
    ['id' => $levelid], '*', MUST_EXIST);
$program = $DB->get_record('local_airpay_programs',
    ['id' => $level->programid], '*', MUST_EXIST);

// Tenant scope (mirrors view.php).
if (!is_siteadmin()) {
    $parts = explode('/', trim($USER->open_path ?? '', '/'));
    $top = isset($parts[0]) && ctype_digit($parts[0]) ? (int) $parts[0] : 0;
    if ($top > 0 && !empty($program->open_path)) {
        $ppath = trim($program->open_path, '/');
        $pparts = explode('/', $ppath);
        $ptop = isset($pparts[0]) && ctype_digit($pparts[0]) ? (int) $pparts[0] : 0;
        if ($ptop !== $top) {
            throw new \moodle_exception('nopermissions', 'error');
        }
    }
}

$can_update = is_siteadmin() || has_capability('local/airpay_programs:update', $context)
    || has_capability('local/airpay_programs:manage', $context);

$page_url = new moodle_url('/local/airpay_programs/levelcourses.php',
    ['levelid' => $levelid]);
$PAGE->set_context($context);
$PAGE->set_url($page_url);
$PAGE->set_title(get_string('manage_level_courses', 'local_airpay_programs', $level->name));
$PAGE->set_heading(format_string($program->name));
$PAGE->set_pagelayout('standard');
$PAGE->set_secondary_navigation(false);

$course_count = \local_airpay_programs\program_manager::count_level_courses($levelid);

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
    'back_url'            => (new moodle_url('/local/airpay_programs/view.php',
        ['id' => (int) $level->programid, 'tab' => 'levels']))->out(false),
    'page_heading'        => get_string('manage_level_courses', 'local_airpay_programs',
                                         format_string($level->name)),
    'can_update'          => $can_update,

    // Same NO-s() rule for JSON in data attributes (G-02 lesson).
    'courses_columns_json' => json_encode($courses_columns),
    'extra_args_json'      => json_encode(['levelid' => $levelid]),
];

echo $OUTPUT->header();
echo $OUTPUT->render_from_template('local_airpay_programs/levelcourses', $data);
echo $OUTPUT->footer();
