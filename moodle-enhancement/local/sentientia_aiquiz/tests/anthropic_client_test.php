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

    // ════════════════════════════════════════════════════════════════
    //  G.1 — Hindi mock mode + prompt-context plumbing
    // ════════════════════════════════════════════════════════════════

    public function test_call_mock_default_is_english(): void {
        // No promptctx → English mock (Phase G.0 behaviour preserved).
        $result = anthropic_client::call_mock('Compliance source.', 3);
        $decoded = json_decode($result['body'], true);
        foreach ($decoded['questions'] as $q) {
            $this->assertStringContainsString('[MOCK Q', $q['qtext']);
            // English mock stems contain Latin "Which statement".
            $this->assertStringContainsString('Which statement', $q['qtext']);
        }
    }

    public function test_call_mock_hindi_version_produces_devanagari(): void {
        $promptctx = ['version' => prompt_builder::VERSION_V2_HINDI, 'template' => null];
        $result = anthropic_client::call_mock('अनुपालन स्रोत सामग्री।', 3, $promptctx);
        $this->assertSame('mock', $result['mode']);
        $decoded = json_decode($result['body'], true);
        $this->assertCount(3, $decoded['questions']);
        foreach ($decoded['questions'] as $q) {
            // Devanagari present in the stem.
            $this->assertMatchesRegularExpression('/\p{Devanagari}/u', $q['qtext']);
            // MOCK marker stays in Latin so reviewers always spot it.
            $this->assertStringContainsString('[MOCK', $q['qtext']);
            // Options are Devanagari.
            foreach ($q['qoptions'] as $opt) {
                $this->assertMatchesRegularExpression('/\p{Devanagari}/u', $opt);
            }
        }
    }

    public function test_call_mock_hindi_questions_pass_parser(): void {
        // The Hindi mock must round-trip cleanly through the parser
        // (proving the end-to-end mock-mode Hindi demo works).
        $promptctx = ['version' => prompt_builder::VERSION_V2_HINDI, 'template' => null];
        $result = anthropic_client::call_mock('स्रोत।', 4, $promptctx);
        $parsed = response_parser::parse($result['body']);
        $this->assertCount(4, $parsed);
        foreach ($parsed as $q) {
            $this->assertSame('multichoice', $q->qtype);
            $this->assertCount(4, $q->qoptions);
            $this->assertMatchesRegularExpression('/\p{Devanagari}/u', $q->qtext);
        }
    }

    public function test_call_mock_hindi_embeds_source_snippet(): void {
        $src = 'धन-शोधन निवारण प्रोटोकॉल पर गोपनीय अनुपालन प्रशिक्षण।';
        $promptctx = ['version' => prompt_builder::VERSION_V2_HINDI, 'template' => null];
        $result = anthropic_client::call_mock($src, 1, $promptctx);
        $decoded = json_decode($result['body'], true);
        // The Devanagari snippet must survive into the stem (proves
        // mb_substr handled the multibyte slice without corruption).
        $this->assertStringContainsString('धन-शोधन', $decoded['questions'][0]['qtext']);
    }

    public function test_call_mock_unknown_version_falls_back_to_english(): void {
        $promptctx = ['version' => 'bogus-version', 'template' => null];
        $result = anthropic_client::call_mock('source', 2, $promptctx);
        $decoded = json_decode($result['body'], true);
        // Unknown version normalises to v1 → English mock.
        $this->assertStringContainsString('Which statement', $decoded['questions'][0]['qtext']);
    }

    public function test_call_live_returns_failed_when_no_api_key_with_promptctx(): void {
        // The new promptctx parameter must not change the no-key fast-fail.
        $this->resetAfterTest();
        set_config('api_key', '', 'local_sentientia_aiquiz');

        $promptctx = ['version' => prompt_builder::VERSION_V2_HINDI, 'template' => 'X'];
        $result = anthropic_client::call_live('src', 5, anthropic_client::DEFAULT_MODEL, $promptctx);
        $this->assertSame('failed', $result['mode']);
        $this->assertSame('api_key_not_set', $result['error']);
    }
}
