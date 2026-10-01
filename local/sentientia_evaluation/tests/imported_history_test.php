<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

namespace local_sentientia_evaluation;

defined('MOODLE_INTERNAL') || die();

use local_sentientia_platform\bizlms\legacymap;

/**
 * What the BizLMS evaluation import leaves behind for the rest of the plugin (ADR-032, mapping doc section 18,
 * "Code fixes"): imported history is read-only, deleting a form no longer strands its assignments, numeric
 * answers keep their decimals in the statistics, and a learner can read their own history.
 *
 * These tests do not run the importer. A form is "imported" when the legacy map says so, so each test marks a form
 * the way the importer would and checks what the plugin then does.
 *
 * @package    local_sentientia_evaluation
 * @category   test
 * @copyright  2026 Airpay Payment Services
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \local_sentientia_evaluation\evaluation_manager
 * @covers     \local_sentientia_evaluation\learner_history
 *
 * @group local_sentientia_evaluation
 * @group bizlms_import
 */
final class imported_history_test extends \advanced_testcase {

    /**
     * Record that the import created an evaluation, as the importer's map row does.
     *
     * @param int $evaluationid
     * @return void
     */
    private function mark_imported(int $evaluationid): void {
        global $DB;
        $DB->insert_record(legacymap::TABLE, (object) [
            'feature' => 'evaluation', 'sourcetable' => 'local_evaluations', 'sourceid' => $evaluationid, 'subkey' => '',
            'targettable' => 'local_sentientia_evaluation', 'targetid' => $evaluationid, 'outcome' => 'imported',
            'reason' => null, 'detail' => null, 'runid' => 0, 'timecreated' => time(),
        ]);
    }

    /**
     * @param callable $action
     * @param string $what
     * @return void
     */
    private function assert_read_only(callable $action, string $what): void {
        try {
            $action();
            $this->fail("{$what} should be refused on an imported form");
        } catch (\moodle_exception $e) {
            $this->assertSame('error_imported_form_read_only', $e->errorcode, $what);
        }
    }

    public function test_an_imported_form_cannot_be_edited_reopened_reordered_or_deleted(): void {
        global $DB;
        $this->resetAfterTest();
        $user = $this->getDataGenerator()->create_user();
        $id = evaluation_manager::create((object) ['name' => 'Imported', 'status' => evaluation_manager::STATUS_ARCHIVED]);
        $qid = evaluation_manager::create_question((object) [
            'evaluationid' => $id, 'questiontext' => 'Question', 'questiontype' => 'text', 'required' => 0,
        ]);
        $this->mark_imported($id);

        $this->assert_read_only(static fn() => evaluation_manager::update($id, (object) ['name' => 'Renamed']), 'update');
        $this->assert_read_only(static fn() => evaluation_manager::change_status($id, evaluation_manager::STATUS_ACTIVE), 'change_status');
        $this->assert_read_only(static fn() => evaluation_manager::delete($id), 'delete');
        $this->assert_read_only(static fn() => evaluation_manager::create_question((object) [
            'evaluationid' => $id, 'questiontext' => 'Another', 'questiontype' => 'text',
        ]), 'create_question');
        $this->assert_read_only(static fn() => evaluation_manager::update_question($qid, (object) ['questiontext' => 'Changed']),
            'update_question');
        $this->assert_read_only(static fn() => evaluation_manager::delete_question($qid), 'delete_question');
        $this->assert_read_only(static fn() => evaluation_manager::reorder_questions($id, [$qid]), 'reorder_questions');
        $this->assert_read_only(static fn() => evaluation_manager::ensure_assignment($id, (int) $user->id), 'ensure_assignment');

        // Nothing moved.
        $form = $DB->get_record('local_sentientia_evaluation', ['id' => $id], '*', MUST_EXIST);
        $this->assertSame('Imported', $form->name);
        $this->assertSame(evaluation_manager::STATUS_ARCHIVED, (int) $form->status);
        $this->assertSame(1, $DB->count_records('local_sentientia_evaluation_questions', ['evaluationid' => $id]));
        $this->assertSame('Question', $DB->get_field('local_sentientia_evaluation_questions', 'questiontext', ['id' => $qid]));
        $this->assertSame(0, $DB->count_records('local_sentientia_evaluation_assign', ['evaluationid' => $id]));
        $this->assertTrue(evaluation_manager::is_imported($id));
    }

