<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

namespace local_sentientia_assistant;

use local_sentientia_assistant\agent\agent_client;

defined('MOODLE_INTERNAL') || die();

/**
 * PHPUnit tests for agent_client — exercises call_mock() (the default,
 * zero-cost path) and the live fast-fail branch only. No real HTTP.
 *
 * @package    local_sentientia_assistant
 * @covers     \local_sentientia_assistant\agent\agent_client
 */
final class agent_client_test extends \advanced_testcase {

    /** Minimal schema set granting all three tools. */
    private function all_schemas(): array {
        return [
            ['name' => 'enrol_course', 'description' => 'x', 'args' => []],
            ['name' => 'book_ilt_session', 'description' => 'x', 'args' => []],
            ['name' => 'recommend_content', 'description' => 'x', 'args' => []],
        ];
    }

    public function test_mock_proposes_enrol_when_context_has_courseid(): void {
        $context = "Available courses:\n- [id=42] Intro to Compliance";
        $r = agent_client::call_mock('please enrol me', $context, $this->all_schemas());
        $this->assertSame('mock', $r['mode']);
        $decoded = json_decode($r['raw'], true);
        $this->assertSame('enrol_course', $decoded['tool']);
        $this->assertSame(42, $decoded['args']['courseid']);
    }

    public function test_mock_proposes_recommend(): void {
        $r = agent_client::call_mock('what should I learn next', '', $this->all_schemas());
        $decoded = json_decode($r['raw'], true);
        $this->assertSame('recommend_content', $decoded['tool']);
    }

    public function test_mock_chat_only_when_no_intent(): void {
        $r = agent_client::call_mock('hello', '', $this->all_schemas());
        $decoded = json_decode($r['raw'], true);
        $this->assertNull($decoded['tool']);
        $this->assertNotEmpty($decoded['message']);
    }

    public function test_mock_respects_allowed_schemas(): void {
        // If enrol_course isn't in the allowed schemas, the mock must NOT
        // propose it even when the message asks to enrol.
        $schemas = [['name' => 'recommend_content', 'description' => 'x', 'args' => []]];
        $context = "Available courses:\n- [id=7] X";
        $r = agent_client::call_mock('enrol me now', $context, $schemas);
        $decoded = json_decode($r['raw'], true);
        $this->assertNotSame('enrol_course', $decoded['tool']);
    }

    public function test_live_fast_fails_without_api_key(): void {
        $this->resetAfterTest();
        set_config('api_key', '', 'local_sentientia_assistant');
        $r = agent_client::call_live('enrol me', '', $this->all_schemas());
        $this->assertSame('failed', $r['mode']);
        // Since 676df829f the no-spend guard answers first under PHPUnit, before
        // the api_key check, so a test run can never reach 'api_key_not_set' --
        // and must not: that path is one step from a paid vendor call. What a
        // test CAN assert is that the live path fails fast without a request.
        $this->assertSame('live_blocked_in_tests', $r['error']);
    }

    public function test_system_prompt_pins_proposal_contract(): void {
        $prompt = agent_client::build_system_prompt($this->all_schemas());
        // Hardening assertions: untrusted-data framing + proposal-only rule.
        $this->assertStringContainsString('do NOT execute', $prompt);
        $this->assertStringContainsString('untrusted', $prompt);
        $this->assertStringContainsString('enrol_course', $prompt);
    }
}
