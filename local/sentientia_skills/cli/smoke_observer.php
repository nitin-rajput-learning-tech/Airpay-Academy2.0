<?php
// Smoke test: skill-credit observer path.
// Simulates a course completion → asserts user_skill row gets created/updated.
//
// Run: php public/local/sentientia_skills/cli/smoke_observer.php
//
// Exit codes: 0 = pass, non-zero = fail.

define('CLI_SCRIPT', true);
require_once(__DIR__ . '/../../../config.php');

global $DB;

// 1. Get a real visible course + a real skill.
$course = $DB->get_record_sql(
    "SELECT id FROM {course} WHERE id <> :siteid AND visible = 1
       ORDER BY id ASC LIMIT 1",
    ['siteid' => SITEID]);
$skill = $DB->get_record_sql(
    "SELECT id, max_level FROM {local_sentientia_skills} ORDER BY id ASC LIMIT 1");
$user = $DB->get_record_sql(
    "SELECT id FROM {user} WHERE deleted = 0 AND suspended = 0
       AND username NOT IN ('admin', 'guest') ORDER BY id ASC LIMIT 1");

if (!$course || !$skill || !$user) {
    fwrite(STDERR, "FAIL: missing fixture (course/skill/user).\n");
    exit(1);
}
echo "Setup: course={$course->id}, skill={$skill->id}, user={$user->id}\n";

// 2. Make sure the course-skill mapping exists at level 3.
$mapid = \local_sentientia_skills\skills_manager::save_course_skill(
    (int) $course->id, (int) $skill->id, 3);
echo "Course-skill mapping id=$mapid (level 3)\n";

// 3. Wipe any pre-existing user_skill row to keep the test clean.
$DB->delete_records('local_sentientia_user_skills',
    ['userid' => (int) $user->id, 'skillid' => (int) $skill->id]);

// 4. Call update_from_course (this is what the observer does).
\local_sentientia_skills\skills_manager::update_from_course(
    (int) $user->id, (int) $course->id);

$us = $DB->get_record('local_sentientia_user_skills', [
    'userid'  => (int) $user->id,
    'skillid' => (int) $skill->id,
]);
if (!$us) {
    fwrite(STDERR, "FAIL: user_skill row not created.\n");
    exit(2);
}
if ((int) $us->current_level !== 3) {
    fwrite(STDERR, "FAIL: expected level 3, got {$us->current_level}.\n");
    exit(3);
}
if ($us->source !== 'course') {
    fwrite(STDERR, "FAIL: source mismatch (expected 'course', got '{$us->source}').\n");
    exit(4);
}
echo "Initial credit: level 3 ✓\n";

// 5. Mapping at HIGHER level (5) → user_skill should upgrade.
\local_sentientia_skills\skills_manager::save_course_skill(
    (int) $course->id, (int) $skill->id, 5);
\local_sentientia_skills\skills_manager::update_from_course(
    (int) $user->id, (int) $course->id);
$us = $DB->get_record('local_sentientia_user_skills',
    ['userid' => (int) $user->id, 'skillid' => (int) $skill->id]);
if ((int) $us->current_level !== 5) {
    fwrite(STDERR, "FAIL: expected upgrade to 5, got {$us->current_level}.\n");
    exit(5);
}
echo "Upgrade L3 → L5 ✓\n";

// 6. Mapping back to LOWER level (1) → must NOT downgrade.
\local_sentientia_skills\skills_manager::save_course_skill(
    (int) $course->id, (int) $skill->id, 1);
\local_sentientia_skills\skills_manager::update_from_course(
    (int) $user->id, (int) $course->id);
$us = $DB->get_record('local_sentientia_user_skills',
    ['userid' => (int) $user->id, 'skillid' => (int) $skill->id]);
if ((int) $us->current_level !== 5) {
    fwrite(STDERR, "FAIL: downgrade should not happen — got level {$us->current_level}.\n");
    exit(6);
}
echo "No-downgrade guard ✓\n";

// 7. Cleanup.
$DB->delete_records('local_sentientia_user_skills',
    ['userid' => (int) $user->id, 'skillid' => (int) $skill->id]);
$DB->delete_records('local_sentientia_course_skills',
    ['courseid' => (int) $course->id, 'skillid' => (int) $skill->id]);
echo "Cleanup ✓\n";

echo "\nALL OK ✓\n";
exit(0);
