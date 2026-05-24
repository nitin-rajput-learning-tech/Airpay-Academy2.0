<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

namespace local_sentientia_aiquiz;

defined('MOODLE_INTERNAL') || die();

/**
 * PHPUnit tests for prompt_builder — Phase G.0.
 *
 * These tests run in the unit-test DB sandbox; they do NOT call
 * Anthropic.
 *
 * @package    local_sentientia_aiquiz
 * @covers     \local_sentientia_aiquiz\prompt_builder
 */
final class prompt_builder_test extends \advanced_testcase {

    public function test_build_system_prompt_returns_non_empty_string(): void {
        $prompt = prompt_builder::build_system_prompt();
        $this->assertIsString($prompt);
        $this->assertGreaterThan(100, strlen($prompt));
        $this->assertStringContainsString('JSON', $prompt);
        $this->assertStringContainsString('multichoice', $prompt);
    }

    public function test_build_system_prompt_mentions_PII_rule(): void {
        $prompt = prompt_builder::build_system_prompt();
        $this->assertStringContainsString('personally identifiable information', $prompt);
    }

    public function test_build_user_message_embeds_source_and_count(): void {
        $msg = prompt_builder::build_user_message("Some training material.", 7);
        $this->assertStringContainsString('exactly 7', $msg);
        $this->assertStringContainsString('Some training material.', $msg);
        $this->assertStringContainsString('SOURCE BEGIN', $msg);
        $this->assertStringContainsString('SOURCE END', $msg);
    }

    public function test_build_user_message_clamps_count_to_max(): void {
        $msg = prompt_builder::build_user_message("x", prompt_builder::MAX_QUESTIONS + 50);
        $this->assertStringContainsString('exactly ' . prompt_builder::MAX_QUESTIONS, $msg);
    }

    public function test_build_user_message_clamps_count_to_min(): void {
        $msg = prompt_builder::build_user_message("x", 0);
        $this->assertStringContainsString('exactly ' . prompt_builder::MIN_QUESTIONS, $msg);
    }

    public function test_validate_source_empty_returns_err_source_empty(): void {
        $errors = prompt_builder::validate_source('   ');
        $this->assertContains('err_source_empty', $errors);
    }

    public function test_validate_source_returns_too_long_when_over_cap(): void {
        $text = str_repeat('word ', 4500);
        $errors = prompt_builder::validate_source($text, 4000);
        $this->assertContains('err_source_too_long', $errors);
    }

    public function test_validate_source_passes_within_cap(): void {
        $text = "This is a perfectly reasonable training paragraph about compliance.";
        $errors = prompt_builder::validate_source($text, 4000);
        $this->assertSame([], $errors);
    }

    public function test_validate_source_detects_aadhaar_pattern(): void {
        $text = "User 1234 5678 9012 was admitted.";
        $errors = prompt_builder::validate_source($text, 4000);
        $this->assertContains('err_source_contains_pii', $errors);
    }

    public function test_validate_source_detects_pan_pattern(): void {
        $text = "PAN of subject: ABCDE1234F should be redacted.";
        $errors = prompt_builder::validate_source($text, 4000);
        $this->assertContains('err_source_contains_pii', $errors);
    }

    public function test_word_count_counts_whitespace_separated_tokens(): void {
        $this->assertSame(0, prompt_builder::word_count(''));
        $this->assertSame(0, prompt_builder::word_count('   '));
        $this->assertSame(1, prompt_builder::word_count('hello'));
        $this->assertSame(3, prompt_builder::word_count('one two three'));
        $this->assertSame(3, prompt_builder::word_count("one\ttwo\nthree"));
    }

    public function test_contains_pii_pattern_false_on_clean_text(): void {
        $this->assertFalse(prompt_builder::contains_pii_pattern('clean training material'));
        // Short numbers don't trigger Aadhaar.
        $this->assertFalse(prompt_builder::contains_pii_pattern('Section 42 of policy 12'));
    }
}
