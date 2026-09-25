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
 * @covers     \local_sentientia_request\external\list_all
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
}
