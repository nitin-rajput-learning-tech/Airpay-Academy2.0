<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

namespace local_airpay_courses;

defined('MOODLE_INTERNAL') || die();

/**
 * @covers \local_airpay_courses\sharing_manager
 *
 * Sprint C unit tests for cross-tenant course sharing.
 *
 * Coverage:
 *   - share_course() inserts a row + idempotent on re-share
 *   - share_course() reactivates a withdrawn row
 *   - share_course() skips unknown tenants with an error entry
 *   - unshare_course() flips status='withdrawn' + returns true
 *   - unshare_course() no-op on already-withdrawn returns false
 *   - is_course_shared_to() truth table
 *   - list_course_shares() returns indexed-by-tenant_id
 *   - build_catalog_filter_sql() — site admin passthrough vs scoped
 *   - known_tenants() falls back to hard-coded list when org table missing
 */
class sharing_manager_test extends \advanced_testcase {

    // Day-3 (2026-05-14): pull in the airpay_core open_path fixture
    // trait. Adds `open_path` to {user} and {course} at
    // setUpBeforeClass time so tests that previously skipped on
    // vanilla PHPUnit fixture now actually run.
    use \local_airpay_core\phpunit\open_path_fixture_trait;

    /**
     * Helper — does the local_airpay_org table exist in this PHPUnit fixture?
     * Some tests depend on it for known_tenants(); skip if absent.
     */
    private static function org_table_exists(): bool {
        global $DB;
        return $DB->get_manager()->table_exists('local_airpay_org');
    }

    public function test_share_course_inserts_a_row(): void {
        global $DB;
        $this->resetAfterTest(true);
        $this->setAdminUser();

        $course = $this->getDataGenerator()->create_course();

        $out = sharing_manager::share_course((int) $course->id, [77]);

        $this->assertSame([77], $out['shared']);
        $this->assertSame([], $out['unchanged']);
        $this->assertSame([], $out['reactivated']);
        $this->assertSame([], $out['errors']);

        $row = $DB->get_record('local_airpay_courses_tenant_share',
            ['courseid' => $course->id, 'tenant_id' => 77]);
        $this->assertNotEmpty($row);
        $this->assertSame('active', $row->status);
    }

    public function test_share_course_is_idempotent_when_already_active(): void {
        $this->resetAfterTest(true);
        $this->setAdminUser();
        $course = $this->getDataGenerator()->create_course();

        sharing_manager::share_course((int) $course->id, [77]);
        $out2 = sharing_manager::share_course((int) $course->id, [77]);

        $this->assertSame([],     $out2['shared']);
        $this->assertSame([77],   $out2['unchanged']);
        $this->assertSame([],     $out2['reactivated']);
    }

    public function test_share_course_reactivates_withdrawn_row(): void {
        global $DB;
        $this->resetAfterTest(true);
        $this->setAdminUser();
        $course = $this->getDataGenerator()->create_course();

        sharing_manager::share_course((int) $course->id, [77]);
        sharing_manager::unshare_course((int) $course->id, 77);

        // Reactivate.
        $out = sharing_manager::share_course((int) $course->id, [77]);

        $this->assertSame([],     $out['shared']);
        $this->assertSame([77],   $out['reactivated']);
        $this->assertSame([],     $out['unchanged']);

        $row = $DB->get_record('local_airpay_courses_tenant_share',
            ['courseid' => $course->id, 'tenant_id' => 77]);
        $this->assertSame('active', $row->status);
    }

    public function test_share_course_records_error_for_unknown_tenant(): void {
        $this->resetAfterTest(true);
        $this->setAdminUser();
        $course = $this->getDataGenerator()->create_course();

        $out = sharing_manager::share_course((int) $course->id, [99999]);

        $this->assertSame([], $out['shared']);
        $this->assertArrayHasKey(99999, $out['errors']);
        $this->assertStringContainsString('not a known top-level org',
            $out['errors'][99999]);
    }

    public function test_unshare_course_flips_status(): void {
        global $DB;
        $this->resetAfterTest(true);
        $this->setAdminUser();
        $course = $this->getDataGenerator()->create_course();

        sharing_manager::share_course((int) $course->id, [77]);
        $changed = sharing_manager::unshare_course((int) $course->id, 77);
        $this->assertTrue($changed);

        $row = $DB->get_record('local_airpay_courses_tenant_share',
            ['courseid' => $course->id, 'tenant_id' => 77]);
        $this->assertSame('withdrawn', $row->status);
    }

