<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

namespace local_sentientia_compliance_report;

defined('MOODLE_INTERNAL') || die();

/**
 * Tests for the compliance-report export permission gate.
 *
 * Locks in the access policy chosen on 2026-05-29 (superseding the inline
 * fix C-002):
 *   - site admins and holders of the export capability may export;
 *   - the capability resolves when granted via a role assigned at CATEGORY
 *     context — the BizLMS Compliance Officer / OrgAdmin shell — which a plain
 *     system-context has_capability() check would miss (caps flow down, not up);
 *   - a user who can only VIEW the dashboard (e.g. a line manager whose role
 *     lacks the cap) may NOT export.
 *
 * @package    local_sentientia_compliance_report
 * @category   test
 * @covers     \local_sentientia_compliance_report\permission
 */
final class permission_test extends \advanced_testcase {

    /**
     * Site admins always pass (capability admin bypass).
     */
    public function test_site_admin_can_export(): void {
        $this->resetAfterTest();
        $this->setAdminUser();
        $this->assertTrue(permission::can_export());
    }

    /**
     * A user with no relevant role cannot export.
     */
    public function test_plain_user_cannot_export(): void {
        $this->resetAfterTest();
        $user = $this->getDataGenerator()->create_user();
        $this->setUser($user);
        $this->assertFalse(permission::can_export());
        // Same answer via the explicit-userid path.
        $this->assertFalse(permission::can_export((int) $user->id));
    }

    /**
     * Capability granted to a role assigned at SYSTEM context → can export.
     */
    public function test_capability_at_system_context_allows_export(): void {
        $this->resetAfterTest();
        $user = $this->getDataGenerator()->create_user();
        $roleid = $this->getDataGenerator()->create_role();
        $syscontext = \context_system::instance();

        assign_capability(permission::EXPORT_CAPABILITY, CAP_ALLOW, $roleid, $syscontext->id, true);
        role_assign($roleid, $user->id, $syscontext->id);

        $this->assertTrue(permission::can_export((int) $user->id));
    }

    /**
     * THE KEY CASE. Capability lives in the role definition (system context),
     * but the role is ASSIGNED at a course-category context — exactly how the
     * BizLMS org-admin role ('administrator', id 9 in the Airpay data) is
     * provisioned. Export must be authorised even though the page itself runs
     * at system context.
     */
    public function test_capability_via_category_role_assignment_allows_export(): void {
        $this->resetAfterTest();
        $user = $this->getDataGenerator()->create_user();
        $category = $this->getDataGenerator()->create_category();
        $catcontext = \context_coursecat::instance($category->id);

        $roleid = $this->getDataGenerator()->create_role();
        assign_capability(permission::EXPORT_CAPABILITY, CAP_ALLOW, $roleid,
            \context_system::instance()->id, true);
        role_assign($roleid, $user->id, $catcontext->id);

        $this->assertTrue(permission::can_export((int) $user->id),
            'A cap granted via a category-context role assignment must authorise export.');
    }

    /**
     * A viewer whose category-context role does NOT include the export
     * capability (the line-manager case) must be denied export.
     */
    public function test_category_viewer_without_cap_cannot_export(): void {
        $this->resetAfterTest();
        $user = $this->getDataGenerator()->create_user();
        $category = $this->getDataGenerator()->create_category();
        $catcontext = \context_coursecat::instance($category->id);

        $roleid = $this->getDataGenerator()->create_role();
        // Deliberately do NOT grant EXPORT_CAPABILITY to this role.
        role_assign($roleid, $user->id, $catcontext->id);

        $this->assertFalse(permission::can_export((int) $user->id));
    }

    /**
     * The install/upgrade grant helper must run without error and be
     * idempotent (it executes inside the plugin install/upgrade path, where a
     * fatal would brick the upgrade).
     */
    public function test_grant_helper_is_safe_and_idempotent(): void {
        $this->resetAfterTest();

        permission::grant_export_to_default_roles();
        permission::grant_export_to_default_roles();

        // Whatever roles exist, the capability is now registered and the helper
        // completed twice without throwing.
        $this->assertNotEmpty(get_capability_info(permission::EXPORT_CAPABILITY),
            'Export capability should be registered after the grant helper runs.');
    }

    /**
     * Vanilla site (no BizLMS org-admin role, as on every fresh Sentientia
     * install and every new customer): the Sentientia Author role must not
     * get :export. A fresh install creates that role at id 9, and until
     * 2026-11-17 the grant went to role id 9 whatever it was.
     *
     * The PHPUnit site is itself a fresh install, so the first assertion also
     * checks what this plugin's install step left behind.
     */
    public function test_vanilla_site_author_role_does_not_get_export(): void {
        global $DB;
        $this->resetAfterTest();
        if (!class_exists(\local_sentientia_authoring\author_role::class)) {
            $this->markTestSkipped('local_sentientia_authoring is not installed.');
        }
        $this->assertNull(permission::compliance_role_id(), 'Precondition: a vanilla site.');

        $authorid = \local_sentientia_authoring\author_role::ensure();
        $syscontext = \context_system::instance();
        $this->assertFalse($this->holds_export($authorid),
            'After install the Sentientia Author role must not hold the export capability.');

        // The helper again, as install and upgrade both run it.
        permission::grant_export_to_default_roles();
        permission::grant_export_to_default_roles();

        $author = $this->getDataGenerator()->create_user();
        role_assign($authorid, $author->id, $syscontext->id);
        $this->assertFalse($this->holds_export($authorid),
            'The Sentientia Author role must never hold the export capability.');
        $this->assertFalse(permission::can_export((int) $author->id),
            'A Sentientia Author must not be able to export the compliance matrix.');

        // Whichever role sits at id 9 got nothing either, unless the manager
        // archetype default gave it the capability.
        $role9 = $DB->get_record('role', ['id' => 9]);
        if ($role9 && $role9->archetype !== 'manager') {
            $this->assertFalse($this->holds_export(9),
                "Role id 9 ({$role9->shortname}) must not be granted export on a vanilla site.");
        }
    }

