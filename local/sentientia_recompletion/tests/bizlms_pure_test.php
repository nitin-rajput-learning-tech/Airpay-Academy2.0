<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

namespace local_sentientia_recompletion;

defined('MOODLE_INTERNAL') || die();

use local_sentientia_recompletion\bizlms\evidence;
use local_sentientia_recompletion\bizlms\mapper;
use local_sentientia_recompletion\bizlms\pairing;

/**
 * The value rules of the BizLMS recompletion import, one at a time, with no database (ADR-032, mapping doc
 * section 12). The import test (bizlms_import_test) proves the whole run; this proves each rule it is built from,
 * including the edge that a whole run would hide.
 *
 * @package    local_sentientia_recompletion
 * @category   test
 * @covers     \local_sentientia_recompletion\bizlms\mapper
 * @covers     \local_sentientia_recompletion\bizlms\pairing
 * @covers     \local_sentientia_recompletion\archive_privacy
 * @covers     \local_sentientia_recompletion\legacy_summary
 *
 * @group local_sentientia_recompletion
 * @group bizlms_import
 */
final class bizlms_pure_test extends \basic_testcase {

    public function test_period_days_rounds_up_to_whole_days_and_refuses_what_is_not_a_duration(): void {
        $this->assertSame(365, mapper::period_days(31536000));
        $this->assertSame(365, mapper::period_days('31536000'));
        $this->assertSame(1, mapper::period_days(1), 'less than a day is one day, not zero');
        $this->assertSame(1, mapper::period_days(86400));
        $this->assertSame(2, mapper::period_days(86401));
        $this->assertSame(1, mapper::period_days('1e3'));
        foreach ([0, '0', '', '  ', 'abc', '-5', -86400, null, true, [], '1e30'] as $bad) {
            $this->assertNull(mapper::period_days($bad), 'not a usable duration: ' . json_encode($bad));
        }
    }

    public function test_only_the_value_one_is_on(): void {
        $this->assertSame(1, mapper::switch_on('1'));
        $this->assertSame(1, mapper::switch_on(' 1 '));
        $this->assertSame(1, mapper::switch_on(1));
        foreach (['0', '2', '', 'yes', null, false, []] as $off) {
            $this->assertSame(0, mapper::switch_on($off), 'off: ' . json_encode($off));
        }
    }

    public function test_a_grade_that_does_not_fit_number_10_5_is_null_not_clamped(): void {
        $this->assertSame(83.5, mapper::bounded_number('83.5'));
        $this->assertSame(99999.99999, mapper::bounded_number('99999.99999'));
        $this->assertSame(12.34568, mapper::bounded_number('12.3456789'));
        $this->assertSame(-5.0, mapper::bounded_number('-5'));
        foreach (['100000', '-100000', '1e9', 'abc', '', null, true, []] as $bad) {
            $this->assertNull(mapper::bounded_number($bad), 'does not fit: ' . json_encode($bad));
        }
    }

    public function test_a_timestamp_is_a_positive_int_10(): void {
        $this->assertSame(1700000000, mapper::timestamp('1700000000'));
        $this->assertSame(1, mapper::timestamp(1));
        foreach (['0', 0, '-5', '3000000000', 'abc', '', null, '1.5', true] as $bad) {
            $this->assertNull(mapper::timestamp($bad), 'not a timestamp: ' . json_encode($bad));
        }
        $this->assertSame(5, mapper::first_timestamp([0, null, '5', 7]));
        $this->assertNull(mapper::first_timestamp([0, null, '']));
    }

    public function test_activity_state_names(): void {
        $this->assertSame('incomplete', mapper::activity_state('0'));
        $this->assertSame('complete', mapper::activity_state(1));
        $this->assertSame('complete_pass', mapper::activity_state('2'));
        $this->assertSame('complete_fail', mapper::activity_state(3));
        $this->assertSame('unknown', mapper::activity_state(9));
        $this->assertSame('unknown', mapper::activity_state(null));
    }

    public function test_who_made_a_reset(): void {
        $this->assertSame(['cron', null, false], mapper::reset_source('cli', 99, 5), 'the scheduled task');
        $this->assertSame(['cron', null, false], mapper::reset_source(' CLI ', 0, 5));
        $this->assertSame(['manual', 5, false], mapper::reset_source('web', 5, 5), 'the learner reset their own');
        // A web reset by somebody else is either the reset page or an administrator running the cron from a
        // browser, and the log row cannot tell which: nobody is named.
        $this->assertSame(['legacy', null, true], mapper::reset_source('web', 99, 5));
        $this->assertSame(['legacy', null, true], mapper::reset_source('web', 0, 5));
        $this->assertSame(['legacy', null, true], mapper::reset_source('', 5, 5));
        $this->assertSame(['legacy', null, true], mapper::reset_source('ws', 5, 5));
    }

