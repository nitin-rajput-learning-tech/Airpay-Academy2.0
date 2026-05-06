<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

namespace local_airpay_evaluation\external;

defined('MOODLE_INTERNAL') || die();

/**
 * Regression tests for list_evaluations WS.
 *
 * Locks in:
 * - /-bounded prefix: orgpath '/1' must NOT match '/100' or '/177'
 * - Sort whitelist: bogus sort columns fall back to 'name'
 * - JSON filter bounds: > 4KB rejected with 'filterstoolong'
 * - Search escapes LIKE wildcards: '%' is treated as literal text
 * - Siteadmin scope: siteadmin sees all tenants
 *
 * @package    local_airpay_evaluation
 * @category   test
 */
final class list_evaluations_test extends \advanced_testcase {

    use \local_airpay_org\test\bizlms_fixture;

    /**
     * Insert an evaluation directly. Tenant scoping is via open_path.
     */
    private function seed_evaluation(string $name, string $path, int $status = 1): int {
        global $DB;
        if (!$DB->get_manager()->table_exists('local_airpay_evaluation')) {
            $this->markTestSkipped('local_airpay_evaluation table not present.');
        }
        $now = time();
        return (int) $DB->insert_record('local_airpay_evaluation', (object)[
            'name'             => $name,
            'description'      => '',
            'kirkpatrick_level' => 1,
            'trigger_event'    => 'manual',
            'days_after'       => 0,
            'costcenterid'     => 0,
            'open_path'        => $path,
            'status'           => $status,
            'anonymous'        => 0,
            'timecreated'      => $now,
            'timemodified'     => $now,
        ]);
    }

    /**
     * Place a user at a path AND grant local/airpay_evaluation:view.
     */
    private function user_at_path(string $path): \stdClass {
        global $DB;
        $u = $this->getDataGenerator()->create_user();
        $DB->set_field('user', 'open_path', $path, ['id' => $u->id]);
        $u->open_path = $path;
        $this->grant_cap($u, 'local/airpay_evaluation:manage');
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

        $this->seed_evaluation('Airpay POSH Survey', '/1', 1);
        $this->seed_evaluation('Tenant 100',         '/100', 1);
        $this->seed_evaluation('ZEEA Feedback',      '/177', 1);

        $u = $this->user_at_path('/1');
        $this->setUser($u);

        $result = list_evaluations::execute('', 'name', 'asc', 0, 25, '{}');

        $this->assertSame(1, (int) $result['total'],
            'caller at /1 leaked rows from /100 or /177');
        $this->assertCount(1, $result['rows']);
        $this->assertSame('Airpay POSH Survey', $result['rows'][0]['name']);
    }

    /**
     * Siteadmin sees ALL tenants (no scope filter).
     */
    public function test_siteadmin_sees_all_tenants(): void {
        $this->resetAfterTest();
        $this->ensure_bizlms_schema();

        $this->seed_evaluation('A', '/1',   1);
        $this->seed_evaluation('B', '/100', 1);
        $this->seed_evaluation('C', '/177', 1);

        $this->setAdminUser();

        $result = list_evaluations::execute('', 'name', 'asc', 0, 25, '{}');
        $this->assertSame(3, (int) $result['total']);
    }

    /**
     * Sort whitelist: bogus sort key falls back to 'name'.
     */
    public function test_sort_whitelist_rejects_bogus_column(): void {
        $this->resetAfterTest();
        $this->ensure_bizlms_schema();

        $this->seed_evaluation('Charlie', '/1', 1);
        $this->seed_evaluation('Alpha',   '/1', 1);
        $this->seed_evaluation('Bravo',   '/1', 1);

        $u = $this->user_at_path('/1');
        $this->setUser($u);

        $result = list_evaluations::execute('', 'notarealcolumn', 'asc', 0, 25, '{}');

        $this->assertSame(3, (int) $result['total']);
        $this->assertSame('Alpha',   $result['rows'][0]['name']);
        $this->assertSame('Bravo',   $result['rows'][1]['name']);
        $this->assertSame('Charlie', $result['rows'][2]['name']);
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

        $this->expectException(\moodle_exception::class);
        $this->expectExceptionMessageMatches('/filterstoolong/');
        list_evaluations::execute('', 'name', 'asc', 0, 25, $bigjson);
    }

    /**
     * Search escapes LIKE wildcards.
     */
    public function test_search_escapes_like_wildcards(): void {
        $this->resetAfterTest();
        $this->ensure_bizlms_schema();

        $this->seed_evaluation('Airpay 50% Eval', '/1', 1);
        $this->seed_evaluation('Airpay Full',     '/1', 1);

        $u = $this->user_at_path('/1');
        $this->setUser($u);

        $result = list_evaluations::execute('50%', 'name', 'asc', 0, 25, '{}');

        $this->assertSame(1, (int) $result['total']);
        $this->assertSame('Airpay 50% Eval', $result['rows'][0]['name']);
    }
}
