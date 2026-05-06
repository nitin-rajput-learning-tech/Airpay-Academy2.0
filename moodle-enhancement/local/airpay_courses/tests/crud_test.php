<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

namespace local_airpay_courses;

defined('MOODLE_INTERNAL') || die();

/**
 * CRUD tests for airpay_courses (delete + toggle visibility).
 *
 * Locks in:
 * - course_manager::delete() refuses to delete the site course (id <= 1)
 * - course_manager::delete() actually marks the course as deleted
 * - course_manager::toggle_visibility() persists the change
 * - external\delete_course requires local/airpay_courses:delete capability
 * - external\toggle_visibility requires local/airpay_courses:visibility capability
 *
 * @package    local_airpay_courses
 * @category   test
 */
final class crud_test extends \advanced_testcase {

    /**
     * delete() refuses to delete the site course (id=1).
     */
    public function test_delete_site_course_throws(): void {
        $this->resetAfterTest();
        $this->setAdminUser();

        try {
            course_manager::delete(1);
            $this->fail('Expected moodle_exception cannotdeletesitecourse');
        } catch (\moodle_exception $e) {
            $this->assertSame('cannotdeletesitecourse', $e->errorcode);
        }
    }

    /**
     * delete() removes a real course.
     */
    public function test_delete_removes_course(): void {
        $this->resetAfterTest();
        $this->setAdminUser();

        global $DB;
        $course = $this->getDataGenerator()->create_course();
        $this->assertTrue($DB->record_exists('course', ['id' => $course->id]));

        $ok = course_manager::delete($course->id);
        $this->assertTrue($ok);

        // Moodle's delete_course actually removes the row, doesn't soft-delete.
        $this->assertFalse($DB->record_exists('course', ['id' => $course->id]));
    }

    /**
     * delete() on non-existent course id throws (MUST_EXIST in get_record).
     */
    public function test_delete_nonexistent_course_throws(): void {
        $this->resetAfterTest();
        $this->setAdminUser();

        $this->expectException(\dml_missing_record_exception::class);
        course_manager::delete(99999999);
    }

    /**
     * toggle_visibility() flips visibility on a course.
     */
    public function test_toggle_visibility_flips_state(): void {
        $this->resetAfterTest();
        $this->setAdminUser();

        global $DB;
        $course = $this->getDataGenerator()->create_course(['visible' => 1]);

        // Toggle to hidden.
        $newstate = course_manager::toggle_visibility($course->id, false);
        $this->assertFalse($newstate);
        $this->assertEquals(0, (int) $DB->get_field('course', 'visible', ['id' => $course->id]));

        // Toggle back to visible.
        $newstate = course_manager::toggle_visibility($course->id, true);
        $this->assertTrue($newstate);
        $this->assertEquals(1, (int) $DB->get_field('course', 'visible', ['id' => $course->id]));
    }

    /**
     * toggle_visibility() with no explicit state flips current state.
     */
    public function test_toggle_visibility_no_arg_inverts(): void {
        $this->resetAfterTest();
        $this->setAdminUser();

        global $DB;
        $course = $this->getDataGenerator()->create_course(['visible' => 1]);

        $newstate = course_manager::toggle_visibility($course->id);
        $this->assertFalse($newstate);
        $this->assertEquals(0, (int) $DB->get_field('course', 'visible', ['id' => $course->id]));
    }

    /**
     * external\delete_course rejects callers without :delete capability.
     */
    public function test_external_delete_course_capability_required(): void {
        $this->resetAfterTest();

        $course = $this->getDataGenerator()->create_course();
        $u = $this->getDataGenerator()->create_user();   // no extra cap
        $this->setUser($u);

        $this->expectException(\required_capability_exception::class);
        external\delete_course::execute($course->id);
    }

    /**
     * external\toggle_visibility rejects callers without :visibility capability.
     */
    public function test_external_toggle_visibility_capability_required(): void {
        $this->resetAfterTest();

        $course = $this->getDataGenerator()->create_course();
        $u = $this->getDataGenerator()->create_user();
        $this->setUser($u);

        $this->expectException(\required_capability_exception::class);
        external\toggle_visibility::execute($course->id, false);
    }
}
