<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

namespace local_sentientia_whatsapp;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->dirroot . '/course/lib.php');

/**
 * @covers \local_sentientia_whatsapp\observer
 *
 * Stream F / Wave E2 P4 (2026-05-25). Integration tests for the
 * content-notification observers, focusing on the novel glue logic:
 *
 *   - course_updated publish-once semantics (announce on the first
 *     visible transition; never re-announce on later edits; re-announce
 *     after a hide → re-publish cycle)
 *   - course_completed → learning-path milestone threshold crossing
 *
 * Drives the REAL Moodle event API (update_course → course_updated) so
 * the db/events.php registration is exercised end-to-end. All sends run
 * in mock mode (engagement.whatsapp.enabled OFF + $CFG->noemailever).
 */
class observer_test extends \advanced_testcase {

    public function setUp(): void {
        parent::setUp();
        $this->resetAfterTest(true);

        // Approve the content_new_course + content_path_milestone templates.
        foreach ([
            [notification_bridge::TPL_NEW_COURSE,
                'Hi {{firstname}}, new course {{course_name}}: {{course_url}}'],
            [notification_bridge::TPL_PATH_MILESTONE,
                'Hi {{firstname}}, {{milestone_label}} of {{path_name}}: {{path_url}}'],
        ] as [$key, $body]) {
            $id = dlt_template_registry::upsert([
                'template_key' => $key,
                'channel'      => 'whatsapp',
                'body'         => $body,
            ]);
            dlt_template_registry::transition_status($id, 'approved');
        }

        $this->force_content_flag(true);
    }

    public function test_publishing_a_course_announces_once_to_enrolled_user(): void {
        global $DB;

        // Hidden course + an opted-in enrolled learner.
        $course = $this->getDataGenerator()->create_course([
            'fullname' => 'New Compliance Course',
            'visible'  => 0,
        ]);
        $user = $this->opted_in_user();
        $this->getDataGenerator()->enrol_user($user->id, $course->id);

        // Publish it. update_course fires \core\event\course_updated.
        $this->setAdminUser();
        $this->publish_course($course);

        $count = $DB->count_records('local_sentientia_send_log', [
            'userid'       => $user->id,
            'template_key' => notification_bridge::TPL_NEW_COURSE,
        ]);
        $this->assertSame(1, $count,
            'Publishing a course should announce exactly once.');
    }

    public function test_editing_a_visible_course_does_not_reannounce(): void {
        global $DB;

        $course = $this->getDataGenerator()->create_course(['visible' => 0]);
        $user = $this->opted_in_user();
        $this->getDataGenerator()->enrol_user($user->id, $course->id);

        $this->setAdminUser();
        $this->publish_course($course);

        // A second, unrelated edit while already visible.
        $course->fullname = 'Renamed While Visible';
        update_course($course);

        $count = $DB->count_records('local_sentientia_send_log', [
            'userid'       => $user->id,
            'template_key' => notification_bridge::TPL_NEW_COURSE,
        ]);
        $this->assertSame(1, $count,
            'Editing an already-visible course must NOT re-announce.');
    }

    public function test_hidden_course_update_does_not_announce(): void {
        global $DB;

        $course = $this->getDataGenerator()->create_course(['visible' => 0]);
        $user = $this->opted_in_user();
        $this->getDataGenerator()->enrol_user($user->id, $course->id);

        // Edit while still hidden.
        $this->setAdminUser();
        $course->fullname = 'Still Hidden';
        update_course($course);

        $count = $DB->count_records('local_sentientia_send_log', [
            'userid'       => $user->id,
            'template_key' => notification_bridge::TPL_NEW_COURSE,
        ]);
        $this->assertSame(0, $count,
            'A hidden course must never announce.');
    }

    public function test_republish_after_hide_announces_again(): void {
        global $DB;

        $course = $this->getDataGenerator()->create_course(['visible' => 0]);
        $user = $this->opted_in_user();
        $this->getDataGenerator()->enrol_user($user->id, $course->id);
        $this->setAdminUser();

        // Publish (1st announce).
        $this->publish_course($course);

        // Hide it again — clears the marker. Move the first send_log row
        // out of the 6h throttle window so the re-publish isn't
        // suppressed by the per-user throttle.
        $course->visible = 0;
        update_course($course);
        $DB->set_field('local_sentientia_send_log', 'timecreated',
            time() - (7 * 3600), ['userid' => $user->id]);

        // Re-publish (2nd announce).
        $this->publish_course($course);

        $count = $DB->count_records('local_sentientia_send_log', [
            'userid'       => $user->id,
            'template_key' => notification_bridge::TPL_NEW_COURSE,
        ]);
        $this->assertSame(2, $count,
            'A genuine hide → re-publish cycle should announce again.');
    }

