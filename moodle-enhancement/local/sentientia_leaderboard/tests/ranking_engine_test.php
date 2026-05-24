<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

namespace local_sentientia_leaderboard;

defined('MOODLE_INTERNAL') || die();

/**
 * Tests for {@see ranking_engine}.
 *
 * Locks in:
 *   - competition tie-handling (1, 2, 2, 4)
 *   - per-type aggregators (quiz, completion, skill)
 *   - recompute idempotency (running twice = same row count)
 *   - tenant scoping (a board with tenantid=77 excludes a tenant-1 user)
 *   - opt-out honoring (opted-out user excluded from learner-facing read,
 *     still present for :viewall reads)
 *
 * @package    local_sentientia_leaderboard
 * @category   test
 * @covers     \local_sentientia_leaderboard\ranking_engine
 */
final class ranking_engine_test extends \advanced_testcase {

    private function create_user_in_tenant(int $tenantid = 1): \stdClass {
        global $DB;
        $u = $this->getDataGenerator()->create_user();
        // Set open_path on the user so the tenant filter resolves correctly.
        $DB->set_field('user', 'open_path', '/' . $tenantid, ['id' => $u->id]);
        return $u;
    }

    /**
     * Seed a completed course completion for $userid at $duration_seconds
     * after enrolment.
     *
     * @return int courseid
     */
    private function complete_course(int $userid, int $duration_seconds = 100): int {
        global $DB;
        $course = $this->getDataGenerator()->create_course(['enablecompletion' => 1]);
        $now = time();
        $DB->insert_record('course_completions', (object) [
            'userid'       => $userid,
            'course'       => $course->id,
            'timeenrolled' => $now - $duration_seconds,
            'timestarted'  => $now - $duration_seconds,
            'timecompleted' => $now,
            'reaggregate'  => 0,
        ]);
        return (int) $course->id;
    }

    public function test_recompute_completion_orders_by_speed(): void {
        global $DB;
        $this->resetAfterTest();
        $this->setAdminUser();

        $u1 = $this->create_user_in_tenant(1);
        $u2 = $this->create_user_in_tenant(1);
        $u3 = $this->create_user_in_tenant(1);

        // u1 fastest (10s), u2 (100s), u3 (1000s).
        $cid = $this->complete_course((int) $u1->id, 10);
        $DB->insert_record('course_completions', (object) [
            'userid'       => $u2->id,
            'course'       => $cid,
            'timeenrolled' => time() - 100,
            'timestarted'  => time() - 100,
            'timecompleted' => time(),
            'reaggregate'  => 0,
        ]);
        $DB->insert_record('course_completions', (object) [
            'userid'       => $u3->id,
            'course'       => $cid,
            'timeenrolled' => time() - 1000,
            'timestarted'  => time() - 1000,
            'timecompleted' => time(),
            'reaggregate'  => 0,
        ]);

        $boardid = board_manager::create([
            'name'     => 'Speed',
            'type'     => board_manager::TYPE_COMPLETION,
            'scope'    => board_manager::SCOPE_COURSE,
            'courseid' => $cid,
            'ownerid'  => (int) $u1->id,
            'tenantid' => 1,
        ]);

        ranking_engine::recompute($boardid);

        // Read entries ordered by rank ASC; should be u1 (1), u2 (2), u3 (3).
        $rows = $DB->get_records('local_sentientia_lb_entries',
            ['boardid' => $boardid], 'userrank ASC');
        $this->assertCount(3, $rows);
        $ranked = array_values($rows);
        $this->assertSame((int) $u1->id, (int) $ranked[0]->userid,
            'fastest learner must take rank 1');
        $this->assertSame(1, (int) $ranked[0]->userrank);
        $this->assertSame((int) $u2->id, (int) $ranked[1]->userid);
        $this->assertSame(2, (int) $ranked[1]->userrank);
        $this->assertSame((int) $u3->id, (int) $ranked[2]->userid);
        $this->assertSame(3, (int) $ranked[2]->userrank);
    }

    public function test_recompute_is_idempotent(): void {
        global $DB;
        $this->resetAfterTest();
        $this->setAdminUser();

        $u = $this->create_user_in_tenant(1);
        $cid = $this->complete_course((int) $u->id, 50);

        $boardid = board_manager::create([
            'name'     => 'Idem',
            'type'     => board_manager::TYPE_COMPLETION,
            'scope'    => board_manager::SCOPE_COURSE,
            'courseid' => $cid,
            'ownerid'  => (int) $u->id,
            'tenantid' => 1,
        ]);

        ranking_engine::recompute($boardid);
        $countAfter1 = $DB->count_records('local_sentientia_lb_entries',
            ['boardid' => $boardid]);

        ranking_engine::recompute($boardid);
        $countAfter2 = $DB->count_records('local_sentientia_lb_entries',
            ['boardid' => $boardid]);

        $this->assertSame($countAfter1, $countAfter2,
            'second recompute must not change row count');
        $this->assertSame(1, (int) $countAfter1);
    }

