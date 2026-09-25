<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

/**
 * ADR-031: role authority is cross-tenant; everything else is tenant-bounded.
 *
 * Tenant admins hold a manager-archetype role at system context. Until
 * 2026-09-25 :manage and :assign defaulted to that archetype and role_manager
 * never looked at a tenant, so any tenant admin could rewrite the role
 * definitions every tenant shares, assign any system role to anyone in any
 * tenant, and read every tenant's role holders and audit log.
 *
 * @package    local_sentientia_roles
 * @category   test
 * @copyright  2026 Airpay Payment Services
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_sentientia_roles;

defined('MOODLE_INTERNAL') || die();

/**
 * @covers \local_sentientia_roles\role_manager
 * @covers \local_sentientia_roles\external\update_capability
 * @group tenant_isolation
 */
final class tenant_scope_test extends \advanced_testcase {

    use \local_sentientia_org\test\bizlms_fixture;

    /** @var int Moodle's stock manager role (manager archetype). */
    private $managerroleid;

    protected function setUp(): void {
        global $DB;
        parent::setUp();
        $this->resetAfterTest();
        $this->ensure_bizlms_schema();
        $this->managerroleid = (int) $DB->get_field('role', 'id', ['shortname' => 'manager'], MUST_EXIST);
    }

    /** A user at $path, reloaded so the record carries open_path. */
    private function user_at(string $path): \stdClass {
        global $DB;
        $u = $this->getDataGenerator()->create_user();
        $DB->set_field('user', 'open_path', $path, ['id' => $u->id]);
        return $DB->get_record('user', ['id' => $u->id], '*', MUST_EXIST);
    }

    /** A tenant admin as UAT has them: a manager-archetype role at system context. */
    private function tenant_admin(string $path): \stdClass {
        $admin = $this->user_at($path);
        role_assign($this->managerroleid, $admin->id, \context_system::instance()->id);
        return $admin;
    }

    /** Assert $fn throws a moodle_exception carrying $errorcode. */
    private function assert_refused(callable $fn, string $errorcode, string $message): void {
        try {
            $fn();
            $this->fail($message . ' (no exception)');
        } catch (\moodle_exception $e) {
            $this->assertSame($errorcode, $e->errorcode, $message);
        }
    }

    public function test_manage_and_assign_have_no_manager_default(): void {
        global $DB;
        foreach ($DB->get_records('role', ['archetype' => 'manager']) as $role) {
            foreach (['local/sentientia_roles:manage', 'local/sentientia_roles:assign'] as $cap) {
                $this->assertFalse($DB->record_exists('role_capabilities',
                    ['roleid' => $role->id, 'capability' => $cap]),
                    "Role {$role->shortname} (manager archetype) must not hold {$cap} by default.");
            }
        }
    }

    public function test_a_tenant_admin_cannot_edit_a_role_definition_even_holding_manage(): void {
        global $DB;
        $admin = $this->tenant_admin('/1');
        $syscontext = \context_system::instance();
        // Even an explicit grant does not make the definition theirs to edit.
        assign_capability('local/sentientia_roles:manage', CAP_ALLOW, $this->managerroleid,
            $syscontext->id, true);
        $target = create_role('Scope target', 'scopetarget', '');
        $this->setUser($admin);

        $this->assert_refused(fn() => role_manager::update_capability($target,
            'local/sentientia_challenge:viewall', 'allow'),
            'err_definitions_crosstenant', 'A scoped caller must not edit a shared role definition.');
        $this->assert_refused(fn() => role_manager::bulk_update_capability([$target, $this->managerroleid],
            'moodle/site:config', 'allow'),
            'err_definitions_crosstenant', 'Nor in bulk.');
        $_POST['sesskey'] = sesskey();
        $this->assert_refused(fn() => external\update_capability::execute($target,
            'moodle/site:config', 'allow', ''),
            'err_definitions_crosstenant', 'Nor through the web service, with :manage granted.');

        $this->assertFalse($DB->record_exists('role_capabilities',
            ['roleid' => $target, 'capability' => 'moodle/site:config']));
        $this->assertFalse($DB->record_exists('role_capabilities',
            ['roleid' => $target, 'capability' => 'local/sentientia_challenge:viewall']));
    }

