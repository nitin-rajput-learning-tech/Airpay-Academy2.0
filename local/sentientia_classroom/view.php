<?php
// Airpay Classroom Training (ILT) — classroom detail view.
//
// Sub-tabs: Overview / Sessions / Users.
// Attendance is per-session: clicking a session row goes to attendance.php?sessionid=N.
//
// @package    local_sentientia_classroom
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
require_capability('local/sentientia_classroom:view', $context);

// Load classroom — must exist — and refuse unless it is in the caller's
// tenant (ADR-031). The inline check this replaces skipped itself when the
// viewer had no tenant or the classroom had no path, so either opened any
// classroom by id.
$classroom = \local_sentientia_classroom\session_manager::require_classroom_access($classroomid);

$can_update = is_siteadmin() || has_capability('local/sentientia_classroom:update', $context)
    || has_capability('local/sentientia_classroom:manage', $context);
$can_attend = has_capability('local/sentientia_classroom:attendance', $context);

$page_url = new moodle_url('/local/sentientia_classroom/view.php',
    ['id' => $classroomid, 'tab' => $tab]);
$PAGE->set_context($context);
$PAGE->set_url($page_url);
$PAGE->set_title(get_string('view_classroom_title', 'local_sentientia_classroom', $classroom->name));
$PAGE->set_heading(format_string($classroom->name));
$PAGE->set_pagelayout('standard');
$PAGE->set_secondary_navigation(false);

// Counts for badges + overview.
$session_count  = \local_sentientia_classroom\session_manager::count_sessions($classroomid);
$enrolled_count = \local_sentientia_classroom\session_manager::count_enrolled($classroomid);

// The imported-history readers (training dates, every trainer, linked courses, logo, roster completion)
// are behind sentientia.classroom.import_history, default OFF (ADR-032, classroom code fix 12).
$history = \local_sentientia_classroom\session_manager::history_enabled();

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
if ($history) {
    $users_columns[] = ['key' => 'completion',   'sortable' => false,
        'label' => get_string('roster_completion', 'local_sentientia_classroom')];
    $users_columns[] = ['key' => 'completed_at', 'sortable' => false,
        'label' => get_string('roster_completed_on', 'local_sentientia_classroom')];
    $users_columns[] = ['key' => 'hours',        'sortable' => false,
        'label' => get_string('roster_hours', 'local_sentientia_classroom')];
}

$status_int   = (int) $classroom->status;
$status_label = \local_sentientia_classroom\session_manager::status_label($status_int);
$status_css   = \local_sentientia_classroom\session_manager::status_badge($status_int);

// What the BizLMS import brought in that the overview did not show (flagged, default OFF).
$historydata = [];
if ($history) {
    $fmt = '%d %b %Y';
    $trainers = [];
    foreach (\local_sentientia_classroom\session_manager::get_trainers($classroomid) as $trainer) {
        $trainers[] = ['name' => fullname($trainer)];
    }
    $courses = [];
    foreach (\local_sentientia_classroom\session_manager::get_linked_courses($classroomid) as $course) {
        $courses[] = [
            // Not escaped here: the template's {{ name }} escapes it once.
            'name' => format_string($course->fullname, true, ['escape' => false]),
            'url'  => (new moodle_url('/course/view.php', ['id' => (int) $course->id]))->out(false),
        ];
    }
    $trainingstart = (int) ($classroom->trainingstart ?? 0);
    $trainingend   = (int) ($classroom->trainingend ?? 0);
    $trainingdates = '';
    if ($trainingstart > 0 && $trainingend > 0) {
        $trainingdates = userdate($trainingstart, $fmt) . ' – ' . userdate($trainingend, $fmt);
    } else if ($trainingstart > 0 || $trainingend > 0) {
        $trainingdates = userdate(max($trainingstart, $trainingend), $fmt);
    }
    $logourl = '';
    $logofiles = get_file_storage()->get_area_files($context->id, 'local_sentientia_classroom',
        'classroomlogo', $classroomid, 'id', false);
    if ($logofiles) {
        $logofile = reset($logofiles);
        $logourl = moodle_url::make_pluginfile_url($context->id, 'local_sentientia_classroom',
            'classroomlogo', $classroomid, $logofile->get_filepath(), $logofile->get_filename())->out(false);
    }
    $completedat = (int) ($classroom->timecompleted ?? 0);
    $historydata = [
        'show_history'       => true,
        'training_dates'     => $trainingdates,
        'has_training_dates' => $trainingdates !== '',
        'completed_human'    => $completedat > 0 ? userdate($completedat, $fmt) : '',
        'has_completed'      => $completedat > 0,
        'trainers'           => $trainers,
        'has_trainers'       => !empty($trainers),
        'courses'            => $courses,
        'has_courses'        => !empty($courses),
        'logo_url'           => $logourl,
        'has_logo'           => $logourl !== '',
    ];
}

$data = [
    'classroomid'         => $classroomid,
    'name'                => format_string($classroom->name, true, ['escape' => false]),
    'description'         => format_text($classroom->description ?? '', FORMAT_HTML),
    'has_description'     => !empty(trim((string) ($classroom->description ?? ''))),
    'location'            => format_string($classroom->location ?? '', true, ['escape' => false]),
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
    'back_url'            => (new moodle_url('/local/sentientia_classroom/index.php'))->out(false),

    'tab_overview_active' => $tab === 'overview',
    'tab_sessions_active' => $tab === 'sessions',
    'tab_users_active'    => $tab === 'users',
    'tab_overview_url'    => (new moodle_url('/local/sentientia_classroom/view.php',
        ['id' => $classroomid, 'tab' => 'overview']))->out(false),
    'tab_sessions_url'    => (new moodle_url('/local/sentientia_classroom/view.php',
        ['id' => $classroomid, 'tab' => 'sessions']))->out(false),
    'tab_users_url'       => (new moodle_url('/local/sentientia_classroom/view.php',
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
] + $historydata;

echo $OUTPUT->header();
echo $OUTPUT->render_from_template('local_sentientia_classroom/view', $data);
echo $OUTPUT->footer();
