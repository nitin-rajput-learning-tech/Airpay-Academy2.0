<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

namespace local_sentientia_emails;

defined('MOODLE_INTERNAL') || die();

/**
 * @covers \local_sentientia_emails\observer
 *
 * Sprint B unit tests for the course_completed event observer.
 *
 * The observer's job is to:
 *   (a) Look up the user's `course_completed` rule and call
 *       notification_sender::send().
 *   (b) Stamp any existing reminder rows for (user, course) as
 *       status='suppressed_completion'.
 *
 * These tests focus on (b) — easier to assert deterministically since
 * notification_sender::send() pipelines through message_send which
 * needs a full message-sink stub. The mark-suppressed behaviour can
 * be exercised in isolation via delivery_log directly.
 *
 * Send-and-attach behaviour is integration-tested via the smoke flow
 * documented in Sprint B's commit message (run a real course
 * completion against a tool_certificate template on local XAMPP).
 */
class observer_test extends \advanced_testcase {

    public function test_mark_reminders_suppressed_on_completion_stamps_sent_rows(): void {
        global $DB;
        $this->resetAfterTest(true);

        $u = $this->getDataGenerator()->create_user();
        $courseid = 42;
        $now = time();

        // Two reminder rows from prior daily cron runs.
        $DB->insert_record('local_sentientia_email_log', (object) [
            'rule_id'      => null,
            'userid'       => $u->id,
            'courseid'     => $courseid,
            'tenant_id'    => 1,
            'channel'      => 'email',
            'subject'      => 'Reminder: continue your course',
            'template_key' => 'notifications/course_not_started',
            'status'       => 'sent',
            'timecreated'  => $now - 3 * 86400,
        ]);
        $DB->insert_record('local_sentientia_email_log', (object) [
            'rule_id'      => null,
            'userid'       => $u->id,
            'courseid'     => $courseid,
            'tenant_id'    => 1,
            'channel'      => 'email',
            'subject'      => 'Reminder: continue your course',
            'template_key' => 'notifications/course_not_started',
            'status'       => 'sent',
            'timecreated'  => $now - 86400,
        ]);
        // A completion email row that must NOT be downgraded.
        $DB->insert_record('local_sentientia_email_log', (object) [
            'rule_id'      => null,
            'userid'       => $u->id,
            'courseid'     => $courseid,
            'tenant_id'    => 1,
            'channel'      => 'email',
            'subject'      => 'Congrats',
            'template_key' => 'enrollment/course_completed',
            'status'       => 'sent',
            'timecreated'  => $now,
        ]);

        $rows = delivery_log::mark_reminders_suppressed_on_completion(
            $u->id, $courseid);
        $this->assertEquals(1, $rows);   // execute() returned ok

        // Verify the two reminder rows were downgraded but the
        // completion email stayed `sent`.
        $reminders = $DB->get_records('local_sentientia_email_log',
            ['userid' => $u->id, 'template_key' => 'notifications/course_not_started']);
        $this->assertCount(2, $reminders);
        foreach ($reminders as $r) {
            $this->assertSame('suppressed_completion', $r->status);
        }

        $completion = $DB->get_record('local_sentientia_email_log',
            ['userid' => $u->id, 'template_key' => 'enrollment/course_completed']);
        $this->assertSame('sent', $completion->status);
    }

    public function test_mark_returns_zero_on_invalid_input(): void {
        // Guard branch: userid=0 or courseid=0 → no work.
        $this->resetAfterTest(true);
        $this->assertSame(0, delivery_log::mark_reminders_suppressed_on_completion(0, 1));
        $this->assertSame(0, delivery_log::mark_reminders_suppressed_on_completion(1, 0));
    }

    public function test_mark_skips_already_suppressed_rows(): void {
        global $DB;
        $this->resetAfterTest(true);
        $u = $this->getDataGenerator()->create_user();

        $DB->insert_record('local_sentientia_email_log', (object) [
            'userid'       => $u->id,
            'courseid'     => 7,
            'tenant_id'    => 1,
            'channel'      => 'email',
            'subject'      => 'r',
            'template_key' => 'notifications/foo',
            'status'       => 'suppressed',   // already suppressed for a different reason
            'timecreated'  => time(),
        ]);

        delivery_log::mark_reminders_suppressed_on_completion($u->id, 7);

        // Should NOT be touched — was already 'suppressed', not 'sent'.
        $r = $DB->get_record('local_sentientia_email_log', ['userid' => $u->id]);
        $this->assertSame('suppressed', $r->status);
    }
}