    public function test_a_native_form_is_unaffected(): void {
        global $DB;
        $this->resetAfterTest();
        $imported = evaluation_manager::create((object) ['name' => 'Imported']);
        $native = evaluation_manager::create((object) ['name' => 'Native']);
        $this->mark_imported($imported);

        $this->assertFalse(evaluation_manager::is_imported($native));
        evaluation_manager::update($native, (object) ['name' => 'Renamed']);
        evaluation_manager::change_status($native, evaluation_manager::STATUS_ACTIVE);
        $this->assertSame('Renamed', $DB->get_field('local_sentientia_evaluation', 'name', ['id' => $native]));
        $this->assertTrue(evaluation_manager::delete($native));
        $this->assertFalse($DB->record_exists('local_sentientia_evaluation', ['id' => $native]));
        $this->assertTrue($DB->record_exists('local_sentientia_evaluation', ['id' => $imported]));
    }

    public function test_a_map_row_that_is_not_imported_does_not_protect_a_form(): void {
        global $DB;
        $this->resetAfterTest();
        $id = evaluation_manager::create((object) ['name' => 'Mapped but archived in the legacy table']);
        $DB->insert_record(legacymap::TABLE, (object) [
            'feature' => 'evaluation', 'sourcetable' => 'local_evaluations', 'sourceid' => $id, 'subkey' => '',
            'targettable' => '', 'targetid' => null, 'outcome' => 'archived', 'reason' => 'deleted_form',
            'detail' => null, 'runid' => 0, 'timecreated' => time(),
        ]);
        $this->assertFalse(evaluation_manager::is_imported($id), 'an archived legacy row created no evaluation');
        $this->assertTrue(evaluation_manager::delete($id));
    }

    public function test_deleting_a_form_clears_its_assignments_and_queued_triggers(): void {
        global $DB;
        $this->resetAfterTest();
        $user = $this->getDataGenerator()->create_user();
        $form = evaluation_manager::create((object) ['name' => 'To delete']);
        $survivor = evaluation_manager::create((object) ['name' => 'Survivor']);
        foreach ([$form, $survivor] as $evaluationid) {
            evaluation_manager::ensure_assignment($evaluationid, (int) $user->id);
            $DB->insert_record('local_sentientia_evaluation_triggers', (object) [
                'evaluationid' => $evaluationid, 'userid' => $user->id, 'itemid' => 0,
                'trigger_event' => 'course_completion', 'fire_after' => time(), 'status' => 0, 'timecreated' => time(),
            ]);
        }

        evaluation_manager::delete($form);

        $this->assertSame(0, $DB->count_records('local_sentientia_evaluation_assign', ['evaluationid' => $form]));
        $this->assertSame(0, $DB->count_records('local_sentientia_evaluation_triggers', ['evaluationid' => $form]));
        $this->assertSame(1, $DB->count_records('local_sentientia_evaluation_assign', ['evaluationid' => $survivor]));
        $this->assertSame(1, $DB->count_records('local_sentientia_evaluation_triggers', ['evaluationid' => $survivor]));
    }

