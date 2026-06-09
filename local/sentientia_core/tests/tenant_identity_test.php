<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

namespace local_sentientia_core;

defined('MOODLE_INTERNAL') || die();

/**
 * Unit tests for the ADR-019 / ADR-018-Wave-2 tenant-identity seam.
 *
 * Every test is property- or current-user-based (sets open_path on an in-memory
 * object / the $USER global) so the suite runs on a vanilla Moodle install in
 * the CI phpunit-52 gate, where mdl_user has no BizLMS open_path column.
 *
 * @package    local_sentientia_core
 * @covers     \local_sentientia_core\tenant_identity
 * @copyright  2026 Airpay Payment Services
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class tenant_identity_test extends \advanced_testcase {

    public function test_legacy_flag_defaults_on_when_unset(): void {
        $this->resetAfterTest();
        unset_config('tenant_identity_legacy', 'local_sentientia_core');
        $this->assertTrue(
            tenant_identity::use_legacy_open_path(),
            'Unset config must be treated as ON so production behaviour never changes implicitly.'
        );
    }

    public function test_legacy_flag_respects_explicit_off(): void {
        $this->resetAfterTest();
        set_config('tenant_identity_legacy', 0, 'local_sentientia_core');
        $this->assertFalse(tenant_identity::use_legacy_open_path());
    }

    public function test_root_for_user_parses_open_path(): void {
        $this->resetAfterTest();
        set_config('tenant_identity_legacy', 1, 'local_sentientia_core');
        $this->assertSame(77, tenant_identity::root_for_user((object) ['open_path' => '/77/5/2']));
        $this->assertSame(1, tenant_identity::root_for_user((object) ['open_path' => '/1']));
        $this->assertSame(177, tenant_identity::root_for_user((object) ['open_path' => '/177']));
    }

    public function test_root_for_user_invalid_path_returns_no_tenant(): void {
        $this->resetAfterTest();
        $this->assertSame(tenant_identity::NO_TENANT,
            tenant_identity::root_for_user((object) ['open_path' => '']));
        $this->assertSame(tenant_identity::NO_TENANT,
            tenant_identity::root_for_user((object) ['open_path' => null]));
        $this->assertSame(tenant_identity::NO_TENANT,
            tenant_identity::root_for_user((object) ['open_path' => '/abc']));
    }

    public function test_off_path_falls_back_to_legacy_until_registry_exists(): void {
        $this->resetAfterTest();
        set_config('tenant_identity_legacy', 0, 'local_sentientia_core');
        // No Sentientia registry yet → resolves via legacy fallback + emits a
        // developer-debug note. Behaviour must still be correct (not break auth).
        $result = tenant_identity::root_for_user((object) ['open_path' => '/77']);
        $this->assertDebuggingCalled();
        $this->assertSame(77, $result);
    }

    public function test_root_for_current_user_zero_when_logged_out(): void {
        global $USER;
        $this->resetAfterTest();
        $USER = new \stdClass();
        $this->assertSame(tenant_identity::NO_TENANT, tenant_identity::root_for_current_user());
    }

    // ---- Wave 2 surface: open_path decomposition ----

    public function test_segments_for_user_decomposes_path(): void {
        $this->resetAfterTest();
        set_config('tenant_identity_legacy', 1, 'local_sentientia_core');
        $this->assertSame([1, 2, 3],
            tenant_identity::segments_for_user((object) ['open_path' => '/1/2/3']));
        $this->assertSame([77],
            tenant_identity::segments_for_user((object) ['open_path' => '/77']));
        $this->assertSame([],
            tenant_identity::segments_for_user((object) ['open_path' => '']));
        $this->assertSame([],
            tenant_identity::segments_for_user((object) ['open_path' => null]));
        // Non-numeric root → no usable decomposition (consistent with root_for_user()).
        $this->assertSame([],
            tenant_identity::segments_for_user((object) ['open_path' => '/abc/2']));
    }

    public function test_department_and_subdepartment_accessors(): void {
        $this->resetAfterTest();
        $u = (object) ['open_path' => '/1/2/3'];
        $this->assertSame(2, tenant_identity::department_for_user($u));
        $this->assertSame(3, tenant_identity::subdepartment_for_user($u));
        // Tenant-only path → no department / sub-department segments.
        $top = (object) ['open_path' => '/77'];
        $this->assertSame(0, tenant_identity::department_for_user($top));
        $this->assertSame(0, tenant_identity::subdepartment_for_user($top));
    }

    public function test_path_for_user_returns_raw_string(): void {
        $this->resetAfterTest();
        $this->assertSame('/1/2/3',
            tenant_identity::path_for_user((object) ['open_path' => '/1/2/3']));
        $this->assertSame('', tenant_identity::path_for_user((object) ['open_path' => null]));
        $this->assertSame('', tenant_identity::path_for_user((object) []));
    }

    public function test_path_root_parses_entity_path_string(): void {
        $this->resetAfterTest();
        $this->assertSame(77, tenant_identity::path_root('/77/5'));
        $this->assertSame(1, tenant_identity::path_root('/1'));
        $this->assertSame(tenant_identity::NO_TENANT, tenant_identity::path_root(''));
        $this->assertSame(tenant_identity::NO_TENANT, tenant_identity::path_root('/abc'));
    }

    // ---- Wave 2 surface: tenant-path access (current-user, vanilla-Moodle safe) ----

    public function test_require_path_access_empty_path_passes(): void {
        $this->resetAfterTest();
        $this->setUser($this->getDataGenerator()->create_user());
        // Empty resource path = legacy unscoped row → always visible, no throw.
        tenant_identity::require_path_access('');
        $this->assertTrue(true);
    }

    public function test_require_path_access_siteadmin_passes(): void {
        $this->resetAfterTest();
        $this->setAdminUser();
        // Admins bypass tenant scoping entirely.
        tenant_identity::require_path_access('/999/strange/path');
        $this->assertTrue(true);
    }

    public function test_can_access_path_same_tenant_and_descendant(): void {
        global $USER;
        $this->resetAfterTest();
        $this->setUser($this->getDataGenerator()->create_user());
        $USER->open_path = '/77';
        // Exact tenant + descendant paths are accessible…
        $this->assertTrue(tenant_identity::can_access_path('/77'));
        $this->assertTrue(tenant_identity::can_access_path('/77/12/4'));
        // …a different tenant is not.
        $this->assertFalse(tenant_identity::can_access_path('/1'));
        $this->assertFalse(tenant_identity::can_access_path('/177/9'));
    }

    public function test_require_path_access_throws_cross_tenant(): void {
        global $USER;
        $this->resetAfterTest();
        $this->setUser($this->getDataGenerator()->create_user());
        $USER->open_path = '/77';
        $this->expectException(\moodle_exception::class);
        tenant_identity::require_path_access('/1/2');  // tenant 1 != viewer tenant 77
    }

    // ---- Wave 2 surface: query filters (current-user, vanilla-Moodle safe) ----

    public function test_sql_filter_admin_vs_tenant(): void {
        global $USER;
        $this->resetAfterTest();
        // Site admin → unrestricted.
        $this->setAdminUser();
        [$sql, $params] = tenant_identity::sql_filter('h');
        $this->assertSame('1=1', $sql);
        $this->assertSame([], $params);
        // Tenant-bound user → costcenterid scoped to their tenant root.
        $this->setUser($this->getDataGenerator()->create_user());
        $USER->open_path = '/77';
        [$sql, $params] = tenant_identity::sql_filter('h');
        $this->assertStringContainsString('h.costcenterid', $sql);
        $this->assertContains(77, array_values($params));
    }

    public function test_path_filter_builds_exact_and_prefix(): void {
        global $USER;
        $this->resetAfterTest();
        $this->setUser($this->getDataGenerator()->create_user());
        $USER->open_path = '/77';
        [$sql, $params] = tenant_identity::path_filter('c');
        $this->assertStringContainsString('c.open_path', $sql);
        $this->assertSame('/77', $params['appathexact']);
        $this->assertSame('/77/%', $params['appathprefix']);
    }
}
