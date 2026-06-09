<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

namespace local_sentientia_aiquiz;

defined('MOODLE_INTERNAL') || die();

/**
 * PHPUnit tests for response_parser — Phase G.0.
 *
 * Uses synthetic Claude-style responses (no API calls).
 *
 * @package    local_sentientia_aiquiz
 * @covers     \local_sentientia_aiquiz\response_parser
 */
final class response_parser_test extends \advanced_testcase {

    /**
     * Build a valid response payload with N questions.
     */
    private function build_payload(int $n, bool $wrapfences = false): string {
        $questions = [];
        for ($i = 1; $i <= $n; $i++) {
            $questions[] = [
                'qtype' => 'multichoice',
                'qtext' => "Question {$i}?",
                'qoptions' => ["A{$i}", "B{$i}", "C{$i}", "D{$i}"],
                'qanswer_index' => ($i % 4),
                'qexplanation' => "Because Q{$i}",
            ];
        }
        $json = json_encode(['questions' => $questions]);
        if ($wrapfences) {
            return "```json\n" . $json . "\n```";
        }
        return $json;
    }

    public function test_parse_returns_normalised_questions(): void {
        $parsed = response_parser::parse($this->build_payload(3));
        $this->assertCount(3, $parsed);
        $this->assertSame('multichoice', $parsed[0]->qtype);
        $this->assertSame('Question 1?', $parsed[0]->qtext);
        $this->assertSame(['A1', 'B1', 'C1', 'D1'], $parsed[0]->qoptions);
        $this->assertSame('1', $parsed[0]->qanswer);
        $this->assertSame('Because Q1', $parsed[0]->qexplanation);
        $this->assertJson($parsed[0]->qoptions_json);
    }

    public function test_parse_strips_markdown_code_fences(): void {
        $parsed = response_parser::parse($this->build_payload(2, true));
        $this->assertCount(2, $parsed);
        $this->assertSame('Question 1?', $parsed[0]->qtext);
    }

    public function test_parse_returns_empty_on_blank_input(): void {
        $this->assertSame([], response_parser::parse(''));
        $this->assertSame([], response_parser::parse('   '));
    }

    public function test_parse_returns_empty_on_non_json_text(): void {
        $this->assertSame([], response_parser::parse('Sorry, I cannot generate questions.'));
    }

    public function test_parse_returns_empty_when_questions_key_missing(): void {
        $body = json_encode(['something_else' => []]);
        $this->assertSame([], response_parser::parse($body));
    }

    public function test_parse_drops_items_with_wrong_number_of_options(): void {
        $bad = json_encode([
            'questions' => [
                [
                    'qtype' => 'multichoice',
                    'qtext' => 'Q?',
                    'qoptions' => ['A', 'B', 'C'],   // only 3
                    'qanswer_index' => 0,
                ],
                [
                    'qtype' => 'multichoice',
                    'qtext' => 'Good Q?',
                    'qoptions' => ['A', 'B', 'C', 'D'],
                    'qanswer_index' => 2,
                ],
            ],
        ]);
        $parsed = response_parser::parse($bad);
        $this->assertCount(1, $parsed);
        $this->assertSame('Good Q?', $parsed[0]->qtext);
    }

    public function test_parse_drops_items_with_duplicate_options(): void {
        $bad = json_encode([
            'questions' => [
                [
                    'qtype' => 'multichoice',
                    'qtext' => 'Q?',
                    'qoptions' => ['A', 'A', 'A', 'A'],
                    'qanswer_index' => 0,
                ],
            ],
        ]);
        $this->assertSame([], response_parser::parse($bad));
    }

    public function test_parse_drops_items_with_out_of_range_answer_index(): void {
        $bad = json_encode([
            'questions' => [
                [
                    'qtype' => 'multichoice',
                    'qtext' => 'Q?',
                    'qoptions' => ['A', 'B', 'C', 'D'],
                    'qanswer_index' => 5,  // out of range
                ],
                [
                    'qtype' => 'multichoice',
                    'qtext' => 'Q?',
                    'qoptions' => ['A', 'B', 'C', 'D'],
                    'qanswer_index' => -1,  // negative
                ],
            ],
        ]);
        $this->assertSame([], response_parser::parse($bad));
    }

    public function test_parse_drops_items_with_unsupported_qtype(): void {
        $bad = json_encode([
            'questions' => [
                [
                    'qtype' => 'shortanswer',  // G.0 only supports multichoice
                    'qtext' => 'Q?',
                    'qoptions' => ['A', 'B', 'C', 'D'],
                    'qanswer_index' => 0,
                ],
            ],
        ]);
        $this->assertSame([], response_parser::parse($bad));
    }

    public function test_parse_drops_items_with_empty_qtext(): void {
        $bad = json_encode([
            'questions' => [
                [
                    'qtype' => 'multichoice',
                    'qtext' => '',
                    'qoptions' => ['A', 'B', 'C', 'D'],
                    'qanswer_index' => 0,
                ],
            ],
        ]);
        $this->assertSame([], response_parser::parse($bad));
    }

    public function test_extract_json_finds_object_in_wrapper_text(): void {
        $wrapped = "Sure, here you go:\n\n{\"questions\": []}\n\nLet me know if you need more.";
        $json = response_parser::extract_json($wrapped);
        $this->assertSame('{"questions": []}', $json);
    }

    public function test_extract_json_returns_empty_when_no_json(): void {
        $this->assertSame('', response_parser::extract_json('plain prose only'));
    }