    public function test_the_inferred_reset_time_is_never_later_than_now_or_earlier_than_the_completion(): void {
        $this->assertSame(150, mapper::inferred_time(100, 50, null, 1000), 'completion plus the duration');
        $this->assertSame(120, mapper::inferred_time(100, 50, 120, 1000), 'capped at the next cycle\'s first evidence');
        $this->assertSame(100, mapper::inferred_time(100, 50, 90, 1000), 'never before the completion');
        $this->assertSame(150, mapper::inferred_time(100, 50, 0, 1000), 'no usable next evidence');
        $this->assertSame(150, mapper::inferred_time(100, 50, null, 150), 'a time AT the import is not after it');
        $this->assertSame(1000, mapper::inferred_time(2000, 50, null, 1000), 'a completion in the future is clamped');
    }

    /**
     * Owner decision recompletion.inferred_reset_without_evidence: the import time is an upper clamp, never the
     * value. A reset dated at cutover would claim the completion stood until then and would give a different
     * answer on every run, while the archived row proves the reset happened inside BizLMS.
     */
    public function test_the_import_time_is_never_the_inferred_reset_time(): void {
        // The completion plus the duration lies after the import: one second after the completion, not the import.
        $this->assertSame(101, mapper::inferred_time(100, 50, null, 110), 'no latest evidence: after the completion');
        $this->assertSame(106, mapper::inferred_time(100, 50, null, 110, 105), 'one second after the latest evidence');
        $this->assertSame(110, mapper::inferred_time(100, 50, null, 110, 500), 'the import is only the upper clamp');
        // No duration, no next cycle: the same.
        $this->assertSame(401, mapper::inferred_time(100, 0, null, 1000, 400));
        $this->assertSame(101, mapper::inferred_time(100, 0, null, 1000), 'with no evidence at all: after the completion');
        // A cycle never completed.
        $this->assertSame(701, mapper::inferred_time(null, 50, null, 1000, 700), 'never completed: its last evidence + 1');
        $this->assertSame(1, mapper::inferred_time(null, 50, null, 1000),
            'no completion and no evidence at all: the earliest second, which the caller lifts to the cycle before');
        // The completion is evidence too, so evidence that is older than it never pulls the reset before it.
        $this->assertSame(501, mapper::inferred_time(500, 0, null, 1000, 400));
        $this->assertSame(501, mapper::inferred_from_evidence(500, 400, 1000));
        // A candidate time that IS usable wins over the evidence.
        $this->assertSame(150, mapper::inferred_time(100, 50, null, 1000, 900));
        $this->assertNull(mapper::inferred_candidate(100, 50, null, 110), 'after the import: no candidate');
        $this->assertNull(mapper::inferred_candidate(null, 50, null, 1000), 'nothing to count from');
        $this->assertSame(120, mapper::inferred_candidate(100, 50, 120, 1000));
    }

    /**
     * Review of 2026-10-07: when a cycle's completion or latest evidence is at or after the import, the import time is the
     * only value left, which the owner decision says it never is; the importer must be able to tell, to report the row.
     */
    public function test_a_reset_that_can_only_be_dated_at_the_import_is_told_apart(): void {
        // The value is the import time exactly when there is no second after the evidence.
        $this->assertSame(1000, mapper::inferred_from_evidence(2000, null, 1000), 'a completion in the future');
        $this->assertTrue(mapper::dated_at_import(2000, null, 1000));
        $this->assertSame(1000, mapper::inferred_from_evidence(null, 1500, 1000), 'evidence after the import');
        $this->assertTrue(mapper::dated_at_import(null, 1500, 1000));
        $this->assertSame(1000, mapper::inferred_from_evidence(500, 999, 1000), 'evidence one second before the import');
        $this->assertTrue(mapper::dated_at_import(500, 999, 1000), 'the second after it IS the import time');
        $this->assertTrue(mapper::dated_at_import(1000, null, 1000), 'at the import');

        // Every ordinary estimate stays unflagged.
        $this->assertFalse(mapper::dated_at_import(500, 400, 1000));
        $this->assertFalse(mapper::dated_at_import(null, 998, 1000), 'the second after it is before the import');
        $this->assertFalse(mapper::dated_at_import(null, null, 1000), 'no evidence at all: second 1');
        $this->assertSame(1, mapper::inferred_from_evidence(null, null, 1000));

        // It always agrees with the value inferred_from_evidence() returns.
        foreach ([[2000, null], [null, 1500], [500, 999], [500, 400], [null, 998], [null, null], [1000, 1000]] as [$completed, $evidence]) {
            $this->assertSame(mapper::inferred_from_evidence($completed, $evidence, 1000) === 1000,
                mapper::dated_at_import($completed, $evidence, 1000),
                'completed=' . var_export($completed, true) . ' evidence=' . var_export($evidence, true));
        }
    }

