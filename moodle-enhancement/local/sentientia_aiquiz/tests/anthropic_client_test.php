<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

namespace local_sentientia_aiquiz;

defined('MOODLE_INTERNAL') || die();

/**
 * PHPUnit tests for anthropic_client — Phase G.0.
 *
 * These tests exercise call_mock() exclusively. The live path
 * (call_live) is only validated for the no-API-key fast-fail
 * branch — actual HTTP calls are out of scope for unit tests.
 *
 * @package    local_sentientia_aiquiz
 * @covers     \local_sentientia_aiquiz\anthropic_client
 */
final class anthropic_client_test extends \advanced_testcase {

    public function test_call_mock_returns_requested_question_count(): void {
        $result = anthropic_client::call_mock("Sample training source.", 7);
        $this->assertSame('mock', $result['mode']);
        $this->assertSame(0, $result['tokens_in']);
        $this->assertSame(0, $result['tokens_out']);
        $this->assertNull($result['error']);
        $decoded = json_decode($result['body'], true);
        $this->assertIsArray($decoded);
        $this->assertCount(7, $decoded['questions']);
    }

    public function test_call_mock_clamps_count_to_max(): void {
        $result = anthropic_client::call_mock("src", 999);
        $decoded = json_decode($result['body'], true);
        $this->assertCount(prompt_builder::MAX_QUESTIONS, $decoded['questions']);
    }

    public function test_call_mock_clamps_count_to_min(): void {
        $result = anthropic_client::call_mock("src", 0);
        $decoded = json_decode($result['body'], true);
        $this->assertGreaterThanOrEqual(1, count($decoded['questions']));
    }

    public function test_call_mock_questions_pass_parser(): void {
        $result = anthropic_client::call_mock("Training material here.", 5);
        $parsed = response_parser::parse($result['body']);
        $this->assertCount(5, $parsed);
        foreach ($parsed as $q) {
            $this->assertSame('multichoice', $q->qtype);
            $this->assertCount(4, $q->qoptions);
        }
    }

    public function test_call_mock_embeds_source_snippet_in_stem(): void {
        $src = "Confidential compliance training about anti-money-laundering protocols.";
        $result = anthropic_client::call_mock($src, 1);
        $decoded = json_decode($result['body'], true);
        $stem = $decoded['questions'][0]['qtext'];
        // The first 80 chars of the source should be reflected in the mock stem.
        $this->assertStringContainsString('Confidential compliance', $stem);
    }

    public function test_call_mock_marks_questions_as_mock(): void {
        $result = anthropic_client::call_mock("Anything.", 3);
        $decoded = json_decode($result['body'], true);
        // Every mock question stem must contain '[MOCK' so a reviewer can
        // never accidentally push them through as real content.
        foreach ($decoded['questions'] as $q) {
            $this->assertStringContainsString('[MOCK', $q['qtext']);
        }
    }

    public function test_call_live_returns_failed_when_no_api_key(): void {
        $this->resetAfterTest();
        set_config('api_key', '', 'local_sentientia_aiquiz');

        $result = anthropic_client::call_live("src", 5, anthropic_client::DEFAULT_MODEL);
        $this->assertSame('failed', $result['mode']);
        $this->assertSame('api_key_not_set', $result['error']);
        $this->assertSame('', $result['body']);
    }

    public function test_is_live_ready_false_without_api_key(): void {
        $this->resetAfterTest();
        // Even if both flags were on, missing api_key must return false.
        set_config('api_key', '', 'local_sentientia_aiquiz');
        // (Cannot assert true-branch without local_airpay_core flag fixture
        // wired into the unit-test DB; tested by integration smoke instead.)
        $this->assertFalse(anthropic_client::is_live_ready());
    }
}
