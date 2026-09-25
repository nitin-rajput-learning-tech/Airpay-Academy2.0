<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

namespace local_sentientia_request;

defined('MOODLE_INTERNAL') || die();

/**
 * ADR-031: the "All requests" list is confined to the caller's tenant.
 *
 * :viewall defaults to the manager archetype (and db/install.php grants it
 * to the 'administrator' role), so every tenant admin holds it. Until
 * 2026-09-25 list_all started from 1=1 and took the tenant from the client's
 * filters JSON: every tenant's requesters, emails, reasons and decision notes.
 *
 * @package    local_sentientia_request
 * @category   test
 * It also pins decide(): an :overrideroute holder (db/install.php grants it
 * to the 'administrator' tenant-admin role) decides only their own tenant's
 * requests, and one whose tenant does not resolve decides nothing - root 0
 * used to equal a costcenterid-0 request and let them approve it.
 *
 * @covers     \local_sentientia_request\external\list_all
 * @covers     \local_sentientia_request\request_manager::decide
 * @group      tenant_isolation
 */
final class tenant_scope_test extends \advanced_testcase {

    use \local_sentientia_org\test\bizlms_fixture;

    protected function setUp(): void {
        parent::setUp();
        $this->resetAfterTest();
        $this->ensure_bizlms_schema();
    }

    /** A user at $path holding the manager role at system context (a tenant admin). */
    private function tenant_admin(string $path): \stdClass {
        global $DB;
        $u = $this->getDataGenerator()->create_user();
        $DB->set_field('user', 'open_path', $path, ['id' => $u->id]);
        $managerid = (int) $DB->get_field('role', 'id', ['shortname' => 'manager'], MUST_EXIST);
        role_assign($managerid, $u->id, \context_system::instance()->id);
        accesslib_clear_all_caches_for_unit_testing();
        return $DB->get_record('user', ['id' => $u->id], '*', MUST_EXIST);
    }

    private function request(int $tenant): int {
        global $DB;
        $requester = $this->getDataGenerator()->create_user();
        $course = $this->getDataGenerator()->create_course();
        return (int) $DB->insert_record('local_sentientia_request', (object) [
            'userid'       => $requester->id,
            'item_type'    => 'course',
            'itemid'       => $course->id,
            'courseid'     => $course->id,
            'costcenterid' => $tenant,
            'reason'       => 'Need it for tenant ' . $tenant,
            'status'       => 'pending',
            'route'        => 'admin',
            'timecreated'  => time(),
            'timemodified' => time(),
        ]);
    }

    private function listed_ids(string $filters = '{}'): array {
        $result = external\list_all::execute('', 'timecreated', 'desc', 0, 100, $filters);
        return array_map('intval', array_column($result['rows'], 'id'));
    }

    public function test_tenant_admin_lists_only_their_tenant(): void {
        $own = $this->request(1);
        $foreign = $this->request(177);
        $orphan = $this->request(0);
        $this->setUser($this->tenant_admin('/1'));

        foreach (['{}', '{"tenant":177}', '{"tenant":0}'] as $filters) {
            $ids = $this->listed_ids($filters);
            $this->assertContains($own, $ids);
            $this->assertNotContains($foreign, $ids, "filters={$filters} must not reach tenant 177.");
            $this->assertNotContains($orphan, $ids);
        }
    }

    public function test_caller_with_no_tenant_gets_nothing(): void {
        $this->request(1);
        $this->request(0);
        $this->setUser($this->tenant_admin(''));

        $result = external\list_all::execute();
        $this->assertSame(0, $result['total'],
            'No tenant must not mean every tenant - nor the costcenterid 0 rows.');
        $this->assertSame([], $result['rows']);
    }

    public function test_site_admin_still_lists_every_tenant(): void {
        $own = $this->request(1);
        $foreign = $this->request(177);
        $this->setAdminUser();

        $ids = $this->listed_ids();
        $this->assertContains($own, $ids);
        $this->assertContains($foreign, $ids);
        $this->assertSame([$foreign], $this->listed_ids('{"tenant":177}'));
    }

    public function test_list_all_page_size_is_bounded_and_stays_in_tenant(): void {
        $own = [$this->request(1), $this->request(1), $this->request(1)];
        $foreign = $this->request(177);
        $this->setUser($this->tenant_admin('/1'));

        $all = external\list_all::execute('', 'timecreated', 'desc', 0, 100000);
        $this->assertSame(external\list_all::MAX_PERPAGE, $all['perpage']);
        $this->assertSame(3, $all['total']);
        $ids = array_map('intval', array_column($all['rows'], 'id'));
        sort($ids);
        $this->assertSame($own, $ids);
        $this->assertNotContains($foreign, $ids);

        $one = external\list_all::execute('', 'timecreated', 'desc', -3, 0);
        $this->assertSame(1, $one['perpage'], 'perpage 0 or less is one row, not an unbounded or broken query.');
        $this->assertSame(0, $one['page']);
        $this->assertCount(1, $one['rows']);
    }

    /** A user at $path holding :overrideroute, as db/install.php grants it to the tenant-admin role. */
    private function override_router(string $path): \stdClass {
        global $DB;
        $u = $this->tenant_admin($path);
        $sys = \context_system::instance();
        $roleid = $this->getDataGenerator()->create_role();
        assign_capability('local/sentientia_request:overrideroute', CAP_ALLOW, $roleid, $sys->id);
        role_assign($roleid, $u->id, $sys->id);
        accesslib_clear_all_caches_for_unit_testing();
        return $u;
    }

    private function assert_decide_refused(int $requestid, int $deciderid, string $why): void {
        global $DB;
        try {
            request_manager::decide($requestid, $deciderid, 'approved', 'ok');
            $this->fail($why);
        } catch (\moodle_exception $e) {
            $this->assertSame('error_outoftenant', $e->errorcode, $why);
        }
        $this->assertSame('pending', $DB->get_field('local_sentientia_request', 'status', ['id' => $requestid]),
            $why . ' The request must still be pending.');
    }

    public function test_override_router_with_no_tenant_cannot_decide_a_tenantless_request(): void {
        $orphan = $this->request(0);
        $own = $this->request(1);
        $router = $this->override_router('');
        $this->setUser($router);

        $this->assert_decide_refused($orphan, (int) $router->id,
            'Root 0 must not match a costcenterid-0 request.');
        $this->assert_decide_refused($own, (int) $router->id,
            'A decider with no tenant is in nobody\'s tenant.');
    }

    public function test_override_router_decides_only_their_own_tenant(): void {
        global $DB;
        $own = $this->request(1);
        $foreign = $this->request(177);
        $orphan = $this->request(0);
        $router = $this->override_router('/1');
        $this->setUser($router);
        $sink = $this->redirectMessages();

        $this->assert_decide_refused($foreign, (int) $router->id, 'Another tenant\'s request is refused.');
        $this->assert_decide_refused($orphan, (int) $router->id,
            'A tenant-less request is cross-tenant only, even for a tenant-scoped router.');

        request_manager::decide($own, (int) $router->id, 'rejected', 'Not this quarter');
        $this->assertSame('rejected', $DB->get_field('local_sentientia_request', 'status', ['id' => $own]));
        $sink->close();
    }

    public function test_site_admin_can_still_decide_a_tenantless_request(): void {
        global $DB;
        $orphan = $this->request(0);
        $this->setAdminUser();
        $sink = $this->redirectMessages();

        request_manager::decide($orphan, (int) get_admin()->id, 'rejected', 'Out of scope');
        $this->assertSame('rejected', $DB->get_field('local_sentientia_request', 'status', ['id' => $orphan]));
        $sink->close();
    }
}
