<?php
// This file is part of Sentientia LMS.

/**
 * ADR-031: one cross-tenant authority, and fail-closed tenant scope.
 *
 * @package    local_sentientia_platform
 * @category   test
 * @copyright  2026 Airpay Payment Services
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_sentientia_platform;

defined('MOODLE_INTERNAL') || die();

/**
 * @covers \local_sentientia_platform\tenant
 * @group tenant_isolation
 */
final class cross_tenant_test extends \advanced_testcase {

    use \local_sentientia_org\test\bizlms_fixture;

    private function user_at(string $path): \stdClass {
        global $DB;
        $u = $this->getDataGenerator()->create_user();
        $DB->set_field('user', 'open_path', $path, ['id' => $u->id]);
        return $DB->get_record('user', ['id' => $u->id], '*', MUST_EXIST);
    }

    private function grant(\stdClass $user, string $capability): void {
        $roleid = $this->getDataGenerator()->create_role();
        assign_capability($capability, CAP_ALLOW, $roleid, \context_system::instance()->id);
        role_assign($roleid, $user->id, \context_system::instance()->id);
        accesslib_clear_all_caches_for_unit_testing();
    }

    protected function setUp(): void {
        parent::setUp();
        $this->resetAfterTest();
        $this->ensure_bizlms_schema();
    }

    public function test_the_capability_has_no_default_holder(): void {
        global $DB;
        $this->assertFalse($DB->record_exists('role_capabilities',
            ['capability' => tenant::CROSS_TENANT_CAPABILITY]),
            'No role may hold :crosstenant by default - tenant admins hold manager-archetype roles.');
    }

    public function test_who_is_cross_tenant(): void {
        global $DB;
        $this->assertTrue(tenant::is_cross_tenant((int) get_admin()->id));

        // A manager-archetype tenant admin is NOT cross-tenant.
        $tenantadmin = $this->user_at('/1');
        $managerid = (int) $DB->get_field('role', 'id', ['shortname' => 'manager']);
        role_assign($managerid, $tenantadmin->id, \context_system::instance()->id);
        accesslib_clear_all_caches_for_unit_testing();
        $this->assertFalse(tenant::is_cross_tenant((int) $tenantadmin->id));

        // Only the explicit capability makes a non-admin cross-tenant.
        $platform = $this->user_at('/1');
        $this->grant($platform, tenant::CROSS_TENANT_CAPABILITY);
        $this->assertTrue(tenant::is_cross_tenant((int) $platform->id));

        $this->assertFalse(tenant::is_cross_tenant(0));
        $this->assertFalse(tenant::is_cross_tenant((int) guest_user()->id));
    }

    public function test_scope_path_fails_closed(): void {
        $this->assertSame('', tenant::scope_path(get_admin()));
        $this->assertSame('/1', tenant::scope_path($this->user_at('/1/2/3')));
        $this->assertSame('/177', tenant::scope_path($this->user_at('/177')));
        foreach (['', '/', 'garbage', 'x/1'] as $path) {
            $this->assertNull(tenant::scope_path($this->user_at($path)),
                "open_path '{$path}' must give no scope at all, never the whole site.");
        }
        $platform = $this->user_at('');
        $this->grant($platform, tenant::CROSS_TENANT_CAPABILITY);
        $this->assertSame('', tenant::scope_path($platform), 'Cross-tenant needs no tenant of its own.');
    }

    public function test_writes_check_the_target_users_tenant(): void {
        $actor = $this->user_at('/1');
        $colleague = $this->user_at('/1/5');
        $foreign = $this->user_at('/177/178');
        $nowhere = $this->user_at('');

        tenant::require_same_tenant_user((int) $colleague->id, (int) $actor->id);

        foreach ([$foreign, $nowhere] as $target) {
            try {
                tenant::require_same_tenant_user((int) $target->id, (int) $actor->id);
                $this->fail('A scoped actor must not act on a user outside their tenant.');
            } catch (\moodle_exception $e) {
                $this->assertSame('error_outoftenant', $e->errorcode);
            }
        }

        // An actor with no tenant cannot act on anyone.
        $this->expectException(\moodle_exception::class);
        tenant::require_same_tenant_user((int) $colleague->id, (int) $nowhere->id);
    }

    public function test_cross_tenant_actor_may_act_anywhere(): void {
        $platform = $this->user_at('/1');
        $this->grant($platform, tenant::CROSS_TENANT_CAPABILITY);
        tenant::require_same_tenant_user((int) $this->user_at('/177/178')->id, (int) $platform->id);
        $this->assertTrue(true);
    }

