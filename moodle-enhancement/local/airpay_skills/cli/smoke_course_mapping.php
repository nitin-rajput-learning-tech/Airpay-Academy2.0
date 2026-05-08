<?php
// Smoke test: save → list → delete round-trip for course-skill mapping.
// Run: php public/local/airpay_skills/cli/smoke_course_mapping.php
//
// Exit codes: 0 = pass, non-zero = fail.

define('CLI_SCRIPT', true);
require_once(__DIR__ . '/../../../config.php');

global $DB;

// Pick a real, visible course + a real skill to round-trip with.
$course = $DB->get_record_sql(
    "SELECT id, fullname FROM {course} WHERE id <> :siteid AND visible = 1
       ORDER BY id ASC LIMIT 1",
    ['siteid' => SITEID]);
if (!$course) {
    fwrite(STDERR, "FAIL: No visible course found.\n");
    exit(1);
}

$skill = $DB->get_record_sql(
    "SELECT id, name, max_level FROM {local_airpay_skills} ORDER BY id ASC LIMIT 1");
if (!$skill) {
    fwrite(STDERR, "FAIL: No skill found in local_airpay_skills.\n");
    exit(1);
}

echo "Round-trip: course={$course->id} ({$course->fullname}) skill={$skill->id} ({$skill->name})\n";

// 1. Pre-state: ensure no existing mapping (if any, will be upserted).
$pre_count = count(\local_airpay_skills\skills_manager::list_course_skills((int) $course->id));
echo "Pre-list count: $pre_count\n";

// 2. Save: upsert mapping at level 2.
$id1 = \local_airpay_skills\skills_manager::save_course_skill(
    (int) $course->id, (int) $skill->id, 2);
echo "Save level=2 → row id $id1\n";

// 3. Verify it's in the list at level 2.
$list = \local_airpay_skills\skills_manager::list_course_skills((int) $course->id);
$match = null;
foreach ($list as $r) {
    if ((int) $r['id'] === (int) $id1) { $match = $r; break; }
}
if (!$match) {
    fwrite(STDERR, "FAIL: Saved row $id1 not in list.\n");
    exit(2);
}
if ((int) $match['teaches_level'] !== 2) {
    fwrite(STDERR, "FAIL: Expected level 2, got {$match['teaches_level']}.\n");
    exit(3);
}
echo "List shows row $id1 at level " . $match['teaches_level'] . " ✓\n";

// 4. Update: re-save same (course, skill) → should upsert to same row, level 4.
$id2 = \local_airpay_skills\skills_manager::save_course_skill(
    (int) $course->id, (int) $skill->id, 4);
if ($id2 !== $id1) {
    fwrite(STDERR, "FAIL: Upsert created new row (id $id2 != $id1).\n");
    exit(4);
}
$list = \local_airpay_skills\skills_manager::list_course_skills((int) $course->id);
$match = null;
foreach ($list as $r) {
    if ((int) $r['id'] === (int) $id2) { $match = $r; break; }
}
if (!$match || (int) $match['teaches_level'] !== 4) {
    fwrite(STDERR, "FAIL: Upsert did not update level to 4.\n");
    exit(5);
}
echo "Upsert level=4 → row $id2 ✓\n";

// 5. Delete.
$ok = \local_airpay_skills\skills_manager::delete_course_skill((int) $id2);
if (!$ok) {
    fwrite(STDERR, "FAIL: Delete returned false.\n");
    exit(6);
}
$list = \local_airpay_skills\skills_manager::list_course_skills((int) $course->id);
foreach ($list as $r) {
    if ((int) $r['id'] === (int) $id2) {
        fwrite(STDERR, "FAIL: Row $id2 still in list after delete.\n");
        exit(7);
    }
}
echo "Delete row $id2 ✓\n";

// 6. Validate level cap.
try {
    \local_airpay_skills\skills_manager::save_course_skill(
        (int) $course->id, (int) $skill->id, (int) $skill->max_level + 1);
    fwrite(STDERR, "FAIL: Save did not throw on out-of-range level.\n");
    exit(8);
} catch (\Throwable $e) {
    echo "Out-of-range guard ✓ ({$e->getMessage()})\n";
}

// 7. search_courses returns results.
$results = \local_airpay_skills\skills_manager::search_courses(
    substr((string) $course->fullname, 0, 3), 5);
if (empty($results)) {
    fwrite(STDERR, "FAIL: search_courses returned 0 rows for partial match.\n");
    exit(9);
}
echo "search_courses returned " . count($results) . " row(s) ✓\n";

echo "\nALL OK ✓\n";
exit(0);