    public function test_scorm_elements(): void {
        foreach (['cmi.core.lesson_status', 'cmi.completion_status', 'cmi.success_status', 'lesson_status'] as $e) {
            $this->assertTrue(mapper::is_scorm_status($e), $e);
        }
        foreach (['cmi.core.score.raw', 'cmi.score.max', 'cmi.core.exit', 'xlesson_status'] as $e) {
            $this->assertFalse(mapper::is_scorm_status($e), $e);
        }
        $this->assertTrue(mapper::is_scorm_score('cmi.core.score.raw'));
        $this->assertTrue(mapper::is_scorm_score('cmi.score.raw'));
        $this->assertFalse(mapper::is_scorm_score('cmi.score.max'));
    }

    public function test_scorm_elements_that_carry_what_the_learner_typed(): void {
        foreach (['cmi.suspend_data', 'cmi.comments', 'cmi.comments_from_learner.0.comment',
                'cmi.interactions.3.learner_response', 'cmi.interactions.3.student_response', 'cmi.core.student_name',
                'cmi.learner_name', 'suspend_data'] as $e) {
            $this->assertTrue(mapper::is_scorm_free_text($e), $e);
        }
        foreach (['cmi.core.lesson_status', 'cmi.core.score.raw', 'cmi.completion_status', 'cmi.comments_from_lms',
                'cmi.core.session_time', 'xsuspend_data'] as $e) {
            $this->assertFalse(mapper::is_scorm_free_text($e), $e);
        }
    }

    public function test_the_payload_keeps_every_column_exactly(): void {
        $row = (object) ['id' => '5', 'userid' => '12', 'element' => 'cmi.suspend_data', 'value' => "caf\u{e9} / \"x\" \u{0939}",
            'timemodified' => '1700000000', 'note' => null];
        $json = mapper::payload($row);
        $this->assertSame(json_decode(json_encode($row), true), json_decode($json, true));
        $this->assertStringContainsString('/', $json, 'slashes are not escaped');
        $this->assertStringContainsString("\u{939}", $json, 'unicode is not escaped');
        $this->assertSame('12:34', mapper::pair_key(12, 34));
    }

    // Which reset ended which archived cycle.

    public function test_each_archived_completion_takes_the_earliest_unmatched_reset_after_it(): void {
        $completions = [
            ['id' => 1, 'completed' => 100, 'started' => 50, 'enrolled' => 10],
            ['id' => 2, 'completed' => 500, 'started' => 410, 'enrolled' => 10],
        ];
        $resets = [['id' => 71, 'time' => 400], ['id' => 72, 'time' => 900]];
        $pair = pairing::pair($completions, $resets);
        $this->assertSame([1 => 71, 2 => 72], $pair['cc']);
        $this->assertSame([71 => 1, 72 => 2], $pair['event']);
    }

    public function test_a_purged_reset_leaves_its_cycle_without_one_instead_of_taking_the_next(): void {
        $completions = [
            ['id' => 1, 'completed' => 100, 'started' => 50, 'enrolled' => 10],
            ['id' => 2, 'completed' => 500, 'started' => 410, 'enrolled' => 10],
        ];
        // The first reset (400) is gone from the log. The one at 900 cannot have ended cycle 1: cycle 2 began at 410.
        $pair = pairing::pair($completions, [['id' => 72, 'time' => 900]]);
        $this->assertSame([1 => null, 2 => 72], $pair['cc']);
        $this->assertSame([72 => 2], $pair['event']);
    }

    public function test_a_cycle_that_was_never_completed_runs_from_its_start(): void {
        $pair = pairing::pair([['id' => 1, 'completed' => 0, 'started' => 20, 'enrolled' => 10]],
            [['id' => 71, 'time' => 30]]);
        $this->assertSame([1 => 71], $pair['cc']);
        $pair = pairing::pair([['id' => 1, 'completed' => 0, 'started' => 0, 'enrolled' => 10]],
            [['id' => 71, 'time' => 12]]);
        $this->assertSame([1 => 71], $pair['cc'], 'with no start the enrolment is what it ran from');
    }

    public function test_a_reset_before_the_cycle_began_ends_nothing(): void {
        $pair = pairing::pair([['id' => 1, 'completed' => 100, 'started' => 50, 'enrolled' => 10]],
            [['id' => 71, 'time' => 99]]);
        $this->assertSame([1 => null], $pair['cc']);
        $this->assertSame([71 => null], $pair['event']);
        $pair = pairing::pair([['id' => 1, 'completed' => 100, 'started' => 50, 'enrolled' => 10]],
            [['id' => 71, 'time' => 100]]);
        $this->assertSame([1 => 71], $pair['cc'], 'a reset in the very second of the completion still ends it');
    }

