<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

namespace local_sentientia_learningpath;

defined('MOODLE_INTERNAL') || die();

/**
 * G-04 — tests for path_manager course-assignment and user-enrolment methods.
 *
 * Locks in:
 *   - assign_courses() is idempotent (already-assigned skipped)
 *   - assign_courses() rejects site-course (id=1) and unknown course IDs
 *   - assign_courses() appends to existing sortorder (doesn't reset)
 *   - assign_courses() throws on non-existent path
 *   - unassign_course() returns false when course wasn't on path
 *   - unassign_course() returns true and removes when course was on path
 *   - reorder_courses() updates sortorder atomically; ignores non-member courses
 *   - enrol_users() is idempotent
 *   - enrol_users() rejects deleted, suspended (no), and system users
 *   - enrol_users() throws on non-existent path
 *   - unenrol_user() returns false when user wasn't enrolled
 *   - unenrol_user() returns true and removes when user was enrolled
 *   - get_path_users() returns paginated rows + correct total
 *   - delete() cascades to courses + users tables
 *
 * @package    local_sentientia_learningpath
 * @category   test
 */
final class path_assignment_test extends \advanced_testcase {

    // Vanilla PHPUnit sites lack the BizLMS user/course columns this plugin
    // queries (open_path etc.) - provision them per-test via the shared trait.
    use \local_sentientia_org\test\bizlms_fixture;

    protected function setUp(): void {
        parent::setUp();
        $this->ensure_bizlms_schema();
    }

    private function seed_path(string $name = 'Test Path'): int {
        global $DB;
        $now = time();
        return (int) $DB->insert_record('local_sentientia_learningpath', (object) [
            'name'         => $name,
            'description'  => '',
            'costcenterid' => 0,
            'open_path'    => '/1',
            'status'       => 1,
            'visible'      => 1,
            'timecreated'  => $now,
            'timemodified' => $now,
        ]);
    }

    // ════════════════════════════════════════════════════════════════════
    // assign_courses
    // ════════════════════════════════════════════════════════════════════

    public function test_assign_courses_inserts_rows(): void {
        $this->resetAfterTest();
        $this->setAdminUser();

        global $DB;
        $pid = $this->seed_path();
        $c1 = $this->getDataGenerator()->create_course();
        $c2 = $this->getDataGenerator()->create_course();

        $count = path_manager::assign_courses($pid, [(int) $c1->id, (int) $c2->id]);

        $this->assertSame(2, $count);
        $this->assertEquals(2, $DB->count_records('local_sentientia_learningpath_courses', ['pathid' => $pid]));
    }

    public function test_assign_courses_idempotent(): void {
        $this->resetAfterTest();
        $this->setAdminUser();

        global $DB;
        $pid = $this->seed_path();
        $c1 = $this->getDataGenerator()->create_course();

        path_manager::assign_courses($pid, [(int) $c1->id]);
        $second = path_manager::assign_courses($pid, [(int) $c1->id]);   // re-add

        $this->assertSame(0, $second, 'Re-adding same course should report 0 inserted');
        $this->assertEquals(1, $DB->count_records('local_sentientia_learningpath_courses', ['pathid' => $pid]));
    }

    public function test_assign_courses_appends_to_sortorder(): void {
        $this->resetAfterTest();
        $this->setAdminUser();

        global $DB;
        $pid = $this->seed_path();
        $c1 = $this->getDataGenerator()->create_course();
        $c2 = $this->getDataGenerator()->create_course();
        $c3 = $this->getDataGenerator()->create_course();

        path_manager::assign_courses($pid, [(int) $c1->id, (int) $c2->id]);
        path_manager::assign_courses($pid, [(int) $c3->id]);

        // Sort orders should be 0, 1, 2 in insertion order.
        $records = $DB->get_records('local_sentientia_learningpath_courses',
            ['pathid' => $pid], 'sortorder ASC', 'courseid, sortorder');
        $orderings = [];
        foreach ($records as $r) {
            $orderings[(int) $r->courseid] = (int) $r->sortorder;
        }
        $this->assertSame(0, $orderings[$c1->id]);
        $this->assertSame(1, $orderings[$c2->id]);
        $this->assertSame(2, $orderings[$c3->id]);
    }

