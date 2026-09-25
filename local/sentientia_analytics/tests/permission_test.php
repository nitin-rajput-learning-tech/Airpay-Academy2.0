<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

namespace local_sentientia_analytics;

defined('MOODLE_INTERNAL') || die();

/**
 * Regression tests for the analytics capability layer (added 2026-09-22).
 *
 * @covers \local_sentientia_analytics\permission
 *
 * What this suite locks in, defect by defect:
 *
 *  1. The dashboard had NO db/access.php. All three entry points gated on
 *     `local/courses:manage`, renamed by ADR-025 and undefined since, so
 *     has_capability() answered false with a debugging() notice. Effective
 *     access was site admins plus holders of hardcoded role id 9 at a
 *     category context. The manager role - the dashboard's whole audience -
 *     got "nopermission". test_manager_role_can_view_the_dashboard().
 *
 *  2. visible_org_path() failed OPEN. index.php fell back to
 *     '/' . ($parts[1] ?? '1'), silently scoping an unresolvable user to
 *     tenant 1; export.php fell back to tenant_manager::get_tenant_path(),
 *     which returns '' - and analytics_manager reads '' as "no filter", i.e.
 *     every tenant at once. test_unresolvable_tenant_is_denied_not_guessed().
 *
 *  3. The ?orgid= branch of index.php was not gated at all, so any viewer
 *     could read another tenant's numbers by editing the query string.
 *     test_requested_org_is_clamped_to_the_viewers_own_subtree().
 *
 *  4. get_course_learners() ordered by `DESC NULLS LAST` - PostgreSQL/Oracle
 *     syntax that MariaDB 10.11 and MySQL 8.0 both reject - so the course
 *     drill-down raised dml_read_exception on every call and had never once
 *     worked on either of our database targets. It also had no tenant filter.
 *     test_course_drilldown_query_executes() and
 *     test_course_drilldown_is_tenant_scoped().
 *
 *  5. (ADR-031, 2026-09-25) :viewallorgs unscoped on its own, and was honoured
 *     at any category where the user held a role - which a tenant admin can
 *     arrange for themselves with the role:assign / role:override they hold.
 *     It now needs is_cross_tenant() and a system-context grant.
 *     test_viewallorgs_unscopes_only_a_cross_tenant_holder() and
 *     test_a_self_granted_category_override_of_viewallorgs_does_not_unscope().
 *
 * @package    local_sentientia_analytics
 * @category   test
 *
 * @group tenant_isolation
 */
final class permission_test extends \advanced_testcase {

    use \local_sentientia_org\test\bizlms_fixture;

    /**
     * Create a user sitting at a specific open_path.
     *
     * @param string|null $path Path to set, or null to leave open_path unset.
     * @return \stdClass
     */
    private function user_at_path(?string $path): \stdClass {
        global $DB;
        $u = $this->getDataGenerator()->create_user();
        if ($path !== null) {
            $DB->set_field('user', 'open_path', $path, ['id' => $u->id]);
            $u->open_path = $path;
        }
        return $u;
    }

    /**
     * Assign a role to a user at system context.
     *
     * @param int $userid
     * @param string $shortname
     * @return void
     */
    private function give_system_role(int $userid, string $shortname): void {
        global $DB;
        $roleid = (int) $DB->get_field('role', 'id', ['shortname' => $shortname]);
        $this->assertGreaterThan(0, $roleid, "role {$shortname} must exist");
        role_assign($roleid, $userid, \context_system::instance()->id);
    }

    // ── 1. the gate ──────────────────────────────────────────────────────

    public function test_manager_role_can_view_the_dashboard(): void {
        $this->resetAfterTest();
        $this->ensure_bizlms_schema();

        $u = $this->user_at_path('/1');
        $this->give_system_role((int) $u->id, 'manager');
        $this->setUser($u);

        $this->assertTrue(permission::can_view(),
            'the manager archetype is the dashboard audience; before the '
            . 'capability layer it got nopermission because the gate named a '
            . 'capability that no longer exists');
    }

    public function test_a_plain_learner_cannot_view_the_dashboard(): void {
        $this->resetAfterTest();
        $this->ensure_bizlms_schema();

        $u = $this->user_at_path('/1');
        $this->setUser($u);

        $this->assertFalse(permission::can_view());
        $this->assertFalse(permission::can_view_all_orgs());
        $this->assertFalse(permission::can_export());
    }

