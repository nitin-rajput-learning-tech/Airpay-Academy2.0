<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

namespace local_sentientia_translate;

defined('MOODLE_INTERNAL') || die();

/**
 * PHPUnit tests for prompt_builder — Phase T.0.
 *
 * These tests run in the unit-test DB sandbox; they do NOT call Anthropic.
 *
 * @package    local_sentientia_translate
 * @covers     \local_sentientia_translate\prompt_builder
 */
final class prompt_builder_test extends \advanced_testcase {

    public function test_build_system_prompt_returns_non_empty_string(): void {
        $prompt = prompt_builder::build_system_prompt('hi', []);
        $this->assertIsString($prompt);
        $this->assertGreaterThan(100, strlen($prompt));
        $this->assertStringContainsString('JSON', $prompt);
        $this->assertStringContainsString('translated_text', $prompt);
    }

    public function test_build_system_prompt_describes_target_script(): void {
        $hi = prompt_builder::build_system_prompt('hi', []);
        $this->assertStringContainsString('Devanagari', $hi);
        $kn = prompt_builder::build_system_prompt('kn', []);
        $this->assertStringContainsString('Kannada', $kn);
        $sw = prompt_builder::build_system_prompt('sw', []);
        $this->assertStringContainsString('Swahili', $sw);
    }

    public function test_build_system_prompt_lists_protected_terms(): void {
        $prompt = prompt_builder::build_system_prompt('hi', ['Airpay', 'UPI']);
        $this->assertStringContainsString('Airpay', $prompt);
        $this->assertStringContainsString('UPI', $prompt);
        $this->assertStringContainsString('PRESERVE', $prompt);
    }

    public function test_build_system_prompt_handles_no_protected_terms(): void {
        $prompt = prompt_builder::build_system_prompt('hi', []);
        $this->assertStringContainsString('(none specified)', $prompt);
    }

    public function test_build_user_message_embeds_source_and_lang(): void {
        $msg = prompt_builder::build_user_message('Welcome to compliance training.', 'kn');
        $this->assertStringContainsString("'kn'", $msg);
        $this->assertStringContainsString('Welcome to compliance training.', $msg);
        $this->assertStringContainsString('SOURCE BEGIN', $msg);
        $this->assertStringContainsString('SOURCE END', $msg);
    }

    public function test_validate_request_clean_passes(): void {
        $errors = prompt_builder::validate_request('Some training content.', 'hi', 4000);
        $this->assertSame([], $errors);
    }

    public function test_validate_request_empty_source(): void {
        $errors = prompt_builder::validate_request('   ', 'hi', 4000);
        $this->assertContains('err_source_empty', $errors);
    }

    public function test_validate_request_unsupported_lang(): void {
        $errors = prompt_builder::validate_request('content', 'fr', 4000);
        $this->assertContains('err_unsupported_lang', $errors);
    }

    public function test_validate_request_too_long(): void {
        $text = str_repeat('word ', 4500);
        $errors = prompt_builder::validate_request($text, 'hi', 4000);
        $this->assertContains('err_source_too_long', $errors);
    }

    public function test_validate_request_detects_aadhaar(): void {
        $errors = prompt_builder::validate_request('User 1234 5678 9012 enrolled.', 'hi', 4000);
        $this->assertContains('err_source_contains_pii', $errors);
    }

    public function test_validate_request_detects_pan(): void {
        $errors = prompt_builder::validate_request('PAN ABCDE1234F here.', 'hi', 4000);
        $this->assertContains('err_source_contains_pii', $errors);
    }

    public function test_word_count(): void {
        $this->assertSame(0, prompt_builder::word_count(''));
        $this->assertSame(0, prompt_builder::word_count('   '));
        $this->assertSame(1, prompt_builder::word_count('hello'));
        $this->assertSame(3, prompt_builder::word_count('one two three'));
    }

    public function test_contains_pii_pattern_false_on_clean(): void {
        $this->assertFalse(prompt_builder::contains_pii_pattern('clean content'));
        $this->assertFalse(prompt_builder::contains_pii_pattern('Section 42 policy 12'));
    }
}
