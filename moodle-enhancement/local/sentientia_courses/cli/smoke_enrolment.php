<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

/**
 * Smoke test for Phase 3 B.2 — native enrolment + unenrolment.
 *
 * @package local_sentientia_courses
 */

define('CLI_SCRIPT', true);
require_once(__DIR__ . '/../../../config.php');

global $DB;

// CLI smokes need to authenticate as an admin so WS validate_context()
// + capability checks pass. The 'academy@airpay.co.in' user is siteadmin.
$admin = $DB->get_record('user', ['username' => 'academy@airpay.co.in']);
\core\session\manager::set_user($admin);

echo "=== Phase 3 B.2 enrolment smoke ===\n\n";

$test = 0; $pass = 0;
$check = function(string $name, bool $ok, string $detail = '') use (&$test, &$pass) {
    $test++; if ($ok) $pass++;
    printf("  %s [%2d] %s%s\n", $ok ? '✓' : '✗', $test, $name, $detail ? " — $detail" : '');
};

$user = $DB->get_record('user', ['username' => 'public.uat@airpay.test']);
$course = $DB->get_record_sql(
    "SELECT c.id, c.fullname FROM {course} c
      WHERE c.id > 1 AND c.visible = 1
        AND c.id NOT IN (
            SELECT e.courseid FROM {enrol} e
            JOIN {user_enrolments} ue ON ue.enrolid = e.id
            WHERE ue.userid = :uid)
      ORDER BY c.id LIMIT 1", ['uid' => $user->id]);

echo "User: $user->username, Course: $course->fullname (id=$course->id)\n\n";

// === 1. enrol_single ===
echo "=== 1. enrol_single ===\n";
$r = \local_sentientia_courses\external\enrol_single::execute(
    (int) $course->id, $user->email);
$check('enrol by email succeeds', $r['success'] && $r['enrolled'],
    "userid={$r['userid']}");

// Idempotent re-enrol
$r2 = \local_sentientia_courses\external\enrol_single::execute(
    (int) $course->id, $user->email);
$check('Re-enrol idempotent (success, enrolled=false)',
    $r2['success'] && !$r2['enrolled'] && $r2['reason'] === 'Already enrolled');

// User not found
$r3 = \local_sentientia_courses\external\enrol_single::execute(
    (int) $course->id, 'nonexistent@nowhere.test');
$check('Unknown user → success=false', !$r3['success'] && !$r3['enrolled']);

// Enrol by employee_id
$user2 = $DB->get_record_sql("SELECT * FROM {user}
    WHERE deleted = 0 AND open_employeeid IS NOT NULL AND open_employeeid != ''
    AND id NOT IN (
      SELECT ue.userid FROM {user_enrolments} ue
      JOIN {enrol} e ON e.id = ue.enrolid WHERE e.courseid = :cid)
    LIMIT 1", ['cid' => $course->id]);
if ($user2) {
    $r_emp = \local_sentientia_courses\external\enrol_single::execute(
        (int) $course->id, $user2->open_employeeid);
    $check('Enrol by employee_id', $r_emp['success'] && $r_emp['enrolled']);
} else {
    $check('Enrol by employee_id (no test user)', true, 'skipped');
}

// === 2. list_course_enrolments ===
echo "\n=== 2. list_course_enrolments ===\n";
$r = \local_sentientia_courses\external\list_course_enrolments::execute(
    '', 'lastname', 'asc', 0, 50, json_encode(['courseid' => $course->id]));
$check('List returns >= 1 row', $r['total'] >= 1, "total={$r['total']}");

$found_our_user = false;
foreach ($r['rows'] as $row) {
    if ($row['userid'] == $user->id) {
        $found_our_user = true;
        $check('Our test user appears in list',  true, "name={$row['fullname']}");
        $check('Status badge shape',
            isset($row['statuslabel']) && isset($row['statuscss']));
        $check('Completion fields present',
            isset($row['completionlabel']) && isset($row['completioncss']));
        $check('Actions HTML for manual enrolment',
            !empty($row['actions']) && str_contains($row['actions'], 'unenrol-user'));
        break;
    }
}
$check('Test user found in enrolment list', $found_our_user);

// Search filter
$r_search = \local_sentientia_courses\external\list_course_enrolments::execute(
    $user->email, 'lastname', 'asc', 0, 50, json_encode(['courseid' => $course->id]));
$check('Search by email returns >= 1', $r_search['total'] >= 1);

// === 3. unenrol_single ===
echo "\n=== 3. unenrol_single ===\n";
$r = \local_sentientia_courses\external\unenrol_single::execute(
    (int) $course->id, (int) $user->id);
$check('unenrol_single succeeds', $r['success'] && $r['unenrolled']);

// Verify gone
$ctx = \context_course::instance($course->id);
$check('User no longer enrolled', !is_enrolled($ctx, $user->id));

// Idempotent un-enrol
$r2 = \local_sentientia_courses\external\unenrol_single::execute(
    (int) $course->id, (int) $user->id);
$check('Re-unenrol idempotent', $r2['success']);

// Cleanup
if ($user2) {
    $instance = $DB->get_record('enrol',
        ['courseid' => $course->id, 'enrol' => 'manual']);
    if ($instance) {
        enrol_get_plugin('manual')->unenrol_user($instance, $user2->id);
    }
}

echo "\n" . str_repeat('=', 50) . "\n";
echo sprintf("Smoke result: %d/%d cases pass\n", $pass, $test);
echo str_repeat('=', 50) . "\n";
exit($pass === $test ? 0 : 1);
