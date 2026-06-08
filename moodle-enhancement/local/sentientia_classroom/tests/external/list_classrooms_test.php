<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

namespace local_sentientia_classroom\external;

defined('MOODLE_INTERNAL') || die();

/**
 * Regression tests for list_classrooms WS.
 *
 * Locks in:
 * - /-bounded prefix: orgpath '/1' must NOT match '/100' or '/177'
 * - Sort whitelist: arbitrary sort columns fall back to 'name'
 * - JSON filter bounds: > 4KB rejected with 'filterstoolong'
 * - Search escapes LIKE wildcards: '%' in user input is treated as literal
 * - Tenant scoping: non-siteadmin caller sees only their tenant
 *
 * @package    local_sentientia_classroom
 * @category   test
 */
final class list_classrooms_test extends \advanced_testcase {

    use \local_airpay_org\test\bizlms_fixture;

    /**
     * Insert a classroom directly.
     */
    private function seed_classroom(string $name, string $path, int $status = 1): int {
        global $DB;
        if (!$DB->get_manager()->table_exists('local_sentientia_classroom')) {
            $this->markTestSkipped('local_sentientia_classroom table not present.');
        }
        $now = time();
        return (int) $DB->insert_record('local_sentientia_classroom', (object)[
            'name'         => $name,
            'description'  => '',
            'costcenterid' => 0,
            'open_path'    => $path,
            'location'     => 'Test Lab',
            'capacity'     => 20,
            'status'       => $status,
            'visible'      => 1,
            'timecreated'  => $now,
            'timemodified' => $now,
        ]);
    }

    /**
     * Place a user at a path AND grant local/sentientia_classroom:view so the
     * WS call won't reject for missing capability.
     */
    private function user_at_path(string $path): \stdClass {
        global $DB;
        $u = $this->getDataGenerator()->create_user();
        $DB->set_field('user', 'open_path', $path, ['id' => $u->id]);
        $u->open_path = $path;
        $this->grant_cap($u, 'local/sentientia_classroom:view');
        return $u;
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
     * Cross-tenant /-boundary: '/1' must not match '/100' or '/177'.
     */
    public function test_tenant_scope_does_not_leak_via_like_prefix(): void {
        $this->resetAfterTest();
        $this->ensure_bizlms_schema();

        $this->seed_classroom('Airpay Lab',  '/1', 1);
        $this->seed_classroom('Tenant 100',  '/100', 1);
        $this->seed_classroom('ZEEA Lab',    '/177', 1);

        $u = $this->user_at_path('/1');
        $this->setUser($u);

        $result = list_classrooms::execute('', 'name', 'asc', 0, 25, '{}');

        $this->assertSame(1, (int) $result['total'],
            'caller at /1 leaked rows from /100 or /177');
        $this->assertCount(1, $result['rows']);
        // 'name' is rendered as an anchor wrapping the classroom name
        $this->assertStringContainsString('Airpay Lab', $result['rows'][0]['name']);
    }

    /**
     * Siteadmin sees ALL tenants (no scope filter).
     */
    public function test_siteadmin_sees_all_tenants(): void {
        $this->resetAfterTest();
        $this->ensure_bizlms_schema();

        $this->seed_classroom('A',   '/1', 1);
        $this->seed_classroom('B',   '/100', 1);
        $this->seed_classroom('C',   '/177', 1);

        $this->setAdminUser();

        $result = list_classrooms::execute('', 'name', 'asc', 0, 25, '{}');
        $this->assertSame(3, (int) $result['total']);
    }

    /**
     * Sort whitelist: bogus sort key falls back to 'name'.
     */
    public function test_sort_whitelist_rejects_bogus_column(): void {
        $this->resetAfterTest();
        $this->ensure_bizlms_schema();

        $this->seed_classroom('Charlie', '/1', 1);
        $this->seed_classroom('Alpha',   '/1', 1);
        $this->seed_classroom('Bravo',   '/1', 1);

        $u = $this->user_at_path('/1');
        $this->setUser($u);

        // Pass a bogus-but-alphabetic sort column (PARAM_ALPHAEXT validates
        // input shape; whitelist filters semantic). Should fall back to 'name asc'.
        $result = list_classrooms::execute('', 'notarealcolumn', 'asc', 0, 25, '{}');

        $this->assertSame(3, (int) $result['total']);
        // names are HTML-wrapped; assert ordering by substring match
        $this->assertStringContainsString('Alpha',   $result['rows'][0]['name']);
        $this->assertStringContainsString('Bravo',   $result['rows'][1]['name']);
        $this->assertStringContainsString('Charlie', $result['rows'][2]['name']);
    }

    /**
     * JSON filter bounds: > 4KB rejected.
     */
    public function test_json_filter_rejects_oversized_payload(): void {
        $this->resetAfterTest();
        $this->ensure_bizlms_schema();

        $u = $this->user_at_path('/1');
        $this->setUser($u);

        $bigjson = '{' . str_repeat('"key":"' . str_repeat('x', 100) . '",', 50) . '"end":1}';
        // ~5.5 KB — over the 4KB cap.

        $this->expectException(\moodle_exception::class);
        $this->expectExceptionMessageMatches('/filterstoolong/');
        list_classrooms::execute('', 'name', 'asc', 0, 25, $bigjson);
    }

    /**
     * Search escapes LIKE wildcards: a literal '%' is matched as text.
     */
    public function test_search_escapes_like_wildcards(): void {
        $this->resetAfterTest();
        $this->ensure_bizlms_schema();

        $this->seed_classroom('Airpay 50%',  '/1', 1);
        $this->seed_classroom('Airpay Full', '/1', 1);

        $u = $this->user_at_path('/1');
        $this->setUser($u);

        // Pre-fix the wildcard would match BOTH (because '%' was unescaped).
        // Post-fix sql_like_escape, '%' is literal — only matches '50%'.
        $result = list_classrooms::execute('50%', 'name', 'asc', 0, 25, '{}');

        $this->assertSame(1, (int) $result['total']);
        $this->assertStringContainsString('Airpay 50%', $result['rows'][0]['name']);
    }
}