    public function test_the_gating_capability_actually_exists(): void {
        $this->resetAfterTest();
        global $DB;

        // The original defect in one assertion: the old gate's capability is
        // not registered, so has_capability() could only ever answer false.
        $this->assertFalse($DB->record_exists('capabilities',
            ['name' => 'local/courses:manage']),
            'if this ever becomes true, revisit the back-fill in '
            . 'permission::grant_to_default_roles()');

        foreach ([permission::VIEW_CAPABILITY,
                  permission::VIEWALL_CAPABILITY,
                  permission::EXPORT_CAPABILITY] as $cap) {
            $this->assertTrue($DB->record_exists('capabilities', ['name' => $cap]),
                "{$cap} must be registered from db/access.php");
        }
    }

    // ── 2. scope resolution fails closed ────────────────────────────────

    public function test_unresolvable_tenant_is_denied_not_guessed(): void {
        $this->resetAfterTest();
        $this->ensure_bizlms_schema();

        foreach ([null, '', '/', 'not-a-path', '//'] as $badpath) {
            $u = $this->user_at_path($badpath);
            $this->give_system_role((int) $u->id, 'teacher');
            $this->setUser($u);

            $this->assertNull(permission::visible_org_path(),
                'an unparseable open_path must deny. Guessing tenant 1 shows '
                . 'another tenant data; guessing the empty string shows every '
                . 'tenant, because analytics_manager reads it as no filter.');
        }
    }

    public function test_a_tenant_user_is_scoped_to_their_own_tenant(): void {
        $this->resetAfterTest();
        $this->ensure_bizlms_schema();

        $u = $this->user_at_path('/177/178/9');
        $this->give_system_role((int) $u->id, 'teacher');
        $this->setUser($u);

        $this->assertSame('/177', permission::visible_org_path());
    }

    public function test_viewallorgs_unscopes_only_a_cross_tenant_holder(): void {
        $this->resetAfterTest();
        $this->ensure_bizlms_schema();
        global $DB;

        // :viewallorgs has no archetype default (2026-09-24), so grant it
        // deliberately, the only way a site should ever get it.
        $syscontext = \context_system::instance();
        $roleid = (int) $DB->get_field('role', 'id', ['shortname' => 'manager']);
        assign_capability(permission::VIEWALL_CAPABILITY, CAP_ALLOW, $roleid, $syscontext->id, true);

        $u = $this->user_at_path('/1/2');
        $this->give_system_role((int) $u->id, 'manager');
        $this->setUser($u);

        // ADR-031 (2026-09-25): the capability says WHAT, never WHERE. Held
        // at system context by a tenant admin, it no longer unscopes.
        $this->assertTrue(has_capability(permission::VIEWALL_CAPABILITY, $syscontext),
            'Precondition: the grant is live at system context.');
        $this->assertFalse(permission::can_view_all_orgs(),
            ':viewallorgs alone must not lift the tenant clamp');
        $this->assertSame('/1', permission::visible_org_path());

        // Cross-tenant as well (local/sentientia_platform:crosstenant): unrestricted.
        $crossrole = $this->getDataGenerator()->create_role();
        assign_capability(\local_sentientia_platform\tenant::CROSS_TENANT_CAPABILITY, CAP_ALLOW,
            $crossrole, $syscontext->id, true);
        role_assign($crossrole, $u->id, $syscontext->id);
        accesslib_clear_all_caches_for_unit_testing();

        $this->assertTrue(permission::can_view_all_orgs());
        $this->assertSame('', permission::visible_org_path(),
            'empty string means site-wide, which only a cross-tenant :viewallorgs holder gets');
        $this->assertSame('/177', permission::clamp_org_path('/177'),
            'and they may pick any org');

        // :crosstenant without :viewallorgs: WHERE, but not the function.
        $other = $this->user_at_path('/1/3');
        role_assign($crossrole, $other->id, $syscontext->id);
        accesslib_clear_all_caches_for_unit_testing();
        $this->assertFalse(permission::can_view_all_orgs((int) $other->id));
        $this->assertSame('/1', permission::visible_org_path((int) $other->id));
    }

    public function test_the_site_admin_is_unrestricted_without_any_grant(): void {
        $this->resetAfterTest();
        $this->ensure_bizlms_schema();

        $this->setAdminUser();
        $this->assertTrue(permission::can_view_all_orgs());
        $this->assertSame('', permission::visible_org_path());
    }

