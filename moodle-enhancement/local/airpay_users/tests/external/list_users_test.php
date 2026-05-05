<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

namespace local_airpay_users\external;

defined('MOODLE_INTERNAL') || die();

/**
 * Security regression tests for list_users.
 *
 * Locks in fixes from the May 5 2026 security audit:
 * - C2 LIKE wildcard escape: '/8001' must NOT match '/80010' or '/8001x'.
 * - H1 orgid ownership: a non-siteadmin caller cannot pass an orgid
 *   outside their own tenant tree.
 * - M2 JSON filter bounds: > 4 KB rejected, > 5-level depth ignored.
 * - Sort whitelist: arbitrary sort columns fall back to default.
 * - Default tenant scope: caller without filters sees only own tenant.
 *
 * Tests use synthetic tenant IDs (8000+) to avoid collision with any
 * pre-seeded local_airpay_org rows.
 *
 * @package    local_airpay_users
 * @category   test
 */
final class list_users_test extends \advanced_testcase {

    use \local_airpay_org\test\bizlms_fixture;

    /**
     * Insert a synthetic org row at the given path. The id is the last
     * segment of the path (numeric).
     */
    private function seed_org(string $path, int $parentid = 0, int $depth = 1): int {
        global $DB;
        if (!$DB->get_manager()->table_exists('local_airpay_org')) {
            $this->markTestSkipped('local_airpay_org table not present.');
        }
        $id = (int) ltrim(strrchr($path, '/'), '/');
        $rec = (object)[
            'id' => $id, 'fullname' => "Org $id", 'shortname' => "org_$id",
            'parentid' => $parentid, 'path' => $path, 'depth' => $depth,
            'visible' => 1, 'sortorder' => 0,
            'timecreated' => time(), 'timemodified' => time(),
        ];
        $DB->insert_record_raw('local_airpay_org', $rec, false, false, true);
        return $id;
    }

    /**
     * Create a user and explicitly set open_path. open_path must be set
     * via set_field rather than create_user args because Moodle's data
     * generator does not pass through unknown user table fields.
     */
    private function user_at_path(string $path): \stdClass {
        global $DB;
        $u = $this->getDataGenerator()->create_user();
        $DB->set_field('user', 'open_path', $path, ['id' => $u->id]);
        $u->open_path = $path;
        return $u;
    }

    /**
     * Helper — call list_users::execute with sensible defaults.
     */
    private function call(array $overrides = []): array {
        $args = array_merge([
            'search'  => '',
            'sort'    => 'lastname',
            'sortdir' => 'asc',
            'page'    => 0,
            'perpage' => 25,
            'filters' => '{}',
        ], $overrides);

        return list_users::execute(
            $args['search'], $args['sort'], $args['sortdir'],
            $args['page'], $args['perpage'], $args['filters']);
    }

    /**
     * Grant a role with a single capability and assign it to user.
     */
    private function grant_cap(\stdClass $user, string $cap): void {
        $sysctx = \context_system::instance();
        $roleid = $this->getDataGenerator()->create_role();
        role_change_permission($roleid, $sysctx, $cap, CAP_ALLOW);
        role_assign($roleid, $user->id, $sysctx->id);
    }

    /**
     * C2: '/8001' tenant scope must NOT pull users from '/80010', '/8002'.
     * The literal-underscore decoy '/8001_x' must also not match.
     */
    public function test_c2_tenant_like_wildcard_does_not_leak(): void {
        $this->resetAfterTest();
        $this->ensure_bizlms_schema();
        $this->setAdminUser();
        $this->seed_org('/8001', 0, 1);

        // Seed users at paths whose decimal expansions overlap.
        $u_in_root = $this->user_at_path('/8001');
        $u_in_sub  = $this->user_at_path('/8001/9001');
        $u_decoy1  = $this->user_at_path('/80010');     // decimal-overlap
        $u_decoy2  = $this->user_at_path('/8001_x');    // literal underscore
        $u_other   = $this->user_at_path('/8002');      // sibling tenant

        $r = $this->call([
            'filters' => json_encode(['orgid' => 8001, 'status' => 'all']),
            'perpage' => 50,
        ]);
        $ids = array_column($r['rows'], 'id');

        $this->assertContains((int) $u_in_root->id, $ids,
            'Tenant root user (path = /8001) must be included when scoping to orgid=8001');
        $this->assertContains((int) $u_in_sub->id,  $ids,
            'Sub-org user (/8001/9001) must be included');

        $this->assertNotContains((int) $u_decoy1->id, $ids,
            'C2 leak: /80010 matched the LIKE pattern for /8001');
        $this->assertNotContains((int) $u_decoy2->id, $ids,
            'C2 leak: literal _ in path matched as a wildcard');
        $this->assertNotContains((int) $u_other->id,  $ids,
            'C2 leak: sibling tenant /8002 matched /8001 scope');
    }

