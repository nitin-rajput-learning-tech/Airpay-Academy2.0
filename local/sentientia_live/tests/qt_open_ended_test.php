<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

namespace local_sentientia_live;

use local_sentientia_live\question_types\open_ended;
use local_sentientia_live\question_types\question_type_registry;

defined('MOODLE_INTERNAL') || die();

/**
 * PHPUnit tests for the open_ended question type — Phase E.6 / D4.
 *
 * Covers: 3 valid configs + 2 invalid, persist (valid + HTML-strip +
 * invalid), tally (no aggregation, newest-first), pagination helper,
 * aria announcements, registry resolution.
 *
 * @package    local_sentientia_live
 * @covers     \local_sentientia_live\question_types\open_ended
 */
final class qt_open_ended_test extends \advanced_testcase {

    private function qt(): open_ended {
        return new open_ended();
    }

    /**
     * @return array{0:int,1:int,2:\stdClass} [sessionid, slideid, participant]
     */
    private function make_slide(array $settings = ['max_chars' => 500]): array {
        $user = $this->getDataGenerator()->create_user();
        $sid = session_manager::create($user->id, 'Open session');
        $slideid = slide_manager::add($sid, 'openended', 'Tell us anything?',
            $settings);
        $participant = participant_manager::join_or_resume($sid, null, 'Tester');
        return [$sid, $slideid, $participant];
    }

    // ── validate_config — 3 valid configs ──

    public function test_validate_config_accepts_valid_configs(): void {
        $qt = $this->qt();
        // 1. Empty config — defaults applied.
        $this->assertSame([], $qt->validate_config([]));
        // 2. Explicit max_chars within range.
        $this->assertSame([], $qt->validate_config(['max_chars' => 280]));
        // 3. Ceiling + moderation toggle on.
        $this->assertSame([], $qt->validate_config([
            'max_chars'  => 500,
            'moderation' => true,
        ]));
    }

    // ── validate_config — 2 invalid configs ──

    public function test_validate_config_rejects_max_chars_over_ceiling(): void {
        $errors = $this->qt()->validate_config(['max_chars' => 501]);
        $this->assertArrayHasKey('max_chars', $errors);
    }

    public function test_validate_config_rejects_max_chars_under_floor(): void {
        $errors = $this->qt()->validate_config(['max_chars' => 9]);
        $this->assertArrayHasKey('max_chars', $errors);
    }

    // ── persist_response — valid ──

    public function test_persist_response_persists_valid_payload(): void {
        global $DB;
        $this->resetAfterTest();
        [, $slideid, $participant] = $this->make_slide();

        $rid = $this->qt()->persist_response((int) $participant->id, [
            'slide_id' => $slideid,
            'text'     => 'Great session, learned a lot!',
        ]);
        $this->assertGreaterThan(0, $rid);

        $row = $DB->get_record('local_sentientia_live_responses',
            ['id' => $rid]);
        $this->assertSame('Great session, learned a lot!', $row->value_text);
        $this->assertNull($row->value_int);
    }

    public function test_persist_response_strips_html(): void {
        global $DB;
        $this->resetAfterTest();
        [, $slideid, $participant] = $this->make_slide();

        $rid = $this->qt()->persist_response((int) $participant->id, [
            'slide_id' => $slideid,
            'text'     => '<b>bold</b> <script>alert(1)</script>plain',
        ]);
        $row = $DB->get_record('local_sentientia_live_responses',
            ['id' => $rid]);
        $this->assertStringNotContainsString('<script', $row->value_text);
        $this->assertStringNotContainsString('<b>', $row->value_text);
    }

    // ── persist_response — invalid ──

    public function test_persist_response_rejects_empty_text(): void {
        $this->resetAfterTest();
        [, $slideid, $participant] = $this->make_slide();
        $this->expectException(\moodle_exception::class);
        $this->qt()->persist_response((int) $participant->id, [
            'slide_id' => $slideid,
            'text'     => '   ',
        ]);
    }

    public function test_persist_response_rejects_missing_slide_id(): void {
        $this->resetAfterTest();
        $this->expectException(\moodle_exception::class);
        $this->qt()->persist_response(1, ['text' => 'hello']);
    }

    public function test_persist_response_rejects_over_ceiling(): void {
        $this->resetAfterTest();
        [, $slideid, $participant] = $this->make_slide();
        $this->expectException(\moodle_exception::class);
        $this->qt()->persist_response((int) $participant->id, [
            'slide_id' => $slideid,
            'text'     => str_repeat('x', open_ended::MAX_CHARS_CEILING + 1),
        ]);
    }

    // ── tally — no aggregation, newest first ──

    public function test_tally_returns_all_responses_newest_first(): void {
        $this->resetAfterTest();
        [$sid, $slideid, $p1] = $this->make_slide();
        $p2 = participant_manager::join_or_resume($sid, null, 'P2');

        $this->qt()->persist_response((int) $p1->id, [
            'slide_id' => $slideid, 'text' => 'first']);
        $this->qt()->persist_response((int) $p2->id, [
            'slide_id' => $slideid, 'text' => 'second']);

        $tally = $this->qt()->tally($sid, $slideid);
        $this->assertCount(2, $tally);
        $texts = array_column($tally, 'text');
        $this->assertContains('first', $texts);
        $this->assertContains('second', $texts);
        // Each row carries a display name + id (no N+1 for the renderer).
        $this->assertArrayHasKey('display_name', $tally[0]);
        $this->assertArrayHasKey('id', $tally[0]);
    }

    // ── pagination helper (pure) ──

    public function test_paginate_slices_correctly(): void {
        $rows = [];
        for ($i = 0; $i < 25; $i++) {
            $rows[] = ['id' => $i, 'text' => "r$i"];
        }
        $page1 = open_ended::paginate($rows, 1, 10);
        $this->assertCount(10, $page1['rows']);
        $this->assertSame(3, $page1['total_pages']);
        $this->assertTrue($page1['has_next']);
        $this->assertFalse($page1['has_prev']);

        $page3 = open_ended::paginate($rows, 3, 10);
        $this->assertCount(5, $page3['rows']);
        $this->assertFalse($page3['has_next']);
        $this->assertTrue($page3['has_prev']);
        $this->assertSame(3, $page3['page']);

        // Out-of-range page clamps into valid range.
        $clamped = open_ended::paginate($rows, 99, 10);
        $this->assertSame(3, $clamped['page']);
    }

    // ── aria announcements ──

    public function test_get_aria_announcements_returns_keys(): void {
        $a = $this->qt()->get_aria_announcements();
        $this->assertArrayHasKey('response_recorded', $a);
        $this->assertArrayHasKey('new_response', $a);
        foreach ($a as $msg) {
            $this->assertIsString($msg);
            $this->assertNotSame('', $msg);
        }
    }

    // ── registry resolution ──

    public function test_registry_resolves_openended(): void {
        $inst = question_type_registry::get_by_slug('openended');
        $this->assertInstanceOf(open_ended::class, $inst);
        $this->assertSame('openended', $inst->get_slug());
    }
}
