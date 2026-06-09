<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

namespace local_sentientia_challenge;

defined('MOODLE_INTERNAL') || die();

/**
 * Engine unit tests — locks in the lifecycle invariants:
 * - tenant detection from open_path
 * - valid statuses + types
 * - join is idempotent
 * - join evaluates eligibility against existing course completions
 * - course completion event triggers re-evaluation of in-progress attempts
 * - completion awards points exactly once
 * - leave removes the attempt row
 * - delete cascades to attempts + leaderboard
 *
 * @package    local_sentientia_challenge
 * @category   test
 */
final class challenge_engine_test extends \advanced_testcase {

    /**
     * Helper: create a course, mark it complete for $userid, and return courseid.
     * Uses the canonical Moodle path: course_completion_criteria + manual completion.
     */
    private function complete_course_for_user(int $userid, ?int $courseid = null): int {
        global $DB;
        if ($courseid === null) {
            $course = $this->getDataGenerator()->create_course([
                'enablecompletion' => 1,
            ]);
            $courseid = (int) $course->id;
        }
        // Enrol user.
        $this->getDataGenerator()->enrol_user($userid, $courseid, 'student');

        // Insert a manual course-completion record (the engine only reads
        // {course_completions}.timecompleted IS NOT NULL).
        $now = time();
        $existing = $DB->get_record('course_completions',
            ['userid' => $userid, 'course' => $courseid]);
        if ($existing) {
            $existing->timecompleted = $now;
            $DB->update_record('course_completions', $existing);
        } else {
            $DB->insert_record('course_completions', (object) [
                'userid'        => $userid,
                'course'        => $courseid,
                'timeenrolled'  => $now,
                'timestarted'   => $now,
                'timecompleted' => $now,
                'reaggregate'   => 0,
            ]);
        }
        return $courseid;
    }

    public function test_tenant_from_path(): void {
        $this->assertSame(0,   challenge_engine::tenant_from_path(null));
        $this->assertSame(0,   challenge_engine::tenant_from_path(''));
        $this->assertSame(1,   challenge_engine::tenant_from_path('/1'));
        $this->assertSame(77,  challenge_engine::tenant_from_path('/77'));
        $this->assertSame(177, challenge_engine::tenant_from_path('/177/2/3'));
        $this->assertSame(0,   challenge_engine::tenant_from_path('/garbage'));
    }

    public function test_valid_statuses_and_types(): void {
        $this->assertSame([0, 1, 2], challenge_engine::valid_statuses());
        $types = challenge_engine::valid_types();
        $this->assertContains('course_completion', $types);
        $this->assertContains('streak',            $types);
        $this->assertContains('quiz_score',        $types);
        $this->assertContains('custom',            $types);
    }

    public function test_create_challenge_persists_and_validates(): void {
        global $DB;
        $this->resetAfterTest();
        $this->setAdminUser();

        $id = challenge_engine::create_challenge([
            'name' => 'Q1 Compliance Sprint',
            'shortname' => 'q1-compliance',
            'description' => 'Complete 3 courses to win 500 points.',
            'targetcount' => 3,
            'pointsreward' => 500,
            'status' => challenge_engine::STATUS_ACTIVE,
        ]);

        $row = $DB->get_record('local_sentientia_challenge_challenges',
            ['id' => $id], '*', MUST_EXIST);
        $this->assertSame('Q1 Compliance Sprint', $row->name);
        $this->assertSame(3, (int) $row->targetcount);
        $this->assertSame(500, (int) $row->pointsreward);
        $this->assertSame((int) challenge_engine::STATUS_ACTIVE, (int) $row->status);
        // courseids defaults to JSON empty array.
        $this->assertSame([], json_decode($row->courseids, true));
    }

    public function test_validate_definition_rejects_empty_name(): void {
        $this->expectException(\invalid_parameter_exception::class);
        challenge_engine::validate_definition(['name' => '']);
    }

    public function test_validate_definition_rejects_invalid_type(): void {
        $this->expectException(\invalid_parameter_exception::class);
        challenge_engine::validate_definition(['name' => 'X', 'type' => 'bogus']);
    }

    public function test_validate_definition_rejects_target_zero(): void {
        $this->expectException(\invalid_parameter_exception::class);
        challenge_engine::validate_definition(['name' => 'X', 'targetcount' => 0]);
    }

