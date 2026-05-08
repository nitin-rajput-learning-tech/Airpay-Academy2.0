<?php
// Smoke test: mass-enrol via CSV.
//
// Run: php public/local/airpay_courses/cli/smoke_enrol_csv.php
//
// Exit codes: 0 = pass, non-zero = fail.

define('CLI_SCRIPT', true);
require_once(__DIR__ . '/../../../config.php');

global $DB;

use local_airpay_courses\enrol_csv_processor;

// Pick 3 users + 1 course with manual enrol enabled.
$users = $DB->get_records_sql(
    "SELECT id, email FROM {user} WHERE deleted = 0 AND suspended = 0
       AND id > 2 AND username NOT IN ('admin', 'guest')
       ORDER BY id ASC LIMIT 3");
if (count($users) < 3) {
    fwrite(STDERR, "FAIL: need 3 users with id>2.\n"); exit(1);
}
$user_arr = array_values($users);

$course = $DB->get_record_sql(
    "SELECT c.id, c.shortname FROM {course} c
       JOIN {enrol} e ON e.courseid = c.id
                      AND e.enrol = 'manual' AND e.status = 0
      WHERE c.id <> :s AND c.visible = 1
   ORDER BY c.id ASC LIMIT 1",
    ['s' => SITEID]);
if (!$course) {
    fwrite(STDERR, "FAIL: no visible course with manual enrol.\n"); exit(2);
}
echo "Test course: {$course->shortname}\n";

// Cleanup any existing enrolments.
$enrol = $DB->get_record('enrol',
    ['courseid' => $course->id, 'enrol' => 'manual', 'status' => 0]);
$DB->delete_records_select('user_enrolments',
    'enrolid = :eid AND userid IN (' . implode(',',
        array_column($user_arr, 'id')) . ')',
    ['eid' => $enrol->id]);

// CSV: 1 valid student + 1 valid (default role) + 1 ghost (skipped) +
// 1 invalid course (skipped) + 1 invalid role (failed) + 1 missing field
// (failed).
$admin = get_admin();
$csv = "email,courseshortname,role\n"
    . "{$user_arr[0]->email},{$course->shortname},student\n"
    . "{$user_arr[1]->email},{$course->shortname},\n"
    . "ghost@nowhere.test,{$course->shortname},student\n"
    . "{$user_arr[2]->email},NOT-A-COURSE,student\n"
    . "{$user_arr[2]->email},{$course->shortname},notarole\n"
    . ",{$course->shortname},student\n";

$summary = enrol_csv_processor::process($csv, (int) $admin->id);

echo "Total: {$summary['total']}, succeeded: " . count($summary['succeeded'])
    . ", skipped: " . count($summary['skipped'])
    . ", failed: " . count($summary['failed']) . "\n";

if ($summary['total'] !== 6) {
    fwrite(STDERR, "FAIL: expected total=6.\n"); exit(3);
}
if (count($summary['succeeded']) !== 2) {
    fwrite(STDERR, "FAIL: expected 2 succeeded, got "
        . count($summary['succeeded']) . "\n");
    print_r($summary);
    exit(4);
}
if (count($summary['skipped']) < 2) {
    fwrite(STDERR, "FAIL: expected ≥2 skipped (ghost + bad course).\n");
    exit(5);
}
if (count($summary['failed']) < 2) {
    fwrite(STDERR, "FAIL: expected ≥2 failed (bad role + missing field).\n");
    exit(6);
}
echo "Counts ✓\n";

// Verify enrolment row in DB.
if (!$DB->record_exists('user_enrolments',
    ['enrolid' => $enrol->id, 'userid' => $user_arr[0]->id])) {
    fwrite(STDERR, "FAIL: user[0] not actually enrolled.\n"); exit(7);
}
echo "User[0] enrolled in DB ✓\n";

// Idempotent re-run.
$summary2 = enrol_csv_processor::process($csv, (int) $admin->id);
if (count($summary2['succeeded']) !== 0) {
    fwrite(STDERR, "FAIL: re-run should have 0 succeeded, got "
        . count($summary2['succeeded']) . "\n");
    exit(8);
}
echo "Idempotent re-run: 0 succeeded ✓\n";

// Cleanup.
$DB->delete_records_select('user_enrolments',
    'enrolid = :eid AND userid IN (' . implode(',',
        array_column($user_arr, 'id')) . ')',
    ['eid' => $enrol->id]);
echo "Cleanup ✓\n";

echo "\nALL OK ✓\n";
exit(0);
