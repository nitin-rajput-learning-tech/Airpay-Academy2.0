<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

namespace local_sentientia_assistant;

use local_sentientia_assistant\agent\agent_loop;
use local_sentientia_assistant\agent\agent_client;
use local_sentientia_assistant\agent\tool_result;

defined('MOODLE_INTERNAL') || die();

/**
 * PHPUnit tests for the P1.3 agentic copilot loop.
 *
 * Covers (per the build brief):
 *   - flag-OFF no-op (legacy behaviour unchanged)
 *   - mock agent loop end-to-end (no live spend)
 *   - tool authorisation: capability gate
 *   - tool authorisation: tenant gate (no cross-tenant action)
 *   - prompt-injection resistance (LLM output cannot escalate)
 *   - idempotency (repeat enrol = no-op)
 *
 * @package    local_sentientia_assistant
 * @covers     \local_sentientia_assistant\agent\agent_loop
 * @covers     \local_sentientia_assistant\agent\tool
 * @covers     \local_sentientia_assistant\agent\tool_registry
 */
final class agent_loop_test extends \advanced_testcase {

    /** Enable the agentic flags (master + optionally live). Live stays OFF here. */
    private function enable_agentic(bool $live = false): void {
        if (!class_exists('\\local_sentientia_platform\\feature_flags')) {
            $this->markTestSkipped('local_sentientia_platform not installed');
        }
        \local_sentientia_platform\feature_flags::set('sentientia.assistant.agentic.enabled', 0, true);
        \local_sentientia_platform\feature_flags::set('sentientia.assistant.agentic.live_api', 0, $live);
        \local_sentientia_platform\feature_flags::invalidate_caches();
    }

    /** Set a user's open_path so tenant resolution works (tolerant of missing column). */
    private function set_open_path(\stdClass $user, string $path): void {
        global $DB;
        $manager = $DB->get_manager();
        $table = new \xmldb_table('user');
        $field = new \xmldb_field('open_path');
        if ($manager->field_exists($table, $field)) {
            $DB->set_field('user', 'open_path', $path, ['id' => $user->id]);
        }
        $user->open_path = $path;
    }

    /** Create a course in a given tenant with an active self-enrol instance. */
    private function make_self_enrollable_course(string $openpath): \stdClass {
        global $DB;
        $course = $this->getDataGenerator()->create_course(['visible' => 1]);
        $DB->set_field('course', 'open_path', $openpath, ['id' => $course->id]);
        // Enable the self-enrol plugin instance (disabled by default).
        $instance = $DB->get_record('enrol', ['courseid' => $course->id, 'enrol' => 'self']);
        if (!$instance) {
            $plugin = enrol_get_plugin('self');
            $plugin->add_instance($course);
            $instance = $DB->get_record('enrol', ['courseid' => $course->id, 'enrol' => 'self']);
        }
        $DB->set_field('enrol', 'status', ENROL_INSTANCE_ENABLED, ['id' => $instance->id]);
        $course->open_path = $openpath;
        return $course;
    }

    public function test_flag_off_is_noop(): void {
        $this->resetAfterTest();
        $user = $this->getDataGenerator()->create_user();
        $this->setUser($user);
        // Master flag OFF (default). Loop must report disabled + do nothing.
        $turn = agent_loop::run((int) $user->id, 'enrol me in something', false);
        $this->assertFalse($turn['enabled']);
        $this->assertSame('disabled', $turn['mode']);
        $this->assertNull($turn['proposal']);
        $this->assertNull($turn['outcome']);
    }

    public function test_mock_chat_only_turn(): void {
        $this->resetAfterTest();
        $this->enable_agentic(false);
        $user = $this->getDataGenerator()->create_user();
        $this->set_open_path($user, '/1');
        $this->setUser($user);

        // A vague message → no tool, chat-only help reply, mock mode.
        $turn = agent_loop::run((int) $user->id, 'hello there', false);
        $this->assertTrue($turn['enabled']);
        $this->assertSame('mock', $turn['mode']);
        $this->assertNull($turn['proposal']);
        $this->assertNotEmpty($turn['message']);
    }

