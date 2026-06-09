<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

namespace local_sentientia_challenge;

defined('MOODLE_INTERNAL') || die();

/**
 * Tests for {@see leaderboard_manager}.
 *
 * Locks in:
 * - per-challenge recompute deletes stale rows + inserts ordered + ranked
 * - aggregate recompute sums across challenges
 * - tie-handling: equal points get the same rank, next rank skips
 * - tenant scoping
 *
 * @package    local_sentientia_challenge
 * @category   test
 */
final class leaderboard_manager_test extends \advanced_testcase {

    private function complete_course(int $userid): int {
        global $DB;
        $course = $this->getDataGenerator()->create_course(['enablecompletion' => 1]);
        $this->getDataGenerator()->enrol_user($userid, $course->id, 'student');
        $now = time();
        $DB->insert_record('course_completions', (object) [
            'userid' => $userid, 'course' => $course->id,
            'timeenrolled' => $now, 'timestarted' => $now, 'timecompleted' => $now,
            'reaggregate' => 0,
        ]);
        return (int) $course->id;
    }

    public function test_recompute_challenge_orders_users_by_points(): void {
        global $DB;
        $this->resetAfterTest();
        $this->setAdminUser();

        $u1 = $this->getDataGenerator()->create_user();
        $u2 = $this->getDataGenerator()->create_user();

        $cid = challenge_engine::create_challenge([
            'name' => 'Top', 'shortname' => 'top',
            'status' => challenge_engine::STATUS_ACTIVE,
            'targetcount' => 1, 'pointsreward' => 100,
        ]);
        // u1 finishes, u2 hasn't.
        $this->complete_course((int) $u1->id);
        challenge_engine::join($cid, (int) $u1->id);
        challenge_engine::join($cid, (int) $u2->id);

        leaderboard_manager::recompute_challenge($cid);

        $rows = $DB->get_records('local_sentientia_challenge_leaderboard',
            ['challengeid' => $cid], 'points DESC');
        $this->assertCount(2, $rows);
        $first = reset($rows);
        $this->assertSame((int) $u1->id, (int) $first->userid,
            'completed user must rank above un-started user');
        $this->assertSame(100, (int) $first->points);
        $this->assertSame(1,   (int) $first->userrank);
    }

    public function test_recompute_handles_ties(): void {
        global $DB;
        $this->resetAfterTest();
        $this->setAdminUser();

        $u1 = $this->getDataGenerator()->create_user();
        $u2 = $this->getDataGenerator()->create_user();
        $u3 = $this->getDataGenerator()->create_user();

        $cid = challenge_engine::create_challenge([
            'name' => 'Tie', 'shortname' => 'tie',
            'status' => challenge_engine::STATUS_ACTIVE,
            'targetcount' => 1, 'pointsreward' => 100,
        ]);
        // u1 + u2 tie (both complete). u3 doesn't.
        $this->complete_course((int) $u1->id);
        $this->complete_course((int) $u2->id);
        challenge_engine::join($cid, (int) $u1->id);
        challenge_engine::join($cid, (int) $u2->id);
        challenge_engine::join($cid, (int) $u3->id);

        leaderboard_manager::recompute_challenge($cid);

        $byrank = $DB->get_records('local_sentientia_challenge_leaderboard',
            ['challengeid' => $cid], 'userrank ASC, userid ASC');
        $this->assertCount(3, $byrank);

        // u1 + u2 should both be rank 1; u3 should be rank 3 (skipping 2).
        // DB driver returns numeric cols as strings; cast for strict compare.
        $ranks = array_map('intval', array_column((array) $byrank, 'userrank'));
        $this->assertSame([1, 1, 3], array_values($ranks),
            'ties take the same rank; next rank skips (competition ranking)');
    }

    public function test_recompute_is_idempotent(): void {
        global $DB;
        $this->resetAfterTest();
        $this->setAdminUser();

        $u = $this->getDataGenerator()->create_user();
        $this->complete_course((int) $u->id);
        $cid = challenge_engine::create_challenge(['name' => 'Idem', 'shortname' => 'idem',
            'status' => challenge_engine::STATUS_ACTIVE, 'targetcount' => 1]);
        challenge_engine::join($cid, (int) $u->id);

        leaderboard_manager::recompute_challenge($cid);
        $countAfter1 = $DB->count_records('local_sentientia_challenge_leaderboard',
            ['challengeid' => $cid]);

        leaderboard_manager::recompute_challenge($cid);
        $countAfter2 = $DB->count_records('local_sentientia_challenge_leaderboard',
            ['challengeid' => $cid]);

        $this->assertSame($countAfter1, $countAfter2,
            'second recompute must not add duplicate rows');
    }

    public function test_aggregate_sums_across_challenges(): void {
        global $DB;
        $this->resetAfterTest();
        $this->setAdminUser();

        $u = $this->getDataGenerator()->create_user();
        $this->complete_course((int) $u->id);
        $this->complete_course((int) $u->id);

        $c1 = challenge_engine::create_challenge(['name' => 'C1', 'shortname' => 'c1agg',
            'status' => challenge_engine::STATUS_ACTIVE, 'targetcount' => 1, 'pointsreward' => 100]);
        $c2 = challenge_engine::create_challenge(['name' => 'C2', 'shortname' => 'c2agg',
            'status' => challenge_engine::STATUS_ACTIVE, 'targetcount' => 1, 'pointsreward' => 200]);
        challenge_engine::join($c1, (int) $u->id);  // → completed, +100
        challenge_engine::join($c2, (int) $u->id);  // → completed, +200

        leaderboard_manager::recompute_aggregate();
        $row = $DB->get_record('local_sentientia_challenge_leaderboard',
            ['challengeid' => 0, 'userid' => (int) $u->id], '*', MUST_EXIST);
        $this->assertSame(300, (int) $row->points,
            'aggregate must sum points across multiple challenges');
        $this->assertSame(2, (int) $row->attemptscompleted);
    }

    public function test_get_top_returns_paginated(): void {
        $this->resetAfterTest();
        $this->setAdminUser();

        // Seed 7 users + 1 challenge.
        $cid = challenge_engine::create_challenge(['name' => 'Pag', 'shortname' => 'pag',
            'status' => challenge_engine::STATUS_ACTIVE, 'targetcount' => 1, 'pointsreward' => 10]);
        for ($i = 0; $i < 7; $i++) {
            $u = $this->getDataGenerator()->create_user();
            $this->complete_course((int) $u->id);
            challenge_engine::join($cid, (int) $u->id);
        }
        leaderboard_manager::recompute_challenge($cid);

        $page = leaderboard_manager::get_top($cid, 0, 0, 5);
        $this->assertSame(7, $page['total']);
        $this->assertCount(5, $page['rows']);
        // Each row has the structural fields.
        foreach ($page['rows'] as $r) {
            $this->assertArrayHasKey('rank',     $r);
            $this->assertArrayHasKey('points',   $r);
            $this->assertArrayHasKey('fullname', $r);
        }
    }
}
