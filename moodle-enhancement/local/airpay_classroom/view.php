<?php
// Airpay Classroom Training (ILT) — classroom detail view.
//
// Sub-tabs: Overview / Sessions / Users.
// Attendance is per-session: clicking a session row goes to attendance.php?sessionid=N.
//
// @package    local_airpay_classroom
// @copyright  2026 Airpay Payment Services
// @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

require_once(__DIR__ . '/../../config.php');

$classroomid = required_param('id', PARAM_INT);
$tab         = optional_param('tab', 'overview', PARAM_ALPHA);
if (!in_array($tab, ['overview', 'sessions', 'users'], true)) {
    $tab = 'overview';
}

require_login();

$context = context_system::instance();
require_capability('local/airpay_classroom:view', $context);

// Load classroom — must exist.
$classroom = $DB->get_record('local_airpay_classroom', ['id' => $classroomid], '*', MUST_EXIST);

// Tenant scope: non-siteadmin only sees classrooms in their org tree.
if (!is_siteadmin()) {
    // ADR-018 Wave 2: viewer + classroom tenant roots via the Sentientia seam.
    $top = \local_sentientia_core\tenant_identity::root_for_current_user();
    if ($top > 0 && !empty($classroom->open_path)) {
        $ctop = \local_sentientia_core\tenant_identity::path_root((string) $classroom->open_path);
        if ($ctop !== $top) {
            throw new \moodle_exception('nopermissions', 'error', '',
                get_string('view_classroom_title', 'local_airpay_classroom', $classroom->name));
        }
    }
}

$can_update = is_siteadmin() || has_capability('local/airpay_classroom:update', $context)
    || has_capability('local/airpay_classroom:manage', $context);
$can_attend = has_capability('local/airpay_classroom:attendance', $context);

$page_url = new moodle_url('/local/airpay_classroom/view.php',
    ['id' => $classroomid, 'tab' => $tab]);
$PAGE->set_context($context);
$PAGE->set_url($page_url);
$PAGE->set_title(get_string('view_classroom_title', 'local_airpay_classroom', $classroom->name));
$PAGE->set_heading(format_string($classroom->name));
$PAGE->set_pagelayout('standard');
$PAGE->set_secondary_navigation(false);

// Counts for badges + overview.
$session_count  = \local_airpay_classroom\session_manager::count_sessions($classroomid);
$enrolled_count = \local_airpay_classroom\session_manager::count_enrolled($classroomid);

$status_map = [
    \local_airpay_classroom\session_manager::STATUS_CANCELLED => 'Cancelled',
    \local_airpay_classroom\session_manager::STATUS_ACTIVE    => 'Active',
    \local_airpay_classroom\session_manager::STATUS_COMPLETED => 'Completed',
];
$status_css_map = [
    \local_airpay_classroom\session_manager::STATUS_CANCELLED => 'badge-secondary',
    \local_airpay_classroom\session_manager::STATUS_ACTIVE    => 'badge-success',
    \local_airpay_classroom\session_manager::STATUS_COMPLETED => 'badge-info',
];

// Trainer name for overview.
$trainer_name = '';
if (!empty($classroom->trainerid)) {
    $tu = \core_user::get_user((int) $classroom->trainerid);
    if ($tu) {
        $trainer_name = fullname($tu);
    }
}

// Datatable columns for Sessions tab.
$sessions_columns = [
    ['key' => 'title',         'label' => 'Session',      'sortable' => true,  'sortkey' => 'title',       'format' => 'html'],
    ['key' => 'sessiondate',   'label' => 'Date',         'sortable' => true,  'sortkey' => 'sessiondate'],
    ['key' => 'time_range',    'label' => 'Time',         'sortable' => false],
    ['key' => 'duration_min',  'label' => 'Duration (min)', 'sortable' => false],
    ['key' => 'location',      'label' => 'Location',     'sortable' => true,  'sortkey' => 'location'],
];

// Datatable columns for Users tab.
$users_columns = [
    ['key' => 'name',         'label' => 'Name',         'sortable' => true,  'sortkey' => 'lastname',  'format' => 'html'],
    ['key' => 'email',        'label' => 'Email',        'sortable' => true,  'sortkey' => 'email'],
    ['key' => 'employeeid',   'label' => 'Emp ID',       'sortable' => false],
    ['key' => 'designation',  'label' => 'Designation',  'sortable' => false],
    ['key' => 'enrolled_at',  'label' => 'Enrolled',     'sortable' => true,  'sortkey' => 'timecreated'],
];

$status_int   = (int) $classroom->status;
$status_label = $status_map[$status_int] ?? 'Active';
$status_css   = $status_css_map[$status_int] ?? 'badge-success';

$data = [
    'classroomid'         => $classroomid,
    'name'                => format_string($classroom->name),
    'description'         => format_text($classroom->description ?? '', FORMAT_HTML),
    'has_description'     => !empty(trim((string) ($classroom->description ?? ''))),
    'location'            => format_string($classroom->location ?? ''),
    'has_location'        => !empty(trim((string) ($classroom->location ?? ''))),
    'capacity'            => (int) $classroom->capacity,
    'trainer_name'        => $trainer_name,
    'has_trainer'         => !empty($trainer_name),
    'status_label'        => $status_label,
    'status_css'          => $status_css,
    'session_count'       => $session_count,
    'enrolled_count'      => $enrolled_count,
    'created_human'       => $classroom->timecreated  ? userdate((int) $classroom->timecreated,  '%d %b %Y') : '—',
    'modified_human'      => $classroom->timemodified ? userdate((int) $classroom->timemodified, '%d %b %Y') : '—',
    'back_url'            => (new moodle_url('/local/airpay_classroom/index.php'))->out(false),

    'tab_overview_active' => $tab === 'overview',
    'tab_sessions_active' => $tab === 'sessions',
    'tab_users_active'    => $tab === 'users',
    'tab_overview_url'    => (new moodle_url('/local/airpay_classroom/view.php',
        ['id' => $classroomid, 'tab' => 'overview']))->out(false),
    'tab_sessions_url'    => (new moodle_url('/local/airpay_classroom/view.php',
        ['id' => $classroomid, 'tab' => 'sessions']))->out(false),
    'tab_users_url'       => (new moodle_url('/local/airpay_classroom/view.php',
        ['id' => $classroomid, 'tab' => 'users']))->out(false),

    'can_update'          => $can_update,
    'can_attend'          => $can_attend,

    // NOTE: do NOT s()-wrap these — mustache's double-brace `{{ json }}`
    // already HTML-escapes once, the browser unescapes during dataset
    // access. s() here would double-escape and produce invalid JSON.
    // (Lesson learned in G-04 path-view; same fix applies here.)
    'sessions_columns_json' => json_encode($sessions_columns),
    'users_columns_json'    => json_encode($users_columns),
    'extra_args_json'       => json_encode(['classroomid' => $classroomid]),
];

echo $OUTPUT->header();
echo $OUTPUT->render_from_template('local_airpay_classroom/view', $data);
echo $OUTPUT->footer();
