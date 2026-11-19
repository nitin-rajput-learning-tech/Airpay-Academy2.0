<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

namespace local_sentientia_courses\external;

use core_external\external_api;
use local_sentientia_platform\feature_flags;

defined('MOODLE_INTERNAL') || die();

/**
 * Post-demo plan 2.4 (2026-11-19): the "Open course" and "Advanced settings" row actions on Manage Courses.
 *
 * Locked in here: the flag is registered and defaults OFF; OFF leaves the row exactly as before (no new action,
 * no context columns in the row); ON adds "Open course" (/course/view.php) on every row, and "Advanced settings"
 * (/course/edit.php) only where the viewer holds moodle/course:update in that course and, for a tenant-scoped
 * viewer, never on a legacy course with no open_path (the same rule as the pencil); and the rows still match
 * execute_returns() when the flag is ON (the preloaded ctx* columns do not leak).
 *
 * @package    local_sentientia_courses
 * @category   test
 * @covers     \local_sentientia_courses\external\list_courses
 * @group      local_sentientia_courses
 * @group      tenant_isolation
 */
final class list_courses_advanced_actions_test extends \advanced_testcase {

    use \local_sentientia_org\test\bizlms_fixture;

    protected function setUp(): void {
        parent::setUp();
        $this->resetAfterTest();
        $this->ensure_bizlms_schema();
        feature_flags::invalidate_caches();
    }

    protected function tearDown(): void {
        feature_flags::invalidate_caches();
        parent::tearDown();
    }

    // Helpers.

    private function flag(bool $on): void {
        feature_flags::set(list_courses::FLAG_ADVANCED_ACTIONS, 0, $on, (int) get_admin()->id, 'test');
        feature_flags::invalidate_caches();
    }

    private function course_at(?string $path): \stdClass {
        global $DB;
        $course = $this->getDataGenerator()->create_course();
        $DB->set_field('course', 'open_path', $path, ['id' => $course->id]);
        return $course;
    }

    /**
     * A tenant-scoped viewer at $path holding :view, plus moodle/course:update at system level if asked.
     */
    private function viewer_at(string $path, bool $courseupdate = false): \stdClass {
        global $DB;
        $u = $this->getDataGenerator()->create_user();
        $DB->set_field('user', 'open_path', $path, ['id' => $u->id]);
        // setUser() takes this object as $USER, and the tenant scope reads $USER->open_path.
        $u->open_path = $path;
        $sysctx = \context_system::instance();
        $roleid = $this->getDataGenerator()->create_role();
        role_change_permission($roleid, $sysctx, 'local/sentientia_courses:view', CAP_ALLOW);
        if ($courseupdate) {
            role_change_permission($roleid, $sysctx, 'moodle/course:update', CAP_ALLOW);
        }
        role_assign($roleid, $u->id, $sysctx->id);
        return $u;
    }

    /** Rows of list_courses keyed by course id. */
    private function rows(): array {
        $r = list_courses::execute('', 'fullname', 'asc', 0, 100, '{}');
        // The shape must still pass the declared return structure.
        external_api::clean_returnvalue(list_courses::execute_returns(), $r);
        return array_column($r['rows'], null, 'id');
    }

    private function open_link(int $courseid): string {
        return s((new \moodle_url('/course/view.php', ['id' => $courseid]))->out(false));
    }

    private function settings_link(int $courseid): string {
        return s((new \moodle_url('/course/edit.php', ['id' => $courseid]))->out(false));
    }

    // The flag.

    public function test_the_flag_is_registered_and_defaults_off(): void {
        $registry = feature_flags::load_registry();
        $this->assertArrayHasKey(list_courses::FLAG_ADVANCED_ACTIONS, $registry);
        $this->assertFalse($registry[list_courses::FLAG_ADVANCED_ACTIONS]['default'],
            'A new user-visible element ships OFF (CLAUDE.md section 5).');
        $this->setAdminUser();
        $this->assertFalse(feature_flags::is_enabled(list_courses::FLAG_ADVANCED_ACTIONS));
    }

