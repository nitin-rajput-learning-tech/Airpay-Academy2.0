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
        $this->assertSame(110, mapper::inferred_time(100, 50, null, 110), 'capped at the import time');
        $this->assertSame(100, mapper::inferred_time(100, 50, 90, 1000), 'never before the completion');
        $this->assertSame(1000, mapper::inferred_time(null, 50, null, 1000), 'never completed: the import time');
        $this->assertSame(1000, mapper::inferred_time(2000, 50, null, 1000), 'a completion in the future');
        $this->assertSame(150, mapper::inferred_time(100, 50, 0, 1000), 'no usable next evidence');
        $this->assertSame(1000, mapper::inferred_time(100, 0, null, 1000),
            'with no duration only the import time is left to cap at');
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
