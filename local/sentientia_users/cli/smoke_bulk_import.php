<?php
// Smoke test: bulk-import new users from CSV.
//
// Run: php public/local/sentientia_users/cli/smoke_bulk_import.php
//
// Exit codes: 0 = pass, non-zero = fail.

define('CLI_SCRIPT', true);
require_once(__DIR__ . '/../../../config.php');

global $DB;

use local_sentientia_users\bulk_import_processor;

$admin = get_admin();

// Random suffix to avoid collisions on repeat runs.
$suffix = substr(md5((string) time()), 0, 6);

// CSV: 1 valid, 1 duplicate email (skipped), 1 missing field (failed),
//      1 invalid email (failed), 1 valid with optional fields.
$existing_email = $DB->get_field_sql(
    "SELECT email FROM {user} WHERE deleted=0 AND id > 2 LIMIT 1");

$csv = "email,firstname,lastname,username,designation,department\n"
    . "smokenew_a_$suffix@airpay.test,Smoke,UserA,smokeuser_a_$suffix,Analyst,Compliance\n"
    . "$existing_email,Dup,User,dupuser_$suffix,,\n"
    . ",NoEmail,User,noemail_$suffix,,\n"
    . "not-an-email,Bad,Email,bademail_$suffix,,\n"
    . "smokenew_b_$suffix@airpay.test,Smoke,UserB,smokeuser_b_$suffix,Manager,Operations\n";

$summary = bulk_import_processor::process($csv, (int) $admin->id);

if ($summary['total'] !== 5) {
    fwrite(STDERR, "FAIL: total " . $summary['total'] . " (expected 5).\n");
    exit(1);
}
if (count($summary['succeeded']) !== 2) {
    fwrite(STDERR, "FAIL: succeeded " . count($summary['succeeded'])
        . " (expected 2).\n");
    print_r($summary);
    exit(2);
}
if (count($summary['skipped']) !== 1) {
    fwrite(STDERR, "FAIL: skipped " . count($summary['skipped'])
        . " (expected 1).\n");
    exit(3);
}
if (count($summary['failed']) !== 2) {
    fwrite(STDERR, "FAIL: failed " . count($summary['failed'])
        . " (expected 2 — missing field + invalid email).\n");
    exit(4);
}
echo "Counts: " . count($summary['succeeded']) . " created, "
    . count($summary['skipped']) . " skipped, "
    . count($summary['failed']) . " failed ✓\n";

// Verify the created users actually exist with the expected fields.
$created_a = $DB->get_record('user',
    ['email' => "smokenew_a_$suffix@airpay.test", 'deleted' => 0]);
if (!$created_a) {
    fwrite(STDERR, "FAIL: user A not in DB.\n"); exit(5);
}
if ((string) $created_a->open_designation !== 'Analyst') {
    fwrite(STDERR, "FAIL: open_designation not set ('"
        . $created_a->open_designation . "').\n");
    exit(6);
}
echo "User A created with designation 'Analyst' ✓\n";

// Re-import same CSV → all skipped now (duplicates).
$summary2 = bulk_import_processor::process($csv, (int) $admin->id);
if (count($summary2['succeeded']) !== 0) {
    fwrite(STDERR, "FAIL: re-import should produce 0 created, got "
        . count($summary2['succeeded']) . "\n");
    exit(7);
}
echo "Idempotent re-run: 0 created ✓\n";

// Cleanup.
foreach ($summary['succeeded'] as $s) {
    $DB->set_field('user', 'deleted', 1, ['id' => $s['userid']]);
}
echo "Cleanup ✓\n";

echo "\nALL OK ✓\n";
exit(0);
