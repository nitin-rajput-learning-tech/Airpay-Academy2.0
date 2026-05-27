<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

namespace local_sentientia_live;

use local_sentientia_live\question_types\multiple_choice;
use local_sentientia_live\question_types\question_type_registry;

defined('MOODLE_INTERNAL') || die();

/**
 * PHPUnit tests for the multiple_choice question type — Phase E.4
 * (2026-05-25).
 *
 * Coverage (per the chip's acceptance criteria):
 *   - 4 valid configs accepted by validate_config
 *   - 3 invalid configs rejected with the expected error key
 *   - persist_response with a VALID payload writes a response row
 *   - persist_response with an INVALID payload throws moodle_exception
 *   - tally aggregates a multi-vote distribution correctly
 *   - tally exposes is_correct flag for correct-answer reveal
 *   - registry.get_by_slug returns the same concrete class
 *
 * Run via:
 *   cd /path/to/moodle/public
 *   vendor/bin/phpunit local/sentientia_live/tests/multiple_choice_test.php
 *
 * @package    local_sentientia_live
 * @covers     \local_sentientia_live\question_types\multiple_choice
 */
final class multiple_choice_test extends \advanced_testcase {

    /**
     * Build a fresh session + slide for the persistence / tally tests.
     * Returns the slide id; session id is on the slide row.
     */
    private function make_mc_slide(array $options = ['Yes', 'No', 'Maybe'],
                                    ?int $correct_index = null): array {
        $user = $this->getDataGenerator()->create_user();
        $sid = session_manager::create($user->id, 'MC test session');
        $settings = ['options' => $options];
        if ($correct_index !== null) {
            $settings['correct_index'] = $correct_index;
        }
        $slideid = slide_manager::add(
            $sid, 'multichoice', 'Pick one', $settings);
        return ['userid' => $user->id, 'sessionid' => $sid,
            'slideid' => $slideid];
    }

    private function make_participant(int $sessionid,
                                       ?int $userid = null,
                                       string $name = 'Tester'): \stdClass {
        return participant_manager::join_or_resume(
            $sessionid, $userid, $name);
    }

    // ───── validate_config: 4 VALID inputs ─────────────────────────────

    public function test_validate_config_accepts_minimum_two_options(): void {
        $this->resetAfterTest();
        $mc = new multiple_choice();
        $errors = $mc->validate_config(['options' => ['Yes', 'No']]);
        $this->assertSame([], $errors,
            'Two options is the minimum and must pass validation.');
    }

    public function test_validate_config_accepts_maximum_six_options(): void {
        $this->resetAfterTest();
        $mc = new multiple_choice();
        $errors = $mc->validate_config([
            'options' => ['A', 'B', 'C', 'D', 'E', 'F'],
        ]);
        $this->assertSame([], $errors,
            'Six options is the maximum and must pass validation.');
    }

    public function test_validate_config_accepts_correct_index_in_range(): void {
        $this->resetAfterTest();
        $mc = new multiple_choice();
        $errors = $mc->validate_config([
            'options'       => ['Apple', 'Banana', 'Cherry'],
            'correct_index' => 1,
        ]);
        $this->assertSame([], $errors,
            'correct_index inside [0, count) must pass validation.');
    }

    public function test_validate_config_accepts_buttons_render_style(): void {
        $this->resetAfterTest();
        $mc = new multiple_choice();
        $errors = $mc->validate_config([
            'options'      => ['Up', 'Down'],
            'render_style' => 'buttons',
        ]);
        $this->assertSame([], $errors,
            '"buttons" render style is a valid choice.');
    }

    // ───── validate_config: 3 INVALID inputs ───────────────────────────

    public function test_validate_config_rejects_single_option(): void {
        $this->resetAfterTest();
        $mc = new multiple_choice();
        $errors = $mc->validate_config(['options' => ['Only one']]);
        $this->assertArrayHasKey('options', $errors,
            'One option should fail the 2-option minimum.');
    }

