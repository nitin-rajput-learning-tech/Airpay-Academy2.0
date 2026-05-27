<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

namespace local_sentientia_recommendations;

defined('MOODLE_INTERNAL') || die();

/**
 * PHPUnit tests for response_parser — Phase H.0.
 *
 * Uses synthetic Claude-style responses (no API calls).
 *
 * @package    local_sentientia_recommendations
 * @covers     \local_sentientia_recommendations\response_parser
 */
final class response_parser_test extends \advanced_testcase {

    private function build_payload(array $items, bool $wrapfences = false): string {
        $json = json_encode(['recommendations' => $items]);
        if ($wrapfences) {
            return "```json\n" . $json . "\n```";
        }
        return $json;
    }

    public function test_parse_returns_normalised_recommendations(): void {
        $body = $this->build_payload([
            ['course_id' => 12, 'score' => 87, 'reasoning' => 'Builds on AML basics.'],
            ['course_id' => 13, 'score' => 70, 'reasoning' => 'Natural follow-up.'],
        ]);
        $parsed = response_parser::parse($body, [12, 13]);
        $this->assertCount(2, $parsed);
        $this->assertSame(12, $parsed[0]->course_id);
        $this->assertSame(87, $parsed[0]->score);
        $this->assertSame('Builds on AML basics.', $parsed[0]->reasoning);
    }

    public function test_parse_strips_markdown_code_fences(): void {
        $body = $this->build_payload([
            ['course_id' => 12, 'score' => 50, 'reasoning' => 'x'],
        ], true);
        $parsed = response_parser::parse($body, [12]);
        $this->assertCount(1, $parsed);
        $this->assertSame(12, $parsed[0]->course_id);
    }

    public function test_parse_returns_empty_on_blank_input(): void {
        $this->assertSame([], response_parser::parse(''));
        $this->assertSame([], response_parser::parse('   '));
    }

    public function test_parse_returns_empty_on_non_json_text(): void {
        $this->assertSame([], response_parser::parse('Sorry, I cannot help.'));
    }

    public function test_parse_returns_empty_when_recommendations_key_missing(): void {
        $body = json_encode(['something_else' => []]);
        $this->assertSame([], response_parser::parse($body));
    }

    public function test_parse_drops_course_id_not_in_allowed_set(): void {
        $body = $this->build_payload([
            ['course_id' => 9999, 'score' => 99, 'reasoning' => 'invented'],
            ['course_id' => 12, 'score' => 80, 'reasoning' => 'valid'],
        ]);
        $parsed = response_parser::parse($body, [12, 13]);
        $this->assertCount(1, $parsed);
        $this->assertSame(12, $parsed[0]->course_id);
    }

    public function test_parse_allows_all_when_allowed_set_empty(): void {
        $body = $this->build_payload([
            ['course_id' => 9999, 'score' => 99, 'reasoning' => 'x'],
        ]);
        $parsed = response_parser::parse($body, []);
        $this->assertCount(1, $parsed);
        $this->assertSame(9999, $parsed[0]->course_id);
    }

    public function test_parse_clamps_score_to_range(): void {
        $body = $this->build_payload([
            ['course_id' => 12, 'score' => 250, 'reasoning' => 'over'],
            ['course_id' => 13, 'score' => -5, 'reasoning' => 'under'],
        ]);
        $parsed = response_parser::parse($body, [12, 13]);
        $this->assertCount(2, $parsed);
        $this->assertSame(100, $parsed[0]->score);
        $this->assertSame(0, $parsed[1]->score);
    }

    public function test_parse_tolerates_string_course_id(): void {
        $body = $this->build_payload([
            ['course_id' => '12', 'score' => 60, 'reasoning' => 'x'],
        ]);
        $parsed = response_parser::parse($body, [12]);
        $this->assertCount(1, $parsed);
        $this->assertSame(12, $parsed[0]->course_id);
    }

    public function test_parse_drops_item_with_no_course_id(): void {
        $body = $this->build_payload([
            ['score' => 90, 'reasoning' => 'no course id'],
            ['course_id' => 12, 'score' => 50, 'reasoning' => 'good'],
        ]);
        $parsed = response_parser::parse($body, [12]);
        $this->assertCount(1, $parsed);
        $this->assertSame(12, $parsed[0]->course_id);
    }

    public function test_parse_dedupes_duplicate_course_ids(): void {
        $body = $this->build_payload([
            ['course_id' => 12, 'score' => 90, 'reasoning' => 'first'],
            ['course_id' => 12, 'score' => 40, 'reasoning' => 'dupe'],
        ]);
        $parsed = response_parser::parse($body, [12]);
        $this->assertCount(1, $parsed);
        $this->assertSame('first', $parsed[0]->reasoning);
    }

    public function test_parse_truncates_long_reasoning(): void {
        $long = str_repeat('x', 1000);
        $body = $this->build_payload([
            ['course_id' => 12, 'score' => 50, 'reasoning' => $long],
        ]);
        $parsed = response_parser::parse($body, [12]);
        $this->assertCount(1, $parsed);
        $this->assertLessThanOrEqual(response_parser::MAX_REASONING_LEN, strlen($parsed[0]->reasoning));
    }

    public function test_extract_json_finds_object_in_wrapper_text(): void {
        $wrapped = "Sure:\n\n{\"recommendations\": []}\n\nHope that helps.";
        $json = response_parser::extract_json($wrapped);
        $this->assertSame('{"recommendations": []}', $json);
    }

    public function test_extract_json_returns_empty_when_no_json(): void {
        $this->assertSame('', response_parser::extract_json('plain prose only'));
    }
}