    public function test_an_imported_template_cannot_be_deleted_and_a_native_one_can(): void {
        global $DB;
        $this->resetAfterTest();
        $user = $this->getDataGenerator()->create_user();
        $form = evaluation_manager::create((object) ['name' => 'Source of both templates']);
        evaluation_manager::create_question((object) [
            'evaluationid' => $form, 'questiontext' => 'Question', 'questiontype' => 'text', 'required' => 0,
        ]);
        // BizLMS numeric items arrive with their range (April: 1 to 5).
        evaluation_manager::create_question((object) [
            'evaluationid' => $form, 'questiontext' => 'Score', 'questiontype' => 'numeric', 'required' => 0,
            'numeric_min' => 1, 'numeric_max' => 5,
        ]);
        $imported = evaluation_manager::save_template_from_evaluation($form, 'Brought over', '', (int) $user->id);
        $native = evaluation_manager::save_template_from_evaluation($form, 'Saved by a person', '', (int) $user->id);
        $DB->insert_record(legacymap::TABLE, (object) [
            'feature' => 'evaluation', 'sourcetable' => 'local_evaluation_template', 'sourceid' => $imported,
            'subkey' => '', 'targettable' => 'local_sentientia_evaluation_template', 'targetid' => $imported,
            'outcome' => 'imported', 'reason' => null, 'detail' => null, 'runid' => 0, 'timecreated' => time(),
        ]);

        $this->assertTrue(evaluation_manager::is_imported_template($imported));
        $this->assertFalse(evaluation_manager::is_imported_template($native));
        $this->assertFalse(evaluation_manager::is_imported_template(0));

        try {
            evaluation_manager::delete_template($imported);
            $this->fail('an imported template should not be deletable');
        } catch (\moodle_exception $e) {
            $this->assertSame('error_imported_template_read_only', $e->errorcode);
        }
        $this->assertTrue($DB->record_exists('local_sentientia_evaluation_template', ['id' => $imported]));

        $this->assertTrue(evaluation_manager::delete_template($native));
        $this->assertFalse($DB->record_exists('local_sentientia_evaluation_template', ['id' => $native]));
        // Using the imported template to start a new evaluation is still how its questions are run again.
        $created = evaluation_manager::create_evaluation_from_template($imported);
        $this->assertSame(2, $created['question_count']);
        // ... with the range of its number question: it came back unbounded when only the count was checked.
        $numeric = array_values(array_filter(evaluation_manager::get_questions($created['id']),
            static fn($q): bool => $q->questiontype === 'numeric'));
        $this->assertCount(1, $numeric);
        $this->assertSame(['min' => 1, 'max' => 5], evaluation_manager::decode_numeric_bounds($numeric[0]->options));
    }

    public function test_numeric_answers_keep_their_decimals_in_the_statistics(): void {
        global $DB;
        $this->resetAfterTest();
        $form = evaluation_manager::create((object) ['name' => 'Numbers']);
        $qid = evaluation_manager::create_question((object) [
            'evaluationid' => $form, 'questiontext' => 'How many?', 'questiontype' => 'numeric', 'required' => 0,
        ]);
        // A BizLMS numeric item allowed decimals: 7.5 and 2.5 are answers the import brings over as they were.
        foreach ([7.5, 2.5] as $answer) {
            $DB->insert_record('local_sentientia_evaluation_responses', (object) [
                'evaluationid' => $form, 'userid' => 0, 'response_data' => json_encode((object) [$qid => $answer]),
                'timesubmitted' => time(),
            ]);
        }
        $stats = evaluation_manager::get_response_stats($form)[$qid];
        $this->assertEquals(2, $stats['count']);
        $this->assertEquals(10.0, $stats['sum'], 'the sum was 9 when each answer was truncated to an int');
        $this->assertEquals(5.0, $stats['avg']);
        $this->assertEquals(2.5, $stats['min_seen']);
        $this->assertEquals(7.5, $stats['max_seen']);

        // A native integer answer is unchanged.
        $DB->insert_record('local_sentientia_evaluation_responses', (object) [
            'evaluationid' => $form, 'userid' => 0, 'response_data' => json_encode((object) [$qid => 5]),
            'timesubmitted' => time(),
        ]);
        $stats = evaluation_manager::get_response_stats($form)[$qid];
        $this->assertEquals(15.0, $stats['sum']);
        $this->assertEquals(5.0, $stats['avg']);
    }

    // The learner's own history.

    /**
     * @param string $name
     * @param int $anonymous
     * @return int
     */
    private function form(string $name, int $anonymous = 0): int {
        return evaluation_manager::create((object) ['name' => $name, 'anonymous' => $anonymous]);
    }

    /**
     * @param int $form
     * @param int $user
     * @param string $status
     * @param int|null $due
     * @param int|null $responded
     * @param int $source The unique key is (form, user, trigger, source), so a second row needs another source.
     * @return void
     */
    private function assign(int $form, int $user, string $status, ?int $due, ?int $responded, int $source = 0): void {
        global $DB;
        $DB->insert_record('local_sentientia_evaluation_assign', (object) [
            'evaluationid' => $form, 'userid' => $user, 'trigger_event' => 'manual', 'source_id' => $source,
            'status' => $status, 'assigned_by_userid' => null, 'due_at' => $due, 'responded_at' => $responded,
            'timecreated' => 1700000000, 'timemodified' => 1700000000,
        ]);
    }