    public function test_validate_config_rejects_seven_options(): void {
        $this->resetAfterTest();
        $mc = new multiple_choice();
        $errors = $mc->validate_config([
            'options' => ['A', 'B', 'C', 'D', 'E', 'F', 'G'],
        ]);
        $this->assertArrayHasKey('options', $errors,
            'Seven options should fail the 6-option class-layer maximum.');
    }

    public function test_validate_config_rejects_correct_index_out_of_range(): void {
        $this->resetAfterTest();
        $mc = new multiple_choice();
        $errors = $mc->validate_config([
            'options'       => ['Apple', 'Banana'],
            'correct_index' => 5,
        ]);
        $this->assertArrayHasKey('correct_index', $errors,
            'correct_index ≥ count(options) should fail.');
    }

    // ───── persist_response: valid + invalid payload ───────────────────

    public function test_persist_response_writes_row_for_valid_payload(): void {
        global $DB;
        $this->resetAfterTest();
        $fix = $this->make_mc_slide();
        $p = $this->make_participant($fix['sessionid']);
        $mc = new multiple_choice();

        $response_id = $mc->persist_response($p->userid ?? 0, [
            'option_index'  => 1,
            'slideid'       => $fix['slideid'],
            'participantid' => $p->id,
        ]);

        $this->assertGreaterThan(0, $response_id,
            'A valid payload must produce a response row id.');
        $row = $DB->get_record('local_sentientia_live_responses',
            ['id' => $response_id], '*', MUST_EXIST);
        $this->assertSame((int) $fix['slideid'], (int) $row->slideid);
        $this->assertSame((int) $p->id, (int) $row->participantid);
        $this->assertSame(1, (int) $row->value_int);
        $this->assertNull($row->value_text);
    }

    public function test_persist_response_rejects_out_of_range_index(): void {
        $this->resetAfterTest();
        $fix = $this->make_mc_slide();   // 3 options
        $p = $this->make_participant($fix['sessionid']);
        $mc = new multiple_choice();

        $this->expectException(\moodle_exception::class);
        $mc->persist_response(0, [
            'option_index'  => 7,            // out of range (only 0-2 valid)
            'slideid'       => $fix['slideid'],
            'participantid' => $p->id,
        ]);
    }

    public function test_persist_response_rejects_negative_index(): void {
        $this->resetAfterTest();
        $fix = $this->make_mc_slide();
        $p = $this->make_participant($fix['sessionid']);
        $mc = new multiple_choice();

        $this->expectException(\moodle_exception::class);
        $mc->persist_response(0, [
            'option_index'  => -1,
            'slideid'       => $fix['slideid'],
            'participantid' => $p->id,
        ]);
    }

    public function test_persist_response_rejects_missing_option_index(): void {
        $this->resetAfterTest();
        $fix = $this->make_mc_slide();
        $p = $this->make_participant($fix['sessionid']);
        $mc = new multiple_choice();

        $this->expectException(\moodle_exception::class);
        $mc->persist_response(0, [
            'slideid'       => $fix['slideid'],
            'participantid' => $p->id,
            // option_index intentionally absent
        ]);
    }

    public function test_persist_response_rejects_missing_participant(): void {
        $this->resetAfterTest();
        $fix = $this->make_mc_slide();
        $mc = new multiple_choice();

        $this->expectException(\moodle_exception::class);
        $mc->persist_response(0, [
            'option_index' => 0,
            'slideid'      => $fix['slideid'],
            // participantid intentionally absent
        ]);
    }

    // ───── tally: aggregation ──────────────────────────────────────────