    public function test_assign_courses_skips_site_course_and_invalid(): void {
        $this->resetAfterTest();
        $this->setAdminUser();

        $pid = $this->seed_path();
        // 1 = site course (rejected); 99999999 = nonexistent (rejected).
        $count = path_manager::assign_courses($pid, [1, 99999999]);
        $this->assertSame(0, $count);
    }

    public function test_assign_courses_throws_on_invalid_path(): void {
        $this->resetAfterTest();
        $this->setAdminUser();

        $this->expectException(\dml_missing_record_exception::class);
        path_manager::assign_courses(99999999, []);
    }

    // ════════════════════════════════════════════════════════════════════
    // unassign_course
    // ════════════════════════════════════════════════════════════════════

    public function test_unassign_course_returns_true_when_present(): void {
        $this->resetAfterTest();
        $this->setAdminUser();

        global $DB;
        $pid = $this->seed_path();
        $c1 = $this->getDataGenerator()->create_course();
        path_manager::assign_courses($pid, [(int) $c1->id]);

        $result = path_manager::unassign_course($pid, (int) $c1->id);
        $this->assertTrue($result);
        $this->assertEquals(0, $DB->count_records('local_sentientia_learningpath_courses', ['pathid' => $pid]));
    }

    public function test_unassign_course_returns_false_when_absent(): void {
        $this->resetAfterTest();
        $this->setAdminUser();

        $pid = $this->seed_path();
        $c1 = $this->getDataGenerator()->create_course();   // never assigned

        $result = path_manager::unassign_course($pid, (int) $c1->id);
        $this->assertFalse($result);
    }

    // ════════════════════════════════════════════════════════════════════
    // reorder_courses
    // ════════════════════════════════════════════════════════════════════

    public function test_reorder_courses_updates_sortorder(): void {
        $this->resetAfterTest();
        $this->setAdminUser();

        global $DB;
        $pid = $this->seed_path();
        $c1 = $this->getDataGenerator()->create_course();
        $c2 = $this->getDataGenerator()->create_course();
        $c3 = $this->getDataGenerator()->create_course();
        path_manager::assign_courses($pid, [(int) $c1->id, (int) $c2->id, (int) $c3->id]);

        // Reverse: c3, c2, c1.
        $updated = path_manager::reorder_courses($pid, [(int) $c3->id, (int) $c2->id, (int) $c1->id]);
        $this->assertGreaterThan(0, $updated);

        $records = $DB->get_records('local_sentientia_learningpath_courses',
            ['pathid' => $pid], '', 'courseid, sortorder');
        $this->assertSame(0, (int) $records[$c3->id]->sortorder);
        $this->assertSame(1, (int) $records[$c2->id]->sortorder);
        $this->assertSame(2, (int) $records[$c1->id]->sortorder);
    }

    public function test_reorder_courses_ignores_non_member_courses(): void {
        $this->resetAfterTest();
        $this->setAdminUser();

        global $DB;
        $pid = $this->seed_path();
        $c1 = $this->getDataGenerator()->create_course();
        $c2 = $this->getDataGenerator()->create_course();
        $c_outsider = $this->getDataGenerator()->create_course();   // never assigned

        path_manager::assign_courses($pid, [(int) $c1->id, (int) $c2->id]);

        // Pass outsider id in the array — should be silently ignored.
        path_manager::reorder_courses($pid, [(int) $c_outsider->id, (int) $c2->id, (int) $c1->id]);

        // Sortorder is 0-based (assign_courses appends from COALESCE(MAX,-1)+1)
        // and the ignored outsider does NOT consume an index — reorder skips it
        // before incrementing, so a garbage id can't create a sortorder gap.
        // c2 → 0, c1 → 1.
        $c2sort = (int) $DB->get_field('local_sentientia_learningpath_courses', 'sortorder',
            ['pathid' => $pid, 'courseid' => $c2->id]);
        $c1sort = (int) $DB->get_field('local_sentientia_learningpath_courses', 'sortorder',
            ['pathid' => $pid, 'courseid' => $c1->id]);
        $this->assertSame(0, $c2sort);
        $this->assertSame(1, $c1sort);
        // Outsider was not added.
        $this->assertFalse($DB->record_exists('local_sentientia_learningpath_courses',
            ['pathid' => $pid, 'courseid' => $c_outsider->id]));
    }

