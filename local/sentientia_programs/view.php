<?php
// Airpay Certification Programs — program detail view (G-03).
//
// Sub-tabs: Overview / Levels / Users.
// Per-level course management is on a separate page: levelcourses.php?levelid=N.
//
// @package    local_sentientia_programs
// @copyright  2026 Airpay Payment Services
// @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

require_once(__DIR__ . '/../../config.php');

$programid = required_param('id', PARAM_INT);
$tab       = optional_param('tab', 'overview', PARAM_ALPHA);
if (!in_array($tab, ['overview', 'levels', 'users'], true)) {
    $tab = 'overview';
}

require_login();

$context = context_system::instance();
require_capability('local/sentientia_programs:view', $context);

$program = $DB->get_record('local_sentientia_programs', ['id' => $programid], '*', MUST_EXIST);

// Tenant scope: non-siteadmin only sees programs in their org tree.
if (!is_siteadmin()) {
    $parts = explode('/', trim($USER->open_path ?? '', '/'));
    $top = isset($parts[0]) && ctype_digit($parts[0]) ? (int) $parts[0] : 0;
    if ($top > 0 && !empty($program->open_path)) {
        $ppath = trim($program->open_path, '/');
        $pparts = explode('/', $ppath);
        $ptop = isset($pparts[0]) && ctype_digit($pparts[0]) ? (int) $pparts[0] : 0;
        if ($ptop !== $top) {
            throw new \moodle_exception('nopermissions', 'error', '',
                get_string('view_program_title', 'local_sentientia_programs', $program->name));
        }
    }
}

$can_update = is_siteadmin() || has_capability('local/sentientia_programs:update', $context)
    || has_capability('local/sentientia_programs:manage', $context);
$can_enrol  = is_siteadmin() || has_capability('local/sentientia_programs:enrol', $context)
    || has_capability('local/sentientia_programs:manage', $context);

$page_url = new moodle_url('/local/sentientia_programs/view.php',
    ['id' => $programid, 'tab' => $tab]);
$PAGE->set_context($context);
$PAGE->set_url($page_url);
$PAGE->set_title(get_string('view_program_title', 'local_sentientia_programs', $program->name));
$PAGE->set_heading(format_string($program->name));
$PAGE->set_pagelayout('standard');
$PAGE->set_secondary_navigation(false);

$level_count    = \local_sentientia_programs\program_manager::count_levels($programid);
$enrolled_count = \local_sentientia_programs\program_manager::count_enrolled($programid);

$status_map = [
    \local_sentientia_programs\program_manager::STATUS_DRAFT    => 'Draft',
    \local_sentientia_programs\program_manager::STATUS_ACTIVE   => 'Active',
    \local_sentientia_programs\program_manager::STATUS_ARCHIVED => 'Archived',
];
$status_css_map = [
    \local_sentientia_programs\program_manager::STATUS_DRAFT    => 'badge-secondary',
    \local_sentientia_programs\program_manager::STATUS_ACTIVE   => 'badge-success',
    \local_sentientia_programs\program_manager::STATUS_ARCHIVED => 'badge-info',
];
// Dark-shade hex for WCAG AA contrast 4.5:1 against white text on small badges.
$status_color_map = [
    \local_sentientia_programs\program_manager::STATUS_DRAFT    => '#5a6070',  // 6.96:1
    \local_sentientia_programs\program_manager::STATUS_ACTIVE   => '#0d7a35',  // 5.07:1
    \local_sentientia_programs\program_manager::STATUS_ARCHIVED => '#0066A7',  // 5.10:1
];

$completion_rule = ((int) $program->completion_required) === 1
    ? 'All levels required (sequential certification)'
    : 'Any level completes the program (parallel certification)';

// Datatable columns.
$levels_columns = [
    ['key' => 'position',     'label' => '#',           'sortable' => true,  'sortkey' => 'sortorder'],
    ['key' => 'name',         'label' => 'Level',       'sortable' => true,  'sortkey' => 'name', 'format' => 'html'],
    ['key' => 'course_count', 'label' => 'Courses',     'sortable' => false],
    ['key' => 'required',     'label' => 'Completion',  'sortable' => false, 'format' => 'html'],
];

