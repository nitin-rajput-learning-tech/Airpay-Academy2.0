<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

namespace local_sentientia_courses;

defined('MOODLE_INTERNAL') || die();

/**
 * Tenant-scoping tests for the Manage Courses KPI tiles + category filter.
 *
 * Locks in the UAT ZEEA findings (2026-09-08):
 *  - #4 KPI tiles must count the SAME row set the datatable lists (own
 *       open_path tree + legacy NULL-open_path courses), not a global
 *       total.
 *  - #3 the category filter must only offer categories that hold a course
 *       in that row set, so cross-tenant category names don't leak.
 *
 * The NULL-open_path row is deliberately kept visible to tenant admins —
 * see external\list_courses and
 * external\list_courses_test::test_null_open_path_courses_remain_visible.
 *
 * @package    local_sentientia_courses
 * @category   test
 * @covers     \local_sentientia_courses\course_manager
 */
final class course_manager_scope_test extends \advanced_testcase {

    use \local_sentientia_org\test\bizlms_fixture;

    /** Create a course at an open_path (null = legacy/unscoped). */
    private function course_at_path(?string $path, int $categoryid = 0, int $visible = 1): \stdClass {
        global $DB;
        $params = ['visible' => $visible];
        if ($categoryid > 0) {
            $params['category'] = $categoryid;
        }
        $course = $this->getDataGenerator()->create_course($params);
        $DB->set_field('course', 'open_path', $path, ['id' => $course->id]);
        return $course;
    }

    /** A non-siteadmin viewer whose tenant root is the first segment of $path. */
    private function viewer_at_path(string $path): \stdClass {
        global $DB;
        $u = $this->getDataGenerator()->create_user();
        $DB->set_field('user', 'open_path', $path, ['id' => $u->id]);
        $u->open_path = $path;
        $sysctx = \context_system::instance();
        $roleid = $this->getDataGenerator()->create_role();
        role_change_permission($roleid, $sysctx, 'local/sentientia_courses:view', CAP_ALLOW);
        role_assign($roleid, $u->id, $sysctx->id);
        return $u;
    }

    /**
     * KPI: a tenant admin counts own-tree + legacy NULL courses only.
     */
    public function test_kpi_counts_tenant_scoped(): void {
        $this->resetAfterTest();
        $this->ensure_bizlms_schema();

        // Tenant A (/8001): 2 visible + 1 hidden.
        $this->course_at_path('/8001', 0, 1);
        $this->course_at_path('/8001/9001', 0, 1);
        $this->course_at_path('/8001', 0, 0);
        // Tenant B (/8002): 1 visible — must NOT count for A.
        $this->course_at_path('/8002', 0, 1);
        // Legacy NULL — counts for every tenant (kept visible by policy).
        $this->course_at_path(null, 0, 1);

        $this->setUser($this->viewer_at_path('/8001'));
        $kpi = course_manager::manage_kpi_counts();

        // own(2 vis + 1 hidden) + NULL(1 vis) = 4 total, 3 visible, 1 hidden.
        $this->assertSame(4, $kpi['total']);
        $this->assertSame(3, $kpi['visible']);
        $this->assertSame(1, $kpi['hidden']);
    }

    /**
     * KPI: a sibling tenant gets its own, non-overlapping count (plus the
     * shared legacy NULL course) — no cross-tenant leak.
     */
    public function test_kpi_counts_sibling_tenant_isolated(): void {
        $this->resetAfterTest();
        $this->ensure_bizlms_schema();

        $this->course_at_path('/8001', 0, 1);
        $this->course_at_path('/8001', 0, 1);
        $this->course_at_path('/8002', 0, 1);
        $this->course_at_path(null, 0, 1);

        $this->setUser($this->viewer_at_path('/8002'));
        $kpi = course_manager::manage_kpi_counts();

        // own(1) + NULL(1) = 2.
        $this->assertSame(2, $kpi['total']);
        $this->assertSame(2, $kpi['visible']);
        $this->assertSame(0, $kpi['hidden']);
    }

    /**
     * KPI: a site admin keeps the global count across all tenants.
     */
    public function test_kpi_counts_siteadmin_global(): void {
        $this->resetAfterTest();
        $this->ensure_bizlms_schema();

        $this->course_at_path('/8001', 0, 1);
        $this->course_at_path('/8002', 0, 1);
        $this->course_at_path('/8003', 0, 0);
        $this->course_at_path(null, 0, 1);

        $this->setAdminUser();
        $kpi = course_manager::manage_kpi_counts();

        // All 4 (id > 1), 3 visible, 1 hidden.
        $this->assertSame(4, $kpi['total']);
        $this->assertSame(3, $kpi['visible']);
        $this->assertSame(1, $kpi['hidden']);
    }

    /**
     * Category filter: a tenant admin only sees categories that hold a
     * course in their own row set (own tree + legacy NULL), never another
     * tenant's category.
     */
    public function test_category_options_tenant_scoped(): void {
        $this->resetAfterTest();
        $this->ensure_bizlms_schema();

        $cata = $this->getDataGenerator()->create_category(['name' => 'Alpha Tenant']);
        $catb = $this->getDataGenerator()->create_category(['name' => 'Beta Tenant']);
        $catnull = $this->getDataGenerator()->create_category(['name' => 'Legacy Bucket']);

        $this->course_at_path('/8001', (int) $cata->id, 1);
        $this->course_at_path('/8002', (int) $catb->id, 1);      // other tenant
        $this->course_at_path(null,    (int) $catnull->id, 1);   // legacy NULL

        $this->setUser($this->viewer_at_path('/8001'));
        $ids = array_column(course_manager::manage_category_options(), 'id');

        $this->assertContains((int) $cata->id, $ids, 'own-tenant category missing');
        $this->assertContains((int) $catnull->id, $ids, 'legacy NULL-course category missing');
        $this->assertNotContains((int) $catb->id, $ids, 'cross-tenant category leaked into filter');
    }

    /**
     * Category filter: a site admin still sees every category, including
     * empty ones (unchanged behaviour).
     */
    public function test_category_options_siteadmin_sees_all(): void {
        $this->resetAfterTest();
        $this->ensure_bizlms_schema();

        $empty = $this->getDataGenerator()->create_category(['name' => 'Empty Cat']);

        $this->setAdminUser();
        $ids = array_column(course_manager::manage_category_options(), 'id');

        $this->assertContains((int) $empty->id, $ids,
            'site admin must keep every category, even empty ones');
    }
}
