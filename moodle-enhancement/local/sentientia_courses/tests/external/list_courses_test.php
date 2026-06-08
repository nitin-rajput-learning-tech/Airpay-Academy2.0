<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

namespace local_sentientia_courses\external;

defined('MOODLE_INTERNAL') || die();

/**
 * Security regression tests for list_courses.
 *
 * Locks in fixes from the May 5 2026 security audit:
 * - M3 tenant scope: list_courses had no tenant filter before the fix.
 * - C2 LIKE wildcard escape.
 * - M2 JSON filter bounds.
 *
 * @package    local_sentientia_courses
 * @category   test
 */
final class list_courses_test extends \advanced_testcase {

    use \local_airpay_org\test\bizlms_fixture;

    private function course_at_path(string $path): \stdClass {
        global $DB;
        $course = $this->getDataGenerator()->create_course();
        $DB->set_field('course', 'open_path', $path, ['id' => $course->id]);
        return $course;
    }

    private function user_at_path(string $path): \stdClass {
        global $DB;
        $u = $this->getDataGenerator()->create_user();
        $DB->set_field('user', 'open_path', $path, ['id' => $u->id]);
        $u->open_path = $path;
        return $u;
    }

    private function viewer_at_path(string $path): \stdClass {
        $u = $this->user_at_path($path);
        $sysctx = \context_system::instance();
        $roleid = $this->getDataGenerator()->create_role();
        role_change_permission($roleid, $sysctx, 'local/sentientia_courses:view', CAP_ALLOW);
        role_assign($roleid, $u->id, $sysctx->id);
        return $u;
    }

    private function call(array $overrides = []): array {
        $args = array_merge([
            'search'  => '',
            'sort'    => 'fullname',
            'sortdir' => 'asc',
            'page'    => 0,
            'perpage' => 25,
            'filters' => '{}',
        ], $overrides);

        return list_courses::execute(
            $args['search'], $args['sort'], $args['sortdir'],
            $args['page'], $args['perpage'], $args['filters']);
    }

    /**
     * M3 + C2: caller in /8001 must not see courses in /8002 or /80010.
     */
    public function test_m3_c2_tenant_scope_no_cross_tenant_leak(): void {
        $this->resetAfterTest();
        $this->ensure_bizlms_schema();

        $own_root  = $this->course_at_path('/8001');
        $own_sub   = $this->course_at_path('/8001/9001');
        $cross_dec = $this->course_at_path('/80010');     // decimal-overlap
        $cross_lit = $this->course_at_path('/8001_x');    // literal underscore
        $cross_oth = $this->course_at_path('/8002');      // sibling tenant

        $caller = $this->viewer_at_path('/8001');
        $this->setUser($caller);

        $r = $this->call(['perpage' => 50]);
        $ids = array_column($r['rows'], 'id');

        $this->assertContains((int) $own_root->id, $ids);
        $this->assertContains((int) $own_sub->id,  $ids);
        $this->assertNotContains((int) $cross_dec->id, $ids,
            'C2: /80010 leaked into /8001 scope');
        $this->assertNotContains((int) $cross_lit->id, $ids,
            'C2: /8001_x leaked into /8001 scope');
        $this->assertNotContains((int) $cross_oth->id, $ids,
            'M3: cross-tenant /8002 leaked');
    }

    /**
     * Siteadmin sees courses across all tenants.
     */
    public function test_siteadmin_sees_all_tenants(): void {
        $this->resetAfterTest();
        $this->ensure_bizlms_schema();
        $this->setAdminUser();

        $a = $this->course_at_path('/8001');
        $b = $this->course_at_path('/8002');
        $c = $this->course_at_path('/8003');

        $r = $this->call(['perpage' => 50]);
        $ids = array_column($r['rows'], 'id');

        $this->assertContains((int) $a->id, $ids);
        $this->assertContains((int) $b->id, $ids);
        $this->assertContains((int) $c->id, $ids);
    }

    /**
     * Courses with NULL open_path remain visible (legacy/unscoped).
     */
    public function test_null_open_path_courses_remain_visible(): void {
        global $DB;
        $this->resetAfterTest();
        $this->ensure_bizlms_schema();

        $caller = $this->viewer_at_path('/8001');
        $this->setUser($caller);

        $legacy = $this->getDataGenerator()->create_course();
        $DB->set_field('course', 'open_path', null, ['id' => $legacy->id]);

        $r = $this->call(['perpage' => 50]);
        $ids = array_column($r['rows'], 'id');
        $this->assertContains((int) $legacy->id, $ids,
            'Legacy course (open_path = NULL) must remain visible until data migration completes');
    }

    /**
     * M2: filters > 4 KB rejected.
     */
    public function test_m2_filter_size_limit(): void {
        $this->resetAfterTest();
        $this->ensure_bizlms_schema();
        $this->setAdminUser();

        $huge = json_encode(['payload' => str_repeat('a', 5000)]);
        $this->expectException(\moodle_exception::class);
        $this->expectExceptionMessage('filterstoolong');
        $this->call(['filters' => $huge]);
    }

    /**
     * Sort whitelist: bad sort key falls back to default.
     */
    public function test_sort_whitelist(): void {
        $this->resetAfterTest();
        $this->ensure_bizlms_schema();
        $this->setAdminUser();
        $this->course_at_path('/8001');
        $r = $this->call(['sort' => 'evil_column']);
        $this->assertIsArray($r);
    }
}