    public function test_tenant_scope_excludes_other_tenants(): void {
        global $DB;
        $this->resetAfterTest();
        $this->setAdminUser();

        $u1 = $this->create_user_in_tenant(1);    // Airpay tenant
        $u2 = $this->create_user_in_tenant(77);   // Public tenant

        // Both users complete the same course.
        $cid = $this->complete_course((int) $u1->id, 10);
        $DB->insert_record('course_completions', (object) [
            'userid'       => $u2->id,
            'course'       => $cid,
            'timeenrolled' => time() - 50,
            'timestarted'  => time() - 50,
            'timecompleted' => time(),
            'reaggregate'  => 0,
        ]);

        // Board scoped to tenant 1.
        $boardid = board_manager::create([
            'name'     => 'Tenant1',
            'type'     => board_manager::TYPE_COMPLETION,
            'scope'    => board_manager::SCOPE_TENANT,
            'courseid' => $cid,
            'ownerid'  => (int) $u1->id,
            'tenantid' => 1,
        ]);

        ranking_engine::recompute($boardid);

        // Only u1 should appear; u2 (tenant 77) excluded.
        $rows = $DB->get_records('local_sentientia_lb_entries',
            ['boardid' => $boardid]);
        $this->assertCount(1, $rows,
            'tenant-scoped board must exclude users from other tenants');
        $row = reset($rows);
        $this->assertSame((int) $u1->id, (int) $row->userid);
    }

    public function test_optout_excludes_user_from_learner_read(): void {
        $this->resetAfterTest();
        $this->setAdminUser();

        $u1 = $this->create_user_in_tenant(1);
        $u2 = $this->create_user_in_tenant(1);
        $cid = $this->complete_course((int) $u1->id, 10);
        global $DB;
        $DB->insert_record('course_completions', (object) [
            'userid'       => $u2->id,
            'course'       => $cid,
            'timeenrolled' => time() - 50,
            'timestarted'  => time() - 50,
            'timecompleted' => time(),
            'reaggregate'  => 0,
        ]);

        $boardid = board_manager::create([
            'name'     => 'OptoutTest',
            'type'     => board_manager::TYPE_COMPLETION,
            'scope'    => board_manager::SCOPE_COURSE,
            'courseid' => $cid,
            'ownerid'  => (int) $u1->id,
            'tenantid' => 1,
        ]);
        ranking_engine::recompute($boardid);

        // Both visible by default.
        $public = ranking_engine::read_top($boardid, 10, false);
        $this->assertCount(2, $public['rows']);
        $this->assertSame(0, $public['optout_total']);

        // u2 opts out.
        optout_manager::opt_out((int) $u2->id);

        // Public read excludes u2.
        $public2 = ranking_engine::read_top($boardid, 10, false);
        $this->assertCount(1, $public2['rows'],
            'learner read must exclude opted-out user');
        $this->assertSame((int) $u1->id, $public2['rows'][0]['userid']);
        $this->assertSame(1, $public2['optout_total']);

        // :viewall (HR) read includes u2.
        $hr = ranking_engine::read_top($boardid, 10, true);
        $this->assertCount(2, $hr['rows'],
            ':viewall (HR) read must bypass the opt-out filter');
    }

    public function test_read_my_rank_returns_null_when_optedout(): void {
        $this->resetAfterTest();
        $this->setAdminUser();

        $u = $this->create_user_in_tenant(1);
        $cid = $this->complete_course((int) $u->id, 10);

        $boardid = board_manager::create([
            'name'     => 'MyRank',
            'type'     => board_manager::TYPE_COMPLETION,
            'scope'    => board_manager::SCOPE_COURSE,
            'courseid' => $cid,
            'ownerid'  => (int) $u->id,
            'tenantid' => 1,
        ]);
        ranking_engine::recompute($boardid);

        $rank_before = ranking_engine::read_my_rank($boardid, (int) $u->id);
        $this->assertNotNull($rank_before);
        $this->assertSame(1, (int) $rank_before['rank']);

        optout_manager::opt_out((int) $u->id);
        $rank_after = ranking_engine::read_my_rank($boardid, (int) $u->id);
        $this->assertNull($rank_after,
            'opted-out user must not see their own rank');
    }

    public function test_competition_tie_handling(): void {
        global $DB;
        $this->resetAfterTest();
        $this->setAdminUser();

        $u1 = $this->create_user_in_tenant(1);
        $u2 = $this->create_user_in_tenant(1);
        $u3 = $this->create_user_in_tenant(1);

        // u1 and u2 tie at duration 100 ; u3 takes 1000.
        $cid = $this->complete_course((int) $u1->id, 100);
        $DB->insert_record('course_completions', (object) [
            'userid'       => $u2->id,
            'course'       => $cid,
            'timeenrolled' => time() - 100,
            'timestarted'  => time() - 100,
            'timecompleted' => time(),
            'reaggregate'  => 0,
        ]);
        $DB->insert_record('course_completions', (object) [
            'userid'       => $u3->id,
            'course'       => $cid,
            'timeenrolled' => time() - 1000,
            'timestarted'  => time() - 1000,
            'timecompleted' => time(),
            'reaggregate'  => 0,
        ]);

        $boardid = board_manager::create([
            'name'     => 'Tie',
            'type'     => board_manager::TYPE_COMPLETION,
            'scope'    => board_manager::SCOPE_COURSE,
            'courseid' => $cid,
            'ownerid'  => (int) $u1->id,
            'tenantid' => 1,
        ]);

        ranking_engine::recompute($boardid);

        $rows = $DB->get_records('local_sentientia_lb_entries',
            ['boardid' => $boardid], 'userrank ASC, userid ASC');
        $this->assertCount(3, $rows);
        $ranks = array_map('intval', array_column((array) $rows, 'userrank'));
        $this->assertSame([1, 1, 3], array_values($ranks),
            'tied scores share a rank; next rank skips (competition ranking)');
    }

