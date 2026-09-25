<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

namespace local_sentientia_whatsapp;

defined('MOODLE_INTERNAL') || die();

/**
 * ADR-031 follow-up (2026-09-25 review): the learning-path milestone message
 * links the learner to a page they can open.
 *
 * {{path_url}} pointed at /local/sentientia_learningpath/view.php?id=N. That
 * page is the admin surface (path rosters with names, emails and employee ids,
 * and the CSV export), and ADR-031 took local/sentientia_learningpath:view
 * away from every learner role. So after the ADR-031 deploy every milestone
 * link answered "required capability". It now points at the learner's own
 * course list, /local/sentientia_catalog/mycourses.php, which needs only a
 * login and lists the path's courses (the path enrolment enrols into them).
 *
 * @package    local_sentientia_whatsapp
 * @category   test
 * @copyright  2026 Airpay Payment Services
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \local_sentientia_whatsapp\notification_bridge::send_path_milestone
 * @group      tenant_isolation
 */
final class path_milestone_link_test extends \advanced_testcase {

    protected function setUp(): void {
        global $DB;
        parent::setUp();
        $this->resetAfterTest();
        if (!$DB->get_manager()->table_exists('local_sentientia_learningpath')) {
            $this->markTestSkipped('local_sentientia_learningpath is not installed.');
        }
        $id = dlt_template_registry::upsert([
            'template_key' => notification_bridge::TPL_PATH_MILESTONE,
            'channel'      => 'whatsapp',
            'body'         => 'Hi {{firstname}}, {{milestone_label}} of {{path_name}}: {{path_url}}',
        ]);
        dlt_template_registry::transition_status($id, 'approved');
        if (class_exists('\\local_sentientia_platform\\feature_flags')) {
            \local_sentientia_platform\feature_flags::set(
                notification_bridge::CONTENT_FLAG, 0, true, null, 'phpunit-test', 0);
        }
    }

    /** An opted-in learner. */
    private function learner(): \stdClass {
        $u = $this->getDataGenerator()->create_user(['firstname' => 'Asha']);
        preference_manager::set($u->id, [
            'mobile_number'    => '+919876543211',
            'whatsapp_optin'   => 1,
            'dlt_consent_text' => 'I agree.',
            'prefer_channel'   => 'whatsapp',
        ]);
        return $u;
    }

    private function path(string $name, string $openpath): int {
        global $DB;
        return (int) $DB->insert_record('local_sentientia_learningpath', (object) [
            'name' => $name, 'description' => '', 'descriptionformat' => 1,
            'costcenterid' => 0, 'open_path' => $openpath, 'status' => 1, 'visible' => 1,
            'timecreated' => time(), 'timemodified' => time(),
        ]);
    }

    public function test_the_milestone_link_is_the_learners_course_list_not_the_admin_page(): void {
        global $DB;
        $learner = $this->learner();
        $pathid = $this->path('Compliance 2026', '/1');
        $this->setUser($learner);

        $this->assertSame('mocked',
            notification_bridge::send_path_milestone((int) $learner->id, $pathid, '75%'));

        $rows = $DB->get_records('local_sentientia_send_log',
            ['userid' => $learner->id, 'template_key' => notification_bridge::TPL_PATH_MILESTONE]);
        $this->assertCount(1, $rows);
        $body = (string) reset($rows)->failure_reason;
        $this->assertStringContainsString(
            (new \moodle_url('/local/sentientia_catalog/mycourses.php'))->out(false), $body);
        $this->assertStringNotContainsString('sentientia_learningpath/view.php', $body,
            'The admin roster page is closed to learners since ADR-031.');
        // The fact the old link ran into: a learner holds no :view there.
        $this->assertFalse(has_capability('local/sentientia_learningpath:view',
            \context_system::instance(), $learner->id));
    }
}