    public function test_validate_definition_rejects_negative_points(): void {
        $this->expectException(\invalid_parameter_exception::class);
        challenge_engine::validate_definition(['name' => 'X', 'pointsreward' => -5]);
    }

    public function test_create_challenge_rejects_duplicate_shortname(): void {
        $this->resetAfterTest();
        $this->setAdminUser();
        challenge_engine::create_challenge(['name' => 'A', 'shortname' => 'sprint']);
        $this->expectException(\moodle_exception::class);
        challenge_engine::create_challenge(['name' => 'B', 'shortname' => 'sprint']);
    }

    public function test_join_blocked_when_challenge_not_active(): void {
        $this->resetAfterTest();
        $this->setAdminUser();
        $u = $this->getDataGenerator()->create_user();

        $cid = challenge_engine::create_challenge([
            'name' => 'Draft', 'shortname' => 'draft1',
            'status' => challenge_engine::STATUS_DRAFT,
        ]);
        $this->expectException(\moodle_exception::class);
        challenge_engine::join($cid, (int) $u->id);
    }

    public function test_join_blocked_outside_date_window(): void {
        $this->resetAfterTest();
        $this->setAdminUser();
        $u = $this->getDataGenerator()->create_user();

        $cid = challenge_engine::create_challenge([
            'name' => 'Future', 'shortname' => 'future1',
            'status' => challenge_engine::STATUS_ACTIVE,
            'startdate' => time() + 86400,  // tomorrow
        ]);
        $this->expectException(\moodle_exception::class);
        challenge_engine::join($cid, (int) $u->id);
    }

    public function test_join_creates_attempt_row(): void {
        global $DB;
        $this->resetAfterTest();
        $this->setAdminUser();
        $u = $this->getDataGenerator()->create_user();

        $cid = challenge_engine::create_challenge([
            'name' => 'C', 'shortname' => 'c1',
            'status' => challenge_engine::STATUS_ACTIVE,
            'targetcount' => 2,
            'pointsreward' => 200,
        ]);
        $aid = challenge_engine::join($cid, (int) $u->id);
        $this->assertGreaterThan(0, $aid);

        $attempt = $DB->get_record('local_sentientia_challenge_attempts',
            ['id' => $aid], '*', MUST_EXIST);
        $this->assertSame((int) $u->id, (int) $attempt->userid);
        $this->assertSame(2, (int) $attempt->targetcount,
            'targetcount snapshot must match challenge value at join time');
        $this->assertSame('enrolled', $attempt->status);
    }

    public function test_join_rejects_double_enrolment(): void {
        $this->resetAfterTest();
        $this->setAdminUser();
        $u = $this->getDataGenerator()->create_user();

        $cid = challenge_engine::create_challenge(['name' => 'C', 'shortname' => 'c2',
            'status' => challenge_engine::STATUS_ACTIVE]);
        challenge_engine::join($cid, (int) $u->id);
        $this->expectException(\moodle_exception::class);
        challenge_engine::join($cid, (int) $u->id);
    }

    public function test_join_evaluates_existing_completions_retroactively(): void {
        global $DB;
        $this->resetAfterTest();
        $this->setAdminUser();

        $u = $this->getDataGenerator()->create_user();
        // Mark 2 courses as completed BEFORE the challenge is even created.
        $this->complete_course_for_user((int) $u->id);
        $this->complete_course_for_user((int) $u->id);

        $cid = challenge_engine::create_challenge([
            'name' => 'Retro', 'shortname' => 'retro',
            'status' => challenge_engine::STATUS_ACTIVE,
            'targetcount' => 2,
            'pointsreward' => 100,
        ]);
        $aid = challenge_engine::join($cid, (int) $u->id);

        $attempt = $DB->get_record('local_sentientia_challenge_attempts',
            ['id' => $aid], '*', MUST_EXIST);
        $this->assertSame(2, (int) $attempt->progress,
            'join() must immediately evaluate existing completions');
        $this->assertSame('completed', $attempt->status);
        $this->assertSame(100, (int) $attempt->pointsawarded);
    }

