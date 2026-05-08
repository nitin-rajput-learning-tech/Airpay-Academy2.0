<?php
// Smoke test: ICS calendar builder for classroom sessions.
//
// Run: php public/local/airpay_classroom/cli/smoke_ics.php
//
// Exit codes: 0 = pass, non-zero = fail.

define('CLI_SCRIPT', true);
require_once(__DIR__ . '/../../../config.php');

global $DB;

use local_airpay_classroom\ics_builder;

// Find any existing classroom + session, or seed test data.
$session = $DB->get_record_sql(
    "SELECT * FROM {local_airpay_classroom_sessions} ORDER BY id DESC LIMIT 1");
$classroom = null;
$cleanup_classroom_id = 0;
$cleanup_session_id = 0;

if (!$session) {
    // Seed.
    $cid = $DB->insert_record('local_airpay_classroom', (object) [
        'name'         => 'Smoke ICS classroom',
        'description'  => '',
        'descriptionformat' => 1,
        'location'     => 'Mumbai HQ — Floor 4',
        'capacity'     => 30,
        'mode'         => 'physical',
        'costcenterid' => 0,
        'open_path'    => '',
        'status'       => 1,
        'visible'      => 1,
        'timecreated'  => time(),
        'timemodified' => time(),
    ]);
    $classroom = $DB->get_record('local_airpay_classroom', ['id' => $cid]);
    $sid = $DB->insert_record('local_airpay_classroom_sessions', (object) [
        'classroomid' => $cid,
        'title'       => 'Day 1 — Onboarding',
        'sessiondate' => time() + 86400,
        'starttime'   => time() + 86400,
        'endtime'     => time() + 86400 + 7200,
        'location'    => 'Conference Room A',
        'trainerid'   => 0,
        'notes'       => 'Bring your laptop. We will cover; commas, semicolons, '
            . "and \nnewlines (escaping test).",
        'timecreated' => time(),
        'timemodified' => time(),
    ]);
    $session = $DB->get_record('local_airpay_classroom_sessions', ['id' => $sid]);
    $cleanup_classroom_id = $cid;
    $cleanup_session_id = $sid;
} else {
    $classroom = $DB->get_record('local_airpay_classroom',
        ['id' => $session->classroomid]);
}

echo "Using session id={$session->id} classroom id={$classroom->id}\n";

$ics = ics_builder::build_session($session, $classroom,
    'support@airpay.academy');

// Validate the file structure.
if (strpos($ics, "BEGIN:VCALENDAR\r\n") !== 0) {
    fwrite(STDERR, "FAIL: missing/wrong VCALENDAR header.\n"); exit(1);
}
if (strpos($ics, "VERSION:2.0") === false) {
    fwrite(STDERR, "FAIL: missing VERSION.\n"); exit(2);
}
if (strpos($ics, "BEGIN:VEVENT") === false) {
    fwrite(STDERR, "FAIL: missing VEVENT.\n"); exit(3);
}
if (strpos($ics, "END:VEVENT") === false) {
    fwrite(STDERR, "FAIL: missing END:VEVENT.\n"); exit(4);
}
if (strpos($ics, 'END:VCALENDAR') === false) {
    fwrite(STDERR, "FAIL: missing END:VCALENDAR.\n"); exit(5);
}

// Date format check (RFC 5545: YYYYMMDDTHHMMSSZ).
if (!preg_match('/DTSTART:\d{8}T\d{6}Z/', $ics)) {
    fwrite(STDERR, "FAIL: DTSTART not in UTC format.\n"); exit(6);
}
if (!preg_match('/DTEND:\d{8}T\d{6}Z/', $ics)) {
    fwrite(STDERR, "FAIL: DTEND not in UTC format.\n"); exit(7);
}

// UID check.
if (!preg_match('/UID:airpay-classroom-session-' . (int) $session->id
    . '@airpay.academy/', $ics)) {
    fwrite(STDERR, "FAIL: UID format wrong.\n"); exit(8);
}
echo "VCALENDAR/VEVENT structure ✓ DTSTART/DTEND in UTC ✓ UID ✓\n";

// Escape-test: the session notes have ; , \n in them. Output must escape:
//   ; → \;     , → \,     \n → \n
if (!empty($session->notes)) {
    if (preg_match('/[;,]\s/', $session->notes)) {
        // Original has semi/commas — confirm escaped versions appear.
        $count_semi = substr_count($ics, '\;');
        $count_comma = substr_count($ics, '\,');
        if ($count_semi === 0 && strpos($session->notes, ';') !== false) {
            fwrite(STDERR, "FAIL: semicolon not escaped.\n"); exit(9);
        }
        if ($count_comma === 0 && strpos($session->notes, ',') !== false) {
            fwrite(STDERR, "FAIL: comma not escaped.\n"); exit(10);
        }
        echo "Special-char escaping ✓ (\\; ×$count_semi, \\, ×$count_comma)\n";
    }
}

// Length: should be reasonable, not crazy short.
if (strlen($ics) < 200) {
    fwrite(STDERR, "FAIL: ICS suspiciously short ("
        . strlen($ics) . " bytes).\n");
    exit(11);
}
echo "Total length: " . strlen($ics) . " bytes ✓\n";

// Cleanup if we seeded.
if ($cleanup_session_id > 0) {
    $DB->delete_records('local_airpay_classroom_sessions',
        ['id' => $cleanup_session_id]);
    $DB->delete_records('local_airpay_classroom',
        ['id' => $cleanup_classroom_id]);
    echo "Cleanup ✓\n";
}

echo "\nALL OK ✓\n";
exit(0);