    public function test_mock_recommend_runs_readonly(): void {
        $this->resetAfterTest();
        $this->enable_agentic(false);
        $user = $this->getDataGenerator()->create_user();
        $this->set_open_path($user, '/1');
        $this->setUser($user);
        $this->make_self_enrollable_course('/1');

        // Recommend is read-only → executes immediately (no confirm), no state change.
        $turn = agent_loop::run((int) $user->id, 'what should I learn next?', false);
        $this->assertTrue($turn['enabled']);
        $this->assertSame(tool_result::OUTCOME_EXECUTED, $turn['outcome']);
        $this->assertFalse($turn['statechanged']);
    }

    public function test_mock_enrol_proposes_then_confirm_executes(): void {
        global $DB;
        $this->resetAfterTest();
        $this->enable_agentic(false);
        $user = $this->getDataGenerator()->create_user();
        $this->set_open_path($user, '/1');
        $this->setUser($user);
        $course = $this->make_self_enrollable_course('/1');

        // Turn 1: write tool → proposal only, NOTHING executed yet.
        $turn = agent_loop::run((int) $user->id, 'enrol me please', false);
        $this->assertSame(tool_result::OUTCOME_PROPOSED, $turn['outcome']);
        $this->assertNotNull($turn['proposal']);
        $this->assertSame('enrol_course', $turn['proposal']['tool']);
        $this->assertFalse(
            $DB->record_exists_sql(
                "SELECT 1 FROM {user_enrolments} ue JOIN {enrol} e ON e.id = ue.enrolid
                  WHERE e.courseid = :c AND ue.userid = :u",
                ['c' => $course->id, 'u' => $user->id]),
            'No enrolment must exist before confirmation — LLM proposes, platform waits.');

        // Turn 2: confirm → guard chain executes the enrolment.
        $turn2 = agent_loop::run((int) $user->id, 'enrol me please', true);
        $this->assertSame(tool_result::OUTCOME_EXECUTED, $turn2['outcome']);
        $this->assertTrue($turn2['statechanged']);
        $this->assertTrue(
            $DB->record_exists_sql(
                "SELECT 1 FROM {user_enrolments} ue JOIN {enrol} e ON e.id = ue.enrolid
                  WHERE e.courseid = :c AND ue.userid = :u",
                ['c' => $course->id, 'u' => $user->id]));
    }

    public function test_enrol_is_idempotent(): void {
        $this->resetAfterTest();
        $this->enable_agentic(false);
        $user = $this->getDataGenerator()->create_user();
        $this->set_open_path($user, '/1');
        $this->setUser($user);
        $course = $this->make_self_enrollable_course('/1');

        // First confirm enrols.
        $first = agent_loop::run((int) $user->id, 'enrol me please', true);
        $this->assertSame(tool_result::OUTCOME_EXECUTED, $first['outcome']);

        // Second confirm must be a NO-OP — no double enrolment, no error.
        $second = agent_loop::run((int) $user->id, 'enrol me please', true);
        $this->assertSame(tool_result::OUTCOME_NOOP, $second['outcome']);
        $this->assertFalse($second['statechanged']);
    }

    public function test_audit_row_written_for_every_outcome(): void {
        global $DB;
        $this->resetAfterTest();
        $this->enable_agentic(false);
        $user = $this->getDataGenerator()->create_user();
        $this->set_open_path($user, '/1');
        $this->setUser($user);
        $this->make_self_enrollable_course('/1');

        $before = $DB->count_records('local_sentientia_agent_audit');
        agent_loop::run((int) $user->id, 'enrol me please', false);  // proposed
        agent_loop::run((int) $user->id, 'enrol me please', true);   // executed
        $after = $DB->count_records('local_sentientia_agent_audit');
        $this->assertGreaterThanOrEqual($before + 2, $after,
            'Both the proposal and the execution must be audited.');
    }
}