    public function test_evaluate_attempt_marks_in_progress_below_target(): void {
        global $DB;
        $this->resetAfterTest();
        $this->setAdminUser();

        $u = $this->getDataGenerator()->create_user();
        $this->complete_course_for_user((int) $u->id);

        $cid = challenge_engine::create_challenge([
            'name' => 'P', 'shortname' => 'p1',
            'status' => challenge_engine::STATUS_ACTIVE,
            'targetcount' => 5,  // unreachable from 1 completion
        ]);
        $aid = challenge_engine::join($cid, (int) $u->id);

        $attempt = $DB->get_record('local_sentientia_challenge_attempts',
            ['id' => $aid], '*', MUST_EXIST);
        $this->assertSame(1, (int) $attempt->progress);
        $this->assertSame('in_progress', $attempt->status);
        $this->assertSame(0, (int) $attempt->pointsawarded);
    }

    public function test_courseids_filter_only_counts_listed_courses(): void {
        global $DB;
        $this->resetAfterTest();
        $this->setAdminUser();

        $u = $this->getDataGenerator()->create_user();
        $courseA = $this->complete_course_for_user((int) $u->id);
        $courseB = $this->complete_course_for_user((int) $u->id);
        // courseB is completed but only courseA is in the qualifying list.

        $cid = challenge_engine::create_challenge([
            'name' => 'Filtered', 'shortname' => 'filt',
            'status' => challenge_engine::STATUS_ACTIVE,
            'targetcount' => 1,
            'courseids' => [$courseA],
        ]);
        $aid = challenge_engine::join($cid, (int) $u->id);

        $attempt = $DB->get_record('local_sentientia_challenge_attempts',
            ['id' => $aid], '*', MUST_EXIST);
        $this->assertSame(1, (int) $attempt->progress,
            'completion of courseB must NOT count when courseids filter excludes it');
        $this->assertSame('completed', $attempt->status);
    }

    public function test_reevaluate_user_picks_up_new_completion(): void {
        global $DB;
        $this->resetAfterTest();
        $this->setAdminUser();

        $u = $this->getDataGenerator()->create_user();

        $cid = challenge_engine::create_challenge([
            'name' => 'Walk', 'shortname' => 'walk',
            'status' => challenge_engine::STATUS_ACTIVE,
            'targetcount' => 2,
        ]);
        $aid = challenge_engine::join($cid, (int) $u->id);
        $this->assertSame(0, (int) $DB->get_field(
            'local_sentientia_challenge_attempts', 'progress', ['id' => $aid]));

        // Simulate Moodle firing a course completion (e.g. user finished a course).
        $this->complete_course_for_user((int) $u->id);
        challenge_engine::reevaluate_user((int) $u->id);
        $this->assertSame('in_progress', $DB->get_field(
            'local_sentientia_challenge_attempts', 'status', ['id' => $aid]));

        // Second completion → cross the target.
        $this->complete_course_for_user((int) $u->id);
        challenge_engine::reevaluate_user((int) $u->id);
        $this->assertSame('completed', $DB->get_field(
            'local_sentientia_challenge_attempts', 'status', ['id' => $aid]));
    }

    public function test_completed_attempt_is_terminal_skips_reevaluation(): void {
        global $DB;
        $this->resetAfterTest();
        $this->setAdminUser();

        $u = $this->getDataGenerator()->create_user();
        $this->complete_course_for_user((int) $u->id);
        $this->complete_course_for_user((int) $u->id);

        $cid = challenge_engine::create_challenge([
            'name' => 'Done', 'shortname' => 'done',
            'status' => challenge_engine::STATUS_ACTIVE,
            'targetcount' => 2,
            'pointsreward' => 50,
        ]);
        $aid = challenge_engine::join($cid, (int) $u->id);

        // Attempt is now 'completed' with 50 points.
        // Simulate someone calling evaluate_attempt again — must be no-op.
        $beforeprogress = (int) $DB->get_field(
            'local_sentientia_challenge_attempts', 'progress', ['id' => $aid]);

        // Add a third completion.
        $this->complete_course_for_user((int) $u->id);
        challenge_engine::evaluate_attempt($aid);

        $after = $DB->get_record('local_sentientia_challenge_attempts',
            ['id' => $aid], '*', MUST_EXIST);
        $this->assertSame('completed', $after->status,
            'completed status is terminal — must not regress');
        $this->assertSame(50, (int) $after->pointsawarded,
            'points must NOT be doubled by a second evaluation');
    }

