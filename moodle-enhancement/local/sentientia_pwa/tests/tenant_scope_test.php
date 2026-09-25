<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

namespace local_sentientia_pwa;

defined('MOODLE_INTERNAL') || die();

/**
 * ADR-031: the push delivery log is confined to the viewer's tenant, and
 * local/sentientia_pwa:manage no longer defaults to the manager archetype.
 *
 * Until 2026-09-25 every tenant admin (a manager-archetype role at system
 * context) could open admin/push_log.php and page through every tenant's
 * push recipients, or type any tenant's user id into the userid filter.
 *
 * @package    local_sentientia_pwa
 * @category   test
 * @covers     \local_sentientia_pwa\push_logger
 * @group      tenant_isolation
 */
final class tenant_scope_test extends \advanced_testcase {

    use \local_sentientia_org\test\bizlms_fixture;

    private const MANAGE = 'local/sentientia_pwa:manage';

    protected function setUp(): void {
        parent::setUp();
        $this->resetAfterTest();
        $this->ensure_bizlms_schema();
    }

    private function user_at(string $path): \stdClass {
        global $DB;
        $u = $this->getDataGenerator()->create_user();
        $DB->set_field('user', 'open_path', $path, ['id' => $u->id]);
        return $DB->get_record('user', ['id' => $u->id], '*', MUST_EXIST);
    }

    /** A tenant admin at $path: the manager role plus, explicitly, :manage. */
    private function tenant_admin(string $path): \stdClass {
        global $DB;
        $u = $this->user_at($path);
        $sys = \context_system::instance();
        $managerid = (int) $DB->get_field('role', 'id', ['shortname' => 'manager'], MUST_EXIST);
        role_assign($managerid, $u->id, $sys->id);
        $roleid = $this->getDataGenerator()->create_role();
        assign_capability(self::MANAGE, CAP_ALLOW, $roleid, $sys->id);
        role_assign($roleid, $u->id, $sys->id);
        accesslib_clear_all_caches_for_unit_testing();
        return $u;
    }

    private function push_row(\stdClass $user): int {
        global $DB;
        return (int) $DB->insert_record('local_sentientia_push_log', (object) [
            'userid'        => $user->id,
            'endpoint_host' => 'fcm.googleapis.com',
            'title'         => '[hash:test]',
            'http_code'     => 201,
            'result'        => 'sent',
            'sent_at'       => time(),
        ]);
    }

    private function ids(array $rows): array {
        return array_map('intval', array_keys($rows));
    }

    public function test_manage_has_no_default_holder(): void {
        global $DB;
        $this->assertFalse($DB->record_exists('role_capabilities', ['capability' => self::MANAGE]),
            'The push log is platform operations: no role may hold :manage by default.');
    }

    public function test_tenant_admin_sees_only_their_tenants_push_log(): void {
        $own = $this->push_row($this->user_at('/1/5'));
        $zeeauser = $this->user_at('/177/178');
        $foreign = $this->push_row($zeeauser);
        $this->setUser($this->tenant_admin('/1'));

        $ids = $this->ids(push_logger::recent(50, 0, []));
        $this->assertContains($own, $ids);
        $this->assertNotContains($foreign, $ids);
        $this->assertSame([], push_logger::recent(50, 0, ['userid' => (int) $zeeauser->id]),
            'The userid filter must not reach a user in another tenant.');
        $this->assertSame(0, push_logger::count(['userid' => (int) $zeeauser->id]));
        $this->assertSame(1, push_logger::count([]));
        $this->assertSame(1, push_logger::stats_last_24h()['total_24h']);
    }

    public function test_viewer_with_no_tenant_sees_nothing(): void {
        $this->push_row($this->user_at('/1/5'));
        $this->setUser($this->tenant_admin(''));

        $this->assertSame([], push_logger::recent(50, 0, []));
        $this->assertSame(0, push_logger::count([]));
        $this->assertSame(0, push_logger::stats_last_24h()['total_24h']);
    }

    public function test_site_admin_still_sees_every_tenant(): void {
        $own = $this->push_row($this->user_at('/1/5'));
        $foreign = $this->push_row($this->user_at('/177/178'));
        $this->setAdminUser();

        $ids = $this->ids(push_logger::recent(50, 0, []));
        $this->assertContains($own, $ids);
        $this->assertContains($foreign, $ids);
        $this->assertSame(2, push_logger::count([]));
        $this->assertSame(2, push_logger::stats_last_24h()['total_24h']);
    }
}
