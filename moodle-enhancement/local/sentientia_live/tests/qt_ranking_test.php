<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

namespace local_sentientia_live;

use local_sentientia_live\question_types\question_type_registry;
use local_sentientia_live\question_types\ranking;

defined('MOODLE_INTERNAL') || die();

/**
 * PHPUnit tests for the ranking question type — Phase E.9 / D4.
 *
 * Covers: 3 valid configs + 2 invalid, persist (valid + duplicate +
 * incomplete), tally (Borda count + average position), pure Borda /
 * avg-position helpers, aria announcements, registry resolution.
 *
 * @package    local_sentientia_live
 * @covers     \local_sentientia_live\question_types\ranking
 */
final class qt_ranking_test extends \advanced_testcase {

    private function qt(): ranking {
        return new ranking();
    }

    /**
     * @return array{0:int,1:int,2:\stdClass} [sessionid, slideid, participant]
     */
    private function make_slide(array $settings): array {
        $user = $this->getDataGenerator()->create_user();
        $sid = session_manager::create($user->id, 'Ranking session');
        $slideid = slide_manager::add($sid, 'ranking', 'Order these?',
            $settings);
        $participant = participant_manager::join_or_resume($sid, null, 'Tester');
        return [$sid, $slideid, $participant];
    }

    // ── validate_config — 3 valid configs ──

    public function test_validate_config_accepts_valid_configs(): void {
        $qt = $this->qt();
        // 1. Two items (minimum).
        $this->assertSame([], $qt->validate_config([
            'items' => ['First', 'Second']]));
        // 2. Four items.
        $this->assertSame([], $qt->validate_config([
            'items' => ['A', 'B', 'C', 'D']]));
        // 3. Three items.
        $this->assertSame([], $qt->validate_config([
            'items' => ['Speed', 'Quality', 'Cost']]));
    }

    // ── validate_config — 2 invalid configs ──

    public function test_validate_config_rejects_too_few_items(): void {
        $errors = $this->qt()->validate_config(['items' => ['Only one']]);
        $this->assertArrayHasKey('items', $errors);
    }

    public function test_validate_config_rejects_non_string_item(): void {
        $errors = $this->qt()->validate_config([
            'items' => ['Valid', 12345]]);
        $this->assertArrayHasKey('items', $errors);
    }

    // ── persist_response — valid + invalid ──

    public function test_persist_response_persists_valid_payload(): void {
        global $DB;
        $this->resetAfterTest();
        [, $slideid, $participant] = $this->make_slide([
            'items' => ['A', 'B', 'C']]);

        $rid = $this->qt()->persist_response((int) $participant->id, [
            'slide_id' => $slideid,
            'order'    => [2, 0, 1],
        ]);
        $row = $DB->get_record('local_sentientia_live_responses',
            ['id' => $rid]);
        $this->assertSame([2, 0, 1], json_decode($row->value_text, true));
    }

    public function test_persist_response_rejects_duplicate(): void {
        $this->resetAfterTest();
        [, $slideid, $participant] = $this->make_slide([
            'items' => ['A', 'B', 'C']]);
        $this->expectException(\moodle_exception::class);
        $this->qt()->persist_response((int) $participant->id, [
            'slide_id' => $slideid,
            'order'    => [0, 0, 1],
        ]);
    }

    public function test_persist_response_rejects_incomplete(): void {
        $this->resetAfterTest();
        [, $slideid, $participant] = $this->make_slide([
            'items' => ['A', 'B', 'C']]);
        $this->expectException(\moodle_exception::class);
        // Only 2 of 3 items — response_recorder rejects as incomplete.
        $this->qt()->persist_response((int) $participant->id, [
            'slide_id' => $slideid,
            'order'    => [0, 1],
        ]);
    }

