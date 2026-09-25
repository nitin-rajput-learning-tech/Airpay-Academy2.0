<?php
// This file is part of Sentientia LMS.

/**
 * ADR-031: who may open the DPDP administration panel.
 *
 * index.php's admin branch lists every tenant's privacy requests (names,
 * emails, reasons) with no tenant filter, and its Approve action erases the
 * requester's data for any request id. Until 2026-09-25 it opened for
 * is_siteadmin() OR local/sentientia_privacy:manage alone. :manage has no
 * archetype default, so nobody but site admins reached it, but a per-tenant
 * DPO grant - the obvious one to make - would have let one tenant's DPO read
 * and erase another tenant's people. ADR-031 decision 3: a plugin capability
 * says WHAT, never WHERE, so :manage now needs is_cross_tenant() as well.
 *
 * @package    local_sentientia_privacy
 * @category   test
 * @copyright  2026 Airpay Payment Services
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_sentientia_privacy;

defined('MOODLE_INTERNAL') || die();

/**
 * @covers \local_sentientia_privacy\privacy_manager::can_administer
 * @group tenant_isolation
 */
final class admin_scope_test extends \advanced_testcase {

    use \local_sentientia_org\test\bizlms_fixture;

    protected function setUp(): void {
        parent::setUp();
        $this->resetAfterTest();
        $this->ensure_bizlms_schema();
    }

    /** A user at $path, as a tenant admin is on UAT: a manager-archetype role at system context. */
    private function tenant_admin(string $path): \stdClass {
        global $DB;
        $u = $this->getDataGenerator()->create_user();
        $DB->set_field('user', 'open_path', $path, ['id' => $u->id]);
        $managerroleid = (int) $DB->get_field('role', 'id', ['shortname' => 'manager'], MUST_EXIST);
        role_assign($managerroleid, $u->id, \context_system::instance()->id);
        return $DB->get_record('user', ['id' => $u->id], '*', MUST_EXIST);
    }

    /** Give $userid a fresh system-context role carrying $capabilities. */
    private function grant(int $userid, string ...$capabilities): void {
        $syscontext = \context_system::instance();
        $roleid = $this->getDataGenerator()->create_role();
        foreach ($capabilities as $capability) {
            assign_capability($capability, CAP_ALLOW, $roleid, $syscontext->id, true);
        }
        role_assign($roleid, $userid, $syscontext->id);
        accesslib_clear_all_caches_for_unit_testing();
    }

    public function test_manage_alone_does_not_open_the_unscoped_panel(): void {
        $dpo = $this->tenant_admin('/177/178');
        $this->grant((int) $dpo->id, privacy_manager::MANAGE_CAPABILITY);
        $this->setUser($dpo);

        $this->assertTrue(has_capability(privacy_manager::MANAGE_CAPABILITY, \context_system::instance()),
            'Precondition: the per-tenant DPO grant is in place.');
        $this->assertFalse(\local_sentientia_platform\tenant::is_cross_tenant(),
            'Precondition: a tenant DPO is not cross-tenant.');
        $this->assertFalse(privacy_manager::can_administer(),
            'A tenant DPO holding :manage must not see or erase every tenant\'s requests.');
        $this->assertFalse(privacy_manager::can_administer((int) $dpo->id),
            'Same answer when asked for by id.');
    }

    public function test_a_tenant_admin_without_manage_is_refused(): void {
        $admin = $this->tenant_admin('/1');
        $this->setUser($admin);
        $this->assertFalse(has_capability(privacy_manager::MANAGE_CAPABILITY, \context_system::instance()),
            'Precondition: :manage has no archetype default.');
        $this->assertFalse(privacy_manager::can_administer());
    }

    public function test_crosstenant_alone_does_not_grant_the_function(): void {
        $platform = $this->tenant_admin('/1');
        $this->grant((int) $platform->id, \local_sentientia_platform\tenant::CROSS_TENANT_CAPABILITY);
        $this->setUser($platform);

        $this->assertTrue(\local_sentientia_platform\tenant::is_cross_tenant());
        $this->assertFalse(privacy_manager::can_administer(),
            ':crosstenant says WHERE; running DPDP requests still needs :manage.');
    }

    public function test_manage_plus_crosstenant_and_site_admins_still_administer(): void {
        $platform = $this->tenant_admin('/1');
        $this->grant((int) $platform->id, privacy_manager::MANAGE_CAPABILITY,
            \local_sentientia_platform\tenant::CROSS_TENANT_CAPABILITY);
        $this->setUser($platform);
        $this->assertTrue(privacy_manager::can_administer(),
            'A cross-tenant :manage holder keeps the panel.');

        $this->setAdminUser();
        $this->assertTrue(privacy_manager::can_administer(), 'The site admin keeps the panel.');
        $this->assertTrue(privacy_manager::can_administer((int) $platform->id),
            'Asked by id for somebody else, the answer is about that user.');
    }

    public function test_nobody_and_the_guest_are_refused(): void {
        $this->setUser(null);
        $this->assertFalse(privacy_manager::can_administer());
        $this->assertFalse(privacy_manager::can_administer(0));
        $this->assertFalse(privacy_manager::can_administer((int) guest_user()->id));

        $learner = $this->getDataGenerator()->create_user();
        $this->setUser($learner);
        $this->assertFalse(privacy_manager::can_administer());
    }
}