    public function test_parse_tolerates_string_qanswer_index(): void {
        $body = json_encode([
            'questions' => [
                [
                    'qtype' => 'multichoice',
                    'qtext' => 'Q?',
                    'qoptions' => ['A', 'B', 'C', 'D'],
                    'qanswer_index' => '2',  // string, not int
                ],
            ],
        ]);
        $parsed = response_parser::parse($body);
        $this->assertCount(1, $parsed);
        $this->assertSame('2', $parsed[0]->qanswer);
    }

    public function test_parse_handles_legacy_qanswer_int_field(): void {
        // Some prompt variants emit qanswer (no _index suffix); be permissive.
        $body = json_encode([
            'questions' => [
                [
                    'qtype' => 'multichoice',
                    'qtext' => 'Q?',
                    'qoptions' => ['A', 'B', 'C', 'D'],
                    'qanswer' => 1,
                ],
            ],
        ]);
        $parsed = response_parser::parse($body);
        $this->assertCount(1, $parsed);
        $this->assertSame('1', $parsed[0]->qanswer);
    }

    // ════════════════════════════════════════════════════════════════
    //  G.1 — Devanagari / Hindi quiz JSON
    // ════════════════════════════════════════════════════════════════

    public function test_parse_handles_devanagari_question(): void {
        $body = json_encode([
            'questions' => [
                [
                    'qtype' => 'multichoice',
                    'qtext' => 'अनुपालन प्रशिक्षण का मुख्य उद्देश्य क्या है?',
                    'qoptions' => [
                        'कर्मचारियों को नियमों से अवगत कराना',
                        'नई नौकरी प्रदान करना',
                        'वेतन-वृद्धि की समीक्षा',
                        'ग्राहक-शिकायत निवारण',
                    ],
                    'qanswer_index' => 0,
                    'qexplanation' => 'अनुपालन-प्रशिक्षण कर्मचारियों को कानूनों से अवगत कराने हेतु होता है।',
                ],
            ],
        ], JSON_UNESCAPED_UNICODE);

        $parsed = response_parser::parse($body);
        $this->assertCount(1, $parsed);
        $this->assertSame('multichoice', $parsed[0]->qtype);
        $this->assertSame('अनुपालन प्रशिक्षण का मुख्य उद्देश्य क्या है?', $parsed[0]->qtext);
        $this->assertCount(4, $parsed[0]->qoptions);
        $this->assertSame('कर्मचारियों को नियमों से अवगत कराना', $parsed[0]->qoptions[0]);
        $this->assertSame('0', $parsed[0]->qanswer);
        $this->assertStringContainsString('अनुपालन-प्रशिक्षण', $parsed[0]->qexplanation);
    }

    public function test_parse_devanagari_options_round_trip_through_json(): void {
        // The persisted qoptions_json must decode back to the same Hindi
        // strings — proving JSON_UNESCAPED_UNICODE preserves Devanagari.
        $body = json_encode([
            'questions' => [
                [
                    'qtype' => 'multichoice',
                    'qtext' => 'प्रश्न?',
                    'qoptions' => ['विकल्प क', 'विकल्प ख', 'विकल्प ग', 'विकल्प घ'],
                    'qanswer_index' => 2,
                ],
            ],
        ], JSON_UNESCAPED_UNICODE);

        $parsed = response_parser::parse($body);
        $this->assertCount(1, $parsed);
        $decoded = json_decode($parsed[0]->qoptions_json, true);
        $this->assertSame(['विकल्प क', 'विकल्प ख', 'विकल्प ग', 'विकल्प घ'], $decoded);
    }

    public function test_parse_devanagari_escaped_unicode_also_decodes(): void {
        // If the model returns \uXXXX-escaped Hindi (not raw UTF-8), the
        // parser must still decode it correctly.
        $escaped = json_encode([
            'questions' => [
                [
                    'qtype' => 'multichoice',
                    'qtext' => 'प्रश्न?',
                    'qoptions' => ['क', 'ख', 'ग', 'घ'],
                    'qanswer_index' => 1,
                ],
            ],
        ]); // No JSON_UNESCAPED_UNICODE → produces \uXXXX escapes.
        $this->assertStringContainsString('\\u', $escaped);

        $parsed = response_parser::parse($escaped);
        $this->assertCount(1, $parsed);
        $this->assertSame('प्रश्न?', $parsed[0]->qtext);
        $this->assertSame(['क', 'ख', 'ग', 'घ'], $parsed[0]->qoptions);
    }

    public function test_parse_counts_devanagari_length_by_characters_not_bytes(): void {
        // A 400-Devanagari-character stem is ~1200 bytes but well within
        // the 1000-CHARACTER limit. It must be accepted (mb_strlen), not
        // dropped (raw strlen would see >1000 bytes and reject it).
        $stem = str_repeat('क', 400) . '?';
        $this->assertLessThanOrEqual(response_parser::MAX_TEXT_LEN, mb_strlen($stem));
        $this->assertGreaterThan(response_parser::MAX_TEXT_LEN, strlen($stem));

        $body = json_encode([
            'questions' => [
                [
                    'qtype' => 'multichoice',
                    'qtext' => $stem,
                    'qoptions' => ['क', 'ख', 'ग', 'घ'],
                    'qanswer_index' => 0,
                ],
            ],
        ], JSON_UNESCAPED_UNICODE);

        $parsed = response_parser::parse($body);
        $this->assertCount(1, $parsed, 'Devanagari stem within char limit must not be dropped');
    }
}
