<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

namespace local_sentientia_notifications;

defined('MOODLE_INTERNAL') || die();

/**
 * Phase-C rule_engine tests — verifies the 8 new rule handlers added
 * in the 2026-05-08 stretch dispatch correctly + degrade gracefully
 * when their underlying tables aren't present.
 *
 * @package    local_sentientia_notifications
 * @category   test
 */
final class rule_engine_phase_c_test extends \advanced_testcase {

    private function seed_rule(string $rule_type, int $trigger_days = 7): \stdClass {
        global $DB;
        $id = $DB->insert_record('local_sentientia_notif_rules', (object) [
            'name'         => 'Test ' . $rule_type,
            'rule_type'    => $rule_type,
            'channel'      => 'inapp',
            'trigger_days' => $trigger_days,
            'audience'     => 'learner',
            'enabled'      => 1,
            'template'     => '',
            'timecreated'  => time(),
            'timemodified' => time(),
        ]);
        return $DB->get_record('local_sentientia_notif_rules', ['id' => $id], '*', MUST_EXIST);
    }

    public function test_unknown_rule_type_returns_zero_counts(): void {
        $this->resetAfterTest();
        $rule = $this->seed_rule('not_a_real_type');
        $result = rule_engine::process_rule($rule);
        $this->assertSame(0, $result['sent']);
        $this->assertSame(0, $result['skipped']);
    }

    public function test_compliance_overdue_handles_zero_candidates(): void {
        $this->resetAfterTest();
        $rule = $this->seed_rule('compliance_overdue');
        $result = rule_engine::process_rule($rule);
        // No enrolments overdue — counts are zero, no exception.
        $this->assertIsArray($result);
        $this->assertArrayHasKey('sent', $result);
        $this->assertArrayHasKey('skipped', $result);
    }

    public function test_certificate_expiring_skips_when_table_missing(): void {
        $this->resetAfterTest();
        $rule = $this->seed_rule('certificate_expiring', 30);
        // tool_certificate_issues likely doesn't exist on stock test DB →
        // method returns empty stats rather than throwing.
        $result = rule_engine::process_rule($rule);
        $this->assertSame(0, $result['sent']);
        $this->assertSame(0, $result['skipped']);
    }

    public function test_ilt_feedback_skips_when_classroom_table_missing(): void {
        $this->resetAfterTest();
        $rule = $this->seed_rule('ilt_feedback_pending', 3);
        // local_sentientia_classroom_sessions may not exist; defensive return.
        $result = rule_engine::process_rule($rule);
        $this->assertSame(0, $result['sent']);
        $this->assertSame(0, $result['skipped']);
    }

    public function test_learning_path_stalled_skips_when_table_missing(): void {
        $this->resetAfterTest();
        $rule = $this->seed_rule('learning_path_stalled', 14);
        $result = rule_engine::process_rule($rule);
        $this->assertSame(0, $result['sent']);
        $this->assertSame(0, $result['skipped']);
    }

    public function test_inactive_user_finds_old_lastaccess(): void {
        global $DB;
        $this->resetAfterTest();

        // Create a user whose lastaccess is 100 days ago.
        $u = $this->getDataGenerator()->create_user();
        $DB->set_field('user', 'lastaccess', time() - 100 * 86400, ['id' => $u->id]);

        $rule = $this->seed_rule('inactive_user', 30);  // trigger > 30 days inactive
        $result = rule_engine::process_rule($rule);

        // The user matches, so we should have at least 1 attempted send.
        // Note: may end up 'skipped' due to dedup window, but the SQL
        // candidate query should at least surface them.
        $this->assertGreaterThanOrEqual(1, $result['sent'] + $result['skipped'],
            'inactive user with lastaccess > trigger_days should be a candidate');
    }

    public function test_enrolment_anniversary_skips_recent_enrolments(): void {
        $this->resetAfterTest();
        $this->setAdminUser();

        $u = $this->getDataGenerator()->create_user();
        $course = $this->getDataGenerator()->create_course();
        $this->getDataGenerator()->enrol_user($u->id, $course->id);
        // user_enrolments.timestart will be ~now → not 1-year-old.

        $rule = $this->seed_rule('enrolment_anniversary');
        $result = rule_engine::process_rule($rule);
        $this->assertSame(0, $result['sent'],
            'recent enrolment must NOT trigger 1-year anniversary rule');
    }

    public function test_quiz_low_score_skips_when_table_missing_or_no_attempts(): void {
        $this->resetAfterTest();
        $rule = $this->seed_rule('quiz_low_score', 70);  // threshold = 70%
        // No quiz attempts seeded → zero candidates.
        $result = rule_engine::process_rule($rule);
        $this->assertSame(0, $result['sent']);
        $this->assertSame(0, $result['skipped']);
    }

    public function test_monthly_summary_skips_when_open_supervisorid_missing(): void {
        global $DB;
        $this->resetAfterTest();
        $rule = $this->seed_rule('monthly_summary');
        // open_supervisorid may not exist; method returns empty stats.
        $result = rule_engine::process_rule($rule);
        // Whether the field exists on this test DB is environment-dependent;
        // the invariant is no exception thrown.
        $this->assertIsArray($result);
    }

    public function test_dispatch_routes_each_phase_c_type(): void {
        $this->resetAfterTest();
        $types = ['compliance_overdue', 'certificate_expiring', 'ilt_feedback_pending',
                  'learning_path_stalled', 'enrolment_anniversary', 'inactive_user',
                  'quiz_low_score', 'monthly_summary'];
        foreach ($types as $type) {
            $rule = $this->seed_rule($type);
            $result = rule_engine::process_rule($rule);
            $this->assertIsArray($result, "$type must return a stats array");
            $this->assertArrayHasKey('sent', $result);
            $this->assertArrayHasKey('skipped', $result);
        }
    }
}
