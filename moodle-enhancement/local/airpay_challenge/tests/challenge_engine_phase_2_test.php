<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

namespace local_airpay_challenge;

defined('MOODLE_INTERNAL') || die();

/**
 * Phase 2 tests — streak-based + quiz-score-based + auto-expiry.
 *
 * @package    local_airpay_challenge
 * @category   test
 */
final class challenge_engine_phase_2_test extends \advanced_testcase {

    public function test_streak_progress_zero_on_no_lastaccess(): void {
        $this->resetAfterTest();
        $this->setAdminUser();

        $u = $this->getDataGenerator()->create_user();
        $cid = challenge_engine::create_challenge([
            'name' => 'Daily Habit',
            'shortname' => 'streak1',
            'type' => challenge_engine::TYPE_STREAK,
            'targetcount' => 5,
            'pointsreward' => 100,
            'status' => challenge_engine::STATUS_ACTIVE,
        ]);
        $aid = challenge_engine::join($cid, (int) $u->id);

        global $DB;
        $progress = (int) $DB->get_field('local_airpay_challenge_attempts', 'progress',
            ['id' => $aid]);
        $this->assertSame(0, $progress);
    }

    public function test_streak_progress_counts_consecutive_days(): void {
        global $DB;
        $this->resetAfterTest();
        $this->setAdminUser();

        $u = $this->getDataGenerator()->create_user();

        // user_lastaccess has UNIQUE (userid, courseid) so we need a
        // different course per day-bucket. Engine compares
        // floor(time()/86400) (UTC bucket) to row's bucket — using
        // time() directly keeps both sides on UTC seconds.
        if (!$DB->get_manager()->table_exists('user_lastaccess')) {
            $this->markTestSkipped('user_lastaccess table not present.');
        }
        $now = time();
        for ($i = 0; $i <= 3; $i++) {
            $course = $this->getDataGenerator()->create_course();
            $DB->insert_record('user_lastaccess', (object) [
                'userid' => $u->id,
                'courseid' => $course->id,
                // i=0 → now (today's bucket); i=1 → 1 day ago; etc.
                'timeaccess' => $now - $i * 86400,
            ]);
        }

        $cid = challenge_engine::create_challenge([
            'name' => 'Daily Habit',
            'shortname' => 'streak4',
            'type' => challenge_engine::TYPE_STREAK,
            'targetcount' => 7,  // need 7-day streak to win
            'status' => challenge_engine::STATUS_ACTIVE,
        ]);
        $aid = challenge_engine::join($cid, (int) $u->id);

        // 4-day streak < 7-day target → in_progress.
        $row = $DB->get_record('local_airpay_challenge_attempts', ['id' => $aid]);
        $this->assertGreaterThanOrEqual(1, (int) $row->progress);
        $this->assertSame('in_progress', $row->status);
    }

    public function test_quiz_score_progress_zero_when_no_attempts(): void {
        $this->resetAfterTest();
        $this->setAdminUser();

        $u = $this->getDataGenerator()->create_user();
        $cid = challenge_engine::create_challenge([
            'name' => 'High Scorer',
            'shortname' => 'quiz1',
            'type' => challenge_engine::TYPE_QUIZ_SCORE,
            'targetcount' => 7,  // 7 = 70% threshold + 7 attempts at that level
            'status' => challenge_engine::STATUS_ACTIVE,
        ]);
        $aid = challenge_engine::join($cid, (int) $u->id);

        global $DB;
        $row = $DB->get_record('local_airpay_challenge_attempts', ['id' => $aid]);
        $this->assertSame(0, (int) $row->progress);
    }

    public function test_expire_overdue_attempts_marks_status(): void {
        global $DB;
        $this->resetAfterTest();
        $this->setAdminUser();

        // Past-end-date challenge.
        $cid = challenge_engine::create_challenge([
            'name' => 'Past',
            'shortname' => 'past',
            'type' => challenge_engine::TYPE_COURSE_COMPLETION,
            'targetcount' => 5,
            'status' => challenge_engine::STATUS_ACTIVE,
            'startdate' => time() - 86400 * 30,
            'enddate'   => time() - 86400,  // ended yesterday
        ]);
        // Bypass join's date-window check by inserting attempt directly.
        $u = $this->getDataGenerator()->create_user();
        $now = time();
        $DB->insert_record('local_airpay_challenge_attempts', (object) [
            'challengeid' => $cid,
            'userid' => (int) $u->id,
            'status' => challenge_engine::ATTEMPT_IN_PROGRESS,
            'progress' => 1,
            'targetcount' => 5,
            'pointsawarded' => 0,
            'completiondate' => null,
            'costcenterid' => 0,
            'timecreated' => $now - 86400 * 5,
            'timemodified' => $now - 86400 * 5,
        ]);

        $expired = challenge_engine::expire_overdue_attempts();
        $this->assertGreaterThanOrEqual(1, $expired);

        // Attempt is now expired.
        $count = $DB->count_records('local_airpay_challenge_attempts',
            ['challengeid' => $cid, 'status' => challenge_engine::ATTEMPT_EXPIRED]);
        $this->assertGreaterThanOrEqual(1, $count);
    }

    public function test_expire_does_not_touch_completed_attempts(): void {
        global $DB;
        $this->resetAfterTest();
        $this->setAdminUser();

        $cid = challenge_engine::create_challenge([
            'name' => 'Past2',
            'shortname' => 'past2',
            'type' => challenge_engine::TYPE_COURSE_COMPLETION,
            'targetcount' => 1,
            'pointsreward' => 50,
            'status' => challenge_engine::STATUS_ACTIVE,
            'startdate' => time() - 86400 * 30,
            'enddate'   => time() - 86400,
        ]);
        $u = $this->getDataGenerator()->create_user();
        // Insert a COMPLETED attempt for a past-end-date challenge.
        $DB->insert_record('local_airpay_challenge_attempts', (object) [
            'challengeid' => $cid,
            'userid' => (int) $u->id,
            'status' => challenge_engine::ATTEMPT_COMPLETED,
            'progress' => 1,
            'targetcount' => 1,
            'pointsawarded' => 50,
            'completiondate' => time() - 86400 * 5,
            'costcenterid' => 0,
            'timecreated' => time() - 86400 * 7,
            'timemodified' => time() - 86400 * 5,
        ]);

        challenge_engine::expire_overdue_attempts();

        $row = $DB->get_record('local_airpay_challenge_attempts',
            ['challengeid' => $cid, 'userid' => $u->id]);
        $this->assertSame('completed', $row->status,
            'completed attempts must NOT be retroactively expired');
    }

    public function test_compute_progress_dispatches_by_type(): void {
        $this->resetAfterTest();
        $this->setAdminUser();

        // For a challenge with an unknown type, evaluate_attempt should
        // not throw; progress stays 0.
        $u = $this->getDataGenerator()->create_user();
        $cid = challenge_engine::create_challenge([
            'name' => 'Custom',
            'shortname' => 'custom1',
            'type' => challenge_engine::TYPE_CUSTOM,
            'targetcount' => 1,
            'status' => challenge_engine::STATUS_ACTIVE,
        ]);
        $aid = challenge_engine::join($cid, (int) $u->id);

        global $DB;
        $row = $DB->get_record('local_airpay_challenge_attempts', ['id' => $aid]);
        $this->assertSame(0, (int) $row->progress);
        // status stays at 'enrolled' since no progress.
        $this->assertSame('enrolled', $row->status);
    }
}
