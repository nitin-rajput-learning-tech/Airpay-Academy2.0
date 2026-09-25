<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

namespace local_sentientia_ai;

defined('MOODLE_INTERNAL') || die();

/**
 * ADR-031: the AI spend ledger is a platform-operator view.
 *
 * :viewledger defaulted to the manager archetype, which every tenant admin
 * holds at system context, and index.php (reachable by direct URL) showed
 * every tenant's AI calls - user ids, features, tokens, cost, error text -
 * and the platform-wide spend. None of it is tenant-filtered, because the
 * quota checks need the global numbers; so the page now needs a cross-tenant
 * viewer, not just the capability.
 *
 * @package    local_sentientia_ai
 * @category   test
 * @copyright  2026 Airpay Payment Services
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \local_sentientia_ai\ledger::can_view
 * @group      tenant_isolation
 */
final class tenant_scope_test extends \advanced_testcase {

    use \local_sentientia_org\test\bizlms_fixture;

    private const VIEW = 'local/sentientia_ai:viewledger';
    private const MANAGE = 'local/sentientia_ai:manage';

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

    private function grant(\stdClass $user, string ...$caps): void {
        $sys = \context_system::instance();
        $roleid = $this->getDataGenerator()->create_role();
        foreach ($caps as $cap) {
            assign_capability($cap, CAP_ALLOW, $roleid, $sys->id);
        }
        role_assign($roleid, $user->id, $sys->id);
        accesslib_clear_all_caches_for_unit_testing();
    }

    /** A tenant admin as UAT has them: a manager-archetype role at SYSTEM context. */
    private function tenant_admin_at(string $path): \stdClass {
        global $DB;
        $u = $this->user_at($path);
        $managerid = (int) $DB->get_field('role', 'id', ['shortname' => 'manager'], MUST_EXIST);
        role_assign($managerid, $u->id, \context_system::instance()->id);
        accesslib_clear_all_caches_for_unit_testing();
        return $u;
    }

    public function test_neither_capability_has_a_default_grant(): void {
        global $DB;
        foreach ($DB->get_records('role', ['archetype' => 'manager']) as $role) {
            foreach ([self::VIEW, self::MANAGE] as $cap) {
                $this->assertFalse($DB->record_exists('role_capabilities',
                    ['roleid' => $role->id, 'capability' => $cap]),
                    "Role {$role->shortname} (manager archetype) must not hold {$cap} by default.");
            }
        }
        $admin = $this->tenant_admin_at('/1');
        $this->assertFalse(has_capability(self::VIEW, \context_system::instance(), $admin->id));
        $this->assertFalse(ledger::can_view((int) $admin->id));
    }

    public function test_a_deliberate_grant_to_a_tenant_admin_still_does_not_open_the_ledger(): void {
        $admin = $this->tenant_admin_at('/1');
        $this->grant($admin, self::VIEW);
        $this->assertTrue(has_capability(self::VIEW, \context_system::instance(), $admin->id));
        $this->assertFalse(ledger::can_view((int) $admin->id),
            'Every figure on the page is platform-wide: tenant 177\'s calls would show.');
    }

    public function test_a_holder_with_no_tenant_is_refused(): void {
        $nobody = $this->user_at('');
        $this->grant($nobody, self::VIEW);
        $this->assertFalse(ledger::can_view((int) $nobody->id));
        $this->assertFalse(ledger::can_view(0));
    }

    public function test_the_site_admin_and_a_crosstenant_holder_may_view(): void {
        $this->assertTrue(ledger::can_view((int) get_admin()->id));

        $platform = $this->user_at('/1');
        $this->grant($platform, self::VIEW,
            \local_sentientia_platform\tenant::CROSS_TENANT_CAPABILITY);
        $this->assertTrue(ledger::can_view((int) $platform->id));

        $crossonly = $this->user_at('/1');
        $this->grant($crossonly, \local_sentientia_platform\tenant::CROSS_TENANT_CAPABILITY);
        $this->assertFalse(ledger::can_view((int) $crossonly->id),
            'Cross-tenant says WHERE; :viewledger still says WHAT.');
    }

    public function test_the_upgrade_revoke_removes_existing_grants(): void {
        global $CFG, $DB;
        require_once($CFG->dirroot . '/local/sentientia_ai/db/upgradelib.php');
        // What an install before 2026-09-25 (or reset_role_capabilities()) left behind.
        $sys = \context_system::instance();
        $managerid = (int) $DB->get_field('role', 'id', ['shortname' => 'manager'], MUST_EXIST);
        assign_capability(self::VIEW, CAP_ALLOW, $managerid, $sys->id);
        assign_capability(self::MANAGE, CAP_ALLOW, $managerid, $sys->id);
        $holder = $this->user_at('/1');
        role_assign($managerid, $holder->id, $sys->id);
        accesslib_clear_all_caches_for_unit_testing();
        $this->assertTrue(has_capability(self::VIEW, $sys, $holder->id));

        $this->assertGreaterThanOrEqual(2, local_sentientia_ai_revoke_operator_caps());

        foreach ([self::VIEW, self::MANAGE] as $cap) {
            $this->assertFalse($DB->record_exists('role_capabilities', ['capability' => $cap]));
        }
        accesslib_clear_all_caches_for_unit_testing();
        $this->assertFalse(has_capability(self::VIEW, $sys, $holder->id));
    }
}