    public function test_persist_response_rejects_out_of_range_index(): void {
        $this->resetAfterTest();
        [, $slideid, $participant] = $this->make_slide([
            'items' => ['A', 'B', 'C']]);
        $this->expectException(\moodle_exception::class);
        // Right length (3) + no duplicates, but index 9 is out of range.
        // Must be rejected so it can't silently distort the Borda tally.
        $this->qt()->persist_response((int) $participant->id, [
            'slide_id' => $slideid,
            'order'    => [0, 1, 9],
        ]);
    }

    public function test_persist_response_accepts_json_value_text_alias(): void {
        global $DB;
        $this->resetAfterTest();
        [, $slideid, $participant] = $this->make_slide([
            'items' => ['A', 'B', 'C']]);
        $rid = $this->qt()->persist_response((int) $participant->id, [
            'slide_id'   => $slideid,
            'value_text' => '[1,2,0]',
        ]);
        $row = $DB->get_record('local_sentientia_live_responses',
            ['id' => $rid]);
        $this->assertSame([1, 2, 0], json_decode($row->value_text, true));
    }

    // ── tally — Borda + average position ──

    public function test_tally_borda_and_avg_position(): void {
        $this->resetAfterTest();
        [$sid, $slideid] = $this->make_slide(['items' => ['A', 'B', 'C']]);
        $qt = $this->qt();

        // Two responses, both rank item 0 (A) first.
        $orders = [[0, 1, 2], [0, 2, 1]];
        foreach ($orders as $i => $order) {
            $p = participant_manager::join_or_resume($sid, null, "P$i");
            $qt->persist_response((int) $p->id, [
                'slide_id' => $slideid, 'order' => $order]);
        }

        $tally = $qt->tally($sid, $slideid);
        // Borda: item0 = (3-0)+(3-0) = 6; item1 = (3-1)+(3-2) = 3;
        //        item2 = (3-2)+(3-1) = 3.
        $this->assertSame(6, $tally['_borda'][0]);
        $this->assertSame(3, $tally['_borda'][1]);
        $this->assertSame(3, $tally['_borda'][2]);
        // Avg position: item0 = (1+1)/2 = 1.0 (most preferred).
        $this->assertEqualsWithDelta(1.0, $tally['_avg_position'][0], 0.01);
        $this->assertSame(2, $tally['_total_responses']);
        $this->assertSame(3, $tally['_item_count']);
        // Borda-ranked list — winner first.
        $this->assertSame(0, $tally['_borda_ranked'][0]['index']);
        $this->assertSame(6, $tally['_borda_ranked'][0]['borda']);
    }

    // ── pure aggregation helpers ──

    public function test_compute_borda_scores_pure(): void {
        // Single response: position 0 → N points, last → 1 point.
        $this->assertSame(
            [0 => 3, 1 => 2, 2 => 1],
            ranking::compute_borda_scores([[0, 1, 2]], 3));
        // Two opposite responses balance to equal Borda.
        $this->assertSame(
            [0 => 4, 1 => 4, 2 => 4],
            ranking::compute_borda_scores([[0, 1, 2], [2, 1, 0]], 3));
    }

    public function test_compute_avg_positions_pure(): void {
        $this->assertSame(
            [0 => 1.0, 1 => 2.0, 2 => 3.0],
            ranking::compute_avg_positions([[0, 1, 2]], 3));
        // Item never ranked → null (no divide-by-zero).
        $avg = ranking::compute_avg_positions([[0, 1]], 3);
        $this->assertNull($avg[2]);
    }

    // ── aria announcements ──

    public function test_get_aria_announcements_returns_keys(): void {
        $a = $this->qt()->get_aria_announcements();
        $this->assertArrayHasKey('item_moved', $a);
        $this->assertArrayHasKey('ranking_changed', $a);
        foreach ($a as $msg) {
            $this->assertIsString($msg);
            $this->assertNotSame('', $msg);
        }
    }

    // ── registry resolution ──

    public function test_registry_resolves_ranking(): void {
        $inst = question_type_registry::get_by_slug('ranking');
        $this->assertInstanceOf(ranking::class, $inst);
        $this->assertSame('ranking', $inst->get_slug());
    }
}
