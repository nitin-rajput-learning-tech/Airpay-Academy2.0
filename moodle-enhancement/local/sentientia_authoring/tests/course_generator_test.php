<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

namespace local_sentientia_authoring;

defined('MOODLE_INTERNAL') || die();

/**
 * Generation-pipeline tests (mock only — never a live API call).
 *
 * Exercises course_generator::call_mock() → response_parser::parse() so the
 * full mock pipeline is proven end to end with zero spend.
 *
 * @package    local_sentientia_authoring
 * @covers     \local_sentientia_authoring\course_generator
 * @covers     \local_sentientia_authoring\response_parser
 */
final class course_generator_test extends \advanced_testcase {

    public function test_mock_returns_requested_card_count(): void {
        $r = course_generator::call_mock('some source', 5, 3);
        $this->assertSame('mock', $r['mode']);
        $this->assertSame(0, $r['tokens_in']);
        $parsed = response_parser::parse($r['body']);
        $this->assertCount(5, $parsed->cards);
        $this->assertCount(3, $parsed->questions);
    }

    public function test_mock_cycles_all_three_question_types(): void {
        $r = course_generator::call_mock('source', 1, 3);
        $parsed = response_parser::parse($r['body']);
        $types = array_map(fn($q) => $q->qtype, $parsed->questions);
        $this->assertSame(['multichoice', 'mrq', 'match'], $types);
    }

    public function test_mock_clamps_counts_to_bounds(): void {
        $r = course_generator::call_mock('source', 999, 999);
        $parsed = response_parser::parse($r['body']);
        $this->assertLessThanOrEqual(prompt_builder::MAX_CARDS, count($parsed->cards));
        $this->assertLessThanOrEqual(prompt_builder::MAX_QUESTIONS, count($parsed->questions));
    }

    public function test_mock_hindi_produces_devanagari(): void {
        $r = course_generator::call_mock('स्रोत', 2, 1, prompt_builder::VERSION_V2_HINDI);
        $parsed = response_parser::parse($r['body']);
        $this->assertNotEmpty($parsed->cards);
        // The MOCK marker stays Latin; the surrounding content is Devanagari.
        $this->assertStringContainsString('MOCK', $parsed->cards[0]->heading);
        $this->assertMatchesRegularExpression('/\p{Devanagari}/u', $parsed->cards[0]->body);
    }

    public function test_flip_cards_carry_a_back_face(): void {
        // Card index 4 is a flip card in the mock generator.
        $r = course_generator::call_mock('source', 4, 0);
        $parsed = response_parser::parse($r['body']);
        $flip = null;
        foreach ($parsed->cards as $c) {
            if ($c->cardtype === 'flip') {
                $flip = $c;
            }
        }
        $this->assertNotNull($flip);
        $this->assertNotEmpty($flip->flip_back);
    }

    public function test_parser_handles_markdown_fenced_json(): void {
        $body = "```json\n" . json_encode([
            'cards' => [['cardtype' => 'concept', 'heading' => 'H', 'body' => 'B']],
            'questions' => [],
        ]) . "\n```";
        $parsed = response_parser::parse($body);
        $this->assertCount(1, $parsed->cards);
    }

    public function test_parser_drops_malformed_question_keeps_valid(): void {
        $body = json_encode([
            'cards' => [],
            'questions' => [
                ['qtype' => 'multichoice', 'qtext' => 'Q', 'qoptions' => ['a', 'b', 'c', 'd'], 'qanswer_index' => 0],
                ['qtype' => 'multichoice', 'qtext' => 'Bad', 'qoptions' => ['a'], 'qanswer_index' => 0],
            ],
        ]);
        $parsed = response_parser::parse($body);
        $this->assertCount(1, $parsed->questions);
    }

    public function test_parser_empty_body_returns_empty_bundle(): void {
        $parsed = response_parser::parse('');
        $this->assertSame([], $parsed->cards);
        $this->assertSame([], $parsed->questions);
    }
}
