<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

namespace local_sentientia_live;

use local_sentientia_live\question_types\question_type_registry;
use local_sentientia_live\question_types\rating_scale;

defined('MOODLE_INTERNAL') || die();

/**
 * PHPUnit tests for the rating_scale question type — Phase E.7 / D4.
 *
 * Covers: 3 valid configs + 3 invalid, persist (valid + out-of-range),
 * tally (distribution + mean + median), pure stat helpers, aria
 * announcements, registry resolution.
 *
 * @package    local_sentientia_live
 * @covers     \local_sentientia_live\question_types\rating_scale
 */
final class qt_rating_scale_test extends \advanced_testcase {

    private function qt(): rating_scale {
        return new rating_scale();
    }

    /**
     * @return array{0:int,1:int,2:\stdClass} [sessionid, slideid, participant]
     */
    private function make_slide(array $settings): array {
        $user = $this->getDataGenerator()->create_user();
        $sid = session_manager::create($user->id, 'Rating session');
        $slideid = slide_manager::add($sid, 'rating', 'Rate this?', $settings);
        $participant = participant_manager::join_or_resume($sid, null, 'Tester');
        return [$sid, $slideid, $participant];
    }

    // ── validate_config — 3 valid configs ──

    public function test_validate_config_accepts_valid_configs(): void {
        $qt = $this->qt();
        // 1. Stars (default bounds).
        $this->assertSame([], $qt->validate_config(['scale_type' => 'stars']));
        // 2. NPS (default bounds 1-10).
        $this->assertSame([], $qt->validate_config(['scale_type' => 'nps']));
        // 3. Stars with full label set (5 labels for a 1-5 scale).
        $this->assertSame([], $qt->validate_config([
            'scale_type'   => 'stars',
            'scale_min'    => 1,
            'scale_max'    => 5,
            'scale_labels' => ['Awful', 'Poor', 'OK', 'Good', 'Great'],
        ]));
    }

    // ── validate_config — 3 invalid configs ──

    public function test_validate_config_rejects_invalid_scale_type(): void {
        $errors = $this->qt()->validate_config(['scale_type' => 'thumbs']);
        $this->assertArrayHasKey('scale_type', $errors);
    }

    public function test_validate_config_rejects_bad_min_max(): void {
        $errors = $this->qt()->validate_config([
            'scale_type' => 'stars',
            'scale_min'  => 5,
            'scale_max'  => 3,
        ]);
        $this->assertArrayHasKey('scale_min_max', $errors);
    }

    public function test_validate_config_rejects_label_length_mismatch(): void {
        $errors = $this->qt()->validate_config([
            'scale_type'   => 'stars',
            'scale_min'    => 1,
            'scale_max'    => 5,
            'scale_labels' => ['only', 'two'],
        ]);
        $this->assertArrayHasKey('scale_labels', $errors);
    }

    // ── persist_response — valid + invalid ──

    public function test_persist_response_persists_valid_payload(): void {
        global $DB;
        $this->resetAfterTest();
        [, $slideid, $participant] = $this->make_slide([
            'scale_type' => 'stars', 'scale_min' => 1, 'scale_max' => 5]);

        $rid = $this->qt()->persist_response((int) $participant->id, [
            'slide_id' => $slideid,
            'value'    => 4,
        ]);
        $row = $DB->get_record('local_sentientia_live_responses',
            ['id' => $rid]);
        $this->assertSame(4, (int) $row->value_int);
    }

    public function test_persist_response_rejects_out_of_range(): void {
        $this->resetAfterTest();
        [, $slideid, $participant] = $this->make_slide([
            'scale_type' => 'stars', 'scale_min' => 1, 'scale_max' => 5]);
        $this->expectException(\moodle_exception::class);
        // 9 is outside a 1-5 star scale — response_recorder rejects.
        $this->qt()->persist_response((int) $participant->id, [
            'slide_id' => $slideid,
            'value'    => 9,
        ]);
    }

    public function test_persist_response_rejects_missing_value(): void {
        $this->resetAfterTest();
        [, $slideid, $participant] = $this->make_slide([
            'scale_type' => 'stars', 'scale_min' => 1, 'scale_max' => 5]);
        $this->expectException(\moodle_exception::class);
        $this->qt()->persist_response((int) $participant->id, [
            'slide_id' => $slideid,
        ]);
    }

    // ── tally — distribution + mean + median ──

    public function test_tally_distribution_mean_median(): void {
        $this->resetAfterTest();
        [$sid, $slideid] = $this->make_slide([
            'scale_type' => 'stars', 'scale_min' => 1, 'scale_max' => 5]);
        $qt = $this->qt();

        // Three responses: 5, 4, 5.
        foreach ([5, 4, 5] as $i => $v) {
            $p = participant_manager::join_or_resume($sid, null, "P$i");
            $qt->persist_response((int) $p->id, [
                'slide_id' => $slideid, 'value' => $v]);
        }

        $tally = $qt->tally($sid, $slideid);
        $this->assertSame(2, $tally[5]);
        $this->assertSame(1, $tally[4]);
        $this->assertSame(0, $tally[1]);
        $this->assertEqualsWithDelta(4.67, $tally['_mean'], 0.01);
        $this->assertEqualsWithDelta(5.0, $tally['_median'], 0.01);
        $this->assertSame(3, $tally['_count']);
        $this->assertSame('stars', $tally['_scale_type']);
        // Back-compat alias preserved for the existing chart_updater.
        $this->assertEqualsWithDelta(4.67, $tally['_avg'], 0.01);
    }

    public function test_tally_nps_scale_bounds(): void {
        $this->resetAfterTest();
        [$sid, $slideid] = $this->make_slide([
            'scale_type' => 'nps', 'scale_min' => 1, 'scale_max' => 10]);
        $qt = $this->qt();
        $p = participant_manager::join_or_resume($sid, null, 'P');
        $qt->persist_response((int) $p->id, [
            'slide_id' => $slideid, 'value' => 9]);

        $tally = $qt->tally($sid, $slideid);
        $this->assertSame('nps', $tally['_scale_type']);
        $this->assertSame(1, $tally['_min']);
        $this->assertSame(10, $tally['_max']);
        $this->assertSame(1, $tally[9]);
    }

    // ── pure stat helpers ──

    public function test_compute_mean_and_median_pure(): void {
        $this->assertEqualsWithDelta(4.67,
            rating_scale::compute_mean([5, 4, 5]), 0.01);
        $this->assertSame(5.0, rating_scale::compute_median([4, 5, 5]));
        // Even-length list — average of the two middle values.
        $this->assertSame(2.5, rating_scale::compute_median([1, 2, 3, 4]));
        // Empty — null, not a divide-by-zero.
        $this->assertNull(rating_scale::compute_mean([]));
        $this->assertNull(rating_scale::compute_median([]));
    }

    // ── aria announcements ──

    public function test_get_aria_announcements_returns_keys(): void {
        $a = $this->qt()->get_aria_announcements();
        $this->assertArrayHasKey('mean_updated', $a);
        $this->assertArrayHasKey('median_updated', $a);
        foreach ($a as $msg) {
            $this->assertIsString($msg);
            $this->assertNotSame('', $msg);
        }
    }

    // ── registry resolution ──

    public function test_registry_resolves_rating(): void {
        $inst = question_type_registry::get_by_slug('rating');
        $this->assertInstanceOf(rating_scale::class, $inst);
        $this->assertSame('rating', $inst->get_slug());
    }
}