    public function test_leave_removes_attempt(): void {
        global $DB;
        $this->resetAfterTest();
        $this->setAdminUser();

        $u = $this->getDataGenerator()->create_user();
        $cid = challenge_engine::create_challenge(['name' => 'X', 'shortname' => 'x',
            'status' => challenge_engine::STATUS_ACTIVE]);
        challenge_engine::join($cid, (int) $u->id);
        $this->assertTrue($DB->record_exists('local_sentientia_challenge_attempts',
            ['challengeid' => $cid, 'userid' => $u->id]));

        challenge_engine::leave($cid, (int) $u->id);
        $this->assertFalse($DB->record_exists('local_sentientia_challenge_attempts',
            ['challengeid' => $cid, 'userid' => $u->id]));
    }

    public function test_leave_throws_when_not_joined(): void {
        $this->resetAfterTest();
        $this->setAdminUser();
        $u = $this->getDataGenerator()->create_user();
        $cid = challenge_engine::create_challenge(['name' => 'Y', 'shortname' => 'y',
            'status' => challenge_engine::STATUS_ACTIVE]);
        $this->expectException(\moodle_exception::class);
        challenge_engine::leave($cid, (int) $u->id);
    }

    public function test_delete_cascades_to_attempts_and_leaderboard(): void {
        global $DB;
        $this->resetAfterTest();
        $this->setAdminUser();

        $u = $this->getDataGenerator()->create_user();
        $cid = challenge_engine::create_challenge(['name' => 'Z', 'shortname' => 'z',
            'status' => challenge_engine::STATUS_ACTIVE]);
        challenge_engine::join($cid, (int) $u->id);
        $DB->insert_record('local_sentientia_challenge_leaderboard', (object) [
            'challengeid' => $cid, 'userid' => (int) $u->id,
            'costcenterid' => 0, 'points' => 100, 'userrank' => 1,
            'attemptscompleted' => 0, 'lastrecomputed' => time(),
        ]);

        challenge_engine::delete_challenge($cid);
        $this->assertFalse($DB->record_exists('local_sentientia_challenge_challenges', ['id' => $cid]));
        $this->assertFalse($DB->record_exists('local_sentientia_challenge_attempts', ['challengeid' => $cid]));
        $this->assertFalse($DB->record_exists('local_sentientia_challenge_leaderboard', ['challengeid' => $cid]));
    }

    public function test_list_challenges_status_filter(): void {
        $this->resetAfterTest();
        $this->setAdminUser();
        challenge_engine::create_challenge(['name' => 'A', 'shortname' => 'a',
            'status' => challenge_engine::STATUS_ACTIVE]);
        challenge_engine::create_challenge(['name' => 'B', 'shortname' => 'b',
            'status' => challenge_engine::STATUS_DRAFT]);
        challenge_engine::create_challenge(['name' => 'C', 'shortname' => 'c',
            'status' => challenge_engine::STATUS_ARCHIVED]);

        $active = challenge_engine::list_challenges(0, 'active', '', 0);
        $this->assertSame(1, $active['total']);
        $draft = challenge_engine::list_challenges(0, 'draft', '', 0);
        $this->assertSame(1, $draft['total']);
        $all = challenge_engine::list_challenges(0, 'all', '', 0);
        $this->assertGreaterThanOrEqual(3, $all['total']);
    }

    public function test_list_challenges_search(): void {
        $this->resetAfterTest();
        $this->setAdminUser();
        challenge_engine::create_challenge(['name' => 'Q1 Compliance', 'shortname' => 'q1c',
            'status' => challenge_engine::STATUS_ACTIVE]);
        challenge_engine::create_challenge(['name' => 'Onboarding', 'shortname' => 'onb',
            'status' => challenge_engine::STATUS_ACTIVE]);

        $r = challenge_engine::list_challenges(0, 'active', 'compliance', 0);
        $this->assertSame(1, $r['total']);
        $this->assertStringContainsString('Compliance', $r['rows'][0]['name']);
    }

    public function test_decode_courseids_parses_safely(): void {
        $this->assertSame([], challenge_engine::decode_courseids(''));
        $this->assertSame([], challenge_engine::decode_courseids('not json'));
        $this->assertSame([1, 2, 3], challenge_engine::decode_courseids('[1,2,3]'));
        // 0s and non-positive get filtered out.
        $this->assertSame([1, 2], challenge_engine::decode_courseids('[1,0,2]'));
    }
}
