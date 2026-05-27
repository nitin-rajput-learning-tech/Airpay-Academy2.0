<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

namespace local_sentientia_live;

use local_sentientia_live\question_types\question_type_registry;
use local_sentientia_live\question_types\quiz;

defined('MOODLE_INTERNAL') || die();

/**
 * PHPUnit tests for the quiz question type — Phase E.8 / D4.
 *
 * Covers: 3 valid configs + 3 invalid (incl. required correct_index),
 * persist (valid + out-of-range), tally (distribution + correct count +
 * leaderboard), per-response scoring helper, aria announcements,
 * registry resolution.
 *
 * @package    local_sentientia_live
 * @covers     \local_sentientia_live\question_types\quiz
 */
final class qt_quiz_test extends \advanced_testcase {

    private function qt(): quiz {
        return new quiz();
    }

    /**
     * @return array{0:int,1:int,2:\stdClass} [sessionid, slideid, participant]
     */
    private function make_slide(array $settings): array {
        $user = $this->getDataGenerator()->create_user();
        $sid = session_manager::create($user->id, 'Quiz session');
        $slideid = slide_manager::add($sid, 'quiz', 'Which is correct?',
            $settings);
        $participant = participant_manager::join_or_resume($sid, null, 'Tester');
        return [$sid, $slideid, $participant];
    }

    // ── validate_config — 3 valid configs ──

    public function test_validate_config_accepts_valid_configs(): void {
        $qt = $this->qt();
        // 1. Two options, correct = first.
        $this->assertSame([], $qt->validate_config([
            'options' => ['Yes', 'No'], 'correct_index' => 0]));
        // 2. Four options, correct = last.
        $this->assertSame([], $qt->validate_config([
            'options' => ['A', 'B', 'C', 'D'], 'correct_index' => 3]));
        // 3. Three options, correct = middle.
        $this->assertSame([], $qt->validate_config([
            'options' => ['Red', 'Green', 'Blue'], 'correct_index' => 1]));
    }

    // ── validate_config — 3 invalid configs ──

    public function test_validate_config_rejects_too_few_options(): void {
        $errors = $this->qt()->validate_config([
            'options' => ['Only one'], 'correct_index' => 0]);
        $this->assertArrayHasKey('options', $errors);
    }

    public function test_validate_config_rejects_missing_correct_index(): void {
        $errors = $this->qt()->validate_config([
            'options' => ['A', 'B']]);
        $this->assertArrayHasKey('correct_index', $errors);
    }

    public function test_validate_config_rejects_correct_index_out_of_range(): void {
        $errors = $this->qt()->validate_config([
            'options' => ['A', 'B'], 'correct_index' => 5]);
        $this->assertArrayHasKey('correct_index', $errors);
    }

    // ── persist_response — valid + invalid ──

    public function test_persist_response_persists_valid_payload(): void {
        global $DB;
        $this->resetAfterTest();
        [, $slideid, $participant] = $this->make_slide([
            'options' => ['A', 'B', 'C'], 'correct_index' => 1]);

        $rid = $this->qt()->persist_response((int) $participant->id, [
            'slide_id'     => $slideid,
            'option_index' => 1,
        ]);
        $row = $DB->get_record('local_sentientia_live_responses',
            ['id' => $rid]);
        $this->assertSame(1, (int) $row->value_int);
    }

    public function test_persist_response_rejects_out_of_range(): void {
        $this->resetAfterTest();
        [, $slideid, $participant] = $this->make_slide([
            'options' => ['A', 'B', 'C'], 'correct_index' => 1]);
        $this->expectException(\moodle_exception::class);
        $this->qt()->persist_response((int) $participant->id, [
            'slide_id'     => $slideid,
            'option_index' => 9,
        ]);
    }

    public function test_persist_response_rejects_missing_option(): void {
        $this->resetAfterTest();
        [, $slideid, $participant] = $this->make_slide([
            'options' => ['A', 'B'], 'correct_index' => 0]);
        $this->expectException(\moodle_exception::class);
        $this->qt()->persist_response((int) $participant->id, [
            'slide_id' => $slideid,
        ]);
    }