    public function test_a_self_granted_category_override_of_viewallorgs_does_not_unscope(): void {
        $this->resetAfterTest();
        $this->ensure_bizlms_schema();
        global $DB;

        // The route a tenant admin had (ADR-031 review, 2026-09-25): role:assign
        // and role:override are inherited in every category, so they assign
        // themselves a role at their own tenant's category and override it to
        // ALLOW :viewallorgs there. has_cap_anywhere() then answered true.
        $u = $this->user_at_path('/1/2');
        $this->give_system_role((int) $u->id, 'manager');
        $category = $this->getDataGenerator()->create_category();
        $catcontext = \context_coursecat::instance($category->id);
        $roleid = (int) $DB->get_field('role', 'id', ['shortname' => 'coursecreator'], MUST_EXIST);
        role_assign($roleid, $u->id, $catcontext->id);
        assign_capability(permission::VIEWALL_CAPABILITY, CAP_ALLOW, $roleid, $catcontext->id, true);
        accesslib_clear_all_caches_for_unit_testing();
        $this->setUser($u);

        $this->assertTrue(has_capability(permission::VIEWALL_CAPABILITY, $catcontext),
            'Precondition: the self-granted override is live at the category.');
        $this->assertFalse(permission::can_view_all_orgs());
        $this->assertSame('/1', permission::visible_org_path(),
            'a category override must never lift the tenant clamp');
        $this->assertSame('/1', permission::clamp_org_path('/177'),
            'nor let ?orgid= reach another tenant');

        // Even a cross-tenant user needs :viewallorgs at SYSTEM context: the
        // category grant is the one a tenant admin can make, so it never counts.
        $crossrole = $this->getDataGenerator()->create_role();
        assign_capability(\local_sentientia_platform\tenant::CROSS_TENANT_CAPABILITY, CAP_ALLOW,
            $crossrole, \context_system::instance()->id, true);
        role_assign($crossrole, $u->id, \context_system::instance()->id);
        accesslib_clear_all_caches_for_unit_testing();
        $this->assertTrue(\local_sentientia_platform\tenant::is_cross_tenant((int) $u->id));
        $this->assertFalse(permission::can_view_all_orgs(),
            ':viewallorgs is read at system context only');
    }

    public function test_a_manager_archetype_tenant_admin_stays_in_their_tenant(): void {
        $this->resetAfterTest();
        $this->ensure_bizlms_schema();

        // The case the first version got wrong. On this platform a tenant admin
        // IS a manager-archetype role at system context (UAT: `administrator`).
        // The original archetype default gave that role :viewallorgs, so the ZEEA
        // admin's dashboard and CSV export carried every tenant's figures.
        $u = $this->user_at_path('/177');
        $this->give_system_role((int) $u->id, 'manager');
        $this->setUser($u);

        $this->assertTrue(permission::can_view(),
            'a tenant admin must still open the dashboard');
        $this->assertTrue(permission::can_export(),
            'and export their own tenant');
        $this->assertFalse(permission::can_view_all_orgs(),
            'but must not see other tenants by default');
        $this->assertSame('/177', permission::visible_org_path());
        $this->assertSame('/177', permission::clamp_org_path('/1'),
            'a query-string org from another tenant is pinned back');
    }

    public function test_the_back_fill_never_grants_viewallorgs(): void {
        $this->resetAfterTest();
        global $DB;

        permission::grant_to_default_roles();

        $this->assertSame(0, $DB->count_records('role_capabilities',
            ['capability' => permission::VIEWALL_CAPABILITY, 'permission' => CAP_ALLOW]),
            'the holders of local/sentientia_courses:manage are tenant admins; '
            . 'granting them cross-tenant analytics is the leak');
    }

    // ── 3. the ?orgid= clamp, including the path boundary ───────────────

