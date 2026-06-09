<?php
// Smoke test: bulk-CSV status change processor.
//
// Run: php public/local/sentientia_users/cli/smoke_bulk_csv.php
//
// Exit codes: 0 = pass, non-zero = fail.

define('CLI_SCRIPT', true);
require_once(__DIR__ . '/../../../config.php');

global $DB;

use local_sentientia_users\bulk_csv_processor;

// Pick 3 real users to act on.
$users = $DB->get_records_sql(
    "SELECT id, email FROM {user} WHERE deleted = 0 AND suspended = 0
       AND username NOT IN ('admin', 'guest') AND id > 2
       ORDER BY id ASC LIMIT 3");
if (count($users) < 3) {
    fwrite(STDERR, "FAIL: need 3 active users.\n"); exit(1);
}
$user_arr = array_values($users);
echo "Test users: "
    . implode(',', array_column($user_arr, 'id'))
    . "\n";

// Pick a tenant admin for the call (siteadmin).
$admin = get_admin();
\core\session\manager::set_user($admin);

// Build CSV: 1 valid suspend, 1 valid activate, 1 unknown email,
// 1 invalid action, 1 missing field, 1 admin email (skipped).
$admin_email = $DB->get_field('user', 'email', ['id' => 2]);
$csv = "email,action\n"
    . "{$user_arr[0]->email},suspend\n"
    . "{$user_arr[1]->email},suspend\n"
    . "ghost-not-real@nowhere.test,suspend\n"
    . "{$user_arr[2]->email},reboot\n"
    . ",activate\n"
    . "{$admin_email},suspend\n";

$summary = bulk_csv_processor::process($csv, (int) $admin->id);

if ($summary['total'] !== 6) {
    fwrite(STDERR, "FAIL: expected total=6, got {$summary['total']}.\n"); exit(2);
}
if (count($summary['succeeded']) !== 2) {
    fwrite(STDERR, "FAIL: expected 2 succeeded, got "
        . count($summary['succeeded']) . "\n");
    exit(3);
}
if (count($summary['failed']) < 2) {  // missing field + invalid action
    fwrite(STDERR, "FAIL: expected ≥2 failed, got "
        . count($summary['failed']) . "\n");
    exit(4);
}
if (count($summary['skipped']) < 2) {  // ghost + admin
    fwrite(STDERR, "FAIL: expected ≥2 skipped, got "
        . count($summary['skipped']) . "\n");
    exit(5);
}
echo "Total=6, succeeded=" . count($summary['succeeded'])
    . ", skipped=" . count($summary['skipped'])
    . ", failed=" . count($summary['failed']) . " ✓\n";

// Verify suspended in DB.
$row = $DB->get_record('user', ['id' => $user_arr[0]->id], 'id, suspended');
if ((int) $row->suspended !== 1) {
    fwrite(STDERR, "FAIL: user 0 not suspended.\n"); exit(6);
}
echo "User 0 actually suspended ✓\n";

// Re-run the same CSV — should skip the already-suspended ones.
$summary2 = bulk_csv_processor::process($csv, (int) $admin->id);
if (count($summary2['succeeded']) !== 0) {
    fwrite(STDERR, "FAIL: re-run should have 0 succeeded (no state change), got "
        . count($summary2['succeeded']) . "\n");
    exit(7);
}
echo "Idempotent re-run: 0 succeeded ✓\n";

// Cleanup — un-suspend.
foreach ($user_arr as $u) {
    $DB->set_field('user', 'suspended', 0, ['id' => $u->id]);
}
echo "Cleanup ✓\n";

echo "\nALL OK ✓\n";
exit(0);