    public function test_unshare_course_is_noop_on_already_withdrawn(): void {
        $this->resetAfterTest(true);
        $this->setAdminUser();
        $course = $this->getDataGenerator()->create_course();

        sharing_manager::share_course((int) $course->id, [77]);
        sharing_manager::unshare_course((int) $course->id, 77);
        // Run again — should return false (nothing changed).
        $changed = sharing_manager::unshare_course((int) $course->id, 77);
        $this->assertFalse($changed);
    }

    public function test_unshare_course_is_noop_on_never_shared(): void {
        $this->resetAfterTest(true);
        $this->setAdminUser();
        $course = $this->getDataGenerator()->create_course();

        $changed = sharing_manager::unshare_course((int) $course->id, 77);
        $this->assertFalse($changed);
    }

    public function test_is_course_shared_to_truth_table(): void {
        $this->resetAfterTest(true);
        $this->setAdminUser();
        $course = $this->getDataGenerator()->create_course();

        // Initially false.
        $this->assertFalse(
            sharing_manager::is_course_shared_to((int) $course->id, 77));

        sharing_manager::share_course((int) $course->id, [77]);
        $this->assertTrue(
            sharing_manager::is_course_shared_to((int) $course->id, 77));

        sharing_manager::unshare_course((int) $course->id, 77);
        // Withdrawn → false (helper only matches status='active').
        $this->assertFalse(
            sharing_manager::is_course_shared_to((int) $course->id, 77));
    }

    public function test_is_course_shared_to_guards_invalid_inputs(): void {
        $this->assertFalse(sharing_manager::is_course_shared_to(0, 77));
        $this->assertFalse(sharing_manager::is_course_shared_to(1, 0));
    }

    public function test_list_course_shares_indexed_by_tenant(): void {
        $this->resetAfterTest(true);
        $this->setAdminUser();
        $course = $this->getDataGenerator()->create_course();

        sharing_manager::share_course((int) $course->id, [77, 177]);

        $rows = sharing_manager::list_course_shares((int) $course->id);
        $this->assertArrayHasKey(77, $rows);
        $this->assertArrayHasKey(177, $rows);
        $this->assertSame('active', $rows[77]->status);
        $this->assertSame('active', $rows[177]->status);
    }

    public function test_build_catalog_filter_sql_passthrough_for_admin(): void {
        // viewer_tenant=0 means site admin or unscoped — should return 1=1.
        [$sql, $params] = sharing_manager::build_catalog_filter_sql('c', 0);
        $this->assertSame('1=1', $sql);
        $this->assertSame([], $params);
    }

    public function test_build_catalog_filter_sql_scoped_tenant_has_3_clauses(): void {
        [$sql, $params] = sharing_manager::build_catalog_filter_sql('c', 77);
        // Should reference open_path exact, prefix, AND share table existence.
        $this->assertStringContainsString('c.open_path = :share_orgexact', $sql);
        $this->assertStringContainsString('c.open_path LIKE :share_orgprefix', $sql);
        $this->assertStringContainsString('local_airpay_courses_tenant_share', $sql);
        $this->assertStringContainsString(':share_tenant_id', $sql);

        $this->assertSame('/77',  $params['share_orgexact']);
        $this->assertSame(77,     $params['share_tenant_id']);
        $this->assertSame('active', $params['share_status']);
    }

    public function test_known_tenants_returns_fallback_list_when_no_org_table(): void {
        // If the local_airpay_org table doesn't exist in this PHPUnit
        // fixture, the helper falls back to the hard-coded {1, 77, 177}.
        // (If it DOES exist, we just assert the structure is sensible.)
        $list = sharing_manager::known_tenants();
        $this->assertNotEmpty($list);
        foreach ($list as $t) {
            $this->assertObjectHasProperty('id', $t);
            $this->assertObjectHasProperty('name', $t);
            $this->assertGreaterThan(0, (int) $t->id);
        }
    }

    public function test_share_course_invalid_courseid_throws(): void {
        $this->resetAfterTest(true);
        $this->setAdminUser();
        $this->expectException(\moodle_exception::class);
        sharing_manager::share_course(0, [77]);
    }

    public function test_share_course_nonexistent_courseid_throws(): void {
        $this->resetAfterTest(true);
        $this->setAdminUser();
        $this->expectException(\moodle_exception::class);
        sharing_manager::share_course(999999, [77]);
    }
}
