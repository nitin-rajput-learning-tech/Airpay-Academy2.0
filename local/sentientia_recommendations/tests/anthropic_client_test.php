<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

namespace local_sentientia_recommendations;

defined('MOODLE_INTERNAL') || die();

/**
 * PHPUnit tests for anthropic_client — Phase H.0.
 *
 * These tests exercise call_mock() exclusively. The live path
 * (call_live) is only validated for the no-API-key fast-fail branch —
 * actual HTTP calls are out of scope for unit tests.
 *
 * @package    local_sentientia_recommendations
 * @covers     \local_sentientia_recommendations\anthropic_client
 */
final class anthropic_client_test extends \advanced_testcase {

    private function sample_profile(array $completed = [10]): \stdClass {
        return (object)[
            'role'      => 'learner',
            'tenant'    => '1',
            'skills'    => ['AML'],
            'completed' => $completed,
        ];
    }

    private function sample_candidates(): array {
        return [
            (object)['id' => 12, 'fullname' => 'Advanced AML', 'shortname' => 'AML2', 'summary' => 'x'],
            (object)['id' => 13, 'fullname' => 'PEP Screening', 'shortname' => 'PEP', 'summary' => 'y'],
            (object)['id' => 14, 'fullname' => 'Fraud', 'shortname' => 'FR', 'summary' => 'z'],
        ];
    }

    public function test_call_mock_returns_requested_count(): void {
        $result = anthropic_client::call_mock($this->sample_profile(), $this->sample_candidates(), 2);
        $this->assertSame('mock', $result['mode']);
        $this->assertSame(0, $result['tokens_in']);
        $this->assertSame(0, $result['tokens_out']);
        $this->assertNull($result['error']);
        $decoded = json_decode($result['body'], true);
        $this->assertIsArray($decoded);
        $this->assertCount(2, $decoded['recommendations']);
    }

    public function test_call_mock_excludes_completed_courses(): void {
        // Mark course 12 as completed — it must not appear in the output.
        $profile = $this->sample_profile([12]);
        $result = anthropic_client::call_mock($profile, $this->sample_candidates(), 5);
        $decoded = json_decode($result['body'], true);
        foreach ($decoded['recommendations'] as $r) {
            $this->assertNotSame(12, (int)$r['course_id']);
        }
    }

    public function test_call_mock_clamps_count_to_available_candidates(): void {
        // Request 10 but only 2 non-completed candidates exist (12 done).
        $profile = $this->sample_profile([12]);
        $result = anthropic_client::call_mock($profile, $this->sample_candidates(), 10);
        $decoded = json_decode($result['body'], true);
        $this->assertLessThanOrEqual(2, count($decoded['recommendations']));
    }

    public function test_call_mock_questions_pass_parser(): void {
        $result = anthropic_client::call_mock($this->sample_profile(), $this->sample_candidates(), 2);
        $allowed = [12, 13, 14];
        $parsed = response_parser::parse($result['body'], $allowed);
        $this->assertCount(2, $parsed);
        foreach ($parsed as $r) {
            $this->assertGreaterThan(0, $r->course_id);
            $this->assertGreaterThanOrEqual(0, $r->score);
            $this->assertLessThanOrEqual(100, $r->score);
        }
    }

    public function test_call_mock_marks_reasoning_as_mock(): void {
        $result = anthropic_client::call_mock($this->sample_profile(), $this->sample_candidates(), 2);
        $decoded = json_decode($result['body'], true);
        foreach ($decoded['recommendations'] as $r) {
            $this->assertStringContainsString('[MOCK]', $r['reasoning']);
        }
    }

    public function test_call_mock_scores_descending(): void {
        $result = anthropic_client::call_mock($this->sample_profile(), $this->sample_candidates(), 3);
        $decoded = json_decode($result['body'], true);
        $prev = 101;
        foreach ($decoded['recommendations'] as $r) {
            $this->assertLessThanOrEqual($prev, (int)$r['score']);
            $prev = (int)$r['score'];
        }
    }

    public function test_call_live_returns_failed_when_no_api_key(): void {
        $this->resetAfterTest();
        set_config('api_key', '', 'local_sentientia_recommendations');

        $result = anthropic_client::call_live(
            $this->sample_profile(), $this->sample_candidates(), 3, anthropic_client::DEFAULT_MODEL);
        $this->assertSame('failed', $result['mode']);
        $this->assertSame('api_key_not_set', $result['error']);
        $this->assertSame('', $result['body']);
    }

    public function test_is_live_ready_false_without_api_key(): void {
        $this->resetAfterTest();
        set_config('api_key', '', 'local_sentientia_recommendations');
        $this->assertFalse(anthropic_client::is_live_ready());
    }
}