    // ── tally — distribution + correct count + leaderboard ──

    public function test_tally_distribution_and_correct_count(): void {
        $this->resetAfterTest();
        [$sid, $slideid] = $this->make_slide([
            'options' => ['A', 'B', 'C'], 'correct_index' => 1]);
        $qt = $this->qt();

        // Picks: 1, 1, 0 → two correct, one wrong.
        foreach ([1, 1, 0] as $i => $choice) {
            $p = participant_manager::join_or_resume($sid, null, "P$i");
            $qt->persist_response((int) $p->id, [
                'slide_id' => $slideid, 'option_index' => $choice]);
        }

        $tally = $qt->tally($sid, $slideid);
        $this->assertSame(1, $tally[0]);
        $this->assertSame(2, $tally[1]);
        $this->assertSame(0, $tally[2]);
        $this->assertSame(1, $tally['_correct_index']);
        $this->assertSame(2, $tally['_correct_count']);
        $this->assertSame(3, $tally['_total']);
    }

    public function test_tally_leaderboard_lists_correct_responders(): void {
        $this->resetAfterTest();
        [$sid, $slideid] = $this->make_slide([
            'options' => ['A', 'B', 'C'], 'correct_index' => 1]);
        $qt = $this->qt();

        // 3 correct + 1 wrong.
        $names = ['Alice', 'Bob', 'Carol', 'Dave'];
        $choices = [1, 1, 1, 0];
        foreach ($choices as $i => $choice) {
            $p = participant_manager::join_or_resume($sid, null, $names[$i]);
            $qt->persist_response((int) $p->id, [
                'slide_id' => $slideid, 'option_index' => $choice]);
        }

        $tally = $qt->tally($sid, $slideid);
        $this->assertArrayHasKey('_leaderboard', $tally);
        $this->assertCount(3, $tally['_leaderboard']);
        foreach ($tally['_leaderboard'] as $entry) {
            $this->assertTrue($entry['is_correct']);
            $this->assertSame(quiz::POINTS_CORRECT, $entry['score']);
        }
        $this->assertSame(1, $tally['_leaderboard'][0]['rank']);
        $this->assertTrue($tally['_leaderboard'][0]['is_winner']);
    }

    public function test_tally_leaderboard_capped_at_ten(): void {
        $this->resetAfterTest();
        [$sid, $slideid] = $this->make_slide([
            'options' => ['A', 'B'], 'correct_index' => 0]);
        $qt = $this->qt();
        // 12 correct answers — leaderboard must cap at 10.
        for ($i = 0; $i < 12; $i++) {
            $p = participant_manager::join_or_resume($sid, null, "P$i");
            $qt->persist_response((int) $p->id, [
                'slide_id' => $slideid, 'option_index' => 0]);
        }
        $tally = $qt->tally($sid, $slideid);
        $this->assertCount(quiz::LEADERBOARD_SIZE, $tally['_leaderboard']);
        $this->assertSame(12, $tally['_correct_count']);
    }

    // ── per-response scoring helper (pure) ──

    public function test_score_response_pure(): void {
        $this->assertSame(quiz::POINTS_CORRECT, quiz::score_response(1, 1));
        $this->assertSame(quiz::POINTS_WRONG, quiz::score_response(0, 1));
        // No correct index defined → never awards points.
        $this->assertSame(quiz::POINTS_WRONG, quiz::score_response(2, -1));
    }

    // ── aria announcements ──

    public function test_get_aria_announcements_returns_keys(): void {
        $a = $this->qt()->get_aria_announcements();
        $this->assertArrayHasKey('correct', $a);
        $this->assertArrayHasKey('incorrect', $a);
        foreach ($a as $msg) {
            $this->assertIsString($msg);
            $this->assertNotSame('', $msg);
        }
    }

    // ── registry resolution ──

    public function test_registry_resolves_quiz(): void {
        $inst = question_type_registry::get_by_slug('quiz');
        $this->assertInstanceOf(quiz::class, $inst);
        $this->assertSame('quiz', $inst->get_slug());
    }
}