    public function test_flag_off_leaves_the_row_as_before(): void {
        $this->setAdminUser();
        $course = $this->course_at('/8001');

        $row = $this->rows()[(int) $course->id];
        $this->assertStringNotContainsString('data-action="open-course"', $row['actions']);
        $this->assertStringNotContainsString('data-action="course-advanced-settings"', $row['actions']);
        $this->assertStringNotContainsString('/course/edit.php', $row['actions']);
        // The pencil (the Manage Courses pop-up) is still there.
        $this->assertStringContainsString('data-action="edit-course"', $row['actions']);
    }

    // The actions.

    public function test_flag_on_gives_a_site_admin_both_actions(): void {
        $this->flag(true);
        $this->setAdminUser();
        $course = $this->course_at('/8001');

        $row = $this->rows()[(int) $course->id];
        $this->assertStringContainsString('data-action="open-course"', $row['actions']);
        $this->assertStringContainsString('href="' . $this->open_link((int) $course->id) . '"', $row['actions']);
        $this->assertStringContainsString('data-action="course-advanced-settings"', $row['actions']);
        $this->assertStringContainsString('href="' . $this->settings_link((int) $course->id) . '"', $row['actions']);
        $this->assertStringContainsString(
            s(get_string('action_opencourse', 'local_sentientia_courses')), $row['actions']);
        $this->assertStringContainsString(
            s(get_string('action_advancedsettings', 'local_sentientia_courses')), $row['actions']);
    }

    public function test_rows_are_the_same_apart_from_actions(): void {
        $this->setAdminUser();
        $this->course_at('/8001');
        $this->course_at('/8002');
        $before = $this->rows();

        $this->flag(true);
        $after = $this->rows();

        $this->assertSame(array_keys($before), array_keys($after));
        foreach ($before as $id => $row) {
            unset($row['actions']);
            $other = $after[$id];
            unset($other['actions']);
            $this->assertSame($row, $other, "row $id: only the actions cell may change");
        }
    }

    public function test_without_course_update_only_open_course_is_offered(): void {
        $this->flag(true);
        $course = $this->course_at('/8001');
        $this->setUser($this->viewer_at('/8001'));

        $row = $this->rows()[(int) $course->id];
        $this->assertStringContainsString('data-action="open-course"', $row['actions']);
        $this->assertStringNotContainsString('data-action="course-advanced-settings"', $row['actions'],
            'core /course/edit.php would refuse a viewer without moodle/course:update');
    }

    public function test_scoped_viewer_with_course_update_gets_settings_on_own_tenant_courses_only(): void {
        $this->flag(true);
        $own = $this->course_at('/8001/9001');
        $legacy = $this->course_at(null);
        $this->setUser($this->viewer_at('/8001', true));

        $rows = $this->rows();
        $this->assertArrayHasKey((int) $own->id, $rows);
        $this->assertStringContainsString('data-action="course-advanced-settings"', $rows[(int) $own->id]['actions']);

        // A legacy course (no open_path) is listed to every tenant: open it, yes; change it, no (as the pencil).
        $this->assertArrayHasKey((int) $legacy->id, $rows);
        $this->assertStringContainsString('data-action="open-course"', $rows[(int) $legacy->id]['actions']);
        $this->assertStringNotContainsString('data-action="course-advanced-settings"',
            $rows[(int) $legacy->id]['actions']);
    }

    public function test_flag_on_does_not_widen_the_tenant_scope(): void {
        $this->flag(true);
        $own = $this->course_at('/8001');
        $foreign = $this->course_at('/8002');
        $this->setUser($this->viewer_at('/8001', true));

        $rows = $this->rows();
        $this->assertArrayHasKey((int) $own->id, $rows);
        $this->assertArrayNotHasKey((int) $foreign->id, $rows);
    }
}