    // ════════════════════════════════════════════════════════════════════
    // enrol_users / unenrol_user
    // ════════════════════════════════════════════════════════════════════

    public function test_enrol_users_inserts_rows(): void {
        $this->resetAfterTest();
        $this->setAdminUser();

        global $DB;
        $pid = $this->seed_path();
        $u1 = $this->getDataGenerator()->create_user();
        $u2 = $this->getDataGenerator()->create_user();

        $count = path_manager::enrol_users($pid, [(int) $u1->id, (int) $u2->id]);

        $this->assertSame(2, $count);
        $this->assertEquals(2, $DB->count_records('local_sentientia_learningpath_users', ['pathid' => $pid]));
        // All start at status NEW.
        $statuses = $DB->get_fieldset_select('local_sentientia_learningpath_users',
            'status', 'pathid = :p', ['p' => $pid]);
        foreach ($statuses as $s) {
            $this->assertSame(path_manager::ENROL_NEW, (int) $s);
        }
    }

    public function test_enrol_users_idempotent(): void {
        $this->resetAfterTest();
        $this->setAdminUser();

        global $DB;
        $pid = $this->seed_path();
        $u = $this->getDataGenerator()->create_user();

        path_manager::enrol_users($pid, [(int) $u->id]);
        $second = path_manager::enrol_users($pid, [(int) $u->id]);

        $this->assertSame(0, $second);
        $this->assertEquals(1, $DB->count_records('local_sentientia_learningpath_users', ['pathid' => $pid]));
    }

    public function test_enrol_users_skips_system_users(): void {
        $this->resetAfterTest();
        $this->setAdminUser();

        $pid = $this->seed_path();
        // 1 = guest, 2 = admin — both rejected (id <= 2).
        $count = path_manager::enrol_users($pid, [1, 2]);
        $this->assertSame(0, $count);
    }

    public function test_enrol_users_skips_deleted_users(): void {
        $this->resetAfterTest();
        $this->setAdminUser();

        global $DB;
        $pid = $this->seed_path();
        $u = $this->getDataGenerator()->create_user(['deleted' => 1]);

        $count = path_manager::enrol_users($pid, [(int) $u->id]);
        $this->assertSame(0, $count);
    }

    public function test_unenrol_user_returns_true_when_enrolled(): void {
        $this->resetAfterTest();
        $this->setAdminUser();

        global $DB;
        $pid = $this->seed_path();
        $u = $this->getDataGenerator()->create_user();
        path_manager::enrol_users($pid, [(int) $u->id]);

        $this->assertTrue(path_manager::unenrol_user($pid, (int) $u->id));
        $this->assertEquals(0, $DB->count_records('local_sentientia_learningpath_users', ['pathid' => $pid]));
    }

    public function test_unenrol_user_returns_false_when_not_enrolled(): void {
        $this->resetAfterTest();
        $this->setAdminUser();

        $pid = $this->seed_path();
        $u = $this->getDataGenerator()->create_user();   // never enrolled

        $this->assertFalse(path_manager::unenrol_user($pid, (int) $u->id));
    }

    // ════════════════════════════════════════════════════════════════════
    // get_path_users (paginated list for the Users tab)
    // ════════════════════════════════════════════════════════════════════

    public function test_get_path_users_returns_total_and_rows(): void {
        $this->resetAfterTest();
        $this->setAdminUser();

        $pid = $this->seed_path();
        $u1 = $this->getDataGenerator()->create_user(['firstname' => 'Alice', 'lastname' => 'Anderson']);
        $u2 = $this->getDataGenerator()->create_user(['firstname' => 'Bob',   'lastname' => 'Brown']);
        $u3 = $this->getDataGenerator()->create_user(['firstname' => 'Carol', 'lastname' => 'Clark']);
        path_manager::enrol_users($pid, [(int) $u1->id, (int) $u2->id, (int) $u3->id]);

        $result = path_manager::get_path_users($pid, '', 0, 25);
        $this->assertSame(3, $result['total']);
        $this->assertCount(3, $result['rows']);

        // Sorted by lastname ASC.
        $this->assertSame((int) $u1->id, (int) $result['rows'][0]->id);
        $this->assertSame((int) $u2->id, (int) $result['rows'][1]->id);
        $this->assertSame((int) $u3->id, (int) $result['rows'][2]->id);
    }

