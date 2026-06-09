<?php
// Smoke test: mass-enrol cohort into a program.
//
// Run: php public/local/sentientia_programs/cli/smoke_enrol_cohort.php
//
// Exit codes: 0 = pass, non-zero = fail.

define('CLI_SCRIPT', true);
require_once(__DIR__ . '/../../../config.php');

global $DB;

use local_sentientia_programs\program_manager;

// Pick 3 active users with id > 2 (enrol_users filters id<=2).
$users = $DB->get_records_sql(
    "SELECT id FROM {user} WHERE deleted = 0 AND suspended = 0
       AND id > 2 AND username NOT IN ('admin', 'guest')
       ORDER BY id ASC LIMIT 3");
if (count($users) < 3) {
    fwrite(STDERR, "FAIL: need 3 users with id>2.\n"); exit(1);
}
$user_ids = array_keys($users);

// Create test cohort.
$cohortid = $DB->insert_record('cohort', (object) [
    'contextid'   => 1,
    'name'        => 'Smoke cohort ' . time(),
    'idnumber'    => '',
    'description' => '',
    'descriptionformat' => 1,
    'visible'     => 1,
    'component'   => '',
    'theme'       => '',
    'timecreated' => time(),
    'timemodified' => time(),
]);
foreach ($user_ids as $uid) {
    $DB->insert_record('cohort_members', (object) [
        'cohortid' => $cohortid,
        'userid'   => $uid,
        'timeadded' => time(),
    ]);
}
echo "Cohort id=$cohortid with 3 members\n";

// Create test program.
$pid = program_manager::create((object) [
    'name' => 'Smoke cohort enrol program',
    'description' => '',
    'costcenterid' => 0,
    'open_path' => '',
    'status' => 1,
    'visible' => 1,
    'completion_required' => 1,
]);
echo "Program id=$pid\n";

// First call — all 3 should be newly enrolled.
$r = program_manager::enrol_cohort($pid, $cohortid);
if ($r['cohort_size'] !== 3) {
    fwrite(STDERR, "FAIL: cohort_size " . $r['cohort_size'] . "\n");
    exit(2);
}
if ($r['newly_enrolled'] !== 3) {
    fwrite(STDERR, "FAIL: newly_enrolled "
        . $r['newly_enrolled'] . " (expected 3).\n");
    exit(3);
}
if ($r['already_enrolled'] !== 0) {
    fwrite(STDERR, "FAIL: already_enrolled "
        . $r['already_enrolled'] . " (expected 0).\n");
    exit(4);
}
echo "First enrol: cohort_size=3, new=3, already=0 ✓\n";

// Second call — all 3 should be already-enrolled.
$r2 = program_manager::enrol_cohort($pid, $cohortid);
if ($r2['newly_enrolled'] !== 0) {
    fwrite(STDERR, "FAIL: re-enrol newly_enrolled "
        . $r2['newly_enrolled'] . " (expected 0).\n");
    exit(5);
}
if ($r2['already_enrolled'] !== 3) {
    fwrite(STDERR, "FAIL: re-enrol already_enrolled "
        . $r2['already_enrolled'] . " (expected 3).\n");
    exit(6);
}
echo "Idempotent re-enrol: new=0, already=3 ✓\n";

// Empty-cohort guard — make a new empty cohort.
$emptyid = $DB->insert_record('cohort', (object) [
    'contextid' => 1,
    'name' => 'Smoke empty cohort ' . time(),
    'idnumber' => '', 'description' => '', 'descriptionformat' => 1,
    'visible' => 1, 'component' => '', 'theme' => '',
    'timecreated' => time(), 'timemodified' => time(),
]);
$r3 = program_manager::enrol_cohort($pid, $emptyid);
if ($r3['cohort_size'] !== 0 || $r3['newly_enrolled'] !== 0) {
    fwrite(STDERR, "FAIL: empty cohort produced unexpected values.\n");
    exit(7);
}
echo "Empty cohort: 0 size, 0 new ✓\n";

// Cleanup.
$DB->delete_records('cohort_members', ['cohortid' => $cohortid]);
$DB->delete_records('cohort', ['id' => $cohortid]);
$DB->delete_records('cohort', ['id' => $emptyid]);
program_manager::delete($pid);
echo "Cleanup ✓\n";

echo "\nALL OK ✓\n";
exit(0);