    /**
     * @param int $form
     * @param int $user
     * @param int $time
     * @return void
     */
    private function respond(int $form, int $user, int $time): void {
        global $DB;
        $DB->insert_record('local_sentientia_evaluation_responses', (object) [
            'evaluationid' => $form, 'userid' => $user, 'response_data' => '{}', 'timesubmitted' => $time,
        ]);
    }

    public function test_a_learner_reads_only_their_own_history(): void {
        $this->resetAfterTest();
        $gen = $this->getDataGenerator();
        $me = (int) $gen->create_user()->id;
        $other = (int) $gen->create_user()->id;
        $t = 1700000000;

        $named = $this->form('Named and answered');
        $anonymous = $this->form('Anonymous and answered', 1);
        $waiting = $this->form('Waiting');
        $missed = $this->form('Missed');
        $notmine = $this->form('Somebody else\'s');
        $this->mark_imported($missed);

        $this->assign($named, $me, 'responded', null, $t + 290);
        $this->respond($named, $me, $t + 300);
        $this->assign($anonymous, $me, 'responded', null, $t + 200);
        $this->respond($anonymous, 0, $t + 205);   // the anonymous answer is stored with user id 0: not mine to read.
        $this->assign($waiting, $me, 'assigned', $t + 400, null);
        $this->assign($missed, $me, 'expired', $t + 100, null);
        $this->assign($notmine, $other, 'responded', null, $t + 500);
        $this->respond($notmine, $other, $t + 500);

        $rows = learner_history::for_user($me);

        $this->assertSame(['Waiting', 'Named and answered', 'Anonymous and answered', 'Missed'],
            array_map(static fn(\stdClass $r): string => $r->name, $rows), 'newest first, and nothing of anybody else\'s');
        $byname = [];
        foreach ($rows as $row) {
            $byname[$row->name] = $row;
        }
        $this->assertSame(learner_history::STATUS_ASSIGNED, $byname['Waiting']->status);
        $this->assertSame($t + 400, $byname['Waiting']->time, 'a waiting assignment shows when it is due');
        $this->assertSame(learner_history::STATUS_RESPONDED, $byname['Named and answered']->status);
        $this->assertSame($t + 300, $byname['Named and answered']->time, 'the response\'s own time, not the assignment\'s');
        $this->assertFalse($byname['Named and answered']->anonymous);
        $this->assertFalse($byname['Named and answered']->unlinked);
        $this->assertSame(learner_history::STATUS_RESPONDED, $byname['Anonymous and answered']->status);
        $this->assertTrue($byname['Anonymous and answered']->anonymous, 'the time is shown to the day');
        $this->assertTrue($byname['Anonymous and answered']->unlinked, 'the page says the answers are not linked');
        $this->assertFalse($byname['Waiting']->unlinked);
        $this->assertFalse($byname['Missed']->unlinked);
        $this->assertSame($t + 200, $byname['Anonymous and answered']->time, 'the assignment\'s day, never the anonymous answer\'s');
        $this->assertSame(learner_history::STATUS_EXPIRED, $byname['Missed']->status);
        $this->assertTrue($byname['Missed']->imported);
        $this->assertFalse($byname['Waiting']->imported);

        $this->assertSame([], learner_history::for_user(0));
        $this->assertCount(1, learner_history::for_user($other));
    }

