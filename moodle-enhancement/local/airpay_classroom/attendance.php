<?php
// Airpay Classroom Training (ILT) — per-session attendance page.
//
// Renders the full roster for a session with radio-group status pickers
// (Absent / Present / Late / Excused) and a bulk save button.
//
// @package    local_airpay_classroom
// @copyright  2026 Airpay Payment Services
// @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

require_once(__DIR__ . '/../../config.php');

$sessionid = required_param('sessionid', PARAM_INT);

require_login();

$context = context_system::instance();
require_capability('local/airpay_classroom:view', $context);

$session = $DB->get_record('local_airpay_classroom_sessions',
    ['id' => $sessionid], '*', MUST_EXIST);
$classroom = $DB->get_record('local_airpay_classroom',
    ['id' => $session->classroomid], '*', MUST_EXIST);

// Tenant scope — same logic as view.php.
if (!is_siteadmin()) {
    // ADR-018 Wave 2: viewer + classroom tenant roots via the Sentientia seam.
    $top = \local_sentientia_core\tenant_identity::root_for_current_user();
    if ($top > 0 && !empty($classroom->open_path)) {
        $ctop = \local_sentientia_core\tenant_identity::path_root((string) $classroom->open_path);
        if ($ctop !== $top) {
            throw new \moodle_exception('nopermissions', 'error');
        }
    }
}

$can_attend = has_capability('local/airpay_classroom:attendance', $context);

$page_url = new moodle_url('/local/airpay_classroom/attendance.php',
    ['sessionid' => $sessionid]);
$PAGE->set_context($context);
$PAGE->set_url($page_url);
$session_title = !empty($session->title) ? $session->title :
    'Session on ' . userdate((int) $session->sessiondate, '%d %b %Y');
$PAGE->set_title(get_string('attendance_for_session', 'local_airpay_classroom', $session_title));
$PAGE->set_heading(format_string($classroom->name));
$PAGE->set_pagelayout('standard');
$PAGE->set_secondary_navigation(false);

// Fetch roster + attendance.
$rows_obj = \local_airpay_classroom\session_manager::get_session_attendance($sessionid);

// Build template rows with status flags for radio rendering.
$rows = [];
$counts = ['present' => 0, 'late' => 0, 'excused' => 0, 'absent' => 0];
foreach ($rows_obj as $r) {
    $status = (int) $r->status;
    $fullname = trim(($r->firstname ?? '') . ' ' . ($r->lastname ?? ''));
    if (empty($fullname)) { $fullname = $r->email; }

    $is_present = ($status === \local_airpay_classroom\session_manager::ATT_PRESENT);
    $is_late    = ($status === \local_airpay_classroom\session_manager::ATT_LATE);
    $is_excused = ($status === \local_airpay_classroom\session_manager::ATT_EXCUSED);
    $is_absent  = !$is_present && !$is_late && !$is_excused;

    if ($is_present) { $counts['present']++; }
    elseif ($is_late) { $counts['late']++; }
    elseif ($is_excused) { $counts['excused']++; }
    else { $counts['absent']++; }

    $rows[] = [
        'userid'      => (int) $r->userid,
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
    'classroomid'       => (int) $session->classroomid,
    'classroom_name'    => format_string($classroom->name),
    'session_title'     => format_string($session_title),
    'page_heading'      => get_string('attendance_for_session', 'local_airpay_classroom',
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
    'back_url'          => (new moodle_url('/local/airpay_classroom/view.php',
        ['id' => (int) $session->classroomid, 'tab' => 'sessions']))->out(false),
];

echo $OUTPUT->header();
echo $OUTPUT->render_from_template('local_airpay_classroom/attendance', $data);
echo $OUTPUT->footer();
