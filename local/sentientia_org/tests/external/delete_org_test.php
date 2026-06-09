<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

namespace local_sentientia_org\external;

defined('MOODLE_INTERNAL') || die();

/**
 * Security regression tests for delete_org WS — H3 tenant scope.
 *
 * @package    local_sentientia_org
 * @category   test
 */
final class delete_org_test extends \advanced_testcase {

    use \local_sentientia_org\test\bizlms_fixture;

    private function seed_org(string $path, int $parentid = 0, int $depth = 1): int {
        global $DB;
        if (!$DB->get_manager()->table_exists('local_sentientia_org')) {
            $this->markTestSkipped('local_sentientia_org table not present.');
        }
        $id = (int) ltrim(strrchr($path, '/'), '/');
        $rec = (object)[
            'id' => $id, 'fullname' => "Org $id", 'shortname' => "org_$id",
            'parentid' => $parentid, 'path' => $path, 'depth' => $depth,
            'visible' => 1, 'sortorder' => 0,
            'timecreated' => time(), 'timemodified' => time(),
        ];
        $DB->insert_record_raw('local_sentientia_org', $rec, false, false, true);
        return $id;
    }

    private function manager_at_path(string $path): \stdClass {
        global $DB;
        $u = $this->getDataGenerator()->create_user();
        $DB->set_field('user', 'open_path', $path, ['id' => $u->id]);
        $u->open_path = $path;
        $sysctx = \context_system::instance();
        $roleid = $this->getDataGenerator()->create_role();
        role_change_permission($roleid, $sysctx, 'local/sentientia_org:manage', CAP_ALLOW);
        role_assign($roleid, $u->id, $sysctx->id);
        return $u;
    }

    /**
     * H3: caller in /8002 cannot delete an org leaf in /8001.
     */
    public function test_h3_cross_tenant_delete_rejected(): void {
        $this->resetAfterTest();
        $this->ensure_bizlms_schema();

        $tenant_a = $this->seed_org('/8001', 0, 1);
        $leaf     = $this->seed_org('/8001/9001', $tenant_a, 2);

        $this->seed_org('/8002', 0, 1);
        $caller = $this->manager_at_path('/8002');
        $this->setUser($caller);

        $this->expectException(\moodle_exception::class);
        $this->expectExceptionMessage('outoftenant');
        delete_org::execute($leaf);
    }

    /**
     * H3 happy path: caller in /8001 can delete a leaf in /8001.
     */
    public function test_in_tenant_delete_works(): void {
        global $DB;
        $this->resetAfterTest();
        $this->ensure_bizlms_schema();

        $tenant = $this->seed_org('/8101', 0, 1);
        $leaf   = $this->seed_org('/8101/9101', $tenant, 2);

        $caller = $this->manager_at_path('/8101');
        $this->setUser($caller);

        $r = delete_org::execute($leaf);
        $this->assertTrue($r['success']);
        $this->assertFalse($DB->record_exists('local_sentientia_org', ['id' => $leaf]));
    }

    /**
     * Siteadmin can delete any leaf regardless of tenant.
     */
    public function test_siteadmin_can_delete_any(): void {
        global $DB;
        $this->resetAfterTest();
        $this->ensure_bizlms_schema();
        $this->setAdminUser();

        $tenant = $this->seed_org('/8201', 0, 1);
        $leaf   = $this->seed_org('/8201/9201', $tenant, 2);

        $r = delete_org::execute($leaf);
        $this->assertTrue($r['success']);
        $this->assertFalse($DB->record_exists('local_sentientia_org', ['id' => $leaf]));
    }

    /**
     * Tenant deletion still blocked even for siteadmin (depth=1 rule).
     * Assert errorcode rather than message — the message resolves to a
     * translated English lang string ("Top-level tenants cannot be...")
     * which makes the assertion brittle to locale changes.
     */
    public function test_tenant_deletion_still_blocked_for_siteadmin(): void {
        $this->resetAfterTest();
        $this->ensure_bizlms_schema();
        $this->setAdminUser();

        $tenant = $this->seed_org('/8301', 0, 1);

        try {
            delete_org::execute($tenant);
            $this->fail('Expected moodle_exception cannotdeletetenant but none was thrown');
        } catch (\moodle_exception $e) {
            $this->assertSame('cannotdeletetenant', $e->errorcode);
        }
    }
}
