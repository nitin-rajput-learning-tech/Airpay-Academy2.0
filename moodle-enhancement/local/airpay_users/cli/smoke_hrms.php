<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
//
// W1-6 (2026-05-16) — CLI smoke test for the HRMS bulk importer.
//
// Usage:  php local/airpay_users/cli/smoke_hrms.php
//
// Generates a 5-row fixture CSV in memory, runs it through hrms_importer,
// then prints the run summary + cleans up the test users so the run is
// idempotent.

define('CLI_SCRIPT', true);
require(__DIR__ . '/../../../config.php');
require_once($CFG->libdir . '/clilib.php');

global $DB, $USER;

cli_writeln('=== HRMS bulk-import smoke test ===');

// Use first available siteadmin (typically academy@airpay.co.in).
$admins = get_admins();
if (empty($admins)) {
    cli_error('No siteadmin found.');
}
$admin = reset($admins);
\core\session\manager::set_user($admin);
$caller_userid = (int) $admin->id;
cli_writeln("Running as: {$admin->email} (id={$caller_userid})");

// Discover a real top-level tenant org to use for company_code.
$tenant = $DB->get_record_sql(
    "SELECT id, shortname FROM {local_airpay_org} WHERE parentid = 0 ORDER BY id ASC",
    [], IGNORE_MULTIPLE);
if (!$tenant) {
    cli_error('No top-level org found in local_airpay_org. Seed at least one.');
}
$company_code = $tenant->shortname;
cli_writeln("Using company_code: $company_code (org id={$tenant->id})");

// Build a 5-row fixture.
$header = implode(',', \local_airpay_users\hrms_importer::STANDARD_COLUMNS);
$rows = [
    // Mike — references Sarah as manager (out of order in CSV).
    "$company_code,smoke_mike,,SMK001,Mr,Mike,Smoketest,M,smoke_mike@airpay.local,"
        . ",,,SMK099,en,Engineer,Permanent,APAC,L3,01-01-1992,15-06-2023,9888777666,Active,Asia/Kolkata,0",
    // Sarah — the manager. No reportingmanager_empid herself.
    "$company_code,smoke_sarah,,SMK099,Ms,Sarah,Manager,F,smoke_sarah@airpay.local,"
        . ",,,,en,Engineering Manager,Permanent,APAC,L5,01-01-1985,01-01-2020,9888777111,Active,Asia/Kolkata,0",
    // Tina — invalid email (mandatory field present but malformed).
    "$company_code,smoke_tina,,SMK002,Ms,Tina,Broken,F,not-an-email,"
        . ",,,,en,Engineer,Permanent,APAC,L3,01-01-1990,01-01-2023,9888777555,Active,Asia/Kolkata,0",
    // Uma — references EMP_NOWHERE as manager (will warn).
    "$company_code,smoke_uma,,SMK003,Ms,Uma,Orphan,F,smoke_uma@airpay.local,"
        . ",,,EMP_NOWHERE,en,Engineer,Contract,APAC,L3,01-01-1991,01-01-2023,9888777444,Active,Asia/Kolkata,0",
    // Vince — inactive.
    "$company_code,smoke_vince,,SMK004,Mr,Vince,Inactive,M,smoke_vince@airpay.local,"
        . ",,,,en,Engineer,Permanent,APAC,L3,01-01-1989,01-01-2023,9888777333,Inactive,Asia/Kolkata,0",
];
$csv = $header . "\n" . implode("\n", $rows) . "\n";

cli_writeln("\n--- Running first import ---");
$run_id = \local_airpay_users\hrms_importer::import_csv($csv, $caller_userid,
    'smoke_test.csv', 'web');
$run = $DB->get_record('local_airpay_users_sync_runs', ['id' => $run_id], '*', MUST_EXIST);

cli_writeln("Run #{$run_id}:");
cli_writeln("  status:    {$run->status}");
cli_writeln("  total:     {$run->totalrows}");
cli_writeln("  inserted:  {$run->insertedcount}  (expect 4 — Tina fails validation)");
cli_writeln("  updated:   {$run->updatedcount}");
cli_writeln("  errors:    {$run->errorcount}     (expect 1 — Tina's bad email)");
cli_writeln("  warnings:  {$run->warningcount}   (expect 1 — Uma's missing manager)");
cli_writeln("  suspended: {$run->suspendedcount} (expect 1 — Vince Inactive)");

// Verify Mike → Sarah manager link.
$mike = $DB->get_record('user', ['email' => 'smoke_mike@airpay.local']);
$sarah = $DB->get_record('user', ['email' => 'smoke_sarah@airpay.local']);
if ($mike && $sarah && (int) $mike->open_supervisorid === (int) $sarah->id) {
    cli_writeln("  PASS 2 link: Mike (id={$mike->id}) → Sarah (id={$sarah->id}) ✓");
} else {
    $sup = $mike ? (int) $mike->open_supervisorid : 'no Mike';
    cli_writeln("  PASS 2 link: FAILED. Mike's supervisor = $sup, expected Sarah's id");
}

// Show every error/warning row.
$errors = $DB->get_records('local_airpay_users_sync_errors', ['runid' => $run_id],
    'severity ASC, csv_line_number ASC');
cli_writeln("\n--- Error/Warning log ---");
foreach ($errors as $e) {
    cli_writeln("  L{$e->csv_line_number} [{$e->severity}] {$e->email}: "
        . substr((string) $e->error_message, 0, 200));
}

cli_writeln("\n--- Re-running same CSV (testing idempotency) ---");
$run_id2 = \local_airpay_users\hrms_importer::import_csv($csv, $caller_userid,
    'smoke_test.csv', 'web');
$run2 = $DB->get_record('local_airpay_users_sync_runs', ['id' => $run_id2]);
cli_writeln("Run #{$run_id2}:  inserted={$run2->insertedcount} (expect 0)"
    . "  updated={$run2->updatedcount} (expect 4)"
    . "  errors={$run2->errorcount}");

// Clean up smoke users so the next run is clean.
cli_writeln("\n--- Cleaning up smoke users ---");
foreach (['smoke_mike', 'smoke_sarah', 'smoke_tina', 'smoke_uma', 'smoke_vince'] as $uname) {
    $u = $DB->get_record('user', ['username' => $uname]);
    if ($u) {
        delete_user($u);
        cli_writeln("  Deleted: $uname (id={$u->id})");
    }
}

cli_writeln("\n=== Smoke test complete ===");
