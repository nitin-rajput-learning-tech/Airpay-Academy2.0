<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

namespace local_airpay_core;

defined('MOODLE_INTERNAL') || die();

/**
 * @covers \local_airpay_core\audit_log
 *
 * Tests that exercise the `sensitive_actions()` SQL path require the
 * BizLMS `user.open_path` column. That column is added by the
 * `local_costcenter` plugin in production but is NOT present on a
 * vanilla Moodle PHPUnit fixture. Tests that need the column skip
 * cleanly when it's absent (same pattern as `tenant_test.php`).
 */
class audit_log_test extends \advanced_testcase {

    private static function open_path_column_exists(): bool {
        global $DB;
        $columns = $DB->get_columns('user');
        return isset($columns['open_path']);
    }

    private function skip_if_no_open_path(): void {
        if (!self::open_path_column_exists()) {
            $this->markTestSkipped(
                'BizLMS user.open_path column not present (PHPUnit fixture)');
        }
    }

    public function test_sensitive_events_list_is_non_empty(): void {
        $this->assertGreaterThan(0, count(audit_log::SENSITIVE_EVENTS));
        // Sanity: list contains role-assigned and user-created at least.
        $this->assertContains('\\core\\event\\role_assigned',
            audit_log::SENSITIVE_EVENTS);
        $this->assertContains('\\core\\event\\user_created',
            audit_log::SENSITIVE_EVENTS);

        // P1 #24 — closes audit item #13 from airpay_courses.md.
        // Course CRUD events (including the previously-missing
        // course_updated) and course_categories CRUD must all be
        // audited. Without these, compliance can't answer "what
        // changed on this course and when?".
        foreach ([
            '\\core\\event\\course_created',
            '\\core\\event\\course_updated',
            '\\core\\event\\course_deleted',
            '\\core\\event\\course_visibility_updated',
            '\\core\\event\\course_section_created',
            '\\core\\event\\course_section_updated',
            '\\core\\event\\course_category_created',
            '\\core\\event\\course_category_updated',
            '\\core\\event\\course_category_deleted',
        ] as $eventname) {
            $this->assertContains($eventname,
                audit_log::SENSITIVE_EVENTS,
                "audit_log::SENSITIVE_EVENTS missing $eventname — compliance gap");
        }
    }

    public function test_tenant_actions_requires_admin_or_viewreports(): void {
        $this->resetAfterTest(true);
        $gen = $this->getDataGenerator();
        $u = $gen->create_user();
        $this->setUser($u);

        $this->expectException(\moodle_exception::class);
        audit_log::tenant_actions(1, time() - 86400, time());
    }

    public function test_actions_by_user_returns_empty_for_unknown_user(): void {
        $this->resetAfterTest(true);
        // Userid 99999 should have no log rows in a fresh fixture.
        $rows = audit_log::actions_by_user(99999, 0, time());
        $this->assertSame([], $rows);
    }

    public function test_sensitive_actions_returns_array(): void {
        $this->resetAfterTest(true);
        $this->skip_if_no_open_path();
        $this->setAdminUser();
        $rows = audit_log::sensitive_actions(24);
        // On a fresh fixture there may be a few rows from
        // resetAfterTest scaffolding; we only require that the call
        // succeeds and returns an array.
        $this->assertIsArray($rows);
    }
}
