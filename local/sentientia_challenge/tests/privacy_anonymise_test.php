<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

namespace local_sentientia_challenge;

defined('MOODLE_INTERNAL') || die();

use core_privacy\local\request\approved_contextlist;
use local_sentientia_challenge\privacy\provider;

/**
 * Pins the 2026-09-24 decision that the DPDP erasure ERASES challenge data.
 *
 * local_sentientia_privacy's privacy_manager::process_deletion() calls each
 * Sentientia provider's anonymise_data_for_user() when it has one (to keep
 * learning and compliance records against the anonymised user row) and
 * delete_data_for_user() otherwise. Challenge attempts and the leaderboard
 * are gamification, not such records - see the provider's class comment -
 * so this provider has no anonymise hook and the DPDP flow takes the
 * delete path. These tests hold that path to what it must do: erase only the
 * subject's rows, keep shared challenges, and stay erased after the
 * leaderboard's scheduled rebuild.
 *
 * @package    local_sentientia_challenge
 * @category   test
 * @covers     \local_sentientia_challenge\privacy\provider
 */
final class privacy_anonymise_test extends \advanced_testcase {

    /**
     * Insert an active challenge. Only NOT NULL columns without a default
     * are named, plus the ones the test reads back.
     */
    private function challenge(string $shortname, int $createdby): int {
        global $DB;
        $now = time();
        return (int) $DB->insert_record('local_sentientia_challenge_challenges', (object) [
            'name'         => 'Complete three courses',
            'shortname'    => $shortname,
            'status'       => challenge_engine::STATUS_ACTIVE,
            'pointsreward' => 100,
            'costcenterid' => 1,
            'open_path'    => '/1/2',
            'createdby'    => $createdby,
            'timecreated'  => $now,
            'timemodified' => $now,
        ]);
    }

    /** Insert a completed attempt worth 100 points. */
    private function completed_attempt(int $challengeid, int $userid): int {
        global $DB;
        $now = time();
        return (int) $DB->insert_record('local_sentientia_challenge_attempts', (object) [
            'challengeid'    => $challengeid,
            'userid'         => $userid,
            'status'         => challenge_engine::ATTEMPT_COMPLETED,
            'progress'       => 3,
            'targetcount'    => 3,
            'pointsawarded'  => 100,
            'completiondate' => $now,
            'costcenterid'   => 1,
            'timecreated'    => $now,
            'timemodified'   => $now,
        ]);
    }

    public function test_the_dpdp_flow_takes_the_delete_path(): void {
        // Deliberate. An anonymise hook that kept attempts would bring the
        // erased person back onto the leaderboard at its next rebuild, which
        // does not filter deleted users. Change that first.
        $this->assertFalse(method_exists(provider::class, 'anonymise_data_for_user'));
    }

    public function test_erasure_takes_only_the_subjects_rows_and_survives_the_rebuild(): void {
        global $DB;
        $this->resetAfterTest();
        $subject = $this->getDataGenerator()->create_user();
        $other = $this->getDataGenerator()->create_user();

        // The subject authored the challenge and both people completed it.
        $cid = $this->challenge('three-courses', (int) $subject->id);
        $this->completed_attempt($cid, (int) $subject->id);
        $theirs = $this->completed_attempt($cid, (int) $other->id);
        leaderboard_manager::recompute_challenge($cid);
        $this->assertEquals(2, $DB->count_records('local_sentientia_challenge_leaderboard',
            ['challengeid' => $cid]));

        provider::delete_data_for_user(new approved_contextlist($subject,
            'local_sentientia_challenge', [\context_system::instance()->id]));

        $this->assertFalse($DB->record_exists('local_sentientia_challenge_attempts',
            ['userid' => $subject->id]));
        $this->assertFalse($DB->record_exists('local_sentientia_challenge_leaderboard',
            ['userid' => $subject->id]));

        // The other participant keeps their attempt and their standing.
        $this->assertTrue($DB->record_exists('local_sentientia_challenge_attempts', ['id' => $theirs]));
        $this->assertTrue($DB->record_exists('local_sentientia_challenge_leaderboard',
            ['challengeid' => $cid, 'userid' => $other->id]));

        // The shared challenge survives with its author anonymised, and stays
        // in the same tenant (scoping reads costcenterid, not open_path).
        $challenge = $DB->get_record('local_sentientia_challenge_challenges', ['id' => $cid],
            '*', MUST_EXIST);
        $this->assertEquals(0, $challenge->createdby);
        $this->assertNull($challenge->open_path);
        $this->assertEquals(1, $challenge->costcenterid);
        $this->assertEquals(challenge_engine::STATUS_ACTIVE, $challenge->status);

        // The 15-minute scheduled rebuild reads attempts. The erased person
        // must not come back from it.
        leaderboard_manager::recompute_challenge($cid);
        leaderboard_manager::recompute_aggregate();
        $this->assertFalse($DB->record_exists('local_sentientia_challenge_leaderboard',
            ['userid' => $subject->id]));
        $this->assertEquals(1, $DB->get_field('local_sentientia_challenge_leaderboard', 'userrank',
            ['challengeid' => $cid, 'userid' => $other->id]),
            'The remaining participant is re-ranked first.');
    }
}