    public function test_a_tenant_admin_cannot_assign_or_unassign_across_tenants(): void {
        global $DB;
        $admin = $this->tenant_admin('/1');
        $theirs = $this->user_at('/177/178');
        $syscontext = \context_system::instance();
        // Seed an assignment in the other tenant for the unassign attempt.
        role_assign($this->managerroleid, $theirs->id, $syscontext->id);
        $this->setUser($admin);

        $other = $this->user_at('/177/179');
        $this->assert_refused(fn() => role_manager::assign_user_to_role($this->managerroleid, (int) $other->id),
            'error_outoftenant', 'No assigning a role to another tenant\'s user.');
        $this->assert_refused(fn() => role_manager::unassign_user_from_role($this->managerroleid, (int) $theirs->id),
            'error_outoftenant', 'No stripping another tenant\'s admin.');
        $this->assert_refused(fn() => role_manager::assign_user_to_role($this->managerroleid, 999999),
            'error_outoftenant', 'A missing id is refused exactly like an out-of-tenant one.');

        $this->assertFalse($DB->record_exists('role_assignments',
            ['roleid' => $this->managerroleid, 'userid' => $other->id, 'contextid' => $syscontext->id]));
        $this->assertTrue($DB->record_exists('role_assignments',
            ['roleid' => $this->managerroleid, 'userid' => $theirs->id, 'contextid' => $syscontext->id]));
    }

    public function test_a_tenant_admin_may_only_assign_a_role_they_hold_inside_their_tenant(): void {
        global $DB;
        $admin = $this->tenant_admin('/1');
        $mine = $this->user_at('/1/2');
        $siteadminid = (int) get_admin()->id;
        $DB->set_field('user', 'open_path', '/1', ['id' => $siteadminid]);
        $coursecreator = (int) $DB->get_field('role', 'id', ['shortname' => 'coursecreator'], MUST_EXIST);
        $syscontext = \context_system::instance();
        $this->setUser($admin);

        // Assignable by a manager, but not held by this one: the target could
        // otherwise end up with capabilities the caller lacks.
        $this->assert_refused(fn() => role_manager::assign_user_to_role($coursecreator, (int) $mine->id),
            'err_role_not_assignable', 'Only a role the caller holds.');
        // Never on yourself, never on a site admin (same tenant or not).
        $this->assert_refused(fn() => role_manager::unassign_user_from_role($this->managerroleid, (int) $admin->id),
            'err_role_not_assignable', 'Not on yourself.');
        $this->assert_refused(fn() => role_manager::assign_user_to_role($this->managerroleid, $siteadminid),
            'err_role_not_assignable', 'Not on a site admin.');

        // The in-tenant function survives: a held, assignable role, to a colleague.
        role_manager::assign_user_to_role($this->managerroleid, (int) $mine->id, 'in tenant');
        $this->assertTrue($DB->record_exists('role_assignments',
            ['roleid' => $this->managerroleid, 'userid' => $mine->id, 'contextid' => $syscontext->id]));
        role_manager::unassign_user_from_role($this->managerroleid, (int) $mine->id, 'in tenant');
        $this->assertFalse($DB->record_exists('role_assignments',
            ['roleid' => $this->managerroleid, 'userid' => $mine->id, 'contextid' => $syscontext->id]));
    }

    public function test_holders_counts_and_audit_are_bounded_to_the_tenant(): void {
        $roleid = create_role('Scoped list', 'scopedlist', '');
        $mine = $this->user_at('/1/2');
        $theirs = $this->user_at('/177/178');
        $admin = $this->tenant_admin('/1');

        // A site admin assigns the role in both tenants (two audit rows).
        $this->setAdminUser();
        role_manager::assign_user_to_role($roleid, (int) $mine->id);
        role_manager::assign_user_to_role($roleid, (int) $theirs->id);

        $this->setUser($admin);
        $holders = role_manager::list_role_assignments($roleid);
        $this->assertSame(1, $holders['total']);
        $this->assertSame([(int) $mine->id], array_column($holders['rows'], 'userid'));
        $this->assertSame(1, role_manager::get_role($roleid)['assigncount']);
        $this->assertSame(1, role_manager::list_audit($roleid)['total'],
            'Only the entry made to a user in the caller\'s tenant.');
        $this->assertCount(1, iterator_to_array(role_manager::audit_rows_all(), false));

        $this->setAdminUser();
        $this->assertSame(2, role_manager::list_role_assignments($roleid)['total']);
        $this->assertSame(2, role_manager::list_audit($roleid)['total']);
        $this->assertSame(2, role_manager::get_role($roleid)['assigncount']);
    }

    public function test_a_caller_with_no_tenant_gets_nothing(): void {
        $roleid = create_role('No tenant', 'notenant', '');
        $holder = $this->user_at('/1/2');
        $this->setAdminUser();
        role_manager::assign_user_to_role($roleid, (int) $holder->id);

        foreach (['', 'garbage'] as $path) {
            $nobody = $this->tenant_admin($path);
            $this->setUser($nobody);
            $this->assertSame(0, role_manager::list_role_assignments($roleid)['total'],
                "open_path '{$path}' must not list every tenant's holders.");
            $this->assertSame(0, role_manager::list_audit()['total']);
            $this->assertSame(0, role_manager::get_role($roleid)['assigncount']);
            $this->assert_refused(fn() => role_manager::assign_user_to_role($roleid, (int) $holder->id),
                'error_outoftenant', "open_path '{$path}' must not assign anybody.");
        }
    }