    public function test_one_reset_ends_one_cycle(): void {
        $completions = [
            ['id' => 1, 'completed' => 100, 'started' => 50, 'enrolled' => 10],
            ['id' => 2, 'completed' => 500, 'started' => 410, 'enrolled' => 10],
        ];
        $pair = pairing::pair($completions, [['id' => 71, 'time' => 300]]);
        $this->assertSame([1 => 71, 2 => null], $pair['cc']);
    }

    public function test_the_pairing_does_not_depend_on_the_order_the_lists_arrive_in(): void {
        $completions = [
            ['id' => 2, 'completed' => 500, 'started' => 410, 'enrolled' => 10],
            ['id' => 1, 'completed' => 100, 'started' => 50, 'enrolled' => 10],
        ];
        $resets = [['id' => 72, 'time' => 900], ['id' => 71, 'time' => 400]];
        $pair = pairing::pair($completions, $resets);
        $this->assertSame(71, $pair['cc'][1]);
        $this->assertSame(72, $pair['cc'][2]);
        $this->assertSame(['cc' => [], 'event' => [71 => null]], pairing::pair([], [['id' => 71, 'time' => 5]]));
    }

    public function test_a_later_cycle_that_was_never_started_is_paired_with_its_own_reset(): void {
        // Core recreates the completion row after a reset with the ORIGINAL enrolment date and timestarted 0, so
        // the second cycle "ran from" 10, before the first one completed (100). By its dates it sorted first and
        // was left with no reset; the first cycle's reset is the earlier of the two and it is the first cycle's.
        $completions = [
            ['id' => 1, 'completed' => 100, 'started' => 50, 'enrolled' => 10],
            ['id' => 2, 'completed' => 0, 'started' => 0, 'enrolled' => 10],
        ];
        $resets = [['id' => 71, 'time' => 400], ['id' => 72, 'time' => 900]];
        $pair = pairing::pair($completions, $resets);
        $this->assertSame([1 => 71, 2 => 72], $pair['cc'], 'a reset is invented for nobody');
        $this->assertSame([71 => 1, 72 => 2], $pair['event']);
    }

    public function test_the_cycles_are_taken_in_the_order_the_legacy_plugin_inserted_them(): void {
        // The row ids are reset order whatever the dates say, and the rows can arrive in any order.
        $completions = [
            ['id' => 8, 'completed' => 0, 'started' => 0, 'enrolled' => 10],
            ['id' => 3, 'completed' => 100, 'started' => 50, 'enrolled' => 10],
        ];
        $pair = pairing::pair($completions, [['id' => 72, 'time' => 900], ['id' => 71, 'time' => 400]]);
        $this->assertSame(71, $pair['cc'][3]);
        $this->assertSame(72, $pair['cc'][8]);

        // A cycle never runs from earlier than the one before it did: with only the later reset in the log, the
        // never-started second cycle does not take a reset that is earlier than the first cycle's completion.
        $pair = pairing::pair($completions, [['id' => 70, 'time' => 60]]);
        $this->assertSame([3 => null, 8 => null], $pair['cc'], 'the reset at 60 is before the first cycle completed');
        $this->assertSame([70 => null], $pair['event']);
    }

    public function test_a_later_cycles_backdated_completion_does_not_cost_the_cycle_before_it_its_reset(): void {
        // After a reset core's cron re-marks the criteria that carry a fixed date (a course end date, a kept grade, a
        // prerequisite course) complete with their OLD times and completes the rebuilt row at the latest of them, so
        // the second cycle "completed" long before it existed, with no start of its own. Both resets are in the log:
        // the first cycle takes the first and the second cycle the second, whatever the second one's dates say.
        $first = ['id' => 1, 'completed' => 100, 'started' => 50, 'enrolled' => 10];
        $resets = [['id' => 71, 'time' => 400], ['id' => 72, 'time' => 500]];
        $shapes = [
            'the same completion time as the first cycle' => ['id' => 2, 'completed' => 100, 'started' => 0, 'enrolled' => 10],
            'a kept grade, dated after the first completion' => ['id' => 2, 'completed' => 200, 'started' => 0, 'enrolled' => 10],
        ];
        foreach ($shapes as $name => $second) {
            $pair = pairing::pair([$first, $second], $resets);
            $this->assertSame([1 => 71, 2 => 72], $pair['cc'], $name);
            $this->assertSame([71 => 1, 72 => 2], $pair['event'], $name);
        }
    }