    public function test_recompute_emits_event(): void {
        global $DB;
        $this->resetAfterTest();
        $this->setAdminUser();

        $u = $this->create_user_in_tenant(1);
        $cid = $this->complete_course((int) $u->id, 10);
        $boardid = board_manager::create([
            'name'     => 'Eventy',
            'type'     => board_manager::TYPE_COMPLETION,
            'scope'    => board_manager::SCOPE_COURSE,
            'courseid' => $cid,
            'ownerid'  => (int) $u->id,
            'tenantid' => 1,
        ]);

        $before = $DB->count_records('local_sentientia_lb_events',
            ['boardid' => $boardid]);
        ranking_engine::recompute($boardid);
        $after = $DB->count_records('local_sentientia_lb_events',
            ['boardid' => $boardid]);

        $this->assertSame($before + 1, $after,
            'recompute must emit exactly one SSE event');

        $event = $DB->get_record_sql(
            "SELECT * FROM {local_sentientia_lb_events}
              WHERE boardid = :bid ORDER BY id DESC",
            ['bid' => $boardid], MUST_EXIST);
        $this->assertSame('leaderboard.recomputed', $event->type);
    }

    public function test_invalid_type_throws(): void {
        $this->resetAfterTest();
        $this->setAdminUser();
        $u = $this->create_user_in_tenant(1);

        $this->expectException(\moodle_exception::class);
        board_manager::create([
            'name'    => 'BadType',
            'type'    => 'not_a_real_type',
            'ownerid' => (int) $u->id,
            'tenantid' => 1,
        ]);
    }

    public function test_quiz_board_requires_quizid(): void {
        $this->resetAfterTest();
        $this->setAdminUser();
        $u = $this->create_user_in_tenant(1);

        $this->expectException(\moodle_exception::class);
        board_manager::create([
            'name'    => 'NoQuiz',
            'type'    => board_manager::TYPE_QUIZ,
            'quizid'  => 0,
            'ownerid' => (int) $u->id,
            'tenantid' => 1,
        ]);
    }

    public function test_completion_board_requires_courseid(): void {
        $this->resetAfterTest();
        $this->setAdminUser();
        $u = $this->create_user_in_tenant(1);

        $this->expectException(\moodle_exception::class);
        board_manager::create([
            'name'     => 'NoCourse',
            'type'     => board_manager::TYPE_COMPLETION,
            'courseid' => 0,
            'ownerid'  => (int) $u->id,
            'tenantid' => 1,
        ]);
    }

    public function test_minimum_recompute_interval_enforced(): void {
        $this->resetAfterTest();
        $this->setAdminUser();
        $u = $this->create_user_in_tenant(1);
        $cid = $this->complete_course((int) $u->id, 10);

        $this->expectException(\moodle_exception::class);
        board_manager::create([
            'name'     => 'TooFast',
            'type'     => board_manager::TYPE_COMPLETION,
            'courseid' => $cid,
            'ownerid'  => (int) $u->id,
            'tenantid' => 1,
            'recompute_seconds' => 5,  // below MIN_RECOMPUTE_SECONDS
        ]);
    }

    public function test_due_recompute_picks_up_stale_boards(): void {
        global $DB;
        $this->resetAfterTest();
        $this->setAdminUser();

        $u = $this->create_user_in_tenant(1);
        $cid = $this->complete_course((int) $u->id, 10);
        $boardid = board_manager::create([
            'name'     => 'Stale',
            'type'     => board_manager::TYPE_COMPLETION,
            'courseid' => $cid,
            'ownerid'  => (int) $u->id,
            'tenantid' => 1,
            'recompute_seconds' => 60,
        ]);

        // Force last_recomputed into the past so the cron picks it up.
        $DB->set_field('local_sentientia_lb_boards', 'last_recomputed',
            time() - 1000, ['id' => $boardid]);

        $count = ranking_engine::recompute_due();
        $this->assertSame(1, $count,
            'recompute_due must pick up boards stale beyond their recompute_seconds');

        // Re-running immediately must not pick it up again (it's fresh).
        $count2 = ranking_engine::recompute_due();
        $this->assertSame(0, $count2,
            'a just-recomputed board must not be due again');
    }
}
