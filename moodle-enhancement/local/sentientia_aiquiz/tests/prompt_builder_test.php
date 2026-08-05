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

    public function test_contains_pii_pattern_works_on_devanagari_surrounding_text(): void {
        // Aadhaar digits embedded in a Hindi sentence must still be caught.
        $text = "उपयोगकर्ता का आधार 1234 5678 9012 दर्ज है।";
        $this->assertTrue(prompt_builder::contains_pii_pattern($text));
        // Clean Hindi text must not false-positive.
        $this->assertFalse(prompt_builder::contains_pii_pattern('अनुपालन प्रशिक्षण सामग्री'));
    }

    public function test_word_count_counts_devanagari_words(): void {
        // Three Devanagari words separated by spaces.
        $this->assertSame(3, prompt_builder::word_count('अनुपालन प्रशिक्षण सामग्री'));
        // Mixed Latin + Devanagari — five whitespace-separated tokens:
        // SCORM / ट्रांसक्रिप्ट / से / quiz / बनाएँ. (The original expectation
        // of 4 was an authoring miscount, corrected 2026-08-04; the
        // implementation was always right.)
        $this->assertSame(5, prompt_builder::word_count('SCORM ट्रांसक्रिप्ट से quiz बनाएँ'));
    }

    // ════════════════════════════════════════════════════════════════
    //  G.1 — version resolution
    // ════════════════════════════════════════════════════════════════

    public function test_valid_versions_lists_v1_and_v2hindi(): void {
        $versions = prompt_builder::valid_versions();
        $this->assertContains(prompt_builder::VERSION_V1, $versions);
        $this->assertContains(prompt_builder::VERSION_V2_HINDI, $versions);
        $this->assertSame('v1', prompt_builder::VERSION_V1);
        $this->assertSame('v2-hindi', prompt_builder::VERSION_V2_HINDI);
    }

    public function test_version_for_locale_maps_hi_to_hindi(): void {
        $this->assertSame(prompt_builder::VERSION_V2_HINDI, prompt_builder::version_for_locale('hi'));
        $this->assertSame(prompt_builder::VERSION_V2_HINDI, prompt_builder::version_for_locale('hi_IN'));
        $this->assertSame(prompt_builder::VERSION_V2_HINDI, prompt_builder::version_for_locale('HI-in'));
    }

    public function test_version_for_locale_defaults_to_v1_for_english_and_unknown(): void {
        $this->assertSame(prompt_builder::VERSION_V1, prompt_builder::version_for_locale('en'));
        $this->assertSame(prompt_builder::VERSION_V1, prompt_builder::version_for_locale('en_GB'));
        // Unknown locale falls back to v1 (safe default).
        $this->assertSame(prompt_builder::VERSION_V1, prompt_builder::version_for_locale('fr'));
        $this->assertSame(prompt_builder::VERSION_V1, prompt_builder::version_for_locale(''));
    }

    public function test_resolve_prompt_version_prefixes_custom(): void {
        $this->assertSame('v1',
            prompt_builder::resolve_prompt_version(prompt_builder::VERSION_V1, false));
        $this->assertSame('v2-hindi',
            prompt_builder::resolve_prompt_version(prompt_builder::VERSION_V2_HINDI, false));
        $this->assertSame('custom:v1',
            prompt_builder::resolve_prompt_version(prompt_builder::VERSION_V1, true));
        $this->assertSame('custom:v2-hindi',
            prompt_builder::resolve_prompt_version(prompt_builder::VERSION_V2_HINDI, true));
    }

    public function test_resolve_prompt_version_clamps_unknown_to_v1(): void {
        $this->assertSame('v1', prompt_builder::resolve_prompt_version('garbage', false));
        $this->assertSame('custom:v1', prompt_builder::resolve_prompt_version('garbage', true));
    }

    // ════════════════════════════════════════════════════════════════
    //  G.1 — Hindi system prompt + user message
    // ════════════════════════════════════════════════════════════════

    public function test_build_system_prompt_v1_is_english(): void {
        $prompt = prompt_builder::build_system_prompt(prompt_builder::VERSION_V1);
        $this->assertStringContainsString('expert L&D quiz writer', $prompt);
        $this->assertStringContainsString('multichoice', $prompt);
        $this->assertStringContainsString('JSON', $prompt);
    }

    public function test_build_system_prompt_default_is_v1(): void {
        // No-arg call must keep Phase G.0 behaviour (English baseline).
        $this->assertSame(
            prompt_builder::build_system_prompt(prompt_builder::VERSION_V1),
            prompt_builder::build_system_prompt()
        );
    }

    public function test_build_system_prompt_v2hindi_uses_devanagari(): void {
        $prompt = prompt_builder::build_system_prompt(prompt_builder::VERSION_V2_HINDI);
        // Must contain Devanagari script.
        $this->assertMatchesRegularExpression('/\p{Devanagari}/u', $prompt);
        // Core Hindi instruction words.
        $this->assertStringContainsString('बहुविकल्पीय', $prompt);
        $this->assertStringContainsString('देवनागरी', $prompt);
        // Still references the JSON contract field names in Latin.
        $this->assertStringContainsString('JSON', $prompt);
        $this->assertStringContainsString('multichoice', $prompt);
        $this->assertStringContainsString('qoptions', $prompt);
        // PII rule must survive translation.
        $this->assertStringContainsString('Aadhaar', $prompt);
        $this->assertStringContainsString('PAN', $prompt);
    }

    public function test_build_system_prompt_v2hindi_embeds_fewshot_example(): void {
        $prompt = prompt_builder::build_system_prompt(prompt_builder::VERSION_V2_HINDI);
        // The few-shot example is a JSON object inside the prompt.
        $this->assertStringContainsString('"questions"', $prompt);
        $this->assertStringContainsString('"qanswer_index"', $prompt);
    }

    public function test_build_system_prompt_custom_template_overrides_baseline(): void {
        $custom = "CUSTOM AIRPAY PROMPT — आप एक विशेष क्विज़-लेखक हैं।";
        // Custom template wins regardless of version.
        $this->assertSame($custom,
            prompt_builder::build_system_prompt(prompt_builder::VERSION_V1, $custom));
        $this->assertSame($custom,
            prompt_builder::build_system_prompt(prompt_builder::VERSION_V2_HINDI, $custom));
    }

    public function test_build_system_prompt_blank_custom_template_falls_back(): void {
        // An empty / whitespace template must NOT replace the baseline.
        $baseline = prompt_builder::build_system_prompt(prompt_builder::VERSION_V2_HINDI);
        $this->assertSame($baseline,
            prompt_builder::build_system_prompt(prompt_builder::VERSION_V2_HINDI, ''));
        $this->assertSame($baseline,
            prompt_builder::build_system_prompt(prompt_builder::VERSION_V2_HINDI, '   '));
    }

    public function test_build_user_message_v2hindi_uses_hindi_wrapper(): void {
        $msg = prompt_builder::build_user_message('स्रोत सामग्री यहाँ है।', 5, prompt_builder::VERSION_V2_HINDI);
        $this->assertStringContainsString('ठीक 5', $msg);
        $this->assertStringContainsString('स्रोत प्रारम्भ', $msg);
        $this->assertStringContainsString('स्रोत समाप्त', $msg);
        // Source text passed through verbatim.
        $this->assertStringContainsString('स्रोत सामग्री यहाँ है।', $msg);
    }

    public function test_build_user_message_v1_still_english(): void {
        $msg = prompt_builder::build_user_message('Some material.', 5, prompt_builder::VERSION_V1);
        $this->assertStringContainsString('exactly 5', $msg);
        $this->assertStringContainsString('SOURCE BEGIN', $msg);
    }

    // ════════════════════════════════════════════════════════════════
    //  G.1 — resolve_for (customer + locale → version/template)
    // ════════════════════════════════════════════════════════════════

    public function test_resolve_for_english_no_override_returns_v1(): void {
        $this->resetAfterTest();
        // Ensure no custom template configured.
        unset_config('customer_1_aiquiz_prompt_template', 'local_sentientia_platform');

        $resolved = prompt_builder::resolve_for(1, 'en');
        $this->assertSame(prompt_builder::VERSION_V1, $resolved['version']);
        $this->assertNull($resolved['template']);
    }

    public function test_resolve_for_hindi_no_override_returns_v2hindi(): void {
        $this->resetAfterTest();
        unset_config('customer_1_aiquiz_prompt_template', 'local_sentientia_platform');

        $resolved = prompt_builder::resolve_for(1, 'hi');
        $this->assertSame(prompt_builder::VERSION_V2_HINDI, $resolved['version']);
        $this->assertNull($resolved['template']);
    }

    public function test_resolve_for_returns_customer_template_when_set(): void {
        $this->resetAfterTest();
        $custom = 'AIRPAY CUSTOM PROMPT BODY';
        set_config('customer_1_aiquiz_prompt_template', $custom, 'local_sentientia_platform');

        // Version still derives from locale; template is the override body.
        $resolved = prompt_builder::resolve_for(1, 'hi');
        $this->assertSame(prompt_builder::VERSION_V2_HINDI, $resolved['version']);
        $this->assertSame($custom, $resolved['template']);

        $resolveden = prompt_builder::resolve_for(1, 'en');
        $this->assertSame(prompt_builder::VERSION_V1, $resolveden['version']);
        $this->assertSame($custom, $resolveden['template']);
    }

    public function test_resolve_for_ignores_blank_customer_template(): void {
        $this->resetAfterTest();
        set_config('customer_1_aiquiz_prompt_template', '   ', 'local_sentientia_platform');

        $resolved = prompt_builder::resolve_for(1, 'en');
        $this->assertNull($resolved['template']);
    }

    public function test_resolve_for_other_customer_has_no_override(): void {
        $this->resetAfterTest();
        // Template set for customer 1 only.
        set_config('customer_1_aiquiz_prompt_template', 'AIRPAY ONLY', 'local_sentientia_platform');

        // A different customer id must NOT inherit customer 1's template.
        $resolved = prompt_builder::resolve_for(99, 'en');
        $this->assertNull($resolved['template']);
        $this->assertSame(prompt_builder::VERSION_V1, $resolved['version']);
    }
}