$users_columns = [
    ['key' => 'name',         'label' => 'Name',        'sortable' => true,  'sortkey' => 'lastname',  'format' => 'html'],
    ['key' => 'email',        'label' => 'Email',       'sortable' => true,  'sortkey' => 'email'],
    ['key' => 'employeeid',   'label' => 'Emp ID',      'sortable' => false],
    ['key' => 'designation',  'label' => 'Designation', 'sortable' => false],
    ['key' => 'enrolled_at',  'label' => 'Enrolled',    'sortable' => true,  'sortkey' => 'timecreated'],
    ['key' => 'statuslabel',  'label' => 'Status',      'sortable' => true,  'sortkey' => 'status', 'format' => 'badge'],
];

$status_int   = (int) $program->status;
$status_label = $status_map[$status_int] ?? 'Draft';
$status_css   = $status_css_map[$status_int] ?? 'badge-secondary';
$status_color = $status_color_map[$status_int] ?? '#5a6070';

// Phase F.1 (2026-05-08) — learner program state with prereq enforcement.
$user_state = null;
$user_enrolled = $DB->record_exists('local_sentientia_programs_users',
    ['programid' => $programid, 'userid' => $USER->id]);
if ($user_enrolled || !$can_update) {
    // For learners + enrolled users, build their progress view so the
    // levels tab can show locked / unlocked / completed state.
    $user_state = \local_sentientia_programs\program_manager::get_user_program_state(
        (int) $programid, (int) $USER->id);
}

$data = [
    'programid'           => $programid,
    'name'                => format_string($program->name),
    'description'         => format_text($program->description ?? '', FORMAT_HTML),
    'has_description'     => !empty(trim((string) ($program->description ?? ''))),
    'completion_rule'     => $completion_rule,
    'status_label'        => $status_label,
    'status_css'          => $status_css,
    'status_color'        => $status_color,
    'level_count'         => $level_count,
    'enrolled_count'      => $enrolled_count,
    'created_human'       => $program->timecreated  ? userdate((int) $program->timecreated,  '%d %b %Y') : '—',
    'modified_human'      => $program->timemodified ? userdate((int) $program->timemodified, '%d %b %Y') : '—',
    'back_url'            => (new moodle_url('/local/sentientia_programs/index.php'))->out(false),
    'has_user_state'      => !empty($user_state) && !empty($user_state['levels']),
    'user_state'          => $user_state,
    'user_enrolled'       => $user_enrolled,

    'tab_overview_active' => $tab === 'overview',
    'tab_levels_active'   => $tab === 'levels',
    'tab_users_active'    => $tab === 'users',
    'tab_overview_url'    => (new moodle_url('/local/sentientia_programs/view.php',
        ['id' => $programid, 'tab' => 'overview']))->out(false),
    'tab_levels_url'      => (new moodle_url('/local/sentientia_programs/view.php',
        ['id' => $programid, 'tab' => 'levels']))->out(false),
    'tab_users_url'       => (new moodle_url('/local/sentientia_programs/view.php',
        ['id' => $programid, 'tab' => 'users']))->out(false),

    'can_update'          => $can_update,
    'can_enrol'           => $can_enrol,

    // NOTE: do NOT s()-wrap these — mustache's `{{ }}` already HTML-escapes.
    // s() here would double-escape, breaking JSON.parse on the dataset access.
    // (Lesson re-confirmed in G-02 classroom view.)
    'levels_columns_json' => json_encode($levels_columns),
    'users_columns_json'  => json_encode($users_columns),
    'extra_args_json'     => json_encode(['programid' => $programid]),
];

echo $OUTPUT->header();
echo $OUTPUT->render_from_template('local_sentientia_programs/view', $data);
echo $OUTPUT->footer();
