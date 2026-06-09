<?php
// Smoke test: prereq enforcement — sequential level unlocking.
//
// Scenario:
//  - Program with 3 levels (A, B, C), all completion_required
//  - Level A has 2 mandatory courses; level B has 1; level C has 1
//  - User has completed both courses in A only
//  → A: completed
//  → B: unlocked (A done) but not completed
//  → C: locked (B not done)
//
// Run: php public/local/sentientia_programs/cli/smoke_prereq.php
//
// Exit codes: 0 = pass, non-zero = fail.

define('CLI_SCRIPT', true);
require_once(__DIR__ . '/../../../config.php');

global $DB;

use local_sentientia_programs\program_manager;

$user = $DB->get_record_sql(
    "SELECT id FROM {user} WHERE deleted = 0 AND suspended = 0
       AND username NOT IN ('admin', 'guest') ORDER BY id ASC LIMIT 1");
if (!$user) { fwrite(STDERR, "FAIL: no user.\n"); exit(1); }
$userid = (int) $user->id;

// Pick 4 visible courses for test data.
$courses = $DB->get_records_sql(
    "SELECT id FROM {course} WHERE id <> :s AND visible = 1
       ORDER BY id ASC LIMIT 4",
    ['s' => SITEID]);
if (count($courses) < 4) {
    fwrite(STDERR, "FAIL: need 4 visible courses.\n"); exit(2);
}
$cids = array_keys($courses);

// Seed program.
$pid = program_manager::create((object) [
    'name' => 'Smoke Prereq Program',
    'description' => '', 'costcenterid' => 0, 'open_path' => '',
    'status' => 1, 'visible' => 1, 'completion_required' => 1,
]);
$lvlA = program_manager::create_level($pid, (object) [
    'name' => 'Level A', 'description' => '', 'sortorder' => 1, 'completion_required' => 1]);
$lvlB = program_manager::create_level($pid, (object) [
    'name' => 'Level B', 'description' => '', 'sortorder' => 2, 'completion_required' => 1]);
$lvlC = program_manager::create_level($pid, (object) [
    'name' => 'Level C', 'description' => '', 'sortorder' => 3, 'completion_required' => 1]);

program_manager::assign_courses_to_level($lvlA, [$cids[0], $cids[1]]);
program_manager::assign_courses_to_level($lvlB, [$cids[2]]);
program_manager::assign_courses_to_level($lvlC, [$cids[3]]);

// Mark A's two courses as completed for the user.
$now = time();
foreach ([$cids[0], $cids[1]] as $cid) {
    $DB->delete_records('course_completions', ['userid' => $userid, 'course' => $cid]);
    $DB->insert_record('course_completions', (object) [
        'userid' => $userid, 'course' => $cid,
        'timeenrolled' => $now - 7200, 'timestarted' => $now - 3600,
        'timecompleted' => $now, 'reaggregate' => 0,
    ]);
}

// Verify.
$state = program_manager::get_user_program_state($pid, $userid);

if ($state['total_levels'] !== 3) {
    fwrite(STDERR, "FAIL: expected 3 levels, got {$state['total_levels']}.\n"); exit(3);
}

$byname = [];
foreach ($state['levels'] as $l) { $byname[$l['name']] = $l; }

if (!$byname['Level A']['completed']) {
    fwrite(STDERR, "FAIL: Level A should be completed.\n"); exit(4);
}
if ($byname['Level A']['locked']) {
    fwrite(STDERR, "FAIL: Level A should NOT be locked.\n"); exit(5);
}
echo "Level A: completed=true, locked=false ✓\n";

if ($byname['Level B']['completed']) {
    fwrite(STDERR, "FAIL: Level B should NOT be completed.\n"); exit(6);
}
if ($byname['Level B']['locked']) {
    fwrite(STDERR, "FAIL: Level B should be unlocked (A is done).\n"); exit(7);
}
echo "Level B: completed=false, locked=false ✓\n";

if ($byname['Level C']['completed']) {
    fwrite(STDERR, "FAIL: Level C should NOT be completed.\n"); exit(8);
}
if (!$byname['Level C']['locked']) {
    fwrite(STDERR, "FAIL: Level C should be locked (B is not done).\n"); exit(9);
}
echo "Level C: completed=false, locked=true ✓\n";

if ($state['overall_pct'] !== 33) {
    fwrite(STDERR, "FAIL: overall_pct should be 33, got {$state['overall_pct']}.\n"); exit(10);
}
echo "overall_pct = 33 ✓ (1/3 levels)\n";

// Cleanup.
foreach ([$cids[0], $cids[1]] as $cid) {
    $DB->delete_records('course_completions',
        ['userid' => $userid, 'course' => $cid]);
}
program_manager::delete($pid);
echo "Cleanup ✓\n";

echo "\nALL OK ✓\n";
exit(0);