    public function test_publish_with_content_flag_off_announces_nothing(): void {
        global $DB;

        $this->force_content_flag(false);
        $course = $this->getDataGenerator()->create_course(['visible' => 0]);
        $user = $this->opted_in_user();
        $this->getDataGenerator()->enrol_user($user->id, $course->id);

        $this->setAdminUser();
        $this->publish_course($course);

        $count = $DB->count_records('local_sentientia_send_log', [
            'userid'       => $user->id,
            'template_key' => notification_bridge::TPL_NEW_COURSE,
        ]);
        $this->assertSame(0, $count,
            'Content flag OFF must suppress all content notifications.');
    }

    public function test_course_completed_fires_path_milestone_at_50pct(): void {
        global $DB;

        if (!$DB->get_manager()->table_exists('local_airpay_learningpath_courses')) {
            $this->markTestSkipped('learning-path tables not installed.');
        }

        // 2-course path. Completing one course = 50%.
        $c1 = $this->getDataGenerator()->create_course();
        $c2 = $this->getDataGenerator()->create_course();
        $pathid = (int) $DB->insert_record('local_airpay_learningpath', (object) [
            'name' => 'Two Step Path', 'description' => '',
            'descriptionformat' => 1, 'costcenterid' => 0, 'status' => 1,
            'visible' => 1, 'timecreated' => time(), 'timemodified' => time(),
        ]);
        foreach ([$c1, $c2] as $i => $c) {
            $DB->insert_record('local_airpay_learningpath_courses', (object) [
                'pathid' => $pathid, 'courseid' => $c->id,
                'sortorder' => $i, 'mandatory' => 1, 'timecreated' => time(),
            ]);
        }

        $user = $this->opted_in_user();
        $DB->insert_record('local_airpay_learningpath_users', (object) [
            'pathid' => $pathid, 'userid' => $user->id, 'status' => 1,
            'timecreated' => time(),
        ]);

        // Mark c1 complete in core, then fire the course_completed event
        // built from the real completion record (matches core's own
        // create_from_completion path, so objectid + snapshot validate).
        $completionid = $DB->insert_record('course_completions', (object) [
            'userid' => $user->id, 'course' => $c1->id,
            'timeenrolled' => time(), 'timestarted' => time(),
            'timecompleted' => time(),
        ]);
        $completion = $DB->get_record('course_completions',
            ['id' => $completionid], '*', MUST_EXIST);

        $event = \core\event\course_completed::create_from_completion($completion);
        observer::course_completed($event);

        $count = $DB->count_records('local_sentientia_send_log', [
            'userid'       => $user->id,
            'template_key' => notification_bridge::TPL_PATH_MILESTONE,
        ]);
        $this->assertSame(1, $count,
            'Completing 1 of 2 path courses should fire a 50% milestone.');

        $row = $DB->get_record('local_sentientia_send_log', [
            'userid'       => $user->id,
            'template_key' => notification_bridge::TPL_PATH_MILESTONE,
        ]);
        $this->assertStringContainsString('[ctx=path:' . $pathid . ':50%]',
            $row->failure_reason);
    }

    // ─── helpers ───────────────────────────────────────────────────────

    private function opted_in_user(): \stdClass {
        $user = $this->getDataGenerator()->create_user(['firstname' => 'Asha']);
        preference_manager::set($user->id, [
            'mobile_number'    => '+919812345678',
            'whatsapp_optin'   => 1,
            'dlt_consent_text' => 'I agree.',
            'prefer_channel'   => 'whatsapp',
        ], $user->id);
        return $user;
    }

    private function publish_course(\stdClass $course): void {
        $course->visible = 1;
        update_course($course);
    }

    private function force_content_flag(bool $on): void {
        if (!class_exists('\\local_airpay_core\\feature_flags')) {
            return;
        }
        \local_airpay_core\feature_flags::set(
            notification_bridge::CONTENT_FLAG, 0, $on, null, 'phpunit-test', 0);
    }
}
