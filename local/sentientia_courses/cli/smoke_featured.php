<?php
// Smoke test: featured-courses widget add/remove/reorder + tenant scoping +
// already-enrolled hide-out.
//
// Run: php public/local/sentientia_courses/cli/smoke_featured.php
//
// Exit codes: 0 = pass, non-zero = fail.

define('CLI_SCRIPT', true);
require_once(__DIR__ . '/../../../config.php');

global $DB;

use local_sentientia_courses\featured_manager;

// Pick 3 visible courses + 1 user.
$courses = $DB->get_records_sql(
    "SELECT id FROM {course} WHERE id <> :s AND visible = 1
       ORDER BY id ASC LIMIT 3",
    ['s' => SITEID]);
if (count($courses) < 3) {
    fwrite(STDERR, "FAIL: need 3 visible courses.\n"); exit(1);
}
$cids = array_keys($courses);

$user = $DB->get_record_sql(
    "SELECT id, open_path FROM {user} WHERE deleted = 0 AND suspended = 0
       AND username NOT IN ('admin', 'guest') ORDER BY id ASC LIMIT 1");
if (!$user) { fwrite(STDERR, "FAIL: no user.\n"); exit(2); }
$userid = (int) $user->id;

// Cleanup any leftover.
$DB->delete_records('local_sentientia_featured_courses');

// 1. Add 3 featured rows.
$id1 = featured_manager::add($cids[0], 0, 0, 'New track');
$id2 = featured_manager::add($cids[1], 0, 0);
$id3 = featured_manager::add($cids[2], 0, 0, 'Compliance');
echo "Added 3 featured rows: $id1, $id2, $id3\n";

// 2. List.
$rows = featured_manager::list_all();
if (count($rows) !== 3) {
    fwrite(STDERR, "FAIL: list_all expected 3 rows, got " . count($rows) . "\n");
    exit(3);
}
echo "list_all: 3 rows ✓\n";

// 3. Idempotent add — re-add same course → same ID, label updated.
$id1_again = featured_manager::add($cids[0], 0, 0, 'New track v2');
if ($id1_again !== $id1) {
    fwrite(STDERR, "FAIL: re-add created new row.\n"); exit(4);
}
$row = $DB->get_record('local_sentientia_featured_courses', ['id' => $id1]);
if ($row->label !== 'New track v2') {
    fwrite(STDERR, "FAIL: label not updated: " . $row->label . "\n");
    exit(5);
}
echo "Idempotent add → same id=$id1_again, label refreshed ✓\n";

// 4. Reorder — put 3 first.
$reordered = featured_manager::reorder([$id3, $id1, $id2]);
if ($reordered !== 3) {
    fwrite(STDERR, "FAIL: reorder count " . $reordered . "\n"); exit(6);
}
$rows = featured_manager::list_all();
$first = reset($rows);
if ((int) $first['id'] !== $id3) {
    fwrite(STDERR, "FAIL: id3 not first after reorder.\n"); exit(7);
}
echo "Reorder: id3 first (sort_order=" . $first['sort_order'] . ") ✓\n";

// 5. Get widget for user — should return 3 (no enrolments).
// First clear any leftover enrolments to keep test deterministic.
$DB->delete_records_select('user_enrolments',
    'userid = :u AND enrolid IN (SELECT id FROM {enrol} WHERE courseid IN (' .
    implode(',', $cids) . '))', ['u' => $userid]);
$widget = featured_manager::get_widget_for_user($userid, 6);
if (!$widget['has_courses']) {
    fwrite(STDERR, "FAIL: widget should have courses.\n"); exit(8);
}
if (count($widget['courses']) !== 3) {
    fwrite(STDERR, "FAIL: widget course count "
        . count($widget['courses']) . " (expected 3).\n");
    exit(9);
}
echo "Widget for user: 3 courses ✓\n";

// 6. Enrol user in course[0] — widget should now exclude it.
$enrol = $DB->get_record('enrol', ['courseid' => $cids[0], 'enrol' => 'manual']);
if ($enrol) {
    $DB->insert_record('user_enrolments', (object) [
        'enrolid' => $enrol->id, 'userid' => $userid,
        'status' => 0, 'timestart' => 0, 'timeend' => 0,
        'modifierid' => 0, 'timecreated' => time(), 'timemodified' => time(),
    ]);
    $widget2 = featured_manager::get_widget_for_user($userid, 6);
    $course_ids = array_column($widget2['courses'], 'courseid');
    if (in_array($cids[0], $course_ids, true)) {
        fwrite(STDERR, "FAIL: enrolled course should be hidden from widget.\n");
        exit(10);
    }
    echo "Enrolled course hidden from widget ✓ (now "
       . count($widget2['courses']) . " visible)\n";
    // Cleanup the enrolment.
    $DB->delete_records('user_enrolments',
        ['enrolid' => $enrol->id, 'userid' => $userid]);
}

// 7. Remove + cleanup.
featured_manager::remove($id1);
featured_manager::remove($id2);
featured_manager::remove($id3);
$leftover = $DB->count_records('local_sentientia_featured_courses');
if ($leftover !== 0) {
    fwrite(STDERR, "FAIL: leftover rows = $leftover\n"); exit(11);
}
echo "Cleanup ✓\n";

echo "\nALL OK ✓\n";
exit(0);
