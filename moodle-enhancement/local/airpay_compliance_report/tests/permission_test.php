<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

namespace local_airpay_compliance_report;

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
 * @package    local_airpay_compliance_report
 * @category   test
 * @covers     \local_airpay_compliance_report\permission
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
     * BizLMS Compliance Officer (role id 9) is provisioned. Export must be
     * authorised even though the page itself runs at system context.
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
}
