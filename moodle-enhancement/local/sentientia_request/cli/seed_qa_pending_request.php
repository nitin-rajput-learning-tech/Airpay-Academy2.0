<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

/**
 * Seed a pending course request from qa_employee, routed to qa_manager.
 *
 * QA fixture for the M2 Playwright walk (manager approves a pending team
 * request). Idempotent — safe to run before every test run:
 *   1. Ensures qa_manager holds the 'manager' role at system context
 *      (carries local/sentientia_request:approve).
 *   2. Ensures qa_employee.open_supervisorid points at qa_manager.
 *   3. Removes any prior QA-fixture requests (matched by the exact
 *      fixture reason marker), so re-runs start clean.
 *   4. Submits a fresh request as qa_employee for a visible course the
 *      user is NOT enrolled in, and verifies it routed to qa_manager
 *      (exercises the WF-018 open_supervisorid routing fix end-to-end).
 *
 * LOCAL/QA INSTANCES ONLY — refuses to run when qa_* accounts are absent.
 *
 * @package local_sentientia_request
 */

define('CLI_SCRIPT', true);
require_once(__DIR__ . '/../../../config.php');

global $DB;

const FIXTURE_REASON = 'QA fixture: Playwright M2 manager-approval walk. Seeded pending request.';

$employee = $DB->get_record('user', ['username' => 'qa_employee', 'deleted' => 0]);
$manager  = $DB->get_record('user', ['username' => 'qa_manager', 'deleted' => 0]);
if (!$employee || !$manager) {
    cli_error('qa_employee / qa_manager not found - this seeder is for QA-provisioned instances only.');
}

// 1. qa_manager must hold the 'manager' role (carries :approve) at system.
$managerrole = $DB->get_record('role', ['shortname' => 'manager'], '*', MUST_EXIST);
$systemctx = context_system::instance();
if (!$DB->record_exists('role_assignments',
        ['roleid' => $managerrole->id, 'userid' => $manager->id, 'contextid' => $systemctx->id])) {
    role_assign($managerrole->id, $manager->id, $systemctx->id);
    echo "role: assigned 'manager' to qa_manager at system context\n";
} else {
    echo "role: qa_manager already holds 'manager'\n";
}

// 2. Supervisor link (BizLMS convention, WF-018).
if ((int) $employee->open_supervisorid !== (int) $manager->id) {
    $DB->set_field('user', 'open_supervisorid', $manager->id, ['id' => $employee->id]);
    echo "org: qa_employee.open_supervisorid -> {$manager->id}\n";
} else {
    echo "org: supervisor link already set\n";
}

// 3. Clear prior fixture rows (exact reason marker only - never touches real
//    data). reason is a TEXT column, so DML equality conditions are not
//    allowed - filter in PHP over the user's own rows instead.
$stale = 0;
foreach ($DB->get_records('local_sentientia_request', ['userid' => $employee->id]) as $row) {
    if ($row->reason === FIXTURE_REASON) {
        $DB->delete_records('local_sentientia_request', ['id' => $row->id]);
        $stale++;
    }
}
if ($stale > 0) {
    echo "cleanup: removed {$stale} prior fixture request(s)\n";
}

// 4. Pick a visible course qa_employee is NOT enrolled in and has no other
//    pending request for, then submit through the real product API.
$course = $DB->get_record_sql(
    "SELECT c.id, c.fullname
       FROM {course} c
      WHERE c.id > 1 AND c.visible = 1
        AND c.id NOT IN (
            SELECT e.courseid FROM {enrol} e
            JOIN {user_enrolments} ue ON ue.enrolid = e.id
            WHERE ue.userid = :uid)
        AND c.id NOT IN (
            SELECT r.courseid FROM {local_sentientia_request} r
            WHERE r.userid = :uid2 AND r.status = 'pending')
   ORDER BY c.id
      LIMIT 1",
    ['uid' => $employee->id, 'uid2' => $employee->id]);
if (!$course) {
    cli_error('No eligible course found (qa_employee enrolled everywhere?)');
}

$request = \local_sentientia_request\request_manager::submit(
    (int) $employee->id, (int) $course->id, FIXTURE_REASON);

echo "seeded: request id={$request->id} course='{$course->fullname}' (id={$course->id})\n";
echo "routed: approver_userid={$request->approver_userid} route={$request->route}\n";

if ((int) $request->approver_userid !== (int) $manager->id) {
    cli_error("ROUTING WRONG: expected qa_manager ({$manager->id}), got {$request->approver_userid}. "
        . 'The WF-018 open_supervisorid fix is not deployed on this instance.');
}

echo "OK: pending request routed to qa_manager - M2 fixture ready.\n";
exit(0);
