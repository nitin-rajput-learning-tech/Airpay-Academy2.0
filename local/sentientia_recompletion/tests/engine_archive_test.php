<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

namespace local_sentientia_recompletion;

defined('MOODLE_INTERNAL') || die();

/**
 * The engine keeps what it deletes (ADR-032, owner decision recompletion.engine_archive_before_delete), and the
 * parity fixes that were required before an imported rule can be enabled.
 *
 * A reset deletes the completion, criteria, activity completions, SCORM tracking and, on request, grades and quiz
 * attempts. Before it does, it copies them into local_sentientia_recompletion_archive, in the shape the BizLMS
 * import fills, and once the history row exists the copies point at it.
 *
 * Parity fixes proven here: a course rule skips a course whose completion tracking is off; every quiz attempt is
 * deleted against its OWN quiz (quiz_delete_attempt() refuses an attempt of another quiz, so the second quiz of
 * a course kept its attempts); the learner is told in their language from lang strings, not hard-coded English.
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
final class engine_archive_test extends \advanced_testcase {

    /** @var array<string, mixed> What setUp() built. */
    private array $w = [];

    protected function setUp(): void {
        global $DB;
        parent::setUp();
        $this->resetAfterTest();
        // The SCORM and quiz generators need a current user.
        $this->setAdminUser();
        // The reminder is held back for a day by an application cache keyed by rule, user and course ids, and those
        // ids repeat from one test to the next once the tables are reset.
        \cache::make('local_sentientia_recompletion', 'warn_dedupe')->purge();
        $g = $this->getDataGenerator();

        $user = $g->create_user(['lang' => 'en']);
        $course = $g->create_course(['enablecompletion' => 1, 'fullname' => 'Annual AML']);
        $page = $g->create_module('page', ['course' => $course->id]);
        $quiz1 = $g->create_module('quiz', ['course' => $course->id, 'grade' => 10]);
        $quiz2 = $g->create_module('quiz', ['course' => $course->id, 'grade' => 10]);
        $scorm = $g->create_module('scorm', ['course' => $course->id]);
        $now = time();
        $old = $now - 400 * DAYSECS;

        $DB->insert_record('course_completions', (object) ['userid' => $user->id, 'course' => $course->id,
            'timeenrolled' => $old - DAYSECS, 'timestarted' => $old - HOURSECS, 'timecompleted' => $old, 'reaggregate' => 0]);
        $DB->insert_record('course_completion_crit_compl', (object) ['userid' => $user->id, 'course' => $course->id,
            'criteriaid' => 7, 'gradefinal' => 88.5, 'timecompleted' => $old]);
        $DB->insert_record('course_modules_completion', (object) ['coursemoduleid' => $page->cmid, 'userid' => $user->id,
            'completionstate' => 1, 'viewed' => 1, 'overrideby' => null, 'timemodified' => $old]);

        // SCORM tracking, as mod_scorm writes it in Moodle 5 (attempt, element, value).
        $scoid = (int) $DB->get_field('scorm_scoes', 'id', ['scorm' => $scorm->id, 'scormtype' => 'sco'], IGNORE_MULTIPLE);
        if (!$scoid) {
            $scoid = (int) $DB->insert_record('scorm_scoes', (object) ['scorm' => $scorm->id, 'manifest' => '',
                'organization' => 'ORG', 'parent' => '/', 'identifier' => 'item_1', 'launch' => 'index.html',
                'scormtype' => 'sco', 'title' => 'SCO 1', 'sortorder' => 0]);
        }
        $attemptid = (int) $DB->insert_record('scorm_attempt', (object) ['userid' => $user->id, 'scormid' => $scorm->id,
            'attempt' => 1]);
        $elementid = $DB->get_field('scorm_element', 'id', ['element' => 'cmi.completion_status']);
        if (!$elementid) {
            $elementid = $DB->insert_record('scorm_element', (object) ['element' => 'cmi.completion_status']);
        }
        $DB->insert_record('scorm_scoes_value', (object) ['scoid' => $scoid, 'attemptid' => $attemptid,
            'elementid' => $elementid, 'value' => 'completed', 'timemodified' => $old]);

        // One attempt and one grade on EACH quiz.
        foreach ([$quiz1, $quiz2] as $i => $quiz) {
            $this->core_row('quiz_attempts', ['quiz' => $quiz->id, 'userid' => $user->id, 'attempt' => 1,
                'uniqueid' => 9000 + $i, 'layout' => '1,0', 'currentpage' => 0, 'preview' => 0, 'state' => 'finished',
                'timestart' => $old - 600, 'timefinish' => $old, 'timemodified' => $old, 'sumgrades' => 5]);
            $DB->insert_record('quiz_grades', (object) ['quiz' => $quiz->id, 'userid' => $user->id, 'grade' => 5,
                'timemodified' => $old]);
        }

        $item = $g->create_grade_item(['courseid' => $course->id, 'itemtype' => 'manual', 'itemname' => 'Manual']);
        $this->core_row('grade_grades', ['itemid' => $item->id, 'userid' => $user->id, 'rawgrade' => 75,
            'finalgrade' => 75, 'timecreated' => $old, 'timemodified' => $old]);

        $this->w = ['user' => $user, 'course' => $course, 'page' => $page, 'quiz1' => $quiz1, 'quiz2' => $quiz2,
            'scorm' => $scorm, 'old' => $old, 'item' => $item];
    }

    /**
     * Insert a row, filling every NOT NULL column that has no default with a neutral value.
     *
     * @param string $table
     * @param array $values
     * @return int
     */
    private function core_row(string $table, array $values): int {
        global $DB;
        $row = new \stdClass();
        foreach ($DB->get_columns($table) as $name => $column) {
            if ($name === 'id') {
                continue;
            }
            if (array_key_exists($name, $values)) {
                $row->$name = $values[$name];
            } else if ($column->not_null && !$column->has_default) {
                $row->$name = in_array($column->meta_type, ['I', 'R', 'N', 'F'], true) ? 0 : '';
            }
        }
        return (int) $DB->insert_record($table, $row);
    }

    /**
     * @param int $courseid
     * @return \stdClass An enabled rule over the course that the seeded completion has outlived.
     */
    private function rule(int $courseid): \stdClass {
        global $DB;
        $now = time();
        $id = (int) $DB->insert_record('local_sentientia_recompletion_rules', (object) [
            'name' => 'Annual', 'courseid' => $courseid, 'period_days' => 365, 'trigger_type' => 'completion',
            'reset_grades' => 1, 'reset_attempts' => 1, 'enabled' => 1, 'costcenterid' => 0,
            'timecreated' => $now, 'timemodified' => $now,
        ]);
        return $DB->get_record('local_sentientia_recompletion_rules', ['id' => $id], '*', MUST_EXIST);
    }

    public function test_a_reset_archives_what_it_deletes_and_the_history_row_owns_the_archive(): void {
        global $DB;
        $user = $this->w['user'];
        $course = $this->w['course'];
        $completion = $DB->get_record('course_completions', ['userid' => $user->id, 'course' => $course->id], '*', MUST_EXIST);
        $sink = $this->redirectMessages();

        $result = recompletion_engine::run_rule($this->rule((int) $course->id), false);
        $this->assertSame(1, $result['reset']);

        // What was deleted is gone.
        $this->assertSame(0, $DB->count_records('course_completions', ['userid' => $user->id]));
        $this->assertSame(0, $DB->count_records('course_completion_crit_compl', ['userid' => $user->id]));
        $this->assertSame(0, $DB->count_records('course_modules_completion', ['userid' => $user->id]));
        $this->assertSame(0, $DB->count_records('scorm_attempt', ['userid' => $user->id]));
        $this->assertSame(0, $DB->count_records('quiz_attempts', ['userid' => $user->id]),
            'the attempts of BOTH quizzes are deleted (each against its own quiz)');
        $this->assertSame(0, $DB->count_records('grade_grades', ['userid' => $user->id, 'itemid' => $this->w['item']->id]));

        // And it was kept first.
        $history = $DB->get_record('local_sentientia_recompletion_history', ['userid' => $user->id], '*', MUST_EXIST);
        $this->assertSame('engine', $history->source);
        $this->assertEquals(0, $history->time_inferred);
        $archive = $DB->get_records('local_sentientia_recompletion_archive', ['userid' => $user->id], 'id ASC');
        $byType = [];
        foreach ($archive as $row) {
            $byType[$row->itemtype][] = $row;
            $this->assertEquals($history->id, $row->historyid, $row->itemtype . ' is attached to the reset that deleted it');
            $this->assertEquals($course->id, $row->courseid);
            $this->assertEqualsWithDelta((int) $history->timecreated, (int) $row->timecreated, 5,
                'archived at the time of the reset');
        }
        $counts = array_map('count', $byType);
        unset($counts['gradebook_grade']);
        ksort($counts);
        $this->assertSame(['activity_completion' => 1, 'course_completion' => 1, 'criteria_completion' => 1,
            'quiz_attempt' => 2, 'quiz_grade' => 2, 'scorm_track' => 1], $counts);
        $manual = array_values(array_filter($byType['gradebook_grade'] ?? [],
            fn($r) => (int) $r->instanceid === (int) $this->w['item']->id));
        $this->assertCount(1, $manual, 'the learner\'s grade on the course\'s grade item');
        $this->assertEquals(75.0, (float) $manual[0]->grade);

        $cc = $byType['course_completion'][0];
        $this->assertSame('complete', $cc->state);
        $this->assertEquals($this->w['old'], $cc->timeevent);
        $this->assertEquals((array) $completion, json_decode($cc->payload, true), 'the payload is the deleted row exactly');
        $this->assertEquals(88.5, (float) $byType['criteria_completion'][0]->grade);
        $this->assertEquals($this->w['page']->cmid, $byType['activity_completion'][0]->cmid);
        $scorm = $byType['scorm_track'][0];
        $this->assertSame('cmi.completion_status', $scorm->itemkey);
        $this->assertSame('completed', $scorm->state);
        $this->assertEquals($this->w['scorm']->id, $scorm->instanceid);
        $this->assertSame('completed', json_decode($scorm->payload, true)['value']);
        $this->assertEqualsCanonicalizing([$this->w['quiz1']->id, $this->w['quiz2']->id],
            array_map(static fn($r) => (int) $r->instanceid, $byType['quiz_attempt']));

        // The learner is told, in English here, with the wording the plugin always used.
        $messages = $sink->get_messages();
        $this->assertCount(1, $messages);
        $this->assertSame("Recompletion: 'Annual AML' has been reset", $messages[0]->subject);
        $this->assertStringContainsString("Your previous completion of 'Annual AML' was on ", $messages[0]->fullmessage);
        $this->assertStringContainsString('Per the 365-day recompletion rule', $messages[0]->fullmessage);
    }

    public function test_the_archive_holds_only_what_the_reset_deletes(): void {
        global $DB;
        $user = $this->w['user'];
        // A reset that keeps grades and quiz attempts archives neither.
        $this->assertTrue(recompletion_engine::reset_user_in_course((int) $user->id, (int) $this->w['course']->id, false, false));
        $types = $DB->get_fieldset_sql('SELECT DISTINCT itemtype FROM {local_sentientia_recompletion_archive}');
        sort($types);
        $this->assertSame(['activity_completion', 'course_completion', 'criteria_completion', 'scorm_track'], $types);
        $this->assertSame(2, $DB->count_records('quiz_attempts', ['userid' => $user->id]));
        $this->assertSame(1, $DB->count_records('grade_grades', ['userid' => $user->id, 'itemid' => $this->w['item']->id]));
        $this->assertSame(0, $DB->count_records_select('local_sentientia_recompletion_archive', 'historyid <> 0'),
            'a caller that writes no history row leaves the archive unattached, not lost');
    }

    public function test_a_manual_bulk_reset_attaches_its_archive_to_its_history_row(): void {
        global $DB;
        $user = $this->w['user'];
        $result = recompletion_engine::bulk_reset((int) $this->w['course']->id, [(int) $user->id], 2, 'bulk', true, true);
        $this->assertSame(['reset' => 1, 'failed' => 0], $result);
        $history = $DB->get_record('local_sentientia_recompletion_history', ['userid' => $user->id], '*', MUST_EXIST);
        $this->assertSame('bulk', $history->reason);
        $this->assertGreaterThan(0, $DB->count_records('local_sentientia_recompletion_archive', ['historyid' => $history->id]));
        $this->assertSame(0, $DB->count_records('local_sentientia_recompletion_archive', ['historyid' => 0]));
    }

    public function test_attaching_nothing_changes_nothing(): void {
        global $DB;
        evidence_archiver::attach([], 5);
        evidence_archiver::attach([1, 2], 0);
        $this->assertSame(0, $DB->count_records('local_sentientia_recompletion_archive'));
    }

    public function test_a_course_rule_skips_a_course_whose_completion_tracking_is_off(): void {
        global $DB;
        $user = $this->w['user'];
        $course = $this->w['course'];
        $DB->set_field('course', 'enablecompletion', 0, ['id' => $course->id]);
        $this->redirectMessages();

        $result = recompletion_engine::run_rule($this->rule((int) $course->id), false);

        $this->assertSame(0, $result['reset'], 'the BizLMS cron required completion tracking for every course');
        $this->assertSame(1, $DB->count_records('course_completions', ['userid' => $user->id]));
        $this->assertSame(0, $DB->count_records('local_sentientia_recompletion_history'));
        $this->assertSame(0, $DB->count_records('local_sentientia_recompletion_archive'));
    }

    public function test_the_reminder_is_worded_in_the_recipients_language_from_lang_strings(): void {
        global $DB;
        $user = $this->w['user'];
        $course = $this->w['course'];
        // Completed 350 days ago with a 365-day period and a 30-day warning window: due in 15 days.
        $DB->set_field('course_completions', 'timecompleted', time() - 350 * DAYSECS, ['userid' => $user->id]);
        $sink = $this->redirectMessages();

        $result = recompletion_engine::run_rule($this->rule((int) $course->id), false);

        $this->assertSame(0, $result['reset']);
        $this->assertSame(1, $result['notified']);
        $messages = $sink->get_messages();
        $this->assertCount(1, $messages);
        $this->assertStringStartsWith('Recompletion due in ', $messages[0]->subject);
        $this->assertStringContainsString("'Annual AML'", $messages[0]->subject);
        $this->assertStringContainsString('will expire in', $messages[0]->fullmessage);
    }

    public function test_a_second_pass_within_the_day_does_not_repeat_the_reminder(): void {
        global $DB;
        $user = $this->w['user'];
        $course = $this->w['course'];
        $DB->set_field('course_completions', 'timecompleted', time() - 350 * DAYSECS, ['userid' => $user->id]);
        $sink = $this->redirectMessages();
        $rule = $this->rule((int) $course->id);

        $first = recompletion_engine::run_rule($rule, false);
        $second = recompletion_engine::run_rule($rule, false);

        $this->assertSame(1, $first['notified']);
        $this->assertSame(0, $second['notified'], 'the warn_dedupe cache holds the reminder back for a day');
        $this->assertCount(1, $sink->get_messages());
    }
}
