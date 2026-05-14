<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

/**
 * Enrolled users — datatable of all users in a course, with status,
 * completion, and per-row unenrol action.
 *
 * Closes Phase 3 B.2 deferred item.
 *
 * @package local_airpay_courses
 */

require_once(__DIR__ . '/../../config.php');
require_login();

global $DB, $OUTPUT, $PAGE;

$courseid = required_param('id', PARAM_INT);
$course = $DB->get_record('course', ['id' => $courseid], '*', MUST_EXIST);

$ctx = context_system::instance();
$PAGE->set_context($ctx);
$PAGE->set_url(new moodle_url('/local/airpay_courses/enrolledusers.php',
    ['id' => $courseid]));
$PAGE->set_pagelayout('admin');
$PAGE->set_title('Enrolled users — ' . format_string($course->fullname));
$PAGE->set_heading('Enrolled users — ' . format_string($course->fullname));
require_capability('local/airpay_courses:view', $ctx);

$can_enrol = has_capability('local/airpay_courses:enrol', $ctx);

// Counts for KPI strip.
$total_enrolled = (int) $DB->count_records_sql(
    "SELECT COUNT(DISTINCT ue.userid)
       FROM {user_enrolments} ue
       JOIN {enrol} e ON e.id = ue.enrolid
      WHERE e.courseid = :cid", ['cid' => $courseid]);
$total_completed = (int) $DB->count_records_sql(
    "SELECT COUNT(*) FROM {course_completions}
      WHERE course = :cid AND timecompleted IS NOT NULL AND timecompleted > 0",
    ['cid' => $courseid]);
$completion_pct = $total_enrolled > 0
    ? round(100 * $total_completed / $total_enrolled, 1)
    : 0;

$columns = [
    ['key' => 'fullname',         'label' => 'Name',         'sortable' => true,  'sortkey' => 'lastname'],
    ['key' => 'email',            'label' => 'Email',        'sortable' => true,  'sortkey' => 'email'],
    ['key' => 'employee_id',      'label' => 'Emp ID',       'sortable' => false],
    ['key' => 'enrol_method',     'label' => 'Enrolled via', 'sortable' => false],
    ['key' => 'statuslabel',      'label' => 'Status',       'sortable' => false, 'format' => 'badge'],
    ['key' => 'last_access',      'label' => 'Last access',  'sortable' => true,  'sortkey' => 'lastaccess'],
    ['key' => 'completionlabel',  'label' => 'Completion',   'sortable' => true,  'sortkey' => 'completed', 'format' => 'badge'],
    ['key' => 'actions',          'label' => '',             'sortable' => false, 'format' => 'html'],
];

// Phase B0+ — stat_card-compatible tiles.
$kpi_tiles = [
    [
        'label' => 'Total Enrolled',
        'value' => number_format($total_enrolled),
        'icon'  => 'users',
        'color' => 'primary',
    ],
    [
        'label' => 'Completed',
        'value' => number_format($total_completed),
        'icon'  => 'check-circle',
        'color' => 'success',
    ],
    [
        'label' => 'Completion Rate',
        'value' => $completion_pct . '%',
        'icon'  => 'line-chart',
        // Semantic colour by performance band.
        'color' => $completion_pct >= 80 ? 'success' : ($completion_pct >= 50 ? 'warning' : 'danger'),
    ],
];

$data = [
    'courseid'        => (int) $courseid,
    'course_name'     => format_string($course->fullname),
    'course_short'    => format_string($course->shortname),
    'total_enrolled'  => number_format($total_enrolled),
    'total_completed' => number_format($total_completed),
    'completion_pct'  => $completion_pct,
    'kpi_tiles'       => $kpi_tiles,
    'has_kpi_tiles'   => !empty($kpi_tiles),
    'columns_json'    => s(json_encode($columns)),
    'can_enrol'       => $can_enrol,
    'back_url'        => (new moodle_url('/local/airpay_courses/index.php'))->out(false),
    'bulk_enrol_url'  => (new moodle_url('/local/airpay_courses/enrol_csv.php',
        ['preselect_courseid' => $courseid]))->out(false),
    'bulk_unenrol_url' => (new moodle_url('/local/airpay_courses/bulk_unenrol.php',
        ['preselect_courseid' => $courseid]))->out(false),
    'extra_args_json' => json_encode(['courseid' => (int) $courseid]),
];

echo $OUTPUT->header();
echo $OUTPUT->render_from_template('local_airpay_courses/enrolledusers', $data);
echo $OUTPUT->footer();