    public function test_the_sql_helpers_fail_closed_for_a_user_with_no_tenant(): void {
        $this->setUser($this->user_at(''));
        $this->assertSame(['1=0', []], tenant::sql_filter('x'));
        $this->assertSame(['1=0', []], tenant::path_filter('x'));

        $this->setUser($this->user_at('/77'));
        [$sql, $args] = tenant::sql_filter('x');
        $this->assertSame('x.costcenterid = :aptenantroot', $sql);
        $this->assertSame(['aptenantroot' => 77], $args);

        $this->setAdminUser();
        $this->assertSame(['1=1', []], tenant::sql_filter('x'));
    }

    public function test_viewer_can_access_honours_the_capability(): void {
        $this->setUser($this->user_at('/1'));
        $this->assertTrue(tenant::viewer_can_access(1));
        $this->assertFalse(tenant::viewer_can_access(177));

        $platform = $this->user_at('/1');
        $this->grant($platform, tenant::CROSS_TENANT_CAPABILITY);
        $this->setUser($platform);
        $this->assertTrue(tenant::viewer_can_access(177));
    }

    /**
     * ADR-031 fix-forward (2026-09-25): viewer_can_access() compared roots
     * only, so a non-cross-tenant viewer with no tenant (root 0) matched
     * 0 === 0 and require_access() let them through on every global
     * (costcenterid 0) resource - writes included. Such a viewer now gets
     * nothing, on both the current-user and the explicit-viewer path.
     */
    public function test_a_viewer_with_no_tenant_is_refused_even_a_global_resource(): void {
        global $DB;
        $managerid = (int) $DB->get_field('role', 'id', ['shortname' => 'manager'], MUST_EXIST);
        foreach (['', 'garbage', '/abc'] as $path) {
            // A manager-archetype holder, as every tenant admin is: the capability says WHAT, not WHERE.
            $nobody = $this->user_at($path);
            role_assign($managerid, $nobody->id, \context_system::instance()->id);
            accesslib_clear_all_caches_for_unit_testing();

            $this->setUser($nobody);
            foreach ([0, 1, 77, 177] as $resourcetenant) {
                $this->assertFalse(tenant::viewer_can_access($resourcetenant),
                    "open_path '{$path}' (current user) must not reach tenant {$resourcetenant}.");
                $this->assertFalse(tenant::viewer_can_access($resourcetenant, (int) $nobody->id),
                    "open_path '{$path}' (current user, id given) must not reach tenant {$resourcetenant}.");
            }
            try {
                tenant::require_access(0);
                $this->fail("require_access(0) must refuse open_path '{$path}'.");
            } catch (\moodle_exception $e) {
                $this->assertSame('error_outoftenant', $e->errorcode);
            }

            // The explicit-viewer (DB-loading) path, asked while somebody else is logged in.
            $this->setAdminUser();
            $this->assertFalse(tenant::viewer_can_access(0, (int) $nobody->id),
                "open_path '{$path}' (explicit viewer) must not reach the global bucket.");
            try {
                tenant::require_access(0, (int) $nobody->id);
                $this->fail("require_access(0, viewer) must refuse open_path '{$path}'.");
            } catch (\moodle_exception $e) {
                $this->assertSame('error_outoftenant', $e->errorcode);
            }
        }

        // Nobody logged in, and a viewer id that does not exist.
        $this->setUser(null);
        $this->assertFalse(tenant::viewer_can_access(0));
        $this->assertFalse(tenant::viewer_can_access(0, 999999));
    }

    public function test_tenant_users_and_cross_tenant_viewers_keep_their_access(): void {
        // A tenant user: their own tenant yes; the global bucket and other tenants no (as before).
        $tenantuser = $this->user_at('/1/2');
        $this->setUser($tenantuser);
        $this->assertTrue(tenant::viewer_can_access(1));
        $this->assertFalse(tenant::viewer_can_access(0));
        $this->assertFalse(tenant::viewer_can_access(177));
        tenant::require_access(1);
        $this->setAdminUser();
        $this->assertTrue(tenant::viewer_can_access(1, (int) $tenantuser->id));
        $this->assertFalse(tenant::viewer_can_access(0, (int) $tenantuser->id));

        // Cross-tenant viewers pass everywhere, the global bucket included, even with no open_path.
        $this->assertTrue(tenant::viewer_can_access(0));
        $this->assertTrue(tenant::viewer_can_access(177));
        $platform = $this->user_at('');
        $this->grant($platform, tenant::CROSS_TENANT_CAPABILITY);
        $this->assertTrue(tenant::viewer_can_access(0, (int) $platform->id));
        $this->setUser($platform);
        foreach ([0, 1, 77, 177] as $resourcetenant) {
            $this->assertTrue(tenant::viewer_can_access($resourcetenant));
        }
        tenant::require_access(0);
    }
}