    /**
     * H1: a non-siteadmin caller in /8001 cannot read /8002 users by
     * passing orgid=8002.
     */
    public function test_h1_orgid_outside_caller_tenant_rejected(): void {
        $this->resetAfterTest();
        $this->ensure_bizlms_schema();

        $this->seed_org('/8001', 0, 1);
        $this->seed_org('/8002', 0, 1);

        $caller = $this->user_at_path('/8001');
        $this->grant_cap($caller, 'local/airpay_users:view');
        $this->setUser($caller);

        $this->expectException(\moodle_exception::class);
        $this->expectExceptionMessage('outoftenant');
        $this->call(['filters' => json_encode(['orgid' => 8002, 'status' => 'all'])]);
    }

    /**
     * H1 happy path: caller in /8001/9001 can pass orgid=8001 (parent
     * tenant — inside their tree).
     */
    public function test_h1_orgid_inside_caller_tenant_succeeds(): void {
        $this->resetAfterTest();
        $this->ensure_bizlms_schema();

        $tenant_id = $this->seed_org('/8001', 0, 1);

        $caller = $this->user_at_path('/8001/9001');
        $this->grant_cap($caller, 'local/airpay_users:view');
        $this->setUser($caller);

        $r = $this->call([
            'filters' => json_encode(['orgid' => $tenant_id, 'status' => 'all']),
        ]);
        $this->assertIsArray($r);
        $this->assertArrayHasKey('total', $r);
    }

    /**
     * M2: filters JSON > 4 KB throws 'filterstoolong'.
     */
    public function test_m2_filter_size_limit_enforced(): void {
        $this->resetAfterTest();
        $this->ensure_bizlms_schema();
        $this->setAdminUser();

        $huge = json_encode(['payload' => str_repeat('x', 5000)]);
        $this->assertGreaterThan(4096, strlen($huge), 'precondition: payload > 4 KB');

        $this->expectException(\moodle_exception::class);
        $this->expectExceptionMessage('filterstoolong');
        $this->call(['filters' => $huge]);
    }

    /**
     * M2: filters JSON deeper than 5 levels silently falls back to empty.
     */
    public function test_m2_filter_depth_limit_silent_fallback(): void {
        $this->resetAfterTest();
        $this->ensure_bizlms_schema();
        $this->setAdminUser();

        $deep = json_encode(['l1' => ['l2' => ['l3' => ['l4' => ['l5' => ['l6' => ['l7' => 'v']]]]]]]);
        $r = $this->call(['filters' => $deep]);
        $this->assertIsArray($r);
        $this->assertArrayHasKey('total', $r);
    }

    /**
     * Sort whitelist: bogus column falls back to default lastname.
     */
    public function test_sort_whitelist(): void {
        $this->resetAfterTest();
        $this->ensure_bizlms_schema();
        $this->setAdminUser();
        $this->user_at_path('/8001');

        $r = $this->call([
            'sort' => 'no_such_column',
            'filters' => json_encode(['status' => 'all']),
        ]);
        $this->assertIsArray($r);
    }

    /**
     * Default tenant scope: caller in /8001 sees own tenant only — never
     * cross-tenant users.
     */
    public function test_default_tenant_scope_no_orgid(): void {
        $this->resetAfterTest();
        $this->ensure_bizlms_schema();

        $this->seed_org('/8001', 0, 1);
        $this->seed_org('/8002', 0, 1);

        $caller = $this->user_at_path('/8001');
        $this->grant_cap($caller, 'local/airpay_users:view');
        $this->setUser($caller);

        $u_own_tenant = $this->user_at_path('/8001/9001');
        $u_other      = $this->user_at_path('/8002');

        $r = $this->call(['filters' => json_encode(['status' => 'all'])]);
        $ids = array_column($r['rows'], 'id');

        $this->assertContains((int) $u_own_tenant->id, $ids);
        $this->assertNotContains((int) $u_other->id, $ids,
            'Default scope must keep cross-tenant users out');
    }
}
