<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

namespace local_sentientia_recompletion;

defined('MOODLE_INTERNAL') || die();

/**
 * One learner whose reset fails does not stop a batch (owner decision, 2026-10-07; LRN-05).
 *
 * reset_user_in_course() ended with `$tx->rollback($e); return false;`. rollback() rethrows, so the `return false`
 * never ran and one failing learner ended a rule's cron batch or a bulk reset part-way through. The contract is now
 * the one Moodle's delegated transactions have: the function returns true or throws (it never swallows a rollback),
 * and its callers catch per learner, count `failed`, log the rule and course ids only and carry on. The archive
 * copy and the deletes are one transaction, so a failed learner keeps their completion, and no archive row or
 * history row is left for a reset that did not happen.
 *
 * A database cannot be made to fail on cue with valid data, so the failure is injected through
 * evidence_archiver::inject_failure(), which throws AFTER the learner's rows have been copied (so the rollback has
 * something to undo).
 *
 * @package    local_sentientia_recompletion
 * @category   test
 * @copyright  2026 Airpay Payment Services
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \local_sentientia_recompletion\recompletion_engine
 * @covers     \local_sentientia_recompletion\evidence_archiver
 *
 * @group local_sentientia_recompletion
 * @group bizlms_import
 */
final class engine_failure_test extends \advanced_testcase {

    /** @var \stdClass[] Three learners, in the order the engine meets them. */
    private array $learners = [];
    /** @var int Course id. */
    private int $courseid;
    /** @var int A page's course module, whose completion row each learner has. */
    private int $cmid;
    /** @var \phpunit_message_sink What the engine sent. */
    private $sink;

    protected function setUp(): void {
        global $DB;
        parent::setUp();
        $this->resetAfterTest();
        // The reset opens its own transaction; a rollback inside a test that is itself in a transaction would mark
        // that one for rollback too, so the test commits its data and is reset by truncation instead.
        $this->preventResetByRollback();
        $this->setAdminUser();

        $g = $this->getDataGenerator();
        $course = $g->create_course(['enablecompletion' => 1, 'fullname' => 'Annual AML']);
        $this->courseid = (int) $course->id;
        $this->cmid = (int) $g->create_module('page', ['course' => $course->id])->cmid;
        $old = time() - 400 * DAYSECS;
        for ($i = 0; $i < 3; $i++) {
            $user = $g->create_user(['lang' => 'en']);
            $this->learners[] = $user;
            $DB->insert_record('course_completions', (object) ['userid' => $user->id, 'course' => $this->courseid,
                'timeenrolled' => $old - DAYSECS, 'timestarted' => $old - HOURSECS, 'timecompleted' => $old, 'reaggregate' => 0]);
            $DB->insert_record('course_modules_completion', (object) ['coursemoduleid' => $this->cmid, 'userid' => $user->id,
                'completionstate' => 1, 'viewed' => 1, 'overrideby' => null, 'timemodified' => $old]);
        }
        $this->sink = $this->redirectMessages();
    }

    protected function tearDown(): void {
        evidence_archiver::inject_failure(null);
        parent::tearDown();
    }

    /**
     * Make the copy fail for one of the learners.
     *
     * @param int $index Which learner (0, 1 or 2).
     * @return void
     */
    private function fail_for(int $index): void {
        $victim = (int) $this->learners[$index]->id;
        evidence_archiver::inject_failure(static function (int $userid, int $courseid) use ($victim): void {
            if ($userid === $victim) {
                throw new \RuntimeException('forced archive failure');
            }
        });
    }

    /**
     * @return \stdClass An enabled rule over the course that the seeded completions have outlived.
     */
    private function rule(): \stdClass {
        global $DB;
        $now = time();
        $id = (int) $DB->insert_record('local_sentientia_recompletion_rules', (object) [
            'name' => 'Annual', 'courseid' => $this->courseid, 'period_days' => 365, 'trigger_type' => 'completion',
            'reset_grades' => 0, 'reset_attempts' => 0, 'enabled' => 1, 'costcenterid' => 0,
            'timecreated' => $now, 'timemodified' => $now,
        ]);
        return $DB->get_record('local_sentientia_recompletion_rules', ['id' => $id], '*', MUST_EXIST);
    }

    /**
     * What a learner has left: [completion rows, activity completion rows, archive rows, history rows].
     *
     * @param \stdClass $user
     * @return int[]
     */
    private function footprint(\stdClass $user): array {
        global $DB;
        return [
            $DB->count_records('course_completions', ['userid' => $user->id, 'course' => $this->courseid]),
            $DB->count_records('course_modules_completion', ['userid' => $user->id, 'coursemoduleid' => $this->cmid]),
            $DB->count_records('local_sentientia_recompletion_archive', ['userid' => $user->id]),
            $DB->count_records('local_sentientia_recompletion_history', ['userid' => $user->id]),
        ];
    }

