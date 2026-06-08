<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

namespace local_sentientia_learningpath\external;

defined('MOODLE_INTERNAL') || die();

/**
 * Regression tests for list_paths WS.
 *
 * Locks in:
 * - /-bounded prefix: orgpath '/1' must NOT match '/100' or '/177'
 * - Sort whitelist: bogus sort columns fall back to 'name'
 * - JSON filter bounds: > 4KB rejected with 'filterstoolong'
 * - Search escapes LIKE wildcards
 * - Tenant scoping for non-siteadmin callers
 *
 * @package    local_sentientia_learningpath
 * @category   test
 */
final class list_paths_test extends \advanced_testcase {

    use \local_airpay_org\test\bizlms_fixture;

    /**
     * Insert a learning path directly.
     */
    private function seed_path(string $name, string $path, int $status = 1): int {
        global $DB;
        if (!$DB->get_manager()->table_exists('local_sentientia_learningpath')) {
            $this->markTestSkipped('local_sentientia_learningpath table not present.');
        }
        $now = time();
        return (int) $DB->insert_record('local_sentientia_learningpath', (object)[
            'name'         => $name,
            'description'  => '',
            'costcenterid' => 0,
            'open_path'    => $path,
            'status'       => $status,
            'visible'      => 1,
            'timecreated'  => $now,
            'timemodified' => $now,
        ]);
    }

    private function user_at_path(string $path): \stdClass {
        global $DB;
        $u = $this->getDataGenerator()->create_user();
        $DB->set_field('user', 'open_path', $path, ['id' => $u->id]);
        $u->open_path = $path;
        $this->grant_cap($u, 'local/sentientia_learningpath:view');
        return $u;
    }

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

        $this->seed_path('Airpay Onboarding', '/1', 1);
        $this->seed_path('Tenant 100 Path',   '/100', 1);
        $this->seed_path('ZEEA Path',         '/177', 1);

        $u = $this->user_at_path('/1');
        $this->setUser($u);

        $result = list_paths::execute('', 'name', 'asc', 0, 25, '{}');

        $this->assertSame(1, (int) $result['total'],
            'caller at /1 leaked rows from /100 or /177');
        $this->assertCount(1, $result['rows']);
        $this->assertSame('Airpay Onboarding', $result['rows'][0]['name']);
    }

    /**
     * Siteadmin sees all tenants.
     */
    public function test_siteadmin_sees_all_tenants(): void {
        $this->resetAfterTest();
        $this->ensure_bizlms_schema();

        $this->seed_path('A', '/1',   1);
        $this->seed_path('B', '/100', 1);
        $this->seed_path('C', '/177', 1);

        $this->setAdminUser();
        $result = list_paths::execute('', 'name', 'asc', 0, 25, '{}');
        $this->assertSame(3, (int) $result['total']);
    }

    /**
     * Sort whitelist.
     */
    public function test_sort_whitelist_rejects_bogus_column(): void {
        $this->resetAfterTest();
        $this->ensure_bizlms_schema();

        $this->seed_path('Charlie', '/1', 1);
        $this->seed_path('Alpha',   '/1', 1);
        $this->seed_path('Bravo',   '/1', 1);

        $u = $this->user_at_path('/1');
        $this->setUser($u);

        $result = list_paths::execute('', 'notarealcolumn', 'asc', 0, 25, '{}');

        $this->assertSame(3, (int) $result['total']);
        $this->assertSame('Alpha',   $result['rows'][0]['name']);
        $this->assertSame('Bravo',   $result['rows'][1]['name']);
        $this->assertSame('Charlie', $result['rows'][2]['name']);
    }

    /**
     * JSON filter bounds.
     */
    public function test_json_filter_rejects_oversized_payload(): void {
        $this->resetAfterTest();
        $this->ensure_bizlms_schema();

        $u = $this->user_at_path('/1');
        $this->setUser($u);

        $bigjson = '{' . str_repeat('"key":"' . str_repeat('x', 100) . '",', 50) . '"end":1}';

        $this->expectException(\moodle_exception::class);
        $this->expectExceptionMessageMatches('/filterstoolong/');
        list_paths::execute('', 'name', 'asc', 0, 25, $bigjson);
    }

    /**
     * Search escapes LIKE wildcards.
     */
    public function test_search_escapes_like_wildcards(): void {
        $this->resetAfterTest();
        $this->ensure_bizlms_schema();

        $this->seed_path('Airpay 50%',  '/1', 1);
        $this->seed_path('Airpay Full', '/1', 1);

        $u = $this->user_at_path('/1');
        $this->setUser($u);

        $result = list_paths::execute('50%', 'name', 'asc', 0, 25, '{}');

        $this->assertSame(1, (int) $result['total']);
        $this->assertSame('Airpay 50%', $result['rows'][0]['name']);
    }
}
