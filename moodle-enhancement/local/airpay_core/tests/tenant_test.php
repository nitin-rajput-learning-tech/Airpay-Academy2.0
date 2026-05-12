<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

namespace local_airpay_core;

defined('MOODLE_INTERNAL') || die();

/**
 * @covers \local_airpay_core\tenant
 */
class tenant_test extends \advanced_testcase {

    public function test_root_for_user_resolves_first_path_segment(): void {
        $u = (object) ['open_path' => '/1/2/3'];
        $this->assertSame(1, tenant::root_for_user($u));

        $u = (object) ['open_path' => '/77'];
        $this->assertSame(77, tenant::root_for_user($u));

        $u = (object) ['open_path' => '/177/5'];
        $this->assertSame(177, tenant::root_for_user($u));
    }

    public function test_root_for_user_returns_zero_on_missing_or_invalid_path(): void {
        $this->assertSame(0, tenant::root_for_user((object) ['open_path' => '']));
        $this->assertSame(0, tenant::root_for_user((object) ['open_path' => null]));
        $this->assertSame(0, tenant::root_for_user((object) ['open_path' => '/abc']));
        $this->assertSame(0, tenant::root_for_user((object) []));
    }

    public function test_assert_valid_throws_for_unknown_tenant(): void {
        $this->expectException(\moodle_exception::class);
        tenant::assert_valid(999);
    }

    public function test_assert_valid_accepts_known_tenants(): void {
        tenant::assert_valid(1);
        tenant::assert_valid(77);
        tenant::assert_valid(177);
        $this->assertTrue(true);  // reached
    }

    public function test_viewer_can_access_blocks_cross_tenant(): void {
        $this->resetAfterTest(true);
        $gen = $this->getDataGenerator();
        $airpay = $gen->create_user(['open_path' => '/1/183']);
        $public = $gen->create_user(['open_path' => '/77']);

        // Airpay user shouldn't see Public-tenant resource.
        $this->assertFalse(tenant::viewer_can_access(77, $airpay->id));
        // Public user shouldn't see Airpay-tenant resource.
        $this->assertFalse(tenant::viewer_can_access(1, $public->id));
        // Same-tenant ok.
        $this->assertTrue(tenant::viewer_can_access(1, $airpay->id));
        $this->assertTrue(tenant::viewer_can_access(77, $public->id));
    }

    public function test_siteadmin_can_access_any_tenant(): void {
        $this->resetAfterTest(true);
        $admin = get_admin();
        $this->assertTrue(tenant::viewer_can_access(1, $admin->id));
        $this->assertTrue(tenant::viewer_can_access(77, $admin->id));
        $this->assertTrue(tenant::viewer_can_access(177, $admin->id));
    }

    public function test_require_access_throws_on_cross_tenant(): void {
        $this->resetAfterTest(true);
        $gen = $this->getDataGenerator();
        $airpay = $gen->create_user(['open_path' => '/1/183']);

        $this->expectException(\moodle_exception::class);
        tenant::require_access(77, $airpay->id);
    }

    public function test_sql_filter_returns_admin_passthrough(): void {
        $this->resetAfterTest(true);
        $this->setAdminUser();
        [$sql, $args] = tenant::sql_filter('h');
        $this->assertSame('1=1', $sql);
        $this->assertSame([], $args);
    }

    public function test_sql_filter_returns_scoped_filter_for_tenant_user(): void {
        $this->resetAfterTest(true);
        $gen = $this->getDataGenerator();
        $u = $gen->create_user(['open_path' => '/77']);
        $this->setUser($u);
        [$sql, $args] = tenant::sql_filter('h');
        $this->assertSame('h.costcenterid = :_tenantroot', $sql);
        $this->assertSame(['_tenantroot' => 77], $args);
    }
}
