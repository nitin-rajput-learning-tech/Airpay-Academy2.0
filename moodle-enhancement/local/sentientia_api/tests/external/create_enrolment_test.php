<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

namespace local_sentientia_api\external\v1;

defined('MOODLE_INTERNAL') || die();

/**
 * Tests for the v1 create_enrolment WRITE endpoint.
 *
 * Covers the [CONFIRM]-equivalent triple gate: master flag, write sub-flag,
 * and the :write capability — plus tenant scoping of both course and user.
 *
 * @package    local_sentientia_api
 * @category   test
 * @covers     \local_sentientia_api\external\v1\create_enrolment
 */
final class create_enrolment_test extends \advanced_testcase {
    use \local_sentientia_org\test\bizlms_fixture;

    private function set_flag(string $key, bool $on): void {
        if (!class_exists('\local_sentientia_platform\feature_flags')) {
            $this->markTestSkipped('local_sentientia_platform not installed.');
        }
        // These tests scope by open_path — provision the BizLMS columns on the
        // phpunit schema (the fixture's DDL persists until the next init; without
        // this call the tests only pass if some earlier test happened to run it).
        $this->ensure_bizlms_schema();
        \local_sentientia_platform\feature_flags::set($key, 0, $on, null, 'phpunit', 0);
        \local_sentientia_platform\feature_flags::invalidate_caches(); // Statics survive resetAfterTest.
    }

    private function user_with_write(): \stdClass {
        $u = $this->getDataGenerator()->create_user(['open_path' => '/1']);
        $ctx = \context_system::instance();
        $roleid = $this->getDataGenerator()->create_role();
        role_change_permission($roleid, $ctx, 'local/sentientia_api:write', CAP_ALLOW);
        role_assign($roleid, $u->id, $ctx->id);
        return $u;
    }

    public function test_write_flag_off_blocks_even_with_master_on(): void {
        global $DB;
        $this->resetAfterTest();
        $this->set_flag('sentientia.api.enabled', true);
        $this->set_flag('sentientia.api.write.enabled', false);

        $course = $this->getDataGenerator()->create_course();
        $DB->set_field('course', 'open_path', '/1', ['id' => $course->id]);
        $target = $this->getDataGenerator()->create_user(['open_path' => '/1']);

        $u = $this->user_with_write();
        $this->setUser($u);

        $this->expectException(\moodle_exception::class);
        $this->expectExceptionMessageMatches('/write.*disabled|disabled/i');
        create_enrolment::execute($course->id, $target->id, 0);
    }

    public function test_write_capability_required(): void {
        global $DB;
        $this->resetAfterTest();
        $this->set_flag('sentientia.api.enabled', true);
        $this->set_flag('sentientia.api.write.enabled', true);

        $course = $this->getDataGenerator()->create_course();
        $DB->set_field('course', 'open_path', '/1', ['id' => $course->id]);
        $target = $this->getDataGenerator()->create_user(['open_path' => '/1']);

        // User without :write cap.
        $u = $this->getDataGenerator()->create_user(['open_path' => '/1']);
        $this->setUser($u);

        $this->expectException(\required_capability_exception::class);
        create_enrolment::execute($course->id, $target->id, 0);
    }

    public function test_cross_tenant_target_user_rejected(): void {
        global $DB;
        $this->resetAfterTest();
        $this->set_flag('sentientia.api.enabled', true);
        $this->set_flag('sentientia.api.write.enabled', true);

        $course = $this->getDataGenerator()->create_course();
        $DB->set_field('course', 'open_path', '/1', ['id' => $course->id]);
        // Target user is in tenant 77 — outside caller tenant 1.
        $target = $this->getDataGenerator()->create_user(['open_path' => '/77']);

        $u = $this->user_with_write();  // tenant 1
        $this->setUser($u);

        $this->expectException(\moodle_exception::class);
        create_enrolment::execute($course->id, $target->id, 0);
    }

    public function test_enrols_user_when_all_gates_pass(): void {
        global $DB;
        $this->resetAfterTest();
        $this->set_flag('sentientia.api.enabled', true);
        $this->set_flag('sentientia.api.write.enabled', true);

        $course = $this->getDataGenerator()->create_course();
        $DB->set_field('course', 'open_path', '/1', ['id' => $course->id]);
        $target = $this->getDataGenerator()->create_user(['open_path' => '/1']);

        $u = $this->user_with_write();
        $this->setUser($u);

        $result = create_enrolment::execute($course->id, $target->id, 0);
        $this->assertSame('enrolled', $result['status']);
        $this->assertTrue(is_enrolled(\context_course::instance($course->id), $target->id));
    }
}
