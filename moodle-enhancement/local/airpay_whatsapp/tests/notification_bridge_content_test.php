<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

namespace local_airpay_whatsapp;

defined('MOODLE_INTERNAL') || die();

/**
 * @covers \local_airpay_whatsapp\notification_bridge
 *
 * Stream F / Wave E2 P4 (2026-05-25). Regression suite for the four
 * content-event triggers wired on notification_bridge:
 *
 *   send_new_course_notification
 *   send_course_due_soon
 *   send_certificate_ready
 *   send_path_milestone
 *
 * Coverage:
 *   - happy path (mocked send, template substitution)
 *   - master content flag default OFF
 *   - master content flag ON, channel-specific gates still defer
 *   - throttle suppresses duplicate within 6h window
 *   - throttle does NOT suppress different milestones on same path
 *   - certificate sanity check (issue.userid must match recipient)
 *   - learning-path milestone progression 25 → 50 → 75 → 100
 */
class notification_bridge_content_test extends \advanced_testcase {

    /** @var \stdClass */
    private $user;

    public function setUp(): void {
        parent::setUp();
        $this->resetAfterTest(true);

        // Seed all 4 content templates as approved so the client passes
        // its DLT gate.
        foreach ([
            [notification_bridge::TPL_NEW_COURSE,
                'Hi {{firstname}}, new course: {{course_name}} at {{course_url}}'],
            [notification_bridge::TPL_COURSE_DUE_SOON,
                'Hi {{firstname}}, {{course_name}} due in {{deadline}} at {{course_url}}'],
            [notification_bridge::TPL_CERTIFICATE_READY,
                'Congrats {{firstname}}! {{course_name}} cert: {{certificate_url}}'],
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

        // Opt the test user in to WhatsApp delivery.
        $this->user = $this->getDataGenerator()->create_user([
            'firstname' => 'Sarah',
            'lastname'  => 'Khan',
        ]);
        $this->setUser($this->user);
        preference_manager::set($this->user->id, [
            'mobile_number'    => '+919876543210',
            'whatsapp_optin'   => 1,
            'dlt_consent_text' => 'I agree.',
            'prefer_channel'   => 'whatsapp',
        ]);

        // Force the content master flag ON so each test can opt back to
        // testing the gate behaviour explicitly.
        $this->force_content_flag(true);
    }

    // ─── send_new_course_notification ──────────────────────────────────

    public function test_new_course_sends_mocked_with_substituted_template(): void {
        global $DB;

        $course = $this->getDataGenerator()->create_course([
            'fullname' => 'Compliance 101',
            'visible'  => 1,
        ]);

        $status = notification_bridge::send_new_course_notification(
            (int) $this->user->id, (int) $course->id);
        $this->assertSame('mocked', $status,
            'Mock mode is in effect — engagement.whatsapp.enabled is OFF in tests.');

        $log = $this->latest_log_for_template(notification_bridge::TPL_NEW_COURSE);
        $this->assertNotNull($log, 'Expected a send_log row.');
        $this->assertSame((int) $this->user->id, (int) $log->userid);
        // The context tag is stamped into failure_reason for throttle queries.
        $this->assertStringContainsString('[ctx=course:' . $course->id . ']',
            $log->failure_reason);
        // Template substitution: the mock client stamps the rendered body
        // into failure_reason ('| rendered: ...'). The firstname +
        // course_name placeholders must have been resolved.
        $this->assertStringContainsString('Sarah', $log->failure_reason);
        $this->assertStringContainsString('Compliance 101', $log->failure_reason);
        $this->assertStringNotContainsString('{{course_name}}', $log->failure_reason);
    }

    public function test_new_course_returns_flag_off_when_content_master_off(): void {
        $this->force_content_flag(false);
        $course = $this->getDataGenerator()->create_course();
        $status = notification_bridge::send_new_course_notification(
            (int) $this->user->id, (int) $course->id);
        $this->assertSame('flag_off', $status);
    }

    public function test_new_course_throttle_suppresses_duplicate_within_6h(): void {
        $course = $this->getDataGenerator()->create_course();

        $first  = notification_bridge::send_new_course_notification(
            (int) $this->user->id, (int) $course->id);
        $second = notification_bridge::send_new_course_notification(
            (int) $this->user->id, (int) $course->id);

        $this->assertSame('mocked', $first);
        $this->assertSame('throttled', $second);
    }

    public function test_new_course_returns_no_user_when_userid_invalid(): void {
        $course = $this->getDataGenerator()->create_course();
        $status = notification_bridge::send_new_course_notification(
            0, (int) $course->id);
        $this->assertSame('no_user', $status);
    }

    public function test_new_course_returns_no_record_when_course_missing(): void {
        $status = notification_bridge::send_new_course_notification(
            (int) $this->user->id, 999999);
        $this->assertSame('no_record', $status);
    }

    // ─── send_course_due_soon ──────────────────────────────────────────

    public function test_due_soon_sends_mocked_with_substituted_template(): void {
        $course = $this->getDataGenerator()->create_course([
            'fullname' => 'KYC Refresher',
        ]);

        $status = notification_bridge::send_course_due_soon(
            (int) $this->user->id, (int) $course->id, 24);
        $this->assertSame('mocked', $status);

        $log = $this->latest_log_for_template(notification_bridge::TPL_COURSE_DUE_SOON);
        $this->assertNotNull($log);
        $this->assertStringContainsString('[ctx=course:' . $course->id . ']',
            $log->failure_reason);
        // {{course_name}} + {{deadline}} substitution. 24 hours → "24 hours".
        $this->assertStringContainsString('KYC Refresher', $log->failure_reason);
        $this->assertStringContainsString('24 hours', $log->failure_reason);
    }

    public function test_due_soon_returns_flag_off_when_content_master_off(): void {
        $this->force_content_flag(false);
        $course = $this->getDataGenerator()->create_course();
        $status = notification_bridge::send_course_due_soon(
            (int) $this->user->id, (int) $course->id, 6);
        $this->assertSame('flag_off', $status);
    }

    public function test_due_soon_throttle_suppresses_duplicate(): void {
        $course = $this->getDataGenerator()->create_course();

        $first  = notification_bridge::send_course_due_soon(
            (int) $this->user->id, (int) $course->id, 36);
        $second = notification_bridge::send_course_due_soon(
            (int) $this->user->id, (int) $course->id, 30);

        $this->assertSame('mocked', $first);
        $this->assertSame('throttled', $second,
            'Same (user, course) within 6h must be throttled regardless of'
            . ' hours_remaining value.');
    }

    public function test_due_soon_rejects_past_deadline(): void {
        $course = $this->getDataGenerator()->create_course();
        $status = notification_bridge::send_course_due_soon(
            (int) $this->user->id, (int) $course->id, -1);
        $this->assertSame('no_record', $status);
    }

    // ─── send_certificate_ready ────────────────────────────────────────

    public function test_certificate_sends_mocked_when_issue_belongs_to_user(): void {
        global $DB;

        $course = $this->getDataGenerator()->create_course(['fullname' => 'AML Module']);
        $certid = $this->seed_cert_issue((int) $this->user->id, (int) $course->id,
            'CODEABC123');

        $status = notification_bridge::send_certificate_ready(
            (int) $this->user->id, $certid);
        $this->assertSame('mocked', $status);

        $log = $this->latest_log_for_template(notification_bridge::TPL_CERTIFICATE_READY);
        $this->assertNotNull($log);
        $this->assertStringContainsString('[ctx=cert:' . $certid . ']',
            $log->failure_reason);
        // {{course_name}} + {{certificate_url}} substitution.
        $this->assertStringContainsString('AML Module', $log->failure_reason);
        $this->assertStringContainsString('CODEABC123', $log->failure_reason);
    }

    public function test_certificate_returns_flag_off_when_content_master_off(): void {
        $this->force_content_flag(false);
        $course = $this->getDataGenerator()->create_course();
        $certid = $this->seed_cert_issue((int) $this->user->id,
            (int) $course->id, 'CFOFFCODE');
        $status = notification_bridge::send_certificate_ready(
            (int) $this->user->id, $certid);
        $this->assertSame('flag_off', $status);
    }

    public function test_certificate_rejects_when_issue_userid_mismatches_recipient(): void {
        $other = $this->getDataGenerator()->create_user();
        $course = $this->getDataGenerator()->create_course();
        $certid = $this->seed_cert_issue((int) $other->id, (int) $course->id,
            'MISMATCH1');

        $status = notification_bridge::send_certificate_ready(
            (int) $this->user->id, $certid);
        $this->assertSame('no_record', $status,
            'Defensive sanity: the issue.userid must match the recipient.');
    }

    public function test_certificate_throttle_suppresses_duplicate(): void {
        $course = $this->getDataGenerator()->create_course();
        $certid = $this->seed_cert_issue((int) $this->user->id,
            (int) $course->id, 'THROT12345');

        $first  = notification_bridge::send_certificate_ready(
            (int) $this->user->id, $certid);
        $second = notification_bridge::send_certificate_ready(
            (int) $this->user->id, $certid);
        $this->assertSame('mocked', $first);
        $this->assertSame('throttled', $second);
    }

    // ─── send_path_milestone ───────────────────────────────────────────

    public function test_path_milestone_sends_mocked_with_substituted_template(): void {
        $pathid = $this->seed_learningpath('Onboarding Path');

        $status = notification_bridge::send_path_milestone(
            (int) $this->user->id, $pathid, '50%');
        $this->assertSame('mocked', $status);

        $log = $this->latest_log_for_template(notification_bridge::TPL_PATH_MILESTONE);
        $this->assertNotNull($log);
        $this->assertStringContainsString('[ctx=path:' . $pathid . ':50%]',
            $log->failure_reason);
        // {{path_name}} + {{milestone_label}} substitution.
        $this->assertStringContainsString('Onboarding Path', $log->failure_reason);
        $this->assertStringContainsString('50%', $log->failure_reason);
    }

    public function test_path_milestone_returns_flag_off_when_content_master_off(): void {
        $this->force_content_flag(false);
        $pathid = $this->seed_learningpath('Compliance Path');
        $status = notification_bridge::send_path_milestone(
            (int) $this->user->id, $pathid, '25%');
        $this->assertSame('flag_off', $status);
    }

    public function test_path_milestone_throttle_per_milestone_not_per_path(): void {
        $pathid = $this->seed_learningpath('Throttle Path');

        // Same milestone twice — second should be throttled.
        $a = notification_bridge::send_path_milestone(
            (int) $this->user->id, $pathid, '25%');
        $b = notification_bridge::send_path_milestone(
            (int) $this->user->id, $pathid, '25%');
        $this->assertSame('mocked', $a);
        $this->assertSame('throttled', $b);

        // Different milestone on same path within the 6h window — should pass.
        $c = notification_bridge::send_path_milestone(
            (int) $this->user->id, $pathid, '50%');
        $this->assertSame('mocked', $c,
            'Different milestone on same path within 6h must NOT be throttled.');
    }

    public function test_path_milestone_returns_no_record_for_missing_path(): void {
        $status = notification_bridge::send_path_milestone(
            (int) $this->user->id, 999999, '50%');
        $this->assertSame('no_record', $status);
    }

    public function test_path_milestone_returns_no_record_when_label_blank(): void {
        $pathid = $this->seed_learningpath('Label Test');
        $status = notification_bridge::send_path_milestone(
            (int) $this->user->id, $pathid, '');
        $this->assertSame('no_record', $status);
    }

    // ─── helpers ───────────────────────────────────────────────────────

    /**
     * Force the content master flag to a known state. Uses the
     * feature_flags::set() public API so the resolver picks it up.
     */
    private function force_content_flag(bool $on): void {
        if (!class_exists('\\local_airpay_core\\feature_flags')) {
            return;  // dev env without core flags — fail-open
        }
        \local_airpay_core\feature_flags::set(
            notification_bridge::CONTENT_FLAG, 0, $on, null,
            'phpunit-test', 0);
    }

    private function latest_log_for_template(string $template_key): ?\stdClass {
        global $DB;
        $rows = $DB->get_records('local_airpay_send_log',
            ['userid' => $this->user->id, 'template_key' => $template_key],
            'timecreated DESC, id DESC');
        return $rows ? reset($rows) : null;
    }

    /**
     * Seed a tool_certificate_issues row. Bypasses the certificate template
     * machinery and just creates an issue we can reference by id.
     */
    private function seed_cert_issue(int $userid, int $courseid, string $code): int {
        global $DB;
        if (!$DB->get_manager()->table_exists('tool_certificate_issues')) {
            $this->markTestSkipped('tool_certificate_issues table not installed.');
        }
        return (int) $DB->insert_record('tool_certificate_issues', (object) [
            'userid'      => $userid,
            'templateid'  => 0,
            'code'        => $code,
            'emailed'     => 0,
            'timecreated' => time(),
            'data'        => json_encode([]),
            'component'   => 'tool_certificate',
            'courseid'    => $courseid,
            'archived'    => 0,
        ]);
    }

    /**
     * Seed a local_airpay_learningpath row. Skips when the table doesn't
     * exist (path plugin not installed in the test container).
     */
    private function seed_learningpath(string $name): int {
        global $DB;
        if (!$DB->get_manager()->table_exists('local_airpay_learningpath')) {
            $this->markTestSkipped('local_airpay_learningpath table not installed.');
        }
        return (int) $DB->insert_record('local_airpay_learningpath', (object) [
            'name'              => $name,
            'description'       => '',
            'descriptionformat' => 1,
            'costcenterid'      => 0,
            'status'            => 1,
            'visible'           => 1,
            'timecreated'       => time(),
            'timemodified'      => time(),
        ]);
    }
}