    public function test_a_start_stamped_with_a_criterions_old_time_is_not_the_next_cycles_beginning(): void {
        // completion_criteria_completion::mark_complete hands the criterion's time to mark_inprogress, so the cron
        // can stamp the rebuilt row's START with the same old date (200 here, long before the reset at 400).
        // Both resets are in the log, so nothing is missing and the start must not take the first one away.
        $completions = [
            ['id' => 1, 'completed' => 100, 'started' => 50, 'enrolled' => 10],
            ['id' => 2, 'completed' => 200, 'started' => 200, 'enrolled' => 10],
        ];
        $pair = pairing::pair($completions, [['id' => 71, 'time' => 400], ['id' => 72, 'time' => 500]]);
        $this->assertSame([1 => 71, 2 => 72], $pair['cc']);
        $this->assertSame([71 => 1, 72 => 2], $pair['event']);
    }

    public function test_the_next_cycles_start_still_says_which_cycle_a_missing_reset_belonged_to(): void {
        // Three cycles; the middle one's reset (at 800) is gone from the log. The reset at 1000 began after cycle 3
        // had started (810), so it cannot be the one that ended cycle 2.
        $completions = [
            ['id' => 1, 'completed' => 100, 'started' => 50, 'enrolled' => 10],
            ['id' => 2, 'completed' => 500, 'started' => 410, 'enrolled' => 10],
            ['id' => 3, 'completed' => 900, 'started' => 810, 'enrolled' => 10],
        ];
        $pair = pairing::pair($completions, [['id' => 71, 'time' => 400], ['id' => 73, 'time' => 1000]]);
        $this->assertSame([1 => 71, 2 => null, 3 => 73], $pair['cc']);
        $this->assertSame([71 => 1, 73 => 3], $pair['event']);
    }

    public function test_a_start_that_is_not_after_the_cycle_ran_from_is_not_a_cap(): void {
        // The second cycle's start (200) is earlier than the first cycle's completion (300): it is an old date the
        // cron stamped, not the second cycle's beginning, so it cannot say the reset at 400 was too late.
        $completions = [
            ['id' => 1, 'completed' => 300, 'started' => 50, 'enrolled' => 10],
            ['id' => 2, 'completed' => 600, 'started' => 200, 'enrolled' => 10],
        ];
        $pair = pairing::pair($completions, [['id' => 71, 'time' => 400]]);
        $this->assertSame([1 => 71, 2 => null], $pair['cc']);
        $this->assertSame([71 => 1], $pair['event']);
    }

    public function test_a_lone_reset_goes_to_the_earlier_cycle_when_the_next_one_never_started(): void {
        // Known ambiguity, pinned so nobody "fixes" it by trusting the next cycle's completion again: the reset at
        // 900 may have ended cycle 1 (cycle 2's date is a backdated one) or cycle 2 (cycle 1's reset was purged).
        // The data cannot say, the earlier cycle takes it, and the importer reports the pair.
        $completions = [
            ['id' => 1, 'completed' => 100, 'started' => 50, 'enrolled' => 10],
            ['id' => 2, 'completed' => 500, 'started' => 0, 'enrolled' => 10],
        ];
        $pair = pairing::pair($completions, [['id' => 72, 'time' => 900]]);
        $this->assertSame([1 => 72, 2 => null], $pair['cc']);
        $this->assertSame([72 => 1], $pair['event']);
    }

    public function test_a_later_cycles_completion_is_evidence_of_its_beginning_only_when_it_has_a_start(): void {
        $cycles = [
            ['id' => 1, 'completed' => 100, 'started' => 50, 'enrolled' => 10],
            ['id' => 2, 'completed' => 300, 'started' => 0, 'enrolled' => 10],
        ];
        $this->assertNull(evidence::later_cycle_evidence($cycles, 1, 100),
            'a completion with no start may be a date the cron backdated: it dates nothing');

        $cycles[1]['started'] = 250;
        $this->assertSame(250, evidence::later_cycle_evidence($cycles, 1, 100), 'the start is the beginning');

        $cycles[1]['started'] = 0;
        $cycles[1]['enrolled'] = 150;
        $this->assertSame(150, evidence::later_cycle_evidence($cycles, 1, 100), 'a re-enrolment after the cycle ended');

        $cycles[1] = ['id' => 2, 'completed' => 100, 'started' => 100, 'enrolled' => 10];
        $this->assertNull(evidence::later_cycle_evidence($cycles, 1, 100), 'nothing at or before the time asked about');
        $this->assertNull(evidence::later_cycle_evidence($cycles, 2, 0), 'an earlier cycle is not evidence of a later one');
    }

