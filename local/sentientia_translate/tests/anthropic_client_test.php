<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

namespace local_sentientia_translate;

defined('MOODLE_INTERNAL') || die();

/**
 * PHPUnit tests for anthropic_client — Phase T.0.
 *
 * Exercises call_mock() exclusively. The live path (call_live) is only
 * validated for the no-API-key fast-fail branch — actual HTTP calls are
 * out of scope for unit tests.
 *
 * @package    local_sentientia_translate
 * @covers     \local_sentientia_translate\anthropic_client
 */
final class anthropic_client_test extends \advanced_testcase {

    public function test_call_mock_returns_shaped_body(): void {
        $result = anthropic_client::call_mock('Hello world', 'hi');
        $this->assertSame('mock', $result['mode']);
        $this->assertSame(0, $result['tokens_in']);
        $this->assertSame(0, $result['tokens_out']);
        $this->assertNull($result['error']);

        $decoded = json_decode($result['body'], true);
        $this->assertIsArray($decoded);
        $this->assertArrayHasKey('translated_text', $decoded);
        $this->assertArrayHasKey('target_lang', $decoded);
        $this->assertSame('hi', $decoded['target_lang']);
    }

    public function test_call_mock_marks_output_as_mock(): void {
        $result = anthropic_client::call_mock('Some content', 'kn');
        $decoded = json_decode($result['body'], true);
        $this->assertStringContainsString('[MOCK kn]', $decoded['translated_text']);
    }

    public function test_call_mock_echoes_source(): void {
        // The mock echoes source so brand post-processing has tokens to act on.
        $result = anthropic_client::call_mock('Welcome to Airpay', 'kn');
        $decoded = json_decode($result['body'], true);
        $this->assertStringContainsString('Airpay', $decoded['translated_text']);
    }

    public function test_call_mock_output_parses(): void {
        $result = anthropic_client::call_mock('Translate me', 'mr');
        $parsed = response_parser::parse($result['body']);
        $this->assertNotNull($parsed);
        $this->assertSame('mr', $parsed->target_lang);
    }

    public function test_call_live_returns_failed_when_no_api_key(): void {
        $this->resetAfterTest();
        set_config('api_key', '', 'local_sentientia_translate');

        $result = anthropic_client::call_live('Hello', 'hi', ['Airpay'], anthropic_client::DEFAULT_MODEL);
        $this->assertSame('failed', $result['mode']);
        $this->assertSame('api_key_not_set', $result['error']);
        $this->assertSame('', $result['body']);
    }

    public function test_is_live_ready_false_without_api_key(): void {
        $this->resetAfterTest();
        set_config('api_key', '', 'local_sentientia_translate');
        $this->assertFalse(anthropic_client::is_live_ready());
    }
}
