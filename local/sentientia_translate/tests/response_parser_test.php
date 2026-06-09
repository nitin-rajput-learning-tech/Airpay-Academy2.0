<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

namespace local_sentientia_translate;

defined('MOODLE_INTERNAL') || die();

/**
 * PHPUnit tests for response_parser — Phase T.0.
 *
 * Uses synthetic Claude-style responses (no API calls).
 *
 * @package    local_sentientia_translate
 * @covers     \local_sentientia_translate\response_parser
 */
final class response_parser_test extends \advanced_testcase {

    private function payload(string $translated, string $lang = 'hi', array $brands = [], bool $fences = false): string {
        $json = json_encode([
            'translated_text'       => $translated,
            'target_lang'           => $lang,
            'brand_terms_preserved' => $brands,
        ], JSON_UNESCAPED_UNICODE);
        return $fences ? "```json\n{$json}\n```" : $json;
    }

    public function test_parse_returns_normalised_object(): void {
        $parsed = response_parser::parse($this->payload('नमस्ते दुनिया', 'hi', ['Airpay']));
        $this->assertNotNull($parsed);
        $this->assertSame('नमस्ते दुनिया', $parsed->translated_text);
        $this->assertSame('hi', $parsed->target_lang);
        $this->assertSame(['Airpay'], $parsed->brand_terms_preserved);
    }

    public function test_parse_strips_markdown_fences(): void {
        $parsed = response_parser::parse($this->payload('ಕನ್ನಡ ಪಠ್ಯ', 'kn', [], true));
        $this->assertNotNull($parsed);
        $this->assertSame('ಕನ್ನಡ ಪಠ್ಯ', $parsed->translated_text);
    }

    public function test_parse_returns_null_on_blank(): void {
        $this->assertNull(response_parser::parse(''));
        $this->assertNull(response_parser::parse('   '));
    }

    public function test_parse_returns_null_on_non_json(): void {
        $this->assertNull(response_parser::parse('Sorry, I cannot translate that.'));
    }

    public function test_parse_returns_null_when_translated_text_missing(): void {
        $body = json_encode(['target_lang' => 'hi']);
        $this->assertNull(response_parser::parse($body));
    }

    public function test_parse_returns_null_when_translated_text_empty(): void {
        $this->assertNull(response_parser::parse($this->payload('   ', 'hi')));
    }

    public function test_parse_extracts_object_from_wrapper_text(): void {
        $wrapped = "Here is your translation:\n\n" . $this->payload('text', 'hi') . "\n\nDone.";
        $parsed = response_parser::parse($wrapped);
        $this->assertNotNull($parsed);
        $this->assertSame('text', $parsed->translated_text);
    }

    public function test_parse_tolerates_missing_brand_array(): void {
        $body = json_encode(['translated_text' => 'x', 'target_lang' => 'hi']);
        $parsed = response_parser::parse($body);
        $this->assertNotNull($parsed);
        $this->assertSame([], $parsed->brand_terms_preserved);
    }

    public function test_parse_filters_non_string_brand_entries(): void {
        $body = json_encode([
            'translated_text'       => 'x',
            'target_lang'           => 'hi',
            'brand_terms_preserved' => ['Airpay', 123, '', 'UPI'],
        ]);
        $parsed = response_parser::parse($body);
        $this->assertSame(['Airpay', 'UPI'], $parsed->brand_terms_preserved);
    }

    public function test_extract_json_returns_empty_when_no_json(): void {
        $this->assertSame('', response_parser::extract_json('plain prose only'));
    }
}
