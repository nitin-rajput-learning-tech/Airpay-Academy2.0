<?php
// Airpay Learning Path — detail view with tabs (Overview, Courses, Users).
//
// @package    local_sentientia_learningpath
// @copyright  2026 Airpay Payment Services
// @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

require_once(__DIR__ . '/../../config.php');
require_login();

$pathid = required_param('id', PARAM_INT);
$tab    = optional_param('tab', 'courses', PARAM_ALPHA);
if (!in_array($tab, ['overview', 'courses', 'users'], true)) {
    $tab = 'courses';
}

$context = context_system::instance();
$PAGE->set_context($context);
require_capability('local/sentientia_learningpath:view', $context);

global $DB, $OUTPUT;

$path = $DB->get_record('local_sentientia_learningpath', ['id' => $pathid], '*', MUST_EXIST);

// Page chrome.
$PAGE->set_url(new moodle_url('/local/sentientia_learningpath/view.php',
    ['id' => $pathid, 'tab' => $tab]));
$PAGE->set_title('Learning path: ' . format_string($path->name));
$PAGE->set_heading('Learning path: ' . format_string($path->name));
$PAGE->set_pagelayout('standard');
$PAGE->set_secondary_navigation(false);

// Permissions for the action buttons.
$can_update = is_siteadmin()
    || has_capability('local/sentientia_learningpath:update', $context)
    || has_capability('local/sentientia_learningpath:manage', $context);
$can_enrol = is_siteadmin()
    || has_capability('local/sentientia_learningpath:enrol', $context)
    || has_capability('local/sentientia_learningpath:manage', $context);

// Datatable column descriptors. Match the keys returned by list_path_courses /
// list_path_users so the shared component can render directly.
$courses_columns = [
    ['key' => 'sortorder',  'label' => '#',          'sortable' => true],
    ['key' => 'name',       'label' => 'Course',     'sortable' => true,  'sortkey' => 'fullname'],
    ['key' => 'shortname',  'label' => 'Shortname',  'sortable' => false],
    ['key' => 'mandatory',  'label' => 'Required',   'sortable' => true,  'format' => 'badge'],
    ['key' => 'visible',    'label' => 'Visibility', 'sortable' => false, 'format' => 'badge'],
    ['key' => 'added',      'label' => 'Added',      'sortable' => false],
];

$users_columns = [
    ['key' => 'fullname',    'label' => 'Name',        'sortable' => false],
    ['key' => 'employeeid',  'label' => 'Employee ID', 'sortable' => false],
    ['key' => 'email',       'label' => 'Email',       'sortable' => false],
    ['key' => 'designation', 'label' => 'Designation', 'sortable' => false],
    ['key' => 'enrolled',    'label' => 'Enrolled',    'sortable' => false],
    ['key' => 'completed',   'label' => 'Completed',   'sortable' => false],
    ['key' => 'statuslabel', 'label' => 'Status',      'sortable' => false, 'format' => 'badge'],
];

$status_label = ((int) $path->status === 1) ? 'Active' : 'Archived';
$status_css   = ((int) $path->status === 1) ? 'badge-success' : 'badge-secondary';

// How many courses + users are on this path right now? (Cheap counts, no joins.)
$course_count = (int) $DB->count_records('local_sentientia_learningpath_courses', ['pathid' => $pathid]);
$user_count   = (int) $DB->count_records('local_sentientia_learningpath_users',   ['pathid' => $pathid]);

$data = [
    'pathid'         => (int) $path->id,
    'name'           => format_string($path->name),
    'description'    => format_text($path->description ?? '', FORMAT_HTML),
    'has_description' => !empty(trim($path->description ?? '')),
    'status_label'   => $status_label,
    'status_css'     => $status_css,
    'created_human'  => userdate($path->timecreated, '%d %b %Y'),
    'modified_human' => userdate($path->timemodified, '%d %b %Y %H:%M'),
    'course_count'   => $course_count,
    'user_count'     => $user_count,
    'back_url'       => (new moodle_url('/local/sentientia_learningpath/index.php'))->out(false),
    'tab_overview_active' => ($tab === 'overview'),
    'tab_courses_active'  => ($tab === 'courses'),
    'tab_users_active'    => ($tab === 'users'),
    'tab_overview_url'    => (new moodle_url('/local/sentientia_learningpath/view.php',
        ['id' => $pathid, 'tab' => 'overview']))->out(false),
    'tab_courses_url'     => (new moodle_url('/local/sentientia_learningpath/view.php',
        ['id' => $pathid, 'tab' => 'courses']))->out(false),
    'tab_users_url'       => (new moodle_url('/local/sentientia_learningpath/view.php',
        ['id' => $pathid, 'tab' => 'users']))->out(false),
    'can_update'           => $can_update,
    'can_enrol'            => $can_enrol,
    'courses_columns_json' => json_encode($courses_columns),
    'users_columns_json'   => json_encode($users_columns),
    // Pre-rendered JSON for the data-extra-args attribute. Building this in
    // PHP rather than inline-templating it sidesteps Mustache HTML-escaping
    // quirks that broke JSON.parse() in the browser.
    'extra_args_json'      => json_encode(['pathid' => (int) $pathid]),
];

echo $OUTPUT->header();
echo $OUTPUT->render_from_template('local_sentientia_learningpath/view', $data);
echo $OUTPUT->footer();
