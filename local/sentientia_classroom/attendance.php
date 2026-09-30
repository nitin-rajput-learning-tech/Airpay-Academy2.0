<?php
// Airpay Classroom Training (ILT) — per-session attendance page.
//
// Renders the full roster for a session with radio-group status pickers
// (Absent / Present / Late / Excused) and a bulk save button.
//
// @package    local_sentientia_classroom
// @copyright  2026 Airpay Payment Services
// @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

require_once(__DIR__ . '/../../config.php');

$sessionid = required_param('sessionid', PARAM_INT);

require_login();

$context = context_system::instance();
require_capability('local/sentientia_classroom:view', $context);

// Tenant scope (ADR-031) — same guard as view.php; it fails closed for a
// viewer with no tenant and for a classroom with no path. Then the trainer rule (owner
// decision 2026-09-30): without :manage, only the session's or classroom's assigned trainer
// opens the grid (session_manager::may_run_session()).
[$session, $classroom] =
    \local_sentientia_classroom\session_manager::require_attendance_access($sessionid);

$can_attend = has_capability('local/sentientia_classroom:attendance', $context);

// Entry point to the QR page (local_sentientia_pages/qr_attendance.php). Behind a default-OFF
// flag: with it off, this page renders exactly as before. The link only appears for someone
// who could open the QR page anyway (:attendance), and only where that plugin is installed.
$show_qr = $can_attend
    && \core_component::get_component_directory('local_sentientia_pages') !== null
    && \local_sentientia_platform\feature_flags::is_enabled('sentientia.classroom.qr_attendance');

$page_url = new moodle_url('/local/sentientia_classroom/attendance.php',
    ['sessionid' => $sessionid]);
$PAGE->set_context($context);
$PAGE->set_url($page_url);
$session_title = !empty($session->title) ? $session->title :
    'Session on ' . userdate((int) $session->sessiondate, '%d %b %Y');
$PAGE->set_title(get_string('attendance_for_session', 'local_sentientia_classroom', $session_title));
$PAGE->set_heading(format_string($classroom->name));
$PAGE->set_pagelayout('standard');
$PAGE->set_secondary_navigation(false);

// Fetch roster + attendance. ADR-031: a scoped caller sees only their own
// tenant's learners - the roster of an in-tenant classroom can still hold
// others (site-admin, approval-flow or pre-fix enrolments). The grid's Save
// sends a mark only for the rows the trainer set (see below), and only rows of
// this tenant's learners are rendered, so the save stays inside the tenant;
// bulk_mark_attendance skips (never refuses) anything else.
//
// $loadedat is taken BEFORE the rows are read, and travels with the Save: a learner who
// scans the QR code after this moment keeps their mark when the trainer saves an Absent for
// them from a grid that was loaded before the scan (session_manager::bulk_mark_attendance()).
// The Save's answer also lists every mark made by someone else since $loadedat (newermarks),
// which the grid shows before it moves $loadedat forward to the save time.
//
// A learner with no stored row is shown as Absent (has_mark false), but the grid only sends a
// row the trainer set (amd/src/attendance.js: a status other than Absent, an explicit Absent,
// or a change from the stored mark), so a learner nobody touched keeps no row and can still
// scan. An Absent exists only because the trainer set it.
$loadedat = time();
$rows_obj = \local_sentientia_classroom\session_manager::get_session_attendance($sessionid, true);

// Build template rows with status flags for radio rendering.
$rows = [];
$counts = ['present' => 0, 'late' => 0, 'excused' => 0, 'absent' => 0];
foreach ($rows_obj as $r) {
    $status = (int) $r->status;
    $fullname = trim(($r->firstname ?? '') . ' ' . ($r->lastname ?? ''));
    if (empty($fullname)) { $fullname = $r->email; }

    $is_present = ($status === \local_sentientia_classroom\session_manager::ATT_PRESENT);
    $is_late    = ($status === \local_sentientia_classroom\session_manager::ATT_LATE);
    $is_excused = ($status === \local_sentientia_classroom\session_manager::ATT_EXCUSED);
    $is_absent  = !$is_present && !$is_late && !$is_excused;

    if ($is_present) { $counts['present']++; }
    elseif ($is_late) { $counts['late']++; }
    elseif ($is_excused) { $counts['excused']++; }
    else { $counts['absent']++; }

    $rows[] = [
        'userid'      => (int) $r->userid,
        'status'      => $is_absent ? \local_sentientia_classroom\session_manager::ATT_ABSENT : $status,
        'has_mark'    => !empty($r->has_mark),
        'fullname'    => format_string($fullname),
        'email'       => s($r->email),
        'is_absent'   => $is_absent,
        'is_present'  => $is_present,
        'is_late'     => $is_late,
        'is_excused'  => $is_excused,
        'marked_at'   => $r->marked_at_human ?? '',
    ];
}

$summary = (object) $counts;

$session_time = userdate((int) $session->starttime, '%a, %d %b %Y · %H:%M')
    . ' – ' . userdate((int) $session->endtime, '%H:%M');

$data = [
    'sessionid'         => $sessionid,
    'loadedat'          => $loadedat,
    'classroomid'       => (int) $session->classroomid,
    'classroom_name'    => format_string($classroom->name),
    'session_title'     => format_string($session_title),
    'page_heading'      => get_string('attendance_for_session', 'local_sentientia_classroom',
                                       format_string($session_title)),
    'session_time'      => $session_time,
    'session_location'  => format_string($session->location ?? ''),
    'has_location'      => !empty(trim((string) ($session->location ?? ''))),
    'rows'              => $rows,
    'has_rows'          => !empty($rows),
    'roster_size'       => count($rows),
    'count_present'     => $counts['present'],
    'count_late'        => $counts['late'],
    'count_excused'     => $counts['excused'],
    'count_absent'      => $counts['absent'],
    'can_attend'        => $can_attend,
    'show_qr'           => $show_qr,
    'qr_url'            => (new moodle_url('/local/sentientia_pages/qr_attendance.php',
        ['sessionid' => $sessionid]))->out(false),
    'back_url'          => (new moodle_url('/local/sentientia_classroom/view.php',
        ['id' => (int) $session->classroomid, 'tab' => 'sessions']))->out(false),
];

echo $OUTPUT->header();
echo $OUTPUT->render_from_template('local_sentientia_classroom/attendance', $data);
echo $OUTPUT->footer();