    public function test_tally_aggregates_votes_per_option(): void {
        $this->resetAfterTest();
        $fix = $this->make_mc_slide(['A', 'B', 'C']);
        $mc = new multiple_choice();

        // 3 votes for A (index 0), 1 vote for B (index 1), 0 for C.
        for ($i = 0; $i < 3; $i++) {
            $p = $this->make_participant($fix['sessionid'], null, 'A_voter_' . $i);
            $mc->persist_response(0, [
                'option_index'  => 0,
                'slideid'       => $fix['slideid'],
                'participantid' => $p->id,
            ]);
        }
        $pb = $this->make_participant($fix['sessionid'], null, 'B_voter');
        $mc->persist_response(0, [
            'option_index'  => 1,
            'slideid'       => $fix['slideid'],
            'participantid' => $pb->id,
        ]);

        $tally = $mc->tally($fix['sessionid'], $fix['slideid']);

        $this->assertCount(3, $tally,
            'Tally must return one row per option.');
        $this->assertSame('A', $tally[0]['label']);
        $this->assertSame(3, $tally[0]['count']);
        $this->assertSame('B', $tally[1]['label']);
        $this->assertSame(1, $tally[1]['count']);
        $this->assertSame('C', $tally[2]['label']);
        $this->assertSame(0, $tally[2]['count'],
            'Zero-vote options must still appear in tally with count=0.');
    }

    public function test_tally_idempotent_resubmission_does_not_double_count(): void {
        $this->resetAfterTest();
        $fix = $this->make_mc_slide(['Yes', 'No']);
        $mc = new multiple_choice();
        $p = $this->make_participant($fix['sessionid']);

        // First vote.
        $mc->persist_response(0, [
            'option_index'  => 0,
            'slideid'       => $fix['slideid'],
            'participantid' => $p->id,
        ]);
        // Same participant changes their mind.
        $mc->persist_response(0, [
            'option_index'  => 1,
            'slideid'       => $fix['slideid'],
            'participantid' => $p->id,
        ]);

        $tally = $mc->tally($fix['sessionid'], $fix['slideid']);
        $this->assertSame(0, $tally[0]['count'],
            'Original "Yes" vote should be overwritten, not retained.');
        $this->assertSame(1, $tally[1]['count'],
            'Updated "No" vote should be the only one.');
    }

    public function test_tally_marks_correct_answer(): void {
        $this->resetAfterTest();
        $fix = $this->make_mc_slide(['Wrong', 'Right'], /* correct_index */ 1);
        $mc = new multiple_choice();
        $p = $this->make_participant($fix['sessionid']);
        $mc->persist_response(0, [
            'option_index'  => 1,
            'slideid'       => $fix['slideid'],
            'participantid' => $p->id,
        ]);

        $tally = $mc->tally($fix['sessionid'], $fix['slideid']);

        $this->assertFalse($tally[0]['is_correct'],
            'Index 0 ("Wrong") must not be flagged correct.');
        $this->assertTrue($tally[1]['is_correct'],
            'Index 1 ("Right") MUST be flagged correct in the tally.');
    }

    public function test_tally_returns_empty_for_mismatched_session(): void {
        $this->resetAfterTest();
        $fix = $this->make_mc_slide();
        $mc = new multiple_choice();

        // Wrong session ID — return empty.
        $tally = $mc->tally(9999999, $fix['slideid']);
        $this->assertSame([], $tally,
            'Tally with mismatched sessionid must return empty array.');
    }

    // ───── Registry wiring ─────────────────────────────────────────────

    public function test_registry_resolves_slug_to_multiple_choice(): void {
        $this->resetAfterTest();
        $instance = question_type_registry::get_by_slug('multichoice');
        $this->assertInstanceOf(multiple_choice::class, $instance,
            'Registry must resolve slug "multichoice" to multiple_choice class.');
    }

    public function test_aria_announcements_returns_expected_keys(): void {
        $this->resetAfterTest();
        $mc = new multiple_choice();
        $ann = $mc->get_aria_announcements();

        $this->assertArrayHasKey('response_recorded', $ann);
        $this->assertArrayHasKey('tally_updated', $ann);
        $this->assertArrayHasKey('correct_revealed', $ann);
        // Values must be non-empty (resolved via get_string).
        foreach ($ann as $key => $val) {
            $this->assertIsString($val);
            $this->assertNotSame('', trim($val),
                "Announcement '$key' must be a non-empty string.");
        }
    }
}
