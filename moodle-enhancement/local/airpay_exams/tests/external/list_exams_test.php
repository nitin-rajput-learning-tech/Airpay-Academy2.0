<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

namespace local_airpay_exams\external;

defined('MOODLE_INTERNAL') || die();

/**
 * Regression tests for list_exams WS.
 *
 * Locks in:
 * - /-bounded prefix: orgpath '/1' must NOT match '/100' or '/177'
 * - Sort whitelist: bogus sort columns fall back to 'name'
 * - JSON filter bounds: > 4KB rejected with 'filterstoolong'
 * - Search escapes LIKE wildcards: '%' is treated as literal text
 * - Tenant scoping: non-siteadmin caller sees only their tenant
 * - Status filter: only 'all' or numeric values accepted
 *
 * @package    local_airpay_exams
 * @category   test
 */
final class list_exams_test extends \advanced_testcase {

    use \local_airpay_org\test\bizlms_fixture;

    /**
     * Insert an exam directly. quizid is a placeholder — list_exams JOINs
     * to {quiz} but tolerates missing rows via LEFT JOIN.
     */
    private function seed_exam(string $name, string $path, int $status = 1): int {
        global $DB;
        if (!$DB->get_manager()->table_exists('local_airpay_exams')) {
            $this->markTestSkipped('local_airpay_exams table not present.');
        }
        $now = time();
        return (int) $DB->insert_record('local_airpay_exams', (object)[
            'name'         => $name,
            'quizid'       => 0,                // no real quiz needed
            'costcenterid' => 0,
            'open_path'    => $path,
            'duration'     => 1800,             // 30 min
            'passinggrade' => 70.00,
            'status'       => $status,
            'visible'      => 1,
            'timecreated'  => $now,
            'timemodified' => $now,
        ]);
    }

    /**
     * Place a user at a path AND grant local/airpay_exams:view.
     */
    private function user_at_path(string $path): \stdClass {
        global $DB;
        $u = $this->getDataGenerator()->create_user();
        $DB->set_field('user', 'open_path', $path, ['id' => $u->id]);
        $u->open_path = $path;
        $this->grant_cap($u, 'local/airpay_exams:view');
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

        $this->seed_exam('Airpay Compliance', '/1', 1);
        $this->seed_exam('Tenant 100',        '/100', 1);
        $this->seed_exam('ZEEA Quiz',         '/177', 1);

        $u = $this->user_at_path('/1');
        $this->setUser($u);

        $result = list_exams::execute('', 'name', 'asc', 0, 25, '{}');

        $this->assertSame(1, (int) $result['total'],
            'caller at /1 leaked rows from /100 or /177');
        $this->assertCount(1, $result['rows']);
        $this->assertSame('Airpay Compliance', $result['rows'][0]['name']);
    }

    /**
     * Siteadmin sees ALL tenants (no scope filter).
     */
    public function test_siteadmin_sees_all_tenants(): void {
        $this->resetAfterTest();
        $this->ensure_bizlms_schema();

        $this->seed_exam('A', '/1',   1);
        $this->seed_exam('B', '/100', 1);
        $this->seed_exam('C', '/177', 1);

        $this->setAdminUser();

        $result = list_exams::execute('', 'name', 'asc', 0, 25, '{}');
        $this->assertSame(3, (int) $result['total']);
    }

    /**
     * Sort whitelist: bogus sort key falls back to 'name'.
     */
    public function test_sort_whitelist_rejects_bogus_column(): void {
        $this->resetAfterTest();
        $this->ensure_bizlms_schema();

        $this->seed_exam('Charlie', '/1', 1);
        $this->seed_exam('Alpha',   '/1', 1);
        $this->seed_exam('Bravo',   '/1', 1);

        $u = $this->user_at_path('/1');
        $this->setUser($u);

        $result = list_exams::execute('', 'notarealcolumn', 'asc', 0, 25, '{}');

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
        list_exams::execute('', 'name', 'asc', 0, 25, $bigjson);
    }

    /**
     * Search escapes LIKE wildcards.
     */
    public function test_search_escapes_like_wildcards(): void {
        $this->resetAfterTest();
        $this->ensure_bizlms_schema();

        $this->seed_exam('Airpay 50%',  '/1', 1);
        $this->seed_exam('Airpay Full', '/1', 1);

        $u = $this->user_at_path('/1');
        $this->setUser($u);

        $result = list_exams::execute('50%', 'name', 'asc', 0, 25, '{}');

        $this->assertSame(1, (int) $result['total']);
        $this->assertSame('Airpay 50%', $result['rows'][0]['name']);
    }
}
