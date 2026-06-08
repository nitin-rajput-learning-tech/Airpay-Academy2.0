<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
//
// Phase H.1 (2026-05-08) — download .ics calendar invite for a classroom session.

require_once(__DIR__ . '/../../config.php');

require_login();

$sessionid = required_param('sessionid', PARAM_INT);

global $DB, $USER;
$session = $DB->get_record('local_sentientia_classroom_sessions',
    ['id' => $sessionid], '*', MUST_EXIST);
$classroom = $DB->get_record('local_sentientia_classroom',
    ['id' => $session->classroomid], '*', MUST_EXIST);

// Access guard: site admin OR enrolled in the classroom OR has view cap.
$context = context_system::instance();
$is_member = $DB->record_exists('local_sentientia_classroom_users', [
    'classroomid' => $session->classroomid, 'userid' => $USER->id,
]);
$can_view = is_siteadmin() || $is_member
    || has_capability('local/sentientia_classroom:view', $context);
if (!$can_view) {
    throw new \moodle_exception('nopermissions', 'error', '', 'classroom session calendar invite');
}

$organizer = !empty($CFG->supportemail) ? (string) $CFG->supportemail
    : 'noreply@airpay.academy';
$ics = \local_sentientia_classroom\ics_builder::build_session(
    $session, $classroom, $organizer);

$slug = preg_replace('/[^A-Za-z0-9_-]+/', '-',
    strtolower((string) $classroom->name)) ?: 'classroom';
$filename = "session-{$slug}-" . date('Ymd', (int) $session->starttime)
    . '.ics';

header('Content-Type: text/calendar; charset=utf-8; method=PUBLISH');
header('Content-Disposition: attachment; filename="' . $filename . '"');
header('Cache-Control: no-store, no-cache, must-revalidate');
header('Pragma: no-cache');
echo $ics;
exit;
