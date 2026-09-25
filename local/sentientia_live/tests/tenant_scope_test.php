<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

namespace local_sentientia_live;

defined('MOODLE_INTERNAL') || die();

/**
 * ADR-031: live sessions are run only by their owner or inside the holder's tenant.
 *
 * :manage_all defaulted to the manager archetype, which every tenant admin
 * holds at system context, and session_manager::can_user_run() never read the
 * session's tenantid. Every trainer route (edit, run, start, end, set_current,
 * add/edit/delete/move slide, delete, the trainer SSE stream, export) gates
 * on can_user_run(), so any tenant admin could read participants' names and
 * free-text answers on, or hard-delete, any tenant's session by its
 * sequential id.
 *
 * @package    local_sentientia_live
 * @category   test
 * @copyright  2026 Airpay Payment Services
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \local_sentientia_live\session_manager::can_user_run
 * @group      tenant_isolation
 */
final class tenant_scope_test extends \advanced_testcase {

    use \local_sentientia_org\test\bizlms_fixture;

    private const CAP = 'local/sentientia_live:manage_all';

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

    /**
     * A tenant admin as UAT has them (manager-archetype role at SYSTEM
     * context), optionally also granted :manage_all deliberately.
     */
    private function tenant_admin_at(string $path, bool $manageall = false): \stdClass {
        global $DB;
        $u = $this->user_at($path);
        $sys = \context_system::instance();
        $managerid = (int) $DB->get_field('role', 'id', ['shortname' => 'manager'], MUST_EXIST);
        role_assign($managerid, $u->id, $sys->id);
        if ($manageall) {
            $roleid = $this->getDataGenerator()->create_role();
            assign_capability(self::CAP, CAP_ALLOW, $roleid, $sys->id);
            role_assign($roleid, $u->id, $sys->id);
        }
        accesslib_clear_all_caches_for_unit_testing();
        return $u;
    }

    /** A session owned by a trainer at $path (its tenantid comes from that path). */
    private function session_at(string $path): int {
        return session_manager::create((int) $this->user_at($path)->id, "Session {$path}");
    }

    public function test_manage_all_has_no_default_grant(): void {
        global $DB;
        foreach ($DB->get_records('role', ['archetype' => 'manager']) as $role) {
            $this->assertFalse($DB->record_exists('role_capabilities', ['roleid' => $role->id, 'capability' => self::CAP]),
                "Role {$role->shortname} (manager archetype) must not hold :manage_all by default.");
        }
    }

    public function test_sessions_record_the_owners_tenant(): void {
        $this->assertSame(177, (int) session_manager::get($this->session_at('/177/4'))->tenantid);
        $this->assertSame(0, (int) session_manager::get($this->session_at(''))->tenantid);
    }

    public function test_a_tenant_admin_cannot_run_another_trainers_session(): void {
        $admin = $this->tenant_admin_at('/1');
        $this->assertFalse(session_manager::can_user_run((int) $admin->id, $this->session_at('/177')),
            'The manager archetype no longer carries :manage_all.');
        $this->assertFalse(session_manager::can_user_run((int) $admin->id, $this->session_at('/1/2')),
            'Without :manage_all a tenant admin runs only their own sessions.');
    }

    public function test_a_manage_all_holder_is_confined_to_their_tenant(): void {
        $holder = $this->tenant_admin_at('/1', true);
        $this->assertTrue(session_manager::can_user_run((int) $holder->id, $this->session_at('/1/2')),
            'A deliberate :manage_all grant still manages sessions in the holder\'s own tenant.');
        $this->assertFalse(session_manager::can_user_run((int) $holder->id, $this->session_at('/177')),
            ':manage_all says WHAT, not WHERE: tenant 177\'s sessions stay out of reach.');
    }

    public function test_tenantless_sessions_and_tenantless_holders_are_refused(): void {
        $holder = $this->tenant_admin_at('/1', true);
        $this->assertFalse(session_manager::can_user_run((int) $holder->id, $this->session_at('')),
            'A tenant-0 session belongs to no tenant: cross-tenant users only.');

        $nobody = $this->tenant_admin_at('', true);
        $this->assertFalse(session_manager::can_user_run((int) $nobody->id, $this->session_at('')),
            'Tenant 0 must not match a holder whose own tenant does not resolve.');
        $this->assertFalse(session_manager::can_user_run((int) $nobody->id, $this->session_at('/1')));
    }

    public function test_the_owner_and_the_site_admin_still_run_sessions(): void {
        $owner = $this->user_at('/177');
        $sid = session_manager::create((int) $owner->id, 'Mine');
        $this->assertTrue(session_manager::can_user_run((int) $owner->id, $sid));
        $this->assertTrue(session_manager::can_user_run((int) get_admin()->id, $sid));
        $this->assertTrue(session_manager::can_user_run((int) get_admin()->id, $this->session_at('')));
    }

    public function test_the_upgrade_revoke_removes_existing_grants(): void {
        global $CFG, $DB;
        require_once($CFG->dirroot . '/local/sentientia_live/db/upgradelib.php');
        // What an install before 2026-09-25 (or reset_role_capabilities()) left behind.
        $managerid = (int) $DB->get_field('role', 'id', ['shortname' => 'manager'], MUST_EXIST);
        assign_capability(self::CAP, CAP_ALLOW, $managerid, \context_system::instance()->id);
        $holder = $this->user_at('/1');
        role_assign($managerid, $holder->id, \context_system::instance()->id);
        accesslib_clear_all_caches_for_unit_testing();
        $this->assertTrue(has_capability(self::CAP, \context_system::instance(), $holder->id));

        $this->assertGreaterThanOrEqual(1, local_sentientia_live_revoke_manage_all());

        $this->assertFalse($DB->record_exists('role_capabilities', ['capability' => self::CAP]));
        accesslib_clear_all_caches_for_unit_testing();
        $this->assertFalse(has_capability(self::CAP, \context_system::instance(), $holder->id));
    }
}