    public function test_a_failed_reset_throws_and_leaves_the_learner_exactly_as_they_were(): void {
        $victim = $this->learners[1];
        $this->fail_for(1);

        try {
            recompletion_engine::reset_user_in_course((int) $victim->id, $this->courseid, false, false);
            $this->fail('a reset that did not commit must throw, not return false');
        } catch (\RuntimeException $e) {
            $this->assertSame('forced archive failure', $e->getMessage(), 'the cause reaches the caller');
        }

        $this->assertSame([1, 1, 0, 0], $this->footprint($victim),
            'the copy was rolled back with the deletes: no completion lost, no archive row left over');

        // The same learner resets cleanly once the failure is gone: nothing was left half done.
        evidence_archiver::inject_failure(null);
        $this->assertTrue(recompletion_engine::reset_user_in_course((int) $victim->id, $this->courseid, false, false));
        [$completions, $activities, $archive] = $this->footprint($victim);
        $this->assertSame([0, 0], [$completions, $activities]);
        $this->assertGreaterThan(0, $archive);
    }

    public function test_a_rule_carries_on_past_a_learner_whose_reset_fails(): void {
        $this->fail_for(1);
        $rule = $this->rule();

        $result = recompletion_engine::run_rule($rule, false);

        $this->assertDebuggingCalled(null, DEBUG_DEVELOPER);
        $this->assertSame(2, $result['reset'], 'learners 1 and 3 are reset');
        $this->assertSame(1, $result['failed']);
        $this->assertSame(0, $result['skipped']);

        [$first, $second, $third] = $this->learners;
        $this->assertSame([0, 0], array_slice($this->footprint($first), 0, 2));
        $this->assertSame([0, 0], array_slice($this->footprint($third), 0, 2));
        $this->assertSame([1, 1, 0, 0], $this->footprint($second), 'learner 2 keeps their completion');
        $this->assertSame(1, $this->footprint($first)[3], 'a history row for the reset that happened');
        $this->assertSame(1, $this->footprint($third)[3]);
        $this->assertGreaterThan(0, $this->footprint($first)[2]);
        $this->assertGreaterThan(0, $this->footprint($third)[2]);

        // The two learners who were reset are told; the one whose reset was rolled back is not told of a reset
        // that did not happen.
        $told = array_map(static fn(\stdClass $m): int => (int) $m->useridto, $this->sink->get_messages());
        $expected = [(int) $first->id, (int) $third->id];
        sort($told);
        sort($expected);
        $this->assertSame($expected, $told);
    }

    public function test_the_log_names_the_rule_and_the_course_never_the_person(): void {
        global $DB;
        $this->fail_for(1);
        $rule = $this->rule();

        recompletion_engine::run_rule($rule, false);

        $messages = $this->getDebuggingMessages();
        $this->assertCount(1, $messages);
        $this->assertStringContainsString('rule ' . $rule->id, $messages[0]->message);
        $this->assertStringContainsString('course ' . $this->courseid, $messages[0]->message);
        $this->assertStringNotContainsString((string) $this->learners[1]->email, $messages[0]->message);
        $this->assertStringNotContainsString($this->learners[1]->lastname, $messages[0]->message);
        $this->assertStringNotContainsString('forced archive failure', $messages[0]->message,
            'a database error message carries the row it failed on, so only the kind of failure is logged');
        $this->resetDebugging();
        $this->assertSame(1, $DB->count_records('course_completions', ['userid' => $this->learners[1]->id]));
    }

    public function test_run_all_adds_the_failures_to_its_totals_and_still_stamps_the_rule(): void {
        global $DB;
        $this->fail_for(1);
        $rule = $this->rule();

        $totals = recompletion_engine::run_all(false);

        $this->assertDebuggingCalled(null, DEBUG_DEVELOPER);
        $this->assertSame(1, $totals['rules_run'], 'the rule ran: one bad learner is not a failed rule');
        $this->assertSame(2, $totals['reset']);
        $this->assertSame(1, $totals['failed']);
        $this->assertSame(0, $totals['errors']);
        $stored = $DB->get_record('local_sentientia_recompletion_rules', ['id' => $rule->id], '*', MUST_EXIST);
        $this->assertNotNull($stored->last_run_at, 'the pass is recorded');
        $this->assertEquals(2, $stored->last_run_resets);
    }

    public function test_a_bulk_reset_carries_on_past_a_learner_whose_reset_fails(): void {
        $this->fail_for(1);
        $ids = array_map(static fn(\stdClass $u): int => (int) $u->id, $this->learners);

        $result = recompletion_engine::bulk_reset($this->courseid, $ids, (int) get_admin()->id, 'bulk', false, false);

        $this->assertDebuggingCalled(null, DEBUG_DEVELOPER);
        $this->assertSame(['reset' => 2, 'failed' => 1], $result);
        $this->assertSame([1, 1, 0, 0], $this->footprint($this->learners[1]));
        $this->assertSame(1, $this->footprint($this->learners[0])[3]);
        $this->assertSame(1, $this->footprint($this->learners[2])[3]);
    }

    public function test_a_dry_run_resets_nothing_and_fails_nothing(): void {
        $this->fail_for(1);
        $result = recompletion_engine::run_rule($this->rule(), true);

        $this->assertSame(0, $result['failed'], 'a dry run copies and deletes nothing, so nothing can fail');
        $this->assertSame([1, 1, 0], array_slice($this->footprint($this->learners[1]), 0, 3));
    }
}
