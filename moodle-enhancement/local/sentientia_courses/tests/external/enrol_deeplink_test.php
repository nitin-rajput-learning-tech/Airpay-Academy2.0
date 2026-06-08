<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

namespace local_sentientia_courses\external;

defined('MOODLE_INTERNAL') || die();

/**
 * G-06 — Verify the Enrol Users deep-link in the courses list datatable.
 *
 * Locks in:
 * - When the caller has local/sentientia_courses:enrol, the row's actions HTML
 *   contains a link to /enrol/users.php?id=<courseid>
 * - When the caller lacks :enrol, the link is NOT in the actions HTML
 * - The link opens in a new tab (target="_blank")
 *
 * @package    local_sentientia_courses
 * @category   test
 */
final class enrol_deeplink_test extends \advanced_testcase {

    use \local_airpay_org\test\bizlms_fixture;

    public function test_enrol_link_present_for_capable_caller(): void {
        global $DB;
        $this->resetAfterTest();
        $this->ensure_bizlms_schema();

        $course = $this->getDataGenerator()->create_course();
        $DB->set_field('course', 'open_path', '/1', ['id' => $course->id]);

        // Siteadmin has all caps including :enrol.
        $this->setAdminUser();

        $result = list_courses::execute('', 'fullname', 'asc', 0, 25, '{}');
        $this->assertGreaterThan(0, $result['total']);

        // Find the row for our course.
        $row = null;
        foreach ($result['rows'] as $r) {
            if ((int) $r['id'] === (int) $course->id) { $row = $r; break; }
        }
        $this->assertNotNull($row, 'seeded course should be in the list');

        $this->assertStringContainsString('/enrol/users.php', $row['actions'],
            'enrol deep-link should be present for siteadmin');
        $this->assertStringContainsString('id=' . $course->id, $row['actions'],
            'enrol deep-link should reference the row courseid');
        $this->assertStringContainsString('target="_blank"', $row['actions'],
            'enrol deep-link should open in a new tab');
        $this->assertStringContainsString('rel="noopener"', $row['actions'],
            'enrol deep-link should set rel=noopener for security');
    }

    public function test_enrol_link_absent_for_view_only_caller(): void {
        global $DB;
        $this->resetAfterTest();
        $this->ensure_bizlms_schema();

        $course = $this->getDataGenerator()->create_course();
        $DB->set_field('course', 'open_path', '/1', ['id' => $course->id]);

        // User has only :view, NOT :enrol.
        $u = $this->getDataGenerator()->create_user();
        $DB->set_field('user', 'open_path', '/1', ['id' => $u->id]);
        $u->open_path = '/1';
        $sysctx = \context_system::instance();
        $roleid = $this->getDataGenerator()->create_role();
        role_change_permission($roleid, $sysctx, 'local/sentientia_courses:view', CAP_ALLOW);
        role_assign($roleid, $u->id, $sysctx->id);
        $this->setUser($u);

        $result = list_courses::execute('', 'fullname', 'asc', 0, 25, '{}');

        // Find the row for our course.
        $row = null;
        foreach ($result['rows'] as $r) {
            if ((int) $r['id'] === (int) $course->id) { $row = $r; break; }
        }
        $this->assertNotNull($row, 'seeded course should still be visible to :view caller');

        $this->assertStringNotContainsString('/enrol/users.php', $row['actions'],
            'enrol deep-link must NOT appear when caller lacks :enrol capability');
    }
}
