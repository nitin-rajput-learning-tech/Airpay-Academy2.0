<?php
// Smoke test: profile context enrichment for skills tab.
//
// Run: php public/local/sentientia_users/cli/smoke_profile_skills.php
//
// Exit codes: 0 = pass, non-zero = fail.

define('CLI_SCRIPT', true);
require_once(__DIR__ . '/../../../config.php');

global $DB;

$user = $DB->get_record_sql(
    "SELECT id FROM {user} WHERE deleted = 0 AND suspended = 0
       AND username NOT IN ('admin', 'guest') ORDER BY id ASC LIMIT 1");
if (!$user) {
    fwrite(STDERR, "FAIL: no user fixture.\n");
    exit(1);
}
$userid = (int) $user->id;
$designation = 'SmokeProfileSkills';
$DB->set_field('user', 'open_designation', $designation, ['id' => $userid]);

// Create a skill + level + required mapping.
$skills = $DB->get_records_sql(
    "SELECT id, max_level FROM {local_sentientia_skills} ORDER BY id ASC LIMIT 2");
if (count($skills) < 2) {
    fwrite(STDERR, "FAIL: need at least 2 skills in fixtures.\n");
    exit(2);
}
$skill_ids = array_keys($skills);
foreach ($skills as $s) {
    \local_sentientia_skills\skills_manager::save_designation_skill(
        $designation, (int) $s->id, min(3, (int) $s->max_level));
}

// User has level 1 in the first skill (gap of 2 to required 3).
$DB->delete_records('local_sentientia_user_skills',
    ['userid' => $userid, 'skillid' => $skill_ids[0]]);
$DB->insert_record('local_sentientia_user_skills', (object) [
    'userid' => $userid, 'skillid' => $skill_ids[0],
    'current_level' => 1, 'source' => 'manual',
    'timecreated' => time(), 'timemodified' => time(),
]);

$ctx = \local_sentientia_users\user_manager::build_profile_context($userid);

if (empty($ctx['ap_has_skills'])) {
    fwrite(STDERR, "FAIL: ap_has_skills not set.\n");
    exit(3);
}
if (count($ctx['ap_skills_rows']) !== 2) {
    fwrite(STDERR, "FAIL: expected 2 rows, got " . count($ctx['ap_skills_rows']) . ".\n");
    exit(4);
}
if (empty($ctx['ap_has_radar']) || empty($ctx['ap_skills_radar']['radar_labels'])) {
    fwrite(STDERR, "FAIL: radar data missing.\n");
    exit(5);
}
echo "ap_has_skills ✓, " . count($ctx['ap_skills_rows']) . " rows, "
   . "designation='{$ctx['ap_skills_designation']}' ✓\n";

// Verify radar JSON has matching label/data lengths.
$labels = json_decode($ctx['ap_skills_radar']['radar_labels'], true);
$current = json_decode($ctx['ap_skills_radar']['radar_current'], true);
$required = json_decode($ctx['ap_skills_radar']['radar_required'], true);
if (count($labels) !== count($current) || count($current) !== count($required)) {
    fwrite(STDERR, "FAIL: radar arrays length mismatch.\n");
    exit(6);
}
echo "radar arrays len=" . count($labels) . " (matched) ✓\n";

// Check the gap math.
$gap_row = null;
foreach ($ctx['ap_skills_rows'] as $r) {
    if ((int) $r['gap'] > 0) { $gap_row = $r; break; }
}
if (!$gap_row) {
    fwrite(STDERR, "FAIL: no gap row (expected at least one).\n");
    exit(7);
}
echo "gap row: {$gap_row['skill_name']} L{$gap_row['current_level']}/L{$gap_row['required_level']}"
   . " gap={$gap_row['gap']} ({$gap_row['status']}) ✓\n";

// Cleanup.
$DB->set_field('user', 'open_designation', '', ['id' => $userid]);
$DB->delete_records('local_sentientia_user_skills',
    ['userid' => $userid, 'skillid' => $skill_ids[0]]);
$DB->delete_records('local_sentientia_role_skills', ['designation' => $designation]);
echo "Cleanup ✓\n";

echo "\nALL OK ✓\n";
exit(0);