    public function test_get_path_users_search_filters_by_name(): void {
        $this->resetAfterTest();
        $this->setAdminUser();

        $pid = $this->seed_path();
        $u1 = $this->getDataGenerator()->create_user(['firstname' => 'Alice', 'lastname' => 'Anderson']);
        $u2 = $this->getDataGenerator()->create_user(['firstname' => 'Bob',   'lastname' => 'Brown']);
        path_manager::enrol_users($pid, [(int) $u1->id, (int) $u2->id]);

        $result = path_manager::get_path_users($pid, 'Anderson', 0, 25);
        $this->assertSame(1, $result['total']);
        $this->assertSame((int) $u1->id, (int) $result['rows'][0]->id);
    }

    // ════════════════════════════════════════════════════════════════════
    // delete cascade (regression — ensures G-04 changes don't break existing)
    // ════════════════════════════════════════════════════════════════════

    public function test_delete_cascades_to_courses_and_users(): void {
        $this->resetAfterTest();
        $this->setAdminUser();

        global $DB;
        $pid = $this->seed_path();
        $c = $this->getDataGenerator()->create_course();
        $u = $this->getDataGenerator()->create_user();

        path_manager::assign_courses($pid, [(int) $c->id]);
        path_manager::enrol_users($pid, [(int) $u->id]);

        path_manager::delete($pid);

        $this->assertEquals(0, $DB->count_records('local_sentientia_learningpath_courses', ['pathid' => $pid]));
        $this->assertEquals(0, $DB->count_records('local_sentientia_learningpath_users',   ['pathid' => $pid]));
        $this->assertEquals(0, $DB->count_records('local_sentientia_learningpath',         ['id' => $pid]));
    }

    // ════════════════════════════════════════════════════════════════════
    // W1-2 (2026-05-15) — enrol-into-Moodle-courses behaviour.
    //
    // BEFORE W1-2: enrol_users() only wrote to local_sentientia_learningpath_users.
    // Learners assigned to a path could see it in their dashboard but every
    // course on the path was inaccessible. assign_courses() added rows without
    // enrolling existing users in new courses, so courses added late were
    // silently missed.
    //
    // AFTER W1-2: enrol_users() back-fills user_enrolments via the standard
    // `manual` enrol plugin, and assign_courses() does the same for users
    // already on the path.
    // ════════════════════════════════════════════════════════════════════

    public function test_enrol_users_also_enrols_in_path_courses(): void {
        $this->resetAfterTest();
        $this->setAdminUser();

        global $DB;
        $pid = $this->seed_path();
        $c1 = $this->getDataGenerator()->create_course();
        $c2 = $this->getDataGenerator()->create_course();
        $u = $this->getDataGenerator()->create_user();

        path_manager::assign_courses($pid, [(int) $c1->id, (int) $c2->id]);

        // BEFORE THIS CALL the user is not enrolled in either course.
        $this->assertFalse(is_enrolled(\context_course::instance($c1->id), $u, '', true));
        $this->assertFalse(is_enrolled(\context_course::instance($c2->id), $u, '', true));

        $count = path_manager::enrol_users($pid, [(int) $u->id]);
        $this->assertSame(1, $count);

        // AFTER: user must be enrolled in BOTH courses.
        $this->assertTrue(is_enrolled(\context_course::instance($c1->id), $u, '', true),
            'W1-2: enrol_users() must enrol the user in every Moodle course on the path');
        $this->assertTrue(is_enrolled(\context_course::instance($c2->id), $u, '', true),
            'W1-2: enrol_users() must enrol the user in every Moodle course on the path');

        // Path-user row also created — the two are independent storage layers.
        $this->assertEquals(1, $DB->count_records('local_sentientia_learningpath_users', ['pathid' => $pid]));
    }

