<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

/**
 * Smoke test — full request lifecycle on real production DB.
 *
 * Walks: submit → routing → decide approve → user enrolled, AND
 *        submit → decide reject (with note required) → user NOT enrolled, AND
 *        submit → cancel (by requester) → status cancelled.
 *
 * @package local_sentientia_request
 */

define('CLI_SCRIPT', true);
require_once(__DIR__ . '/../../../config.php');
require_once(__DIR__ . '/../lib.php');

global $DB;

echo "=== sentientia_request smoke test ===\n\n";

$test = 0; $pass = 0;
$check = function(string $name, bool $ok, string $detail = '') use (&$test, &$pass) {
    $test++; if ($ok) $pass++;
    printf("  %s [%2d] %s%s\n", $ok ? '✓' : '✗', $test, $name, $detail ? " — $detail" : '');
};

// Pick a real user + course pair where the user is NOT enrolled.
$user = $DB->get_record_sql(
    "SELECT u.id, u.username, u.email, u.open_path FROM {user} u
      WHERE u.deleted = 0 AND u.id > 2 AND u.username = :un",
    ['un' => 'public.uat@airpay.test']);
if (!$user) {
    echo "FAIL: test user public.uat@airpay.test not found.\n";
    exit(1);
}

$course = $DB->get_record_sql(
    "SELECT c.id, c.fullname FROM {course} c
      WHERE c.id > 1 AND c.visible = 1
        AND c.id NOT IN (
            SELECT e.courseid FROM {enrol} e
            JOIN {user_enrolments} ue ON ue.enrolid = e.id
            WHERE ue.userid = :uid)
      ORDER BY c.id LIMIT 1", ['uid' => $user->id]);
echo "Test user: $user->username (id=$user->id)\n";
echo "Test course: $course->fullname (id=$course->id)\n\n";

$admin = $DB->get_record_sql("SELECT id FROM {user} WHERE username='academy@airpay.co.in' LIMIT 1");

// === 1. Submit ===
echo "=== 1. Submit ===\n";

// 1a. Reason too short — should throw
try {
    \local_sentientia_request\request_manager::submit($user->id, $course->id, 'too short');
    $check('Reason < 20 chars rejected', false, 'no exception thrown');
} catch (\moodle_exception $e) {
    $check('Reason < 20 chars rejected', $e->errorcode === 'error_reasonshort');
}

// 1b. Valid submit
$req = \local_sentientia_request\request_manager::submit($user->id, $course->id,
    'I need this course to complete my onboarding compliance training.');
$check('Submit accepted', !empty($req->id), "id={$req->id}");
$check('Status = pending', $req->status === 'pending');
$check('Has approver routed', !empty($req->approver_userid),
    "approver=$req->approver_userid (route=$req->route)");
$check('SLA timedue set in future', $req->timedue > time(),
    'due ' . userdate($req->timedue));

// 1c. Duplicate prevention
try {
    \local_sentientia_request\request_manager::submit($user->id, $course->id,
        'Trying again with a different but still long enough reason text here.');
    $check('Duplicate request blocked', false);
} catch (\moodle_exception $e) {
    $check('Duplicate request blocked', $e->errorcode === 'error_alreadyrequested');
}

// === 2. Decide — approve ===
echo "\n=== 2. Decide: approve ===\n";
$rec = \local_sentientia_request\request_manager::decide(
    (int) $req->id, (int) $admin->id, 'approved', 'Approved for compliance.');
$check('Status = approved', $rec->status === 'approved');
$check('decided_by set', $rec->decided_by_userid == $admin->id);

// Verify enrolment happened
$ctx = \context_course::instance($course->id);
$check('User enrolled in course after approval', is_enrolled($ctx, $user->id));

// 2b. Decide again — should error (invalid state)
try {
    \local_sentientia_request\request_manager::decide(
        (int) $req->id, (int) $admin->id, 'rejected', 'changed mind');
    $check('Decide on non-pending request blocked', false);
} catch (\moodle_exception $e) {
    $check('Decide on non-pending request blocked', $e->errorcode === 'error_invalidstate');
}

// Unenrol so we can test reject next
$manual = enrol_get_plugin('manual');
$inst = $DB->get_record('enrol', ['courseid' => $course->id, 'enrol' => 'manual']);
if ($inst) $manual->unenrol_user($inst, $user->id);

// === 3. Decide — reject ===
echo "\n=== 3. Decide: reject ===\n";
$req2 = \local_sentientia_request\request_manager::submit($user->id, $course->id,
    'Second attempt — please reject this one for the smoke test.');

// 3a. Reject without note — should error
try {
    \local_sentientia_request\request_manager::decide(
        (int) $req2->id, (int) $admin->id, 'rejected', '');
    $check('Reject without note blocked', false);
} catch (\moodle_exception $e) {
    $check('Reject without note blocked', true);
}

// 3b. Reject with note
$rec2 = \local_sentientia_request\request_manager::decide(
    (int) $req2->id, (int) $admin->id, 'rejected', 'Not relevant to your role yet.');
$check('Status = rejected', $rec2->status === 'rejected');
$check('Decision note saved', $rec2->decision_note === 'Not relevant to your role yet.');
$check('User NOT enrolled after rejection', !is_enrolled($ctx, $user->id));

// === 4. Cancel ===
echo "\n=== 4. Cancel ===\n";
$req3 = \local_sentientia_request\request_manager::submit($user->id, $course->id,
    'Third attempt — will cancel before decision is made.');
$ok = \local_sentientia_request\request_manager::cancel((int) $req3->id, (int) $user->id);
$check('Cancel by requester returns true', $ok);
$rec3 = $DB->get_record('local_sentientia_request', ['id' => $req3->id]);
$check('Status = cancelled', $rec3->status === 'cancelled');

// 4b. Someone else can't cancel
$req4 = \local_sentientia_request\request_manager::submit($user->id, $course->id,
    'Fourth attempt — testing other-user cancel rejection.');
try {
    \local_sentientia_request\request_manager::cancel((int) $req4->id, (int) $admin->id);
    $check('Other user cannot cancel', false);
} catch (\moodle_exception $e) {
    $check('Other user cannot cancel', true);
}

// === 5. Pending count ===
echo "\n=== 5. Pending count ===\n";
// req4 is still pending; approver is from routing (likely admin since user has no manager)
$approver_id = (int) $req4->approver_userid;
$pending = \local_sentientia_request\request_manager::pending_count_for_approver($approver_id);
$check('Pending count for approver ≥ 1', $pending >= 1, "got $pending");

// === 6. Escalate ===
echo "\n=== 6. Escalate overdue ===\n";
// Force req4 into manager route + overdue so escalation triggers.
// (Default routing put it on 'admin' because test user has no manager,
//  but the cron handles manager → admin escalation specifically.)
$req4->route = 'manager';
$req4->approver_userid = 99999;  // fake manager id
$req4->timedue = time() - 60;
$req4->timeescalated = null;
$DB->update_record('local_sentientia_request', $req4);

$escalated = \local_sentientia_request\request_manager::escalate_overdue();
$check('Escalation count > 0', $escalated > 0, "$escalated escalated");
$rec4 = $DB->get_record('local_sentientia_request', ['id' => $req4->id]);
$check('Route flipped to admin after escalation', $rec4->route === 'admin');
$check('Approver reassigned to default', (int) $rec4->approver_userid > 0
    && (int) $rec4->approver_userid !== 99999, "approver=$rec4->approver_userid");
$check('timeescalated stamped', !empty($rec4->timeescalated));
$check('timedue extended past now', $rec4->timedue > time());

// Clean up smoke artifacts
$DB->delete_records('local_sentientia_request',
    ['userid' => $user->id, 'courseid' => $course->id]);

echo "\n" . str_repeat('=', 50) . "\n";
echo sprintf("Smoke result: %d/%d cases pass\n", $pass, $test);
echo str_repeat('=', 50) . "\n";
exit($pass === $test ? 0 : 1);
