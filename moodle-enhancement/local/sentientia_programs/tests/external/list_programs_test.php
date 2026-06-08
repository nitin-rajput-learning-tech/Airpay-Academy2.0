<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

namespace local_sentientia_programs\external;

defined('MOODLE_INTERNAL') || die();

/**
 * Regression tests for list_programs WS.
 *
 * Locks in:
 * - /-bounded prefix: orgpath '/1' must NOT match '/100' or '/177'
 * - Sort whitelist: bogus sort columns fall back to 'name'
 * - JSON filter bounds: > 4KB rejected with 'filterstoolong'
 * - Search escapes LIKE wildcards: '%' is treated as literal text
 * - Siteadmin scope: siteadmin sees all tenants
 *
 * @package    local_sentientia_programs
 * @category   test
 */
final class list_programs_test extends \advanced_testcase {

    use \local_airpay_org\test\bizlms_fixture;

    /**
     * Insert a program directly. costcenterid is left at 0; tenant
     * scoping is via open_path.
     */
    private function seed_program(string $name, string $path, int $status = 1): int {
        global $DB;
        if (!$DB->get_manager()->table_exists('local_sentientia_programs')) {
            $this->markTestSkipped('local_sentientia_programs table not present.');
        }
        $now = time();
        return (int) $DB->insert_record('local_sentientia_programs', (object)[
            'name'                => $name,
            'description'         => '',
            'costcenterid'        => 0,
            'open_path'           => $path,
            'status'              => $status,
            'visible'             => 1,
            'completion_required' => 1,
            'timecreated'         => $now,
            'timemodified'        => $now,
        ]);
    }

    /**
     * Place a user at a path AND grant local/sentientia_programs:view.
     */
    private function user_at_path(string $path): \stdClass {
        global $DB;
        $u = $this->getDataGenerator()->create_user();
        $DB->set_field('user', 'open_path', $path, ['id' => $u->id]);
        $u->open_path = $path;
        $this->grant_cap($u, 'local/sentientia_programs:view');
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
     * This is the bug fixed by ac22501e8 (cross-tenant LIKE over-count fix).
     */
    public function test_tenant_scope_does_not_leak_via_like_prefix(): void {
        $this->resetAfterTest();
        $this->ensure_bizlms_schema();

        $this->seed_program('Airpay Onboarding', '/1', 1);
        $this->seed_program('Tenant 100 Path',   '/100', 1);
        $this->seed_program('ZEEA Programs',     '/177', 1);

        $u = $this->user_at_path('/1');
        $this->setUser($u);

        $result = list_programs::execute('', 'name', 'asc', 0, 25, '{}');

        $this->assertSame(1, (int) $result['total'],
            'caller at /1 leaked rows from /100 or /177');
        $this->assertCount(1, $result['rows']);
        // Name is wrapped in an <a href="view.php?id=N"> link (G-03 nav fix);
        // the literal program name is still substring-present.
        $this->assertStringContainsString('Airpay Onboarding', $result['rows'][0]['name']);
    }

    /**
     * Siteadmin sees ALL tenants (no scope filter).
     */
    public function test_siteadmin_sees_all_tenants(): void {
        $this->resetAfterTest();
        $this->ensure_bizlms_schema();

        $this->seed_program('A', '/1',   1);
        $this->seed_program('B', '/100', 1);
        $this->seed_program('C', '/177', 1);

        $this->setAdminUser();

        $result = list_programs::execute('', 'name', 'asc', 0, 25, '{}');
        $this->assertSame(3, (int) $result['total']);
    }

    /**
     * Sort whitelist: bogus sort key falls back to 'name'.
     */
    public function test_sort_whitelist_rejects_bogus_column(): void {
        $this->resetAfterTest();
        $this->ensure_bizlms_schema();

        $this->seed_program('Charlie', '/1', 1);
        $this->seed_program('Alpha',   '/1', 1);
        $this->seed_program('Bravo',   '/1', 1);

        $u = $this->user_at_path('/1');
        $this->setUser($u);

        $result = list_programs::execute('', 'notarealcolumn', 'asc', 0, 25, '{}');

        $this->assertSame(3, (int) $result['total']);
        // Name is wrapped in an <a> link (G-03); use substring assertion.
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

        $this->expectException(\moodle_exception::class);
        $this->expectExceptionMessageMatches('/filterstoolong/');
        list_programs::execute('', 'name', 'asc', 0, 25, $bigjson);
    }

    /**
     * Search escapes LIKE wildcards: '50%' must match the literal '50%' string,
     * not "anything containing 50".
     */
    public function test_search_escapes_like_wildcards(): void {
        $this->resetAfterTest();
        $this->ensure_bizlms_schema();

        $this->seed_program('Airpay 50% Path', '/1', 1);
        $this->seed_program('Airpay Full',     '/1', 1);

        $u = $this->user_at_path('/1');
        $this->setUser($u);

        $result = list_programs::execute('50%', 'name', 'asc', 0, 25, '{}');

        $this->assertSame(1, (int) $result['total']);
        // Name is wrapped in an <a> link (G-03); use substring assertion.
        $this->assertStringContainsString('Airpay 50% Path', $result['rows'][0]['name']);
    }
}