    public function test_the_person_evaluated_on_a_supervisor_form_is_not_shown_as_having_responded(): void {
        global $DB;
        $this->resetAfterTest();
        $gen = $this->getDataGenerator();
        $me = (int) $gen->create_user()->id;
        $supervisor = (int) $gen->create_user()->id;
        $t = 1700000000;

        // A supervisor evaluation: the assignment names the team member evaluated, "responded" means the SUPERVISOR
        // answered, and the response keeps the person it is about. BizLMS listed only self evaluations to learners.
        $review = $this->form('Supervisor review');
        $this->mark_imported($review);
        $this->assign($review, $me, 'responded', null, $t + 100);
        $DB->insert_record('local_sentientia_evaluation_responses', (object) [
            'evaluationid' => $review, 'userid' => $supervisor, 'subject_userid' => $me, 'response_data' => '{}',
            'timesubmitted' => $t + 110,
        ]);
        // A self evaluation of the same learner is still listed.
        $self = $this->form('Self evaluation');
        $this->mark_imported($self);
        $this->assign($self, $me, 'responded', null, $t + 200);
        $this->respond($self, $me, $t + 210);

        $this->assertSame(['Self evaluation'],
            array_map(static fn(\stdClass $r): string => $r->name, learner_history::for_user($me)),
            'the person evaluated did not respond to the supervisor review');

        // The supervisor did answer it: that is their response, and it is listed.
        $rows = learner_history::for_user($supervisor);
        $this->assertSame(['Supervisor review'], array_map(static fn(\stdClass $r): string => $r->name, $rows));
        $this->assertSame(learner_history::STATUS_RESPONDED, $rows[0]->status);
        $this->assertSame($t + 110, $rows[0]->time, 'their own response, not the assignment row that names somebody else');
    }

    public function test_the_unlinked_note_is_for_a_learner_whose_answers_are_not_linked_to_them(): void {
        $this->resetAfterTest();
        $me = (int) $this->getDataGenerator()->create_user()->id;
        $t = 1700000000;

        // A named form that once took a guest's anonymous answer: its respondents are hidden from administrators
        // (the date is shown to the day), but this learner's own answer is named and linked to them.
        $mixed = $this->form('Named with a guest answer');
        $this->assign($mixed, $me, 'responded', null, $t + 10);
        $this->respond($mixed, $me, $t + 20);
        $this->respond($mixed, 0, $t + 30);
        // ... and one of its assignees who has not answered: their answer WILL be named, so no note either.
        $unanswered = $this->form('Named, guest answer, not answered by me');
        $this->assign($unanswered, $me, 'assigned', $t + 900, null);
        $this->respond($unanswered, 0, $t + 40);
        // An anonymous form: waiting or answered, nothing of theirs is linked.
        $waiting = $this->form('Anonymous, waiting', 1);
        $this->assign($waiting, $me, 'assigned', $t + 800, null);
        $answered = $this->form('Anonymous, answered', 1);
        $this->assign($answered, $me, 'responded', null, $t + 50);
        $this->respond($answered, 0, $t + 55);

        $byname = [];
        foreach (learner_history::for_user($me) as $row) {
            $byname[$row->name] = $row;
        }
        $this->assertTrue($byname['Named with a guest answer']->anonymous, 'protected: the date is to the day');
        $this->assertFalse($byname['Named with a guest answer']->unlinked, 'but their own answer is linked to them');
        $this->assertTrue($byname['Named, guest answer, not answered by me']->anonymous);
        $this->assertFalse($byname['Named, guest answer, not answered by me']->unlinked);
        $this->assertTrue($byname['Anonymous, waiting']->unlinked);
        $this->assertTrue($byname['Anonymous, answered']->unlinked);
    }

    public function test_one_row_per_form_however_many_assignments_and_answers(): void {
        $this->resetAfterTest();
        $me = (int) $this->getDataGenerator()->create_user()->id;
        $form = $this->form('Repeatable');
        $this->assign($form, $me, 'expired', null, null);
        $this->assign($form, $me, 'responded', null, 1700000100, 1);
        $this->respond($form, $me, 1700000200);
        $this->respond($form, $me, 1700000300);

        $rows = learner_history::for_user($me);
        $this->assertCount(1, $rows);
        $this->assertSame(learner_history::STATUS_RESPONDED, $rows[0]->status, 'a response settles it');
        $this->assertSame(1700000300, $rows[0]->time, 'the latest answer');
    }

    public function test_the_history_page_is_behind_a_flag_that_is_off_by_default(): void {
        $this->resetAfterTest();
        \local_sentientia_platform\feature_flags::invalidate_caches();
        $registry = \local_sentientia_platform\feature_flags::load_registry();
        $this->assertArrayHasKey('sentientia.evaluation.learner_history', $registry);
        $this->assertFalse($registry['sentientia.evaluation.learner_history']['default']);
        $this->assertFalse(\local_sentientia_platform\feature_flags::is_enabled('sentientia.evaluation.learner_history'));
    }
}