    public function test_requested_org_is_clamped_to_the_viewers_own_subtree(): void {
        $this->resetAfterTest();
        $this->ensure_bizlms_schema();

        // Deliberately NOT a :viewallorgs holder - that is the case where the
        // clamp has to bite.
        $u = $this->user_at_path('/1');
        $this->setUser($u);
        $this->assertFalse(permission::can_view_all_orgs());

        // In their own tenant: honoured.
        $this->assertSame('/1', permission::clamp_org_path('/1'));
        $this->assertSame('/1/2', permission::clamp_org_path('/1/2'));
        $this->assertSame('/1/2/3', permission::clamp_org_path('/1/2/3'));

        // Another tenant: clamped back to their own root, never honoured.
        $this->assertSame('/1', permission::clamp_org_path('/177'),
            'a query-string org id must not widen scope across tenants');
        $this->assertSame('/1', permission::clamp_org_path('/77'));

        // The boundary that started all of this: '/1' must not authorise
        // '/177', '/10' or '/1x' just because the string starts the same way.
        $this->assertSame('/1', permission::clamp_org_path('/10'));
        $this->assertSame('/1', permission::clamp_org_path('/177/178'));

        // Nothing requested: their own root.
        $this->assertSame('/1', permission::clamp_org_path(''));
    }

    public function test_clamp_denies_when_no_scope_can_be_established(): void {
        $this->resetAfterTest();
        $this->ensure_bizlms_schema();

        $u = $this->user_at_path(null);
        $this->give_system_role((int) $u->id, 'teacher');
        $this->setUser($u);

        $this->assertNull(permission::clamp_org_path('/1'));
        $this->assertNull(permission::clamp_org_path(''));
    }

    // ── 4. the course drill-down query ──────────────────────────────────

    public function test_course_drilldown_query_executes(): void {
        $this->resetAfterTest();
        $this->ensure_bizlms_schema();

        $course = $this->getDataGenerator()->create_course();
        $u = $this->user_at_path('/1/2');
        $this->getDataGenerator()->enrol_user($u->id, $course->id);

        // Before 2026-09-22 this line raised dml_read_exception, because
        // `ORDER BY ... DESC NULLS LAST` is PostgreSQL/Oracle syntax and both
        // MariaDB and MySQL reject it. The assertion is simply that the query
        // runs at all; that is the regression.
        $rows = analytics_manager::get_course_learners((int) $course->id, 50, '');

        $this->assertIsArray($rows);
        $this->assertCount(1, $rows);
        $this->assertSame((int) $u->id, (int) $rows[0]->id);
    }

    public function test_course_drilldown_is_tenant_scoped(): void {
        $this->resetAfterTest();
        $this->ensure_bizlms_schema();

        $course = $this->getDataGenerator()->create_course();

        $airpay = $this->user_at_path('/1/2');
        $zeea   = $this->user_at_path('/177');
        // '/1' must not sweep in '/177' or '/10' on a prefix match.
        $decoy  = $this->user_at_path('/10');

        foreach ([$airpay, $zeea, $decoy] as $u) {
            $this->getDataGenerator()->enrol_user($u->id, $course->id);
        }

        $ids = array_map('intval', array_column(
            analytics_manager::get_course_learners((int) $course->id, 50, '/1'), 'id'));

        $this->assertContains((int) $airpay->id, $ids);
        $this->assertNotContains((int) $zeea->id, $ids,
            'the course drill-down released every enrolled learner name and '
            . 'email regardless of tenant before it took an org path');
        $this->assertNotContains((int) $decoy->id, $ids,
            "'/1' must not match '/10' -- the prefix boundary");
        $this->assertCount(1, $ids);
    }

    public function test_unscoped_course_drilldown_still_returns_everyone(): void {
        $this->resetAfterTest();
        $this->ensure_bizlms_schema();

        // A :viewallorgs holder passes '' and must still see every tenant,
        // so the clamp cannot be implemented by making '' mean "deny".
        $course = $this->getDataGenerator()->create_course();
        foreach (['/1/2', '/177', '/10'] as $path) {
            $u = $this->user_at_path($path);
            $this->getDataGenerator()->enrol_user($u->id, $course->id);
        }

        $this->assertCount(3,
            analytics_manager::get_course_learners((int) $course->id, 50, ''));
    }

    // ── 5. the back-fill is idempotent ──────────────────────────────────

    public function test_grant_to_default_roles_is_idempotent(): void {
        $this->resetAfterTest();
        global $DB;

        permission::grant_to_default_roles();
        $first = $DB->count_records_select('role_capabilities',
            'capability = :cap', ['cap' => permission::VIEW_CAPABILITY]);

        permission::grant_to_default_roles();
        $second = $DB->count_records_select('role_capabilities',
            'capability = :cap', ['cap' => permission::VIEW_CAPABILITY]);

        $this->assertSame($first, $second,
            'the install and upgrade hooks both call this, and an upgrade may '
            . 'replay, so it must not accumulate rows');
    }
}