    public function test_the_site_admin_keeps_full_role_authority(): void {
        global $DB;
        $theirs = $this->user_at('/177/178');
        $roleid = create_role('Admin still', 'adminstill', '');
        $this->setAdminUser();

        role_manager::update_capability($roleid, 'moodle/course:create', 'allow', 'admin');
        $this->assertSame(CAP_ALLOW, (int) $DB->get_field('role_capabilities', 'permission',
            ['roleid' => $roleid, 'capability' => 'moodle/course:create']));
        role_manager::assign_user_to_role($roleid, (int) $theirs->id);
        $this->assertTrue($DB->record_exists('role_assignments',
            ['roleid' => $roleid, 'userid' => $theirs->id]));
        role_manager::unassign_user_from_role($roleid, (int) $theirs->id);
        $this->assertFalse($DB->record_exists('role_assignments',
            ['roleid' => $roleid, 'userid' => $theirs->id]));
    }

    public function test_no_role_holds_role_authority_beyond_what_is_decided(): void {
        global $DB;
        // Structural, over EVERY role and context, not just the manager
        // archetype: the plugin's role authority is granted by nobody. Site
        // admins need no role for it, and a cross-tenant caller is recognised
        // by role_manager through tenant::is_cross_tenant().
        foreach (['local/sentientia_roles:manage', 'local/sentientia_roles:assign'] as $cap) {
            $holders = $DB->get_fieldset_select('role_capabilities', 'roleid',
                'capability = :cap AND permission = :allow', ['cap' => $cap, 'allow' => CAP_ALLOW]);
            $this->assertSame([], array_values($holders), "No role may hold {$cap}.");
        }

        // Core moodle/role:manage reaches the same escalation through
        // /admin/roles/*.php. Moodle grants it to the manager archetype, which
        // is what tenant admins hold; revoking it from them is an open decision
        // (PROHIBIT on the tenant-admin role, or category-context assignment),
        // so that one default is pinned here and nothing else may join it.
        $managerarchetype = array_map('intval', $DB->get_fieldset_select('role', 'id',
            'archetype = :archetype', ['archetype' => 'manager']));
        $holders = array_map('intval', $DB->get_fieldset_select('role_capabilities', 'roleid',
            'capability = :cap AND permission = :allow', ['cap' => 'moodle/role:manage', 'allow' => CAP_ALLOW]));
        $this->assertSame([], array_values(array_diff($holders, $managerarchetype)),
            'Only the manager archetype may hold moodle/role:manage, pending the tenant-admin decision.');
    }

    public function test_unassigning_a_role_that_is_not_held_writes_no_audit_row(): void {
        global $DB;
        $roleid = create_role('Never held', 'neverheld', '');
        $theirs = $this->user_at('/177/178');
        $mine = $this->user_at('/1/2');
        $before = $DB->count_records('local_sentientia_roles_auditlog');

        // Cross-tenant caller: an assignment that does not exist, and an id
        // that is nobody, are refused instead of logged as "role_unassigned".
        $this->setAdminUser();
        $this->assert_refused(fn() => role_manager::unassign_user_from_role($roleid, (int) $theirs->id),
            'err_assignment_not_found', 'Nothing to remove, so nothing to audit.');
        $this->assert_refused(fn() => role_manager::unassign_user_from_role($roleid, 999999),
            'err_assignment_not_found', 'Nor for an id that is nobody.');

        // Scoped caller: the tenant refusal still comes first, so a missing or
        // foreign id is not told apart by an "assignment not found".
        $admin = $this->tenant_admin('/1');
        $this->setUser($admin);
        $this->assert_refused(fn() => role_manager::unassign_user_from_role($this->managerroleid, 999999),
            'error_outoftenant', 'A missing id is the tenant refusal for a scoped caller.');
        $this->assert_refused(fn() => role_manager::unassign_user_from_role($this->managerroleid, (int) $theirs->id),
            'error_outoftenant', 'So is another tenant\'s user.');
        $this->assert_refused(fn() => role_manager::unassign_user_from_role($this->managerroleid, (int) $mine->id),
            'err_assignment_not_found', 'An in-tenant colleague who does not hold the role.');

        $this->assertSame($before, $DB->count_records('local_sentientia_roles_auditlog'),
            'No refused unassign may write an audit row.');
    }
}
