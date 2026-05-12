<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

namespace local_airpay_core;

defined('MOODLE_INTERNAL') || die();

/**
 * @covers \local_airpay_core\tenant
 *
 * Tests that exercise the DB-loading path of `viewer_can_access()` and
 * `sql_filter()` require the BizLMS `user.open_path` column. That column
 * is added by the `local_costcenter` plugin in production but is NOT
 * present on a vanilla Moodle PHPUnit fixture (the bizlms plugin is
 * disabled in our checkout). The helper class itself is correct against
 * production schema; the tests that need the column simply skip when
 * it's absent.
 */
class tenant_test extends \advanced_testcase {

    /**
     * Cached probe — does the BizLMS open_path column exist in this env?
     */
    private static function open_path_column_exists(): bool {
        global $DB;
        $columns = $DB->get_columns('user');
        return isset($columns['open_path']);
    }

    private function skip_if_no_open_path(): void {
        if (!self::open_path_column_exists()) {
            $this->markTestSkipped(
                'BizLMS user.open_path column not present (PHPUnit fixture). '
                . 'Test verifies production-only schema behaviour.');
        }
    }

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
        $this->skip_if_no_open_path();

        global $DB;
        $gen = $this->getDataGenerator();
        $airpay = $gen->create_user(['open_path' => '/1/183']);
        $public = $gen->create_user(['open_path' => '/77']);
        // create_user silently ignores unknown columns. Re-set open_path
        // explicitly so the DB-load path inside viewer_can_access works.
        $DB->set_field('user', 'open_path', '/1/183', ['id' => $airpay->id]);
        $DB->set_field('user', 'open_path', '/77',    ['id' => $public->id]);

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
        $this->skip_if_no_open_path();

        global $DB;
        $gen = $this->getDataGenerator();
        $airpay = $gen->create_user(['open_path' => '/1/183']);
        $DB->set_field('user', 'open_path', '/1/183', ['id' => $airpay->id]);

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
        $this->skip_if_no_open_path();

        global $DB;
        $gen = $this->getDataGenerator();
        $u = $gen->create_user(['open_path' => '/77']);
        $DB->set_field('user', 'open_path', '/77', ['id' => $u->id]);
        // setUser pulls a fresh row from DB; ensure that row has the field.
        $u = $DB->get_record('user', ['id' => $u->id]);
        $this->setUser($u);
        [$sql, $args] = tenant::sql_filter('h');
        $this->assertSame('h.costcenterid = :_tenantroot', $sql);
        $this->assertSame(['_tenantroot' => 77], $args);
    }
}
