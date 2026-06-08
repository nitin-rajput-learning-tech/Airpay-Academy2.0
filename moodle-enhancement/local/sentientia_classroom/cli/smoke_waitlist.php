<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

/**
 * Smoke test for Phase 3 B.4 — classroom waiting list with auto-promote.
 *
 * @package local_sentientia_classroom
 */

define('CLI_SCRIPT', true);
require_once(__DIR__ . '/../../../config.php');

global $DB;

echo "=== sentientia_classroom waitlist smoke ===\n\n";

$test = 0; $pass = 0;
$check = function(string $name, bool $ok, string $detail = '') use (&$test, &$pass) {
    $test++; if ($ok) $pass++;
    printf("  %s [%2d] %s%s\n", $ok ? '✓' : '✗', $test, $name, $detail ? " — $detail" : '');
};

// Pick or create a classroom with low capacity.
$classroom = $DB->get_record_sql(
    "SELECT * FROM {local_sentientia_classroom} ORDER BY id LIMIT 1");
if (!$classroom) {
    // Create a minimal test classroom.
    $classroom = (object) [
        'name'         => 'Smoke test classroom',
        'description'  => 'auto-created for waitlist smoke',
        'costcenterid' => 1,
        'departmentid' => 1,
        'open_path'    => '/1',
        'trainerid'    => 2,
        'location'     => 'Test room',
        'capacity'     => 2,
        'status'       => 1,
        'visible'      => 1,
        'timecreated'  => time(),
        'timemodified' => time(),
    ];
    $classroom->id = $DB->insert_record('local_sentientia_classroom', $classroom);
} else {
    // Ensure capacity = 2 for the test.
    $classroom->capacity = 2;
    $DB->update_record('local_sentientia_classroom', $classroom);
}

echo "Test classroom id=$classroom->id capacity={$classroom->capacity}\n\n";

// Get 4 test users.
$users = $DB->get_records_sql(
    "SELECT id, username FROM {user} WHERE deleted = 0 AND id > 2
      ORDER BY id LIMIT 4");
$user_ids = array_keys($users);
if (count($user_ids) < 4) { echo "FAIL: need 4 users\n"; exit(1); }
[$u1, $u2, $u3, $u4] = $user_ids;

// Clean slate.
$DB->delete_records('local_sentientia_classroom_waitlist', ['classroomid' => $classroom->id]);
$DB->delete_records('local_sentientia_classroom_users',    ['classroomid' => $classroom->id]);

// === 1. Fill capacity (2 users enrolled) ===
echo "=== 1. Fill capacity directly ===\n";
foreach ([$u1, $u2] as $uid) {
    $DB->insert_record('local_sentientia_classroom_users', [
        'classroomid' => $classroom->id, 'userid' => $uid,
        'enrolledby' => 2, 'timecreated' => time(), 'timemodified' => time(),
    ]);
}
$count = $DB->count_records('local_sentientia_classroom_users',
    ['classroomid' => $classroom->id]);
$check('Classroom at full capacity (2)', $count === 2);

// === 2. Two more users join the waiting list ===
echo "\n=== 2. Two users join waitlist ===\n";
$w3 = \local_sentientia_classroom\waitlist_manager::join($classroom->id, $u3);
$check('First waitlister gets position 1', (int) $w3->position === 1,
    "position={$w3->position}");
$w4 = \local_sentientia_classroom\waitlist_manager::join($classroom->id, $u4);
$check('Second waitlister gets position 2', (int) $w4->position === 2);

// 2b. Join again is idempotent
$w3b = \local_sentientia_classroom\waitlist_manager::join($classroom->id, $u3);
$check('Idempotent re-join (same id)', (int) $w3b->id === (int) $w3->id);

// 2c. Cannot join if already enrolled
try {
    \local_sentientia_classroom\waitlist_manager::join($classroom->id, $u1);
    $check('Already-enrolled user blocked from waitlist', false);
} catch (\moodle_exception $e) {
    $check('Already-enrolled user blocked from waitlist', true);
}

// === 3. Unenrol u1 → auto-promote should kick u3 up ===
echo "\n=== 3. Unenrol u1 → auto-promote ===\n";
\local_sentientia_classroom\session_manager::unenrol_user($classroom->id, $u1);
$check('u1 removed from roster',
    !$DB->record_exists('local_sentientia_classroom_users',
        ['classroomid' => $classroom->id, 'userid' => $u1]));
$check('u3 promoted to roster',
    $DB->record_exists('local_sentientia_classroom_users',
        ['classroomid' => $classroom->id, 'userid' => $u3]));

$w3_after = $DB->get_record('local_sentientia_classroom_waitlist', ['id' => $w3->id]);
$check('u3 waitlist status = promoted', $w3_after->status === 'promoted');
$check('u3 promoted_at stamped', !empty($w3_after->promoted_at));

// u4 should now be position 1.
$w4_after = $DB->get_record('local_sentientia_classroom_waitlist', ['id' => $w4->id]);
$check('u4 renumbered to position 1', (int) $w4_after->position === 1,
    "position={$w4_after->position}");

// === 4. Leave waitlist ===
echo "\n=== 4. Leave waitlist ===\n";
$ok = \local_sentientia_classroom\waitlist_manager::leave($w4->id, $u4);
$check('Leave returns true', $ok);
$w4_left = $DB->get_record('local_sentientia_classroom_waitlist', ['id' => $w4->id]);
$check('u4 waitlist status = removed', $w4_left->status === 'removed');

// 4b. Other user can't remove
try {
    $w5_id = \local_sentientia_classroom\waitlist_manager::join($classroom->id, $u4)->id;
    \local_sentientia_classroom\waitlist_manager::leave($w5_id, $u1);
    $check('Other user blocked from leaving someone else', false);
} catch (\moodle_exception $e) {
    $check('Other user blocked from leaving someone else', true);
}

// === 5. list_waiting returns shaped output ===
echo "\n=== 5. List waitlist ===\n";
$rows = \local_sentientia_classroom\waitlist_manager::list_waiting($classroom->id);
$check('list_waiting returns rows', count($rows) > 0, count($rows) . ' rows');
$has_position = false;
foreach ($rows as $r) {
    if (isset($r->position) && isset($r->status)) { $has_position = true; break; }
}
$check('Rows have position + status', $has_position);

// === 6. Auto-promote returns 0 when waitlist empty ===
echo "\n=== 6. Auto-promote when waitlist empty ===\n";
$DB->delete_records('local_sentientia_classroom_waitlist', ['classroomid' => $classroom->id]);
$promoted = \local_sentientia_classroom\waitlist_manager::auto_promote($classroom->id);
$check('auto_promote returns 0 when empty', $promoted === 0);

// Cleanup
$DB->delete_records('local_sentientia_classroom_waitlist', ['classroomid' => $classroom->id]);
$DB->delete_records('local_sentientia_classroom_users',    ['classroomid' => $classroom->id]);

echo "\n" . str_repeat('=', 50) . "\n";
echo sprintf("Smoke result: %d/%d cases pass\n", $pass, $test);
echo str_repeat('=', 50) . "\n";
exit($pass === $test ? 0 : 1);