    /**
     * Where the BizLMS org-admin role exists (the Airpay data), the grant
     * finds it by shortname, whatever its id, and it works at category context.
     */
    public function test_grant_reaches_the_org_admin_role_by_shortname(): void {
        $this->resetAfterTest();
        // No archetype, so the grant cannot come from the db/access.php default.
        $roleid = create_role('Administrator', permission::COMPLIANCE_ROLE_SHORTNAME, '', '');
        $this->assertSame((int) $roleid, permission::compliance_role_id());

        permission::grant_export_to_default_roles();

        $this->assertTrue($this->holds_export((int) $roleid));
        $user = $this->getDataGenerator()->create_user();
        $category = $this->getDataGenerator()->create_category();
        role_assign($roleid, $user->id, \context_coursecat::instance($category->id)->id);
        $this->assertTrue(permission::can_export((int) $user->id));
    }

    /**
     * The upgrade step removes the old grant from a role 9 that is not the
     * org-admin role, and is idempotent.
     */
    public function test_revoke_removes_the_misdirected_grant_from_role_9(): void {
        $this->resetAfterTest();
        $this->role_9('');
        // What the pre-2026-11-17 install step wrote.
        assign_capability(permission::EXPORT_CAPABILITY, CAP_ALLOW, 9, \context_system::instance()->id, true);

        $this->assertTrue(permission::revoke_misdirected_export_grant());
        $this->assertFalse($this->holds_export(9));
        $this->assertFalse(permission::revoke_misdirected_export_grant(), 'Second run changes nothing.');
    }

    /**
     * Where role 9 IS the org-admin role (the Airpay data), the grant stays.
     */
    public function test_revoke_keeps_the_grant_where_role_9_is_the_org_admin(): void {
        global $DB;
        $this->resetAfterTest();
        $this->role_9('');
        $DB->set_field('role', 'shortname', permission::COMPLIANCE_ROLE_SHORTNAME, ['id' => 9]);
        assign_capability(permission::EXPORT_CAPABILITY, CAP_ALLOW, 9, \context_system::instance()->id, true);

        $this->assertFalse(permission::revoke_misdirected_export_grant());
        $this->assertTrue($this->holds_export(9));
    }

    /**
     * A manager-archetype role 9 gets :export from the db/access.php default
     * anyway, so its grant was not wrong and stays. A PROHIBIT an admin set is
     * left alone too: the old code only ever wrote ALLOW.
     */
    public function test_revoke_keeps_a_manager_archetype_role_9_and_an_admin_prohibit(): void {
        global $DB;
        $this->resetAfterTest();
        $syscontextid = \context_system::instance()->id;

        $this->role_9('manager');
        assign_capability(permission::EXPORT_CAPABILITY, CAP_ALLOW, 9, $syscontextid, true);
        $this->assertFalse(permission::revoke_misdirected_export_grant());
        $this->assertTrue($this->holds_export(9));

        $DB->set_field('role', 'archetype', '', ['id' => 9]);
        assign_capability(permission::EXPORT_CAPABILITY, CAP_PROHIBIT, 9, $syscontextid, true);
        $this->assertFalse(permission::revoke_misdirected_export_grant());
        $this->assertSame((string) CAP_PROHIBIT, (string) $DB->get_field('role_capabilities', 'permission',
            ['roleid' => 9, 'contextid' => $syscontextid, 'capability' => permission::EXPORT_CAPABILITY]));
    }

    /**
     * Whether a role holds ALLOW for the export capability at system context.
     */
    private function holds_export(int $roleid): bool {
        global $DB;
        return $DB->record_exists('role_capabilities', [
            'roleid' => $roleid,
            'contextid' => \context_system::instance()->id,
            'capability' => permission::EXPORT_CAPABILITY,
            'permission' => CAP_ALLOW,
        ]);
    }

    /**
     * Make sure a role with id 9 exists, is not the org-admin role, and has the
     * given archetype. On the PHPUnit site id 9 is normally the Sentientia
     * Author role; it is created only where it is missing.
     */
    private function role_9(string $archetype): void {
        global $DB;
        if (!$DB->record_exists('role', ['id' => 9])) {
            $DB->import_record('role', (object) [
                'id' => 9, 'name' => 'Role nine', 'shortname' => 'rolenine', 'description' => '',
                'sortorder' => (int) $DB->get_field_sql('SELECT COALESCE(MAX(sortorder), 0) + 1 FROM {role}'),
                'archetype' => $archetype,
            ]);
            $DB->get_manager()->reset_sequence('role');
        }
        $DB->set_field('role', 'archetype', $archetype, ['id' => 9]);
        if ($DB->get_field('role', 'shortname', ['id' => 9]) === permission::COMPLIANCE_ROLE_SHORTNAME) {
            $DB->set_field('role', 'shortname', 'rolenine', ['id' => 9]);
        }
    }
}
