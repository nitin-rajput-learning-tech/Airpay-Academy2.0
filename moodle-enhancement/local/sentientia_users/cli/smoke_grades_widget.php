<?php
// Smoke test: get_grades_summary returns sensible structure even when
// no completions exist + correctly calculates percentages when data is
// available.
//
// Run: php public/local/sentientia_users/cli/smoke_grades_widget.php
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

// 1. With no completions, summary should be {completed:0, has_grade_count:0, average_pct:null}.
$DB->delete_records('course_completions', ['userid' => $userid]);
$res = \local_sentientia_users\user_manager::get_grades_summary($userid);
if ((int) $res['summary']['completed'] !== 0
    || $res['summary']['average_pct'] !== null
    || !empty($res['courses'])) {
    fwrite(STDERR, "FAIL: empty case mismatch: " . print_r($res, true) . "\n");
    exit(2);
}
echo "Empty case ✓\n";

// 2. Seed a completion + grade.
$course = $DB->get_record_sql(
    "SELECT id FROM {course} WHERE id <> :siteid AND visible = 1
       ORDER BY id ASC LIMIT 1",
    ['siteid' => SITEID]);
if (!$course) {
    fwrite(STDERR, "FAIL: no course fixture.\n");
    exit(3);
}
$courseid = (int) $course->id;
$now = time() - 3600;

$DB->insert_record('course_completions', (object) [
    'userid'        => $userid,
    'course'        => $courseid,
    'timeenrolled'  => $now - 86400,
    'timestarted'   => $now - 7200,
    'timecompleted' => $now,
    'reaggregate'   => 0,
]);

// Ensure grade_item exists for the course.
$item = $DB->get_record('grade_items',
    ['courseid' => $courseid, 'itemtype' => 'course']);
if (!$item) {
    fwrite(STDERR, "FAIL: no grade_items row for course (Moodle should auto-create).\n");
    exit(4);
}

// Upsert a grade_grades row for this user/itemid.
$DB->delete_records('grade_grades', ['itemid' => $item->id, 'userid' => $userid]);
$DB->insert_record('grade_grades', (object) [
    'itemid'        => (int) $item->id,
    'userid'        => $userid,
    'finalgrade'    => 85,         // 85 out of 100 = 85%
    'rawgrademax'   => 100,
    'rawgrademin'   => 0,
    'timecreated'   => $now,
    'timemodified'  => $now,
    'aggregationstatus' => 'unknown',
    'aggregationweight' => 0,
]);
// Make sure grademax/min are set on the grade_item — set_field, not
// update_record, so we don't trip the gradebook re-aggregation logic.
$DB->set_field('grade_items', 'grademax', 100,
    ['id' => $item->id]);
$DB->set_field('grade_items', 'grademin', 0,
    ['id' => $item->id]);

$res = \local_sentientia_users\user_manager::get_grades_summary($userid, 6);
if (count($res['courses']) !== 1) {
    fwrite(STDERR, "FAIL: expected 1 course, got " . count($res['courses']) . "\n");
    exit(5);
}
$c = $res['courses'][0];
if ((int) $c['courseid'] !== $courseid) {
    fwrite(STDERR, "FAIL: courseid mismatch.\n");
    exit(6);
}
if ((int) $c['grade_pct'] !== 85) {
    fwrite(STDERR, "FAIL: grade_pct should be 85, got " . var_export($c['grade_pct'], true) . "\n");
    exit(7);
}
if ((int) $res['summary']['completed'] !== 1) {
    fwrite(STDERR, "FAIL: completed count should be 1, got "
        . $res['summary']['completed'] . "\n");
    exit(8);
}
if ((int) $res['summary']['average_pct'] !== 85) {
    fwrite(STDERR, "FAIL: average_pct should be 85, got "
        . var_export($res['summary']['average_pct'], true) . "\n");
    exit(9);
}
echo "1 completion @ 85% ✓ avg=85 ✓\n";

// 3. Cleanup.
$DB->delete_records('course_completions',
    ['userid' => $userid, 'course' => $courseid]);
$DB->delete_records('grade_grades',
    ['itemid' => $item->id, 'userid' => $userid]);
echo "Cleanup ✓\n";

echo "\nALL OK ✓\n";
exit(0);
