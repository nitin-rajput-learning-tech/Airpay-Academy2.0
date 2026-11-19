<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

namespace local_sentientia_emails;

defined('MOODLE_INTERNAL') || die();

use local_sentientia_org\test\bizlms_fixture;
use local_sentientia_platform\feature_flags;

/**
 * Post-demo quick win (2026-11-19, plan 2.4): the learning-path enrolment e-mail links the learner to My courses.
 *
 * It linked the path's admin page (local/sentientia_learningpath/view.php), which needs the :view capability that
 * learner roles do not hold, so every learner who followed the link was refused.
 *
 * Nothing is delivered: the message sink catches the message before any processor runs.
 *
 * @package    local_sentientia_emails
 * @category   test
 * @covers     \local_sentientia_emails\parity_senders::learning_path_enrolled
 * @covers     \local_sentientia_emails\parity_senders::path_enrolled_url
 * @group      local_sentientia_emails
 */
final class path_enrolled_link_test extends \advanced_testcase {
    use bizlms_fixture;

    protected function setUp(): void {
        parent::setUp();
        $this->resetAfterTest();
        $this->ensure_bizlms_schema();
        $this->setAdminUser();
    }

    protected function tearDown(): void {
        try {
            feature_flags::set(parity_senders::FLAG_PATH_ENROLMENT, 0, null);
        } catch (\Throwable $e) {
            debugging('could not unset the test flag: ' . $e->getMessage(), DEBUG_DEVELOPER);
        }
        parent::tearDown();
    }

    public function test_the_link_is_my_courses(): void {
        global $CFG;
        $this->assertSame($CFG->wwwroot . '/my/courses.php', parity_senders::path_enrolled_url());
    }

    public function test_the_sent_email_links_my_courses_and_never_the_admin_page(): void {
        global $CFG, $DB;
        if (!$DB->get_manager()->table_exists('local_sentientia_learningpath_courses')) {
            $this->markTestSkipped('the learning-path plugin is not installed on this test site');
        }
        $CFG->noemailever = false;   // The sink below catches the message; nothing leaves the test.
        $sink = $this->redirectMessages();

        $course = $this->getDataGenerator()->create_course();
        $now = time();
        $pathid = (int) $DB->insert_record('local_sentientia_learningpath', (object) [
            'name' => 'Onboarding', 'status' => 1, 'visible' => 1, 'timecreated' => $now, 'timemodified' => $now,
        ]);
        $DB->insert_record('local_sentientia_learningpath_courses', (object) [
            'pathid' => $pathid, 'courseid' => $course->id, 'sortorder' => 0, 'timecreated' => $now, 'timemodified' => $now,
        ]);
        $user = $this->getDataGenerator()->create_user();
        $DB->set_field('user', 'open_path', '/1/5', ['id' => $user->id]);
        feature_flags::set(parity_senders::FLAG_PATH_ENROLMENT, 0, true);

        $this->assertTrue(parity_senders::learning_path_enrolled((int) $user->id, $pathid, 2));

        $messages = $sink->get_messages();
        $sink->close();
        $this->assertNotEmpty($messages, 'the e-mail was handed to the message API');
        foreach ($messages as $message) {
            $this->assertSame((int) $user->id, (int) $message->useridto);
            $this->assertStringContainsString('href="' . $CFG->wwwroot . '/my/courses.php"', $message->fullmessagehtml);
            $this->assertStringNotContainsString('sentientia_learningpath/view.php', $message->fullmessagehtml);
            $this->assertStringContainsString('Go to My courses', $message->fullmessagehtml);
        }
    }

    public function test_the_preview_sample_links_my_courses_too(): void {
        global $CFG;
        $context = email_context::get_sample('enrollment/learning_path_enrolled');
        $this->assertSame($CFG->wwwroot . '/my/courses.php', $context['path_url']);
    }
}