    public function test_an_inferred_reset_does_not_take_the_evidence_it_was_capped_at(): void {
        // The inferred time is capped at the first evidence of the next cycle, so evidence with exactly that
        // time IS the next cycle's first evidence and must not be attached to the reset that ended this one.
        $inferred = ['id' => 9, 'time' => 200, 'inferred' => true];
        $real = ['id' => 9, 'time' => 200, 'inferred' => false];
        $this->assertTrue(evidence::ends_cycle_of($inferred, 199));
        $this->assertFalse(evidence::ends_cycle_of($inferred, 200));
        $this->assertFalse(evidence::ends_cycle_of($inferred, 201));
        $this->assertTrue(evidence::ends_cycle_of($real, 200), 'a logged reset at the same second still ended it');
        $this->assertFalse(evidence::ends_cycle_of($real, 201));
    }

    // What is personal inside a payload.

    public function test_erasing_the_learner_takes_the_person_and_the_typed_text_out_of_the_payload(): void {
        $answer = json_encode(['id' => '5', 'response_id' => '3', 'question_id' => '7', 'response' => 'my secret']);
        $scrubbed = json_decode(archive_privacy::scrub_subject($answer, 'questionnaire_answer'), true);
        $this->assertSame('', $scrubbed['response']);
        $this->assertSame('7', $scrubbed['question_id'], 'everything else stays');

        $completion = json_encode(['userid' => '12', 'overrideby' => '99', 'timemodified' => '1']);
        $this->assertSame(['userid' => '0', 'overrideby' => '0', 'timemodified' => '1'],
            json_decode(archive_privacy::scrub_subject($completion, 'activity_completion'), true));
        $typed = json_encode(['userid' => 12, 'overrideby' => 99]);
        $this->assertSame(['userid' => 0, 'overrideby' => 0],
            json_decode(archive_privacy::scrub_subject($typed, 'activity_completion'), true), 'numbers stay numbers');
        $nulls = json_encode(['userid' => '12', 'overrideby' => null]);
        $this->assertNull(json_decode(archive_privacy::scrub_subject($nulls, 'activity_completion'), true)['overrideby']);

        // A response column on any other kind of row is not a questionnaire's free text.
        $other = json_encode(['userid' => '12', 'response' => 'kept']);
        $this->assertSame('kept', json_decode(archive_privacy::scrub_subject($other, 'quiz_attempt'), true)['response']);
        $this->assertSame('not json', archive_privacy::scrub_subject('not json', 'quiz_attempt'));
    }

    public function test_the_dpdp_scrub_empties_only_the_free_text_and_keeps_every_id(): void {
        $answer = json_encode(['id' => '5', 'userid' => '12', 'question_id' => '7', 'response' => 'my secret']);
        $cleared = json_decode(archive_privacy::scrub_dpdp($answer, 'questionnaire_answer'), true);
        $this->assertSame('', $cleared['response']);
        $this->assertSame('12', $cleared['userid'], 'the row stays keyed to the anonymised user row');
        $this->assertSame('7', $cleared['question_id']);

        $grade = json_encode(['userid' => '12', 'usermodified' => '99', 'finalgrade' => '8.50000',
            'feedback' => 'Well done, Priya', 'information' => 'Re-marked']);
        $cleared = json_decode(archive_privacy::scrub_dpdp($grade, 'gradebook_grade'), true);
        $this->assertSame('', $cleared['feedback']);
        $this->assertSame('', $cleared['information']);
        $this->assertSame('99', $cleared['usermodified'], 'the grader is another person: scrubbed when THEY are erased');
        $this->assertSame('12', $cleared['userid']);
        $this->assertSame('8.50000', $cleared['finalgrade'], 'the result is the evidence');

        $typed = json_encode(['userid' => '12', 'element' => 'cmi.suspend_data', 'value' => 'note: call Priya']);
        $this->assertSame('', json_decode(archive_privacy::scrub_dpdp($typed, 'scorm_track'), true)['value']);
        $status = json_encode(['userid' => '12', 'element' => 'cmi.core.lesson_status', 'value' => 'completed']);
        $this->assertSame('completed', json_decode(archive_privacy::scrub_dpdp($status, 'scorm_track'), true)['value']);

        $completion = json_encode(['userid' => '12', 'overrideby' => '99', 'timemodified' => '1']);
        $this->assertSame(['userid' => '12', 'overrideby' => '99', 'timemodified' => '1'],
            json_decode(archive_privacy::scrub_dpdp($completion, 'activity_completion'), true), 'nothing to clear: unchanged');
        $other = json_encode(['response' => 'kept']);
        $this->assertSame('kept', json_decode(archive_privacy::scrub_dpdp($other, 'quiz_attempt'), true)['response']);
        $this->assertSame('not json', archive_privacy::scrub_dpdp('not json', 'questionnaire_answer'));
        $nulls = json_encode(['response' => null]);
        $this->assertNull(json_decode(archive_privacy::scrub_dpdp($nulls, 'questionnaire_answer'), true)['response']);
    }