    public function test_enrol_users_works_when_path_has_no_courses_yet(): void {
        // A path can legitimately have zero courses (admin still in setup mode);
        // enrol_users() must succeed for the path-user side even with nothing to enrol into.
        $this->resetAfterTest();
        $this->setAdminUser();

        global $DB;
        $pid = $this->seed_path();
        $u = $this->getDataGenerator()->create_user();

        $count = path_manager::enrol_users($pid, [(int) $u->id]);

        $this->assertSame(1, $count);
        $this->assertEquals(1, $DB->count_records('local_sentientia_learningpath_users', ['pathid' => $pid]));
    }

    public function test_assign_courses_backfills_existing_users(): void {
        $this->resetAfterTest();
        $this->setAdminUser();

        $pid = $this->seed_path();
        $u1 = $this->getDataGenerator()->create_user();
        $u2 = $this->getDataGenerator()->create_user();

        // Step 1: enrol users while the path has zero courses.
        path_manager::enrol_users($pid, [(int) $u1->id, (int) $u2->id]);

        // Step 2: add a course later. Both users must be back-filled.
        $c_late = $this->getDataGenerator()->create_course();
        path_manager::assign_courses($pid, [(int) $c_late->id]);

        $this->assertTrue(is_enrolled(\context_course::instance($c_late->id), $u1, '', true),
            'W1-2: assign_courses() must back-fill course enrolment for existing path users');
        $this->assertTrue(is_enrolled(\context_course::instance($c_late->id), $u2, '', true),
            'W1-2: assign_courses() must back-fill course enrolment for existing path users');
    }

    public function test_enrol_users_is_idempotent_for_course_enrolments(): void {
        // Calling enrol_users() with a mix of new + already-enrolled users
        // should still leave each user enrolled exactly once in each course
        // (i.e. no duplicate user_enrolments rows).
        $this->resetAfterTest();
        $this->setAdminUser();

        global $DB;
        $pid = $this->seed_path();
        $c = $this->getDataGenerator()->create_course();
        $u = $this->getDataGenerator()->create_user();
        path_manager::assign_courses($pid, [(int) $c->id]);

        path_manager::enrol_users($pid, [(int) $u->id]);          // first time
        path_manager::enrol_users($pid, [(int) $u->id]);          // duplicate

        // Find the manual enrol instance for this course.
        $manualinstance = $DB->get_record('enrol',
            ['courseid' => $c->id, 'enrol' => 'manual'], '*', MUST_EXIST);
        $count = $DB->count_records('user_enrolments',
            ['enrolid' => $manualinstance->id, 'userid' => $u->id]);
        $this->assertSame(1, $count,
            'W1-2: repeated enrol_users() calls must not duplicate user_enrolments rows');
    }

    public function test_enrol_users_survives_course_with_disabled_manual_plugin(): void {
        // If manual enrol is disabled on a particular course, the path-user
        // row should still be created — the rest of the path's courses (if
        // any) still get enrolled. Graceful degradation per audit.
        $this->resetAfterTest();
        $this->setAdminUser();

        global $DB;
        $pid = $this->seed_path();
        $c1 = $this->getDataGenerator()->create_course();
        $c2 = $this->getDataGenerator()->create_course();

        // Disable manual instance on c1.
        $manual_c1 = $DB->get_record('enrol', ['courseid' => $c1->id, 'enrol' => 'manual']);
        if ($manual_c1) {
            $DB->set_field('enrol', 'status', ENROL_INSTANCE_DISABLED, ['id' => $manual_c1->id]);
        }

        $u = $this->getDataGenerator()->create_user();
        path_manager::assign_courses($pid, [(int) $c1->id, (int) $c2->id]);

        $count = path_manager::enrol_users($pid, [(int) $u->id]);

        // Path-user row created despite c1 not accepting the enrol.
        $this->assertSame(1, $count);
        $this->assertEquals(1, $DB->count_records('local_sentientia_learningpath_users', ['pathid' => $pid]));
        // c2 still got enrolled.
        $this->assertTrue(is_enrolled(\context_course::instance($c2->id), $u, '', true));
    }
}
