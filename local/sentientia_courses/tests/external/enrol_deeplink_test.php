<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

namespace local_sentientia_courses\external;

defined('MOODLE_INTERNAL') || die();

/**
 * Verify the Enrol Users action in the courses list datatable.
 *
 * G-06 (2026-05-07) shipped this as a new-tab deep-link to
 * /enrol/users.php. Phase F.5 (2026-05-08) replaced it with the native
 * in-page enrol modal (amd course_actions.js `enrol-users-modal` →
 * core_form/modalform on \local_sentientia_courses\form\enrol_users_modal),
 * keeping the /enrol/users.php href as the no-JS / new-tab fallback.
 *
 * Locks in:
 * - When the caller has local/sentientia_courses:enrol, the row's actions HTML
 *   has exactly one enrol trigger: data-action="enrol-users-modal",
 *   data-courseid="<courseid>", href fallback /enrol/users.php?id=<courseid>
 * - The modal form class the trigger opens exists
 * - When the caller lacks :enrol, neither the modal trigger nor the link is
 *   in the actions HTML
 *
 * @package    local_sentientia_courses
 * @category   test
 */
final class enrol_deeplink_test extends \advanced_testcase {

    use \local_sentientia_org\test\bizlms_fixture;

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

        // Isolate the enrol trigger: other row actions (enrolled users, share)
        // also carry id=<courseid>, so a whole-string search proves little.
        $count = preg_match_all('/<a\s[^>]*data-action="enrol-users-modal"[^>]*>/',
            $row['actions'], $matches);
        $this->assertSame(1, $count,
            'exactly one enrol-users-modal trigger should be present for siteadmin');
        $anchor = $matches[0][0];

        $this->assertStringContainsString('data-courseid="' . (int) $course->id . '"', $anchor,
            'modal trigger should carry the row courseid for the JS handler');
        $enrolurl = (new \moodle_url('/enrol/users.php', ['id' => (int) $course->id]))->out(false);
        $this->assertStringContainsString('href="' . s($enrolurl) . '"', $anchor,
            'modal trigger should keep /enrol/users.php?id=<courseid> as its fallback href');
        $this->assertTrue(class_exists(\local_sentientia_courses\form\enrol_users_modal::class),
            'the modal form the trigger opens must exist');
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
        $this->assertStringNotContainsString('enrol-users-modal', $row['actions'],
            'enrol modal trigger must NOT appear when caller lacks :enrol capability');
    }
}