    public function test_erasing_an_administrator_changes_only_the_rows_that_name_them(): void {
        $row = json_encode(['userid' => '12', 'overrideby' => '99']);
        $changed = json_decode((string) archive_privacy::scrub_actor($row, 99), true);
        $this->assertSame(['userid' => '12', 'overrideby' => '0'], $changed, 'the learner is untouched');
        $this->assertNull(archive_privacy::scrub_actor($row, 98));
        $this->assertNull(archive_privacy::scrub_actor(json_encode(['overrideby' => null]), 99));
        $this->assertNull(archive_privacy::scrub_actor(json_encode(['userid' => '12']), 99));
        $this->assertNull(archive_privacy::scrub_actor('not json', 99));
        $number = json_decode((string) archive_privacy::scrub_actor(json_encode(['overrideby' => 99]), 99), true);
        $this->assertSame(0, $number['overrideby']);

        $this->assertSame([99], archive_privacy::actors($row));
        $this->assertSame([99], archive_privacy::actors(json_encode(['overrideby' => 99])));
        foreach ([['overrideby' => null], ['overrideby' => '0'], ['overrideby' => 'abc'], ['userid' => '3']] as $none) {
            $this->assertSame([], archive_privacy::actors(json_encode($none)));
        }
        $patterns = archive_privacy::actor_patterns(99);
        $this->assertCount(3, $patterns);
        $this->assertContains('%"overrideby":"99"%', $patterns);
        // The patterns find the id in a JSON object written by mapper::payload, as a string or as a number.
        $written = mapper::payload((object) ['overrideby' => '99', 'userid' => '1']);
        $this->assertTrue((bool) preg_match('/"overrideby":"99"/', $written));
    }

    public function test_a_grade_names_its_grader_and_carries_their_written_feedback(): void {
        $grade = json_encode(['id' => '5', 'itemid' => '9', 'userid' => '12', 'finalgrade' => '8.50000',
            'usermodified' => '99', 'feedback' => 'Well done, Priya', 'information' => 'Re-marked by Mr Rao',
            'overridden' => '0']);
        $scrubbed = json_decode(archive_privacy::scrub_subject($grade, 'gradebook_grade'), true);
        $this->assertSame('0', $scrubbed['userid']);
        $this->assertSame('0', $scrubbed['usermodified'], 'the grader is another person; a row nobody can be blamed for names nobody');
        $this->assertSame('', $scrubbed['feedback']);
        $this->assertSame('', $scrubbed['information']);
        $this->assertSame('8.50000', $scrubbed['finalgrade'], 'the grade itself is the evidence and stays');
        $this->assertSame('9', $scrubbed['itemid']);

        // The same keys on another kind of row are not a grader's words.
        $other = json_encode(['userid' => '12', 'usermodified' => '99', 'feedback' => 'kept']);
        $kept = json_decode(archive_privacy::scrub_subject($other, 'quiz_grade'), true);
        $this->assertSame('99', $kept['usermodified']);
        $this->assertSame('kept', $kept['feedback']);
    }

    public function test_erasing_a_grader_changes_only_the_grades_that_name_them(): void {
        $grade = json_encode(['userid' => '12', 'usermodified' => '99', 'feedback' => 'Well done']);
        $changed = json_decode((string) archive_privacy::scrub_actor($grade, 99, 'gradebook_grade'), true);
        $this->assertSame(['userid' => '12', 'usermodified' => '0', 'feedback' => 'Well done'], $changed,
            'the learner and what was written stay; only the grader goes');
        $this->assertNull(archive_privacy::scrub_actor($grade, 98, 'gradebook_grade'));
        $this->assertNull(archive_privacy::scrub_actor(json_encode(['usermodified' => null]), 99, 'gradebook_grade'));
        $number = json_decode((string) archive_privacy::scrub_actor(json_encode(['usermodified' => 99]), 99, 'gradebook_grade'), true);
        $this->assertSame(0, $number['usermodified']);
        // A grade's usermodified is not an overriding administrator, and the other way round.
        $this->assertNull(archive_privacy::scrub_actor($grade, 99));
        $this->assertNull(archive_privacy::scrub_actor(json_encode(['overrideby' => '99']), 99, 'gradebook_grade'));
        $this->assertNull(archive_privacy::scrub_actor($grade, 99, 'quiz_grade'));

        $this->assertSame([99], archive_privacy::actors($grade, 'gradebook_grade'));
        $this->assertSame([], archive_privacy::actors($grade), 'read as an activity completion it names nobody');
        $this->assertSame([], archive_privacy::actors(json_encode(['usermodified' => '0']), 'gradebook_grade'));
        $this->assertContains('%"usermodified":"99"%', archive_privacy::actor_patterns(99, 'usermodified'));
        $this->assertSame(['activity_completion', 'gradebook_grade'], archive_privacy::actor_types());
        $this->assertSame('usermodified', archive_privacy::actor_key('gradebook_grade'));
        $this->assertNull(archive_privacy::actor_key('quiz_attempt'));
    }

