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

    // Day-3 (2026-05-14): pulls in the open_path_fixture_trait which
    // adds `open_path` to {user} and {course} at setUpBeforeClass time.
    // Replaces the old per-test `markTestSkipped` pattern — those skips
    // hid the tests entirely from CI; now they actually run.
    use \local_airpay_core\phpunit\open_path_fixture_trait;

    /**
     * Kept for callers — now a no-op because the trait guarantees the
     * column is present. Left in place so old test bodies that still
     * call $this->skip_if_no_open_path() don't fail.
     */
    private function skip_if_no_open_path(): void {
        // Trait ensured the column exists. Nothing to do.
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
        $this->assertSame('h.costcenterid = :aptenantroot', $sql);
        $this->assertSame(['aptenantroot' => 77], $args);
    }

    // ── require_path_access() — added in Engineering 15, regression-guarded
    //    here in Engineering 29 against the silent-pass bug that motivated
    //    its introduction. The bespoke pre-helper pattern looked like:
    //
    //        $caller_top = '...';  // could be empty
    //        $is_inside = strpos($existing->path, $caller_top . '/') === 0;
    //                  ^^^ when $caller_top is empty, this becomes
    //                  strpos($existing->path, '/'), which returns 0
    //                  ("found at position 0") whenever the path starts
    //                  with '/' — i.e. ALWAYS. Silent pass for any
    //                  caller without a tenant root.
    //
    //    Tests below verify the helper closes that hole AND preserves
    //    the legitimate happy paths.

    public function test_require_path_access_empty_resource_returns_silently(): void {
        // Legacy unscoped row — same tolerance as the inline pattern had.
        tenant::require_path_access('');
        $this->assertTrue(true);  // no exception is the assertion
    }

    public function test_require_path_access_siteadmin_passes_any_path(): void {
        $this->resetAfterTest(true);
        $this->setAdminUser();
        tenant::require_path_access('/1');
        tenant::require_path_access('/1/183');
        tenant::require_path_access('/77');
        tenant::require_path_access('/177');
        $this->assertTrue(true);
    }

    public function test_require_path_access_accepts_exact_tenant_root(): void {
        $this->resetAfterTest(true);
        $this->skip_if_no_open_path();

        global $DB;
        $gen = $this->getDataGenerator();
        $u = $gen->create_user(['open_path' => '/1']);
        $DB->set_field('user', 'open_path', '/1', ['id' => $u->id]);
        $u = $DB->get_record('user', ['id' => $u->id]);
        $this->setUser($u);

        tenant::require_path_access('/1');  // exact tenant root
        $this->assertTrue(true);
    }

    public function test_require_path_access_accepts_nested_path_in_own_tenant(): void {
        $this->resetAfterTest(true);
        $this->skip_if_no_open_path();

        global $DB;
        $gen = $this->getDataGenerator();
        $u = $gen->create_user(['open_path' => '/1']);
        $DB->set_field('user', 'open_path', '/1', ['id' => $u->id]);
        $u = $DB->get_record('user', ['id' => $u->id]);
        $this->setUser($u);

        tenant::require_path_access('/1/183');
        tenant::require_path_access('/1/183/4');
        $this->assertTrue(true);
    }

    public function test_require_path_access_throws_on_cross_tenant(): void {
        $this->resetAfterTest(true);
        $this->skip_if_no_open_path();

        global $DB;
        $gen = $this->getDataGenerator();
        $u = $gen->create_user(['open_path' => '/1']);
        $DB->set_field('user', 'open_path', '/1', ['id' => $u->id]);
        $u = $DB->get_record('user', ['id' => $u->id]);
        $this->setUser($u);

        $this->expectException(\moodle_exception::class);
        tenant::require_path_access('/77');
    }

    public function test_require_path_access_throws_on_prefix_collision(): void {
        $this->resetAfterTest(true);
        $this->skip_if_no_open_path();

        global $DB;
        $gen = $this->getDataGenerator();
        // Tenant root /1, resource path /177 — naive substring match
        // would let /1 match the front of /177 ("starts with /1"). The
        // helper uses slash-bounded comparison so this throws.
        $u = $gen->create_user(['open_path' => '/1']);
        $DB->set_field('user', 'open_path', '/1', ['id' => $u->id]);
        $u = $DB->get_record('user', ['id' => $u->id]);
        $this->setUser($u);

        $this->expectException(\moodle_exception::class);
        tenant::require_path_access('/177');
    }

    public function test_require_path_access_throws_on_viewer_with_no_tenant(): void {
        // REGRESSION TEST for the silent-pass bug. A user with an
        // EMPTY open_path used to silently pass the bespoke inline
        // pattern (because the explode() + strpos() chain returned
        // truthy when caller_top was empty). The helper must throw.
        $this->resetAfterTest(true);
        $this->skip_if_no_open_path();

        global $DB;
        $gen = $this->getDataGenerator();
        $u = $gen->create_user();
        // Explicitly clear open_path so the user has no tenant root.
        $DB->set_field('user', 'open_path', '', ['id' => $u->id]);
        $u = $DB->get_record('user', ['id' => $u->id]);
        $this->setUser($u);

        $this->expectException(\moodle_exception::class);
        tenant::require_path_access('/1/183');
    }

    public function test_require_path_access_accepts_named_viewerid(): void {
        // Calling with an explicit viewerid should look up that user's
        // tenant root, not the currently-logged-in $USER.
        $this->resetAfterTest(true);
        $this->skip_if_no_open_path();

        global $DB;
        $gen = $this->getDataGenerator();
        $airpay = $gen->create_user(['open_path' => '/1']);
        $public = $gen->create_user(['open_path' => '/77']);
        $DB->set_field('user', 'open_path', '/1',  ['id' => $airpay->id]);
        $DB->set_field('user', 'open_path', '/77', ['id' => $public->id]);

        $this->setUser($airpay);  // current user is in /1

        // But we pass viewerid = $public — so the helper checks
        // against /77, not /1. /77 access to /77/x is allowed.
        tenant::require_path_access('/77/100', $public->id);
        $this->assertTrue(true);

        // And /77 viewer trying /1 is denied.
        $this->expectException(\moodle_exception::class);
        tenant::require_path_access('/1/100', $public->id);
    }
}
