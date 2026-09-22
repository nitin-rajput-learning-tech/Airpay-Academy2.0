<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

namespace local_sentientia_platform;

defined('MOODLE_INTERNAL') || die();

/**
 * @covers \local_sentientia_platform\tenant
 *
 * Tests that exercise the DB-loading path of `viewer_can_access()` and
 * `sql_filter()` require the BizLMS `user.open_path` column. That column
 * is added by the `local_costcenter` plugin in production but is NOT
 * present on a vanilla Moodle PHPUnit fixture (the bizlms plugin is
 * disabled in our checkout). The helper class itself is correct against
 * production schema; the tests that need the column simply skip when
 * it's absent.
 *
 * @group tenant_isolation
 */
class tenant_test extends \advanced_testcase {

    // Day-3 (2026-05-14): pulls in the open_path_fixture_trait which
    // adds `open_path` to {user} and {course} at setUpBeforeClass time.
    // Replaces the old per-test `markTestSkipped` pattern — those skips
    // hid the tests entirely from CI; now they actually run.
    use \local_sentientia_platform\phpunit\open_path_fixture_trait;

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

    // ── Path-boundary regression suite (2026-09-22) ─────────────────────
    // These execute the SQL the helpers EMIT against real rows that
    // include every known collision, rather than asserting on the
    // fragment string. The prefix-collision class of defect shipped
    // twice while string-level assertions passed, so the proof has to
    // go through the database.

    /** Seed {user}.open_path with the full collision set. @return array label => userid */
    private function seed_collision_paths(): array {
        global $DB;
        $paths = [
            'root'          => '/1',        // the tenant root itself
            'child'         => '/1/2',      // a direct child
            'grandchild'    => '/1/2/3',    // deeper descendant
            'sibling_digit' => '/1/20',     // path-boundary-ok: names the defect this suite locks out
            'other_tenant'  => '/10',       // collides with /1 under `'/1' . '%'`
            'zeea'          => '/177',      // the tenant that actually leaked, twice
            'suffix'        => '/1x',       // non-numeric suffix collision
        ];
        $ids = [];
        foreach ($paths as $label => $p) {
            $u = $this->getDataGenerator()->create_user();
            $DB->set_field('user', 'open_path', $p, ['id' => $u->id]);
            $ids[$label] = (int) $u->id;
        }
        return $ids;
    }

    /** Run a filter fragment against {user} and return the matched labels. */
    private function labels_matching(array $filter, array $ids): array {
        global $DB;
        [$sql, $params] = $filter;
        $rows = $DB->get_records_select('user', $sql, $params, '', 'id');
        $matched = [];
        foreach ($ids as $label => $id) {
            if (isset($rows[$id])) {
                $matched[] = $label;
            }
        }
        sort($matched);
        return $matched;
    }

    /**
     * path_descendant_filter('/1') must match /1, /1/2, /1/2/3 and NOTHING
     * else. /1/20 is a sibling of /1/2 not a descendant of it, but it IS a
     * descendant of /1 — so it matches here and must NOT match when the
     * scope is /1/2 (asserted in the next test).
     */
    public function test_path_descendant_filter_matches_root_and_children_only(): void {
        $this->resetAfterTest();
        $ids = $this->seed_collision_paths();

        $matched = $this->labels_matching(
            tenant::path_descendant_filter('/1'), $ids);

        $this->assertSame(['child', 'grandchild', 'root', 'sibling_digit'], $matched,
            'scope /1 must match /1 and its /-bounded descendants, and must NOT match /10, /177 or /1x');
    }

    /**
     * THE REGRESSION. `$path . '%'` for '/1/2' silently swallowed '/1/20'.
     * This is the department-scorecard over-count defect.
     */
    public function test_path_descendant_filter_excludes_digit_prefix_sibling(): void {
        $this->resetAfterTest();
        $ids = $this->seed_collision_paths();

        $matched = $this->labels_matching(
            tenant::path_descendant_filter('/1/2'), $ids);

        $this->assertSame(['child', 'grandchild'], $matched,
            // path-boundary-ok: the message names the defect being asserted against
            "scope /1/2 must match /1/2 and /1/2/3 but NEVER /1/20 (the `\$path . '%'` defect)");
        $this->assertNotContains('sibling_digit', $matched);
    }