    public function test_an_export_leaves_out_the_other_person_a_row_names(): void {
        $grade = json_encode(['userid' => '12', 'usermodified' => '99', 'feedback' => 'Well done', 'finalgrade' => '8']);
        $exported = archive_privacy::for_export($grade, 'gradebook_grade');
        $this->assertArrayNotHasKey('usermodified', $exported, 'the grader\'s id is the grader\'s data');
        $this->assertSame('Well done', $exported['feedback'], 'what was written to the learner is theirs');
        $this->assertSame('12', $exported['userid']);

        $completion = json_encode(['userid' => '12', 'overrideby' => '99', 'completionstate' => '1']);
        $this->assertArrayNotHasKey('overrideby', archive_privacy::for_export($completion, 'activity_completion'));
        $this->assertSame('1', archive_privacy::for_export($completion, 'activity_completion')['completionstate']);

        $quiz = json_encode(['userid' => '12', 'usermodified' => '99']);
        $this->assertSame('99', archive_privacy::for_export($quiz, 'quiz_attempt')['usermodified'], 'not an actor of this type');
        $this->assertNull(archive_privacy::for_export('not json', 'quiz_attempt'));
    }

    public function test_erasing_the_learner_empties_what_they_typed_into_a_scorm_package(): void {
        $typed = json_encode(['userid' => '12', 'element' => 'cmi.suspend_data', 'value' => 'name=Priya;bookmark=4']);
        $this->assertSame('', json_decode(archive_privacy::scrub_subject($typed, 'scorm_track'), true)['value']);
        $name = json_encode(['userid' => '12', 'element' => 'cmi.core.student_name', 'value' => 'Rao, Priya']);
        $this->assertSame('', json_decode(archive_privacy::scrub_subject($name, 'scorm_track'), true)['value']);

        // The status and the score are the evidence; they stay.
        $status = json_encode(['userid' => '12', 'element' => 'cmi.core.lesson_status', 'value' => 'passed']);
        $kept = json_decode(archive_privacy::scrub_subject($status, 'scorm_track'), true);
        $this->assertSame('passed', $kept['value']);
        $this->assertSame('0', $kept['userid']);
        // Only a SCORM row is read this way.
        $this->assertSame('kept', json_decode(archive_privacy::scrub_subject(
            json_encode(['element' => 'cmi.suspend_data', 'value' => 'kept']), 'quiz_attempt'), true)['value']);
    }

    // What the rules page says about an imported rule.

    public function test_the_legacy_summary_lists_what_the_course_had_in_display_order(): void {
        $config = json_encode([
            'enable' => '1', 'recompletionduration' => '31536000', 'deletegradedata' => '1',
            'archivecompletiondata' => '1', 'quiz' => '1', 'archivequiz' => '1', 'scorm' => '1',
            'archivescorm' => '1', 'assign' => '2', 'recompletionemailsubject' => 'Hello', 'recompletionemailbody' => 'Body',
            'deletescormdata' => '1',
        ]);
        $lines = legacy_summary::lines($config);
        $this->assertSame(['enable', 'recompletionduration', 'deletegradedata', 'archivecompletiondata', 'quiz',
            'archivequiz', 'scorm', 'archivescorm', 'assign'], array_column($lines, 'name'));
        $by = array_column($lines, 'value', 'name');
        $this->assertSame('365', $by['recompletionduration']);
        $this->assertSame('1', $by['deletegradedata']);
        $this->assertSame('2', $by['assign']);
        $this->assertTrue(legacy_summary::has_dead_scorm_setting($config));
        $this->assertFalse(legacy_summary::has_dead_scorm_setting(json_encode(['scorm' => '1'])));
    }

    public function test_the_legacy_summary_is_empty_for_a_rule_made_in_sentientia_and_defends_against_bad_values(): void {
        foreach ([null, '', '   ', 'not json', '"a string"'] as $none) {
            $this->assertSame([], legacy_summary::lines($none), json_encode($none));
        }
        $this->assertFalse(legacy_summary::has_dead_scorm_setting(null));
        $lines = array_column(legacy_summary::lines(json_encode(['recompletionduration' => '0', 'quiz' => '7', 'enable' => 'x'])),
            'value', 'name');
        $this->assertArrayNotHasKey('recompletionduration', $lines, 'no usable duration, nothing to show');
        $this->assertSame('0', $lines['quiz'], 'an unknown choice is shown as nothing');
        $this->assertSame('0', $lines['enable']);
    }
}
