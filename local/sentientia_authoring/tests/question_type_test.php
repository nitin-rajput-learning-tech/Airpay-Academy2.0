<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

namespace local_sentientia_authoring;

defined('MOODLE_INTERNAL') || die();

/**
 * Question-type validation tests (MRQ / match / multichoice).
 *
 * The expanded question types are the core of gap P0.3 #4 — these tests pin
 * the validation contract so a malformed AI response (or a bad manual edit)
 * can never persist an invalid question.
 *
 * @package    local_sentientia_authoring
 * @covers     \local_sentientia_authoring\question_type
 */
final class question_type_test extends \advanced_testcase {

    public function test_multichoice_valid(): void {
        $out = question_type::normalise([
            'qtype' => 'multichoice',
            'qtext' => 'What is 2+2?',
            'qoptions' => ['3', '4', '5', '6'],
            'qanswer_index' => 1,
        ]);
        $this->assertNotNull($out);
        $this->assertSame('multichoice', $out->qtype);
        $this->assertSame('1', $out->qanswer);
        $this->assertCount(4, $out->qoptions);
    }

    public function test_multichoice_rejects_wrong_option_count(): void {
        $this->assertNull(question_type::normalise([
            'qtype' => 'multichoice', 'qtext' => 'Q', 'qoptions' => ['a', 'b', 'c'], 'qanswer_index' => 0,
        ]));
    }

    public function test_multichoice_rejects_out_of_range_index(): void {
        $this->assertNull(question_type::normalise([
            'qtype' => 'multichoice', 'qtext' => 'Q', 'qoptions' => ['a', 'b', 'c', 'd'], 'qanswer_index' => 7,
        ]));
    }

    public function test_multichoice_rejects_duplicate_options(): void {
        $this->assertNull(question_type::normalise([
            'qtype' => 'multichoice', 'qtext' => 'Q', 'qoptions' => ['a', 'a', 'c', 'd'], 'qanswer_index' => 0,
        ]));
    }

    public function test_mrq_valid(): void {
        $out = question_type::normalise([
            'qtype' => 'mrq',
            'qtext' => 'Select all primes.',
            'qoptions' => ['2', '3', '4', '6'],
            'qanswer_indices' => [0, 1],
        ]);
        $this->assertNotNull($out);
        $this->assertSame('mrq', $out->qtype);
        $this->assertSame([0, 1], json_decode($out->qanswer, true));
    }

    public function test_mrq_rejects_empty_answer(): void {
        $this->assertNull(question_type::normalise([
            'qtype' => 'mrq', 'qtext' => 'Q', 'qoptions' => ['a', 'b', 'c'], 'qanswer_indices' => [],
        ]));
    }

    public function test_mrq_rejects_all_correct_degenerate(): void {
        // Marking every option correct is a degenerate "select all" — rejected.
        $this->assertNull(question_type::normalise([
            'qtype' => 'mrq', 'qtext' => 'Q', 'qoptions' => ['a', 'b', 'c'], 'qanswer_indices' => [0, 1, 2],
        ]));
    }

    public function test_mrq_rejects_out_of_range_index(): void {
        $this->assertNull(question_type::normalise([
            'qtype' => 'mrq', 'qtext' => 'Q', 'qoptions' => ['a', 'b', 'c'], 'qanswer_indices' => [0, 9],
        ]));
    }

    public function test_mrq_dedupes_repeated_indices(): void {
        $out = question_type::normalise([
            'qtype' => 'mrq', 'qtext' => 'Q', 'qoptions' => ['a', 'b', 'c', 'd'], 'qanswer_indices' => [1, 1, 2],
        ]);
        $this->assertNotNull($out);
        $this->assertSame([1, 2], json_decode($out->qanswer, true));
    }

    public function test_match_valid(): void {
        $out = question_type::normalise([
            'qtype' => 'match',
            'qtext' => 'Match terms.',
            'qpairs' => [
                ['left' => 'A', 'right' => '1'],
                ['left' => 'B', 'right' => '2'],
                ['left' => 'C', 'right' => '3'],
            ],
        ]);
        $this->assertNotNull($out);
        $this->assertSame('match', $out->qtype);
        $answer = json_decode($out->qanswer, true);
        $this->assertSame('1', $answer['0']);
        $this->assertSame('2', $answer['1']);
    }

    public function test_match_rejects_too_few_pairs(): void {
        $this->assertNull(question_type::normalise([
            'qtype' => 'match', 'qtext' => 'Q', 'qpairs' => [['left' => 'A', 'right' => '1']],
        ]));
    }

    public function test_match_rejects_duplicate_lefts(): void {
        $this->assertNull(question_type::normalise([
            'qtype' => 'match', 'qtext' => 'Q', 'qpairs' => [
                ['left' => 'A', 'right' => '1'],
                ['left' => 'A', 'right' => '2'],
            ],
        ]));
    }

    public function test_match_rejects_duplicate_rights(): void {
        $this->assertNull(question_type::normalise([
            'qtype' => 'match', 'qtext' => 'Q', 'qpairs' => [
                ['left' => 'A', 'right' => '1'],
                ['left' => 'B', 'right' => '1'],
            ],
        ]));
    }

    public function test_unknown_type_rejected(): void {
        $this->assertNull(question_type::normalise([
            'qtype' => 'essay', 'qtext' => 'Discuss.', 'qoptions' => [],
        ]));
    }

    public function test_decode_answer_round_trips(): void {
        $this->assertSame(2, question_type::decode_answer('multichoice', '2'));
        $this->assertSame([0, 3], question_type::decode_answer('mrq', '[0,3]'));
        $this->assertSame(['0' => 'x'], question_type::decode_answer('match', '{"0":"x"}'));
    }

    public function test_devanagari_text_counts_characters_not_bytes(): void {
        // A short Hindi stem must pass — mb_strlen, not strlen, governs length.
        $out = question_type::normalise([
            'qtype' => 'multichoice',
            'qtext' => 'अनुपालन क्या है?',
            'qoptions' => ['विकल्प क', 'विकल्प ख', 'विकल्प ग', 'विकल्प घ'],
            'qanswer_index' => 0,
        ]);
        $this->assertNotNull($out);
    }
}