    /** A leaf node must match itself — the `'%/' . $id . '/%'` pattern missed it. */
    public function test_path_descendant_filter_matches_a_leaf_node_exactly(): void {
        $this->resetAfterTest();
        $ids = $this->seed_collision_paths();

        $matched = $this->labels_matching(
            tenant::path_descendant_filter('/1/20'), $ids);

        $this->assertSame(['sibling_digit'], $matched,
            "a leaf path must match itself (the `'%/id/%'` pattern under-counted leaf users)");
    }

    /** Trailing slashes and surrounding whitespace must not change the result. */
    public function test_path_descendant_filter_normalises_input(): void {
        $this->resetAfterTest();
        $ids = $this->seed_collision_paths();

        $expected = $this->labels_matching(tenant::path_descendant_filter('/1/2'), $ids);
        foreach (['/1/2/', '  /1/2  ', "/1/2\n"] as $variant) {
            $this->assertSame($expected,
                $this->labels_matching(tenant::path_descendant_filter($variant), $ids),
                "input '{$variant}' must normalise to /1/2");
        }
    }

    /** An empty path means "no restriction", not "match nothing". */
    public function test_path_descendant_filter_empty_path_is_unrestricted(): void {
        $this->assertSame(['1=1', []], tenant::path_descendant_filter(''));
        $this->assertSame(['1=1', []], tenant::path_descendant_filter('/'));
    }

    /** Two filters in one query must not collide on parameter names. */
    public function test_path_descendant_filter_tags_keep_params_distinct(): void {
        [$sql1, $p1] = tenant::path_descendant_filter('/1', 'u', 'open_path', 'org');
        [$sql2, $p2] = tenant::path_descendant_filter('/1/2', 's', 'department_path', 'dept');

        $this->assertSame([], array_intersect_key($p1, $p2),
            'tagged filters must not share parameter names');
        $this->assertStringContainsString('u.open_path', $sql1);
        $this->assertStringContainsString('s.department_path', $sql2);
    }

    /** allow_null tolerates legacy unscoped rows, and is off by default. */
    public function test_path_descendant_filter_null_tolerance_is_opt_in(): void {
        $this->resetAfterTest();
        global $DB;
        $ids = $this->seed_collision_paths();
        $u = $this->getDataGenerator()->create_user();
        $DB->set_field('user', 'open_path', null, ['id' => $u->id]);
        $ids['nullpath'] = (int) $u->id;

        $this->assertNotContains('nullpath',
            $this->labels_matching(tenant::path_descendant_filter('/1'), $ids),
            'NULL paths must be excluded by default');
        $this->assertContains('nullpath',
            $this->labels_matching(
                tenant::path_descendant_filter('/1', '', 'open_path', 'apdesc', true), $ids),
            'NULL paths must be included when allow_null is set');
    }

    /**
     * The viewer-scoped path_filter() emits SQL that is never executed in
     * the rest of the suite. Run it against the collision set as a
     * tenant-/1 user and prove /10, /177 and /1x do not come back.
     */
    public function test_path_filter_sql_excludes_colliding_tenants_in_the_database(): void {
        $this->resetAfterTest();
        global $DB;
        $ids = $this->seed_collision_paths();

        $viewer = $this->getDataGenerator()->create_user();
        $DB->set_field('user', 'open_path', '/1', ['id' => $viewer->id]);
        $viewer->open_path = '/1';
        $this->setUser($viewer);

        $matched = $this->labels_matching(tenant::path_filter(), $ids);

        $this->assertContains('root', $matched);
        $this->assertContains('child', $matched);
        $this->assertContains('grandchild', $matched);
        foreach (['other_tenant', 'zeea', 'suffix'] as $forbidden) {
            $this->assertNotContains($forbidden, $matched,
                "a /1 viewer must never see rows at {$forbidden}");
        }
    }
}
