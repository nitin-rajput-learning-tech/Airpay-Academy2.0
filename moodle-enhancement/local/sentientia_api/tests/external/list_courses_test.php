<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

namespace local_sentientia_api\external\v1;

defined('MOODLE_INTERNAL') || die();

/**
 * Tests for the v1 list_courses endpoint and the shared base gate.
 *
 * Covers: flag-OFF no-op, capability enforcement, tenant scoping, and
 * parameter validation. Skipped gracefully when the platform feature-flag
 * plugin is not present in the test run.
 *
 * @package    local_sentientia_api
 * @category   test
 * @covers     \local_sentientia_api\external\v1\list_courses
 * @covers     \local_sentientia_api\external\v1\base
 */
final class list_courses_test extends \advanced_testcase {
    use \local_sentientia_org\test\bizlms_fixture;

    /** Force the API master flag to a known state via a global override row. */
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

    private function user_with_read(): \stdClass {
        $u = $this->getDataGenerator()->create_user(['open_path' => '/1']);
        $ctx = \context_system::instance();
        $roleid = $this->getDataGenerator()->create_role();
        role_change_permission($roleid, $ctx, 'local/sentientia_api:read', CAP_ALLOW);
        role_assign($roleid, $u->id, $ctx->id);
        return $u;
    }

    public function test_flag_off_is_noop(): void {
        $this->resetAfterTest();
        $this->set_flag('sentientia.api.enabled', false);

        $u = $this->user_with_read();
        $this->setUser($u);

        $this->expectException(\moodle_exception::class);
        $this->expectExceptionMessageMatches('/disabled/i');
        list_courses::execute('', 0, 25);
    }

    public function test_capability_required(): void {
        $this->resetAfterTest();
        $this->set_flag('sentientia.api.enabled', true);

        // User without the read cap.
        $u = $this->getDataGenerator()->create_user(['open_path' => '/1']);
        $this->setUser($u);

        $this->expectException(\required_capability_exception::class);
        list_courses::execute('', 0, 25);
    }

    public function test_tenant_scoping_filters_other_tenants(): void {
        global $DB;
        $this->resetAfterTest();
        $this->set_flag('sentientia.api.enabled', true);

        // Course in tenant 1 and a course in tenant 77.
        $c1 = $this->getDataGenerator()->create_course(['fullname' => 'Tenant1 Course']);
        $c77 = $this->getDataGenerator()->create_course(['fullname' => 'Tenant77 Course']);
        $DB->set_field('course', 'open_path', '/1', ['id' => $c1->id]);
        $DB->set_field('course', 'open_path', '/77', ['id' => $c77->id]);

        $u = $this->user_with_read();  // open_path /1
        $this->setUser($u);

        $result = list_courses::execute('', 0, 50);
        $names = array_map(fn($c) => $c['fullname'], $result['courses']);
        $this->assertContains('Tenant1 Course', $names);
        $this->assertNotContains('Tenant77 Course', $names);
    }

    public function test_perpage_clamped(): void {
        $this->resetAfterTest();
        $this->set_flag('sentientia.api.enabled', true);
        $u = $this->user_with_read();
        $this->setUser($u);

        $result = list_courses::execute('', 0, 9999);
        $this->assertSame(100, $result['perpage']);  // clamped to max 100
    }
}
