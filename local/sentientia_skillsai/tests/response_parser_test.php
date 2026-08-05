<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

namespace local_sentientia_skillsai;

defined('MOODLE_INTERNAL') || die();

/**
 * PHPUnit tests for response_parser + prompt_builder + the mock client.
 *
 * Covers the mock-mode AI path and the security-relevant input validation
 * (PII heuristic, malformed-JSON robustness, level/confidence clamping).
 *
 * @package    local_sentientia_skillsai
 * @covers     \local_sentientia_skillsai\response_parser
 * @covers     \local_sentientia_skillsai\prompt_builder
 * @covers     \local_sentientia_skillsai\anthropic_client
 */
final class response_parser_test extends \advanced_testcase {

    public function test_mock_extraction_round_trips(): void {
        $mock = anthropic_client::call_mock('KYC verification SOP text', 15);
        $this->assertSame('mock', $mock['mode']);
        $this->assertSame(0, $mock['tokens_in']);
        $this->assertSame(0, $mock['tokens_out']);

        $skills = response_parser::parse($mock['body']);
        $this->assertCount(5, $skills);
        foreach ($skills as $s) {
            $this->assertNotSame('', $s->name);
            $this->assertContains($s->category, response_parser::CATEGORIES);
            $this->assertGreaterThanOrEqual(1, $s->level);
            $this->assertLessThanOrEqual(5, $s->level);
            $this->assertGreaterThanOrEqual(0.0, $s->confidence);
            $this->assertLessThanOrEqual(1.0, $s->confidence);
            // Mock evidence carries the [MOCK] marker — reviewers must see it.
            $this->assertStringContainsString('[MOCK]', $s->evidence);
        }
    }

    public function test_mock_respects_max_skills(): void {
        $mock = anthropic_client::call_mock('src', 2);
        $skills = response_parser::parse($mock['body']);
        $this->assertCount(2, $skills);
    }

    public function test_hindi_mock_produces_devanagari(): void {
        $mock = anthropic_client::call_mock('अनुपालन', 5,
            ['version' => prompt_builder::VERSION_V2_HINDI, 'template' => null]);
        $skills = response_parser::parse($mock['body']);
        $this->assertNotEmpty($skills);
        $this->assertMatchesRegularExpression('/\p{Devanagari}/u', $skills[0]->name);
    }

    public function test_parse_drops_malformed_items(): void {
        $this->assertCount(0, response_parser::parse(''));
        $this->assertCount(0, response_parser::parse('I cannot do that.'));
        $this->assertCount(0, response_parser::parse('{"foo":"bar"}'));
        $this->assertCount(0, response_parser::parse('{"skills":[{"description":"no name"}]}'));
    }

    public function test_parse_clamps_level_and_confidence(): void {
        $body = '{"skills":[{"name":"Overflow","level":99,"confidence":2.5,"category":"Nonsense"}]}';
        $out = response_parser::parse($body);
        $this->assertCount(1, $out);
        $this->assertSame(5, $out[0]->level);
        $this->assertSame(1.0, $out[0]->confidence);
        // Unknown category is coerced to a safe default.
        $this->assertSame('Process', $out[0]->category);
    }

    public function test_parse_strips_code_fence(): void {
        $body = "```json\n{\"skills\":[{\"name\":\"Fenced Skill\",\"level\":3,\"confidence\":0.8,\"category\":\"Technical\"}]}\n```";
        $out = response_parser::parse($body);
        $this->assertCount(1, $out);
        $this->assertSame('Fenced Skill', $out[0]->name);
    }

    public function test_parse_deduplicates_by_name(): void {
        $body = '{"skills":['
            . '{"name":"KYC","level":3,"confidence":0.8,"category":"Compliance"},'
            . '{"name":"kyc","level":4,"confidence":0.9,"category":"Compliance"}]}';
        $out = response_parser::parse($body);
        $this->assertCount(1, $out);
    }

    // ── Security: PII heuristic ─────────────────────────────────────────

    public function test_validate_source_flags_empty(): void {
        $errors = prompt_builder::validate_source('   ');
        $this->assertContains('err_source_empty', $errors);
    }

    public function test_validate_source_flags_aadhaar(): void {
        $errors = prompt_builder::validate_source('See Aadhaar 1234 5678 9012 in the record.');
        $this->assertContains('err_source_contains_pii', $errors);
    }

    public function test_validate_source_flags_pan(): void {
        $errors = prompt_builder::validate_source('PAN ABCDE1234F is on file.');
        $this->assertContains('err_source_contains_pii', $errors);
    }

    public function test_validate_source_flags_too_long(): void {
        $long = str_repeat('word ', 50);
        $errors = prompt_builder::validate_source($long, 10);
        $this->assertContains('err_source_too_long', $errors);
    }

    public function test_validate_source_clean_passes(): void {
        $errors = prompt_builder::validate_source('A clean SOP about merchant onboarding steps.');
        $this->assertSame([], $errors);
    }

    // ── prompt_builder version resolution ──────────────────────────────

    public function test_version_for_locale(): void {
        $this->assertSame(prompt_builder::VERSION_V2_HINDI, prompt_builder::version_for_locale('hi'));
        $this->assertSame(prompt_builder::VERSION_V2_HINDI, prompt_builder::version_for_locale('hi_IN'));
        $this->assertSame(prompt_builder::VERSION_V1, prompt_builder::version_for_locale('en'));
        $this->assertSame(prompt_builder::VERSION_V1, prompt_builder::version_for_locale('xx'));
    }

    public function test_resolve_prompt_version_marks_custom(): void {
        $this->assertSame('v1', prompt_builder::resolve_prompt_version('v1', false));
        $this->assertSame('custom:v2-hindi',
            prompt_builder::resolve_prompt_version(prompt_builder::VERSION_V2_HINDI, true));
    }

    public function test_custom_template_overrides_system_prompt(): void {
        $custom = 'CUSTOM EXTRACTION PROMPT BODY';
        $this->assertSame($custom,
            prompt_builder::build_system_prompt(prompt_builder::VERSION_V1, $custom));
    }
}
