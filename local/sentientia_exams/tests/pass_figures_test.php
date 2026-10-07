<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

namespace local_sentientia_exams;

defined('MOODLE_INTERNAL') || die();

/**
 * Doc item "exams pass figures" (owner decisions, 2026-10-07): the pass rate and the failures of the analytics tab count
 * LEARNERS on both sides.
 *
 * view.php divided the learners who passed by the number of attempts, and subtracted learners from attempts for the failures:
 * a learner who needed three tries was three on one side and one on the other. exam_manager::pass_figures() returns the
 * finished attempts (what "Total Attempts" says), the distinct learners behind them, the learners who passed, those who did
 * not, and the percentage of learners who passed.
 *
 * @package    local_sentientia_exams
 * @category   test
 * @covers     \local_sentientia_exams\exam_manager::pass_figures
 * @group      local_sentientia_exams
 */
final class pass_figures_test extends \advanced_testcase {

    /** @var int Timestamp base of the seed. */
    private const T0 = 1700000000;

    /** @var int Unique attempt ids. */
    private int $uniqueid = 0;

    /**
     * A quiz that offers ten marks.
     *
     * @return int
     */
    private function quiz(): int {
        global $DB;
        $course = $this->getDataGenerator()->create_course();
        $quiz = $this->getDataGenerator()->create_module('quiz', ['course' => $course->id]);
        $DB->set_field('quiz', 'sumgrades', 10, ['id' => $quiz->id]);
        return (int) $quiz->id;
    }

    private function learner(): int {
        return (int) $this->getDataGenerator()->create_user()->id;
    }

    /**
     * @param int $quizid
     * @param int $userid
     * @param int $attempt The learner's n-th attempt.
     * @param string $state
     * @param float|null $sumgrades
     * @return void
     */
    private function attempt(int $quizid, int $userid, int $attempt, string $state, ?float $sumgrades): void {
        global $DB;
        $DB->insert_record('quiz_attempts', (object) [
            'quiz' => $quizid, 'userid' => $userid, 'attempt' => $attempt, 'uniqueid' => 910000 + (++$this->uniqueid),
            'layout' => '', 'currentpage' => 0, 'preview' => 0, 'state' => $state,
            'timestart' => self::T0 + 100, 'timefinish' => $state === 'finished' ? self::T0 + 200 : 0,
            'timemodified' => self::T0 + 200, 'timemodifiedoffline' => 0, 'timecheckstate' => null,
            'sumgrades' => $sumgrades, 'gradednotificationsenttime' => null,
        ]);
    }

    public function test_a_learner_with_several_attempts_is_one_learner_on_both_sides(): void {
        $this->resetAfterTest();
        $quiz = $this->quiz();
        $a = $this->learner();
        $b = $this->learner();
        $c = $this->learner();
        $d = $this->learner();
        // A tries three times and passes on the last (80 percent), B fails once, C has not finished, D is not graded yet.
        $this->attempt($quiz, $a, 1, 'finished', 2.0);
        $this->attempt($quiz, $a, 2, 'finished', 4.0);
        $this->attempt($quiz, $a, 3, 'finished', 8.0);
        $this->attempt($quiz, $b, 1, 'finished', 3.0);
        $this->attempt($quiz, $c, 1, 'inprogress', 10.0);
        $this->attempt($quiz, $d, 1, 'finished', null);

        $f = exam_manager::pass_figures($quiz, 50.0);

        $this->assertSame(5, $f['attempts'], 'finished attempts: A three times, B once, D once');
        $this->assertSame(3, $f['learners'], 'A, B and D; C has not finished');
        $this->assertSame(1, $f['passed'], 'A passed once');
        $this->assertSame(2, $f['failed'], 'B and D: learners, not attempts (the old figure said 4)');
        $this->assertEquals(33.3, $f['pass_pct'], 'one learner in three (the old figure said 20 percent)');
    }

    public function test_passed_plus_failed_is_always_the_learners(): void {
        $this->resetAfterTest();
        $quiz = $this->quiz();
        foreach ([9.0, 6.0, 5.0, 1.0] as $score) {
            $this->attempt($quiz, $this->learner(), 1, 'finished', $score);
        }
        foreach ([0.0, 50.0, 60.0, 90.0, 100.0] as $threshold) {
            $f = exam_manager::pass_figures($quiz, $threshold);
            $this->assertSame($f['learners'], $f['passed'] + $f['failed'], "threshold {$threshold}");
            $this->assertSame(4, $f['learners']);
            $this->assertSame(4, $f['attempts']);
        }
        $this->assertSame(3, exam_manager::pass_figures($quiz, 50.0)['passed'], '90, 60 and 50 percent reach 50');
        $this->assertEquals(75.0, exam_manager::pass_figures($quiz, 50.0)['pass_pct']);
    }

    public function test_a_quiz_nobody_finished_has_no_figures_and_no_division(): void {
        $this->resetAfterTest();
        $quiz = $this->quiz();
        $this->attempt($quiz, $this->learner(), 1, 'inprogress', null);

        $this->assertSame(
            ['attempts' => 0, 'learners' => 0, 'passed' => 0, 'failed' => 0, 'pass_pct' => 0],
            exam_manager::pass_figures($quiz, 50.0));
    }

    public function test_the_tenant_condition_applies_to_every_figure(): void {
        $this->resetAfterTest();
        $quiz = $this->quiz();
        $this->attempt($quiz, $this->learner(), 1, 'finished', 9.0);

        $f = exam_manager::pass_figures($quiz, 50.0, '1 = 0');

        $this->assertSame(0, $f['attempts']);
        $this->assertSame(0, $f['learners']);
        $this->assertSame(0, $f['passed']);
    }

    public function test_another_quizs_attempts_are_not_counted(): void {
        $this->resetAfterTest();
        $quiz = $this->quiz();
        $other = $this->quiz();
        $this->attempt($other, $this->learner(), 1, 'finished', 9.0);

        $this->assertSame(0, exam_manager::pass_figures($quiz, 50.0)['learners']);
        $this->assertSame(1, exam_manager::pass_figures($other, 50.0)['learners']);
    }
}
