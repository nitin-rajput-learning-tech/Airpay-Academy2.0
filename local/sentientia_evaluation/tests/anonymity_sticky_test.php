<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

namespace local_sentientia_evaluation;

defined('MOODLE_INTERNAL') || die();

/**
 * Anonymity is a promise made to the people who have already answered.
 *
 * Until 2026-09-25 respondents_hidden() read only the evaluation's CURRENT
 * anonymous flag. A tenant admin could untick "Collect responses
 * anonymously" once responses were in and read the Responded tab (names,
 * emails, responded_at to the minute) against the anonymous answers, whose
 * timesubmitted is the same minute. The same held for a single anonymous
 * question. These tests pin the sticky rule: respondents stay hidden while
 * the evaluation is anonymous, once any response was collected anonymously
 * (stored with userid 0), or while any question is anonymous; anonymity
 * cannot be switched off once someone has answered; and the response list /
 * CSV show those evaluations' submission times to the day, never the minute.
 *
 * @package    local_sentientia_evaluation
 * @category   test
 * @copyright  2026 Airpay Payment Services
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \local_sentientia_evaluation\evaluation_manager
 * @covers     \local_sentientia_evaluation\form\edit_evaluation
 * @covers     \local_sentientia_evaluation\form\edit_question
 * @group      tenant_isolation
 */
final class anonymity_sticky_test extends \advanced_testcase {

    use \local_sentientia_org\test\bizlms_fixture;

    /** @var int the /1 tenant root org */
    private int $org1;

    protected function setUp(): void {
        global $DB;
        parent::setUp();
        $this->resetAfterTest();
        $this->ensure_bizlms_schema();
        $now = time();
        $this->org1 = (int) $DB->insert_record('local_sentientia_org', (object) [
            'fullname' => 'Airpay', 'shortname' => 'airpay_anon', 'parentid' => 0,
            'path' => '/1', 'depth' => 1, 'visible' => 1, 'sortorder' => 0,
            'timecreated' => $now, 'timemodified' => $now,
        ]);
    }

    private function user_at(string $path): \stdClass {
        global $DB;
        $u = $this->getDataGenerator()->create_user();
        $DB->set_field('user', 'open_path', $path, ['id' => $u->id]);
        return $DB->get_record('user', ['id' => $u->id], '*', MUST_EXIST);
    }

    /** A tenant admin: the stock manager-archetype role at system context. */
    private function tenant_admin(string $path): \stdClass {
        global $DB;
        $u = $this->user_at($path);
        $managerid = (int) $DB->get_field('role', 'id', ['shortname' => 'manager'], MUST_EXIST);
        role_assign($managerid, $u->id, \context_system::instance()->id);
        accesslib_clear_all_caches_for_unit_testing();
        return $u;
    }

    private function seed_evaluation(string $name, int $anonymous, array $extra = []): int {
        global $DB;
        $now = time();
        return (int) $DB->insert_record('local_sentientia_evaluation', (object) ($extra + [
            'name'              => $name,
            'description'       => '',
            'kirkpatrick_level' => 1,
            'trigger_event'     => 'manual',
            'days_after'        => 0,
            'costcenterid'      => $this->org1,
            'open_path'         => '/1',
            'status'            => evaluation_manager::STATUS_ACTIVE,
            'anonymous'         => $anonymous,
            'timecreated'       => $now,
            'timemodified'      => $now,
        ]));
    }

    private function seed_question(int $evaluationid, int $anonymous, int $sortorder = 0): int {
        global $DB;
        return (int) $DB->insert_record('local_sentientia_evaluation_questions', (object) [
            'evaluationid' => $evaluationid,
            'questiontype' => 'rating',
            'questiontext' => 'Question ' . $sortorder,
            'options'      => null,
            'required'     => 1,
            'anonymous'    => $anonymous,
            'sortorder'    => $sortorder,
            'timecreated'  => time(),
        ]);
    }

    /** A response row as submit_response() stores it (userid 0 when anonymous). */
    private function seed_response(int $evaluationid, int $storeduserid, array $answers,
                                   int $timesubmitted = 0): int {
        global $DB;
        return (int) $DB->insert_record('local_sentientia_evaluation_responses', (object) [
            'evaluationid'  => $evaluationid,
            'userid'        => $storeduserid,
            'response_data' => json_encode($answers),
            'timesubmitted' => $timesubmitted ?: time(),
        ]);
    }

    private function seed_assignment(int $evaluationid, int $userid, string $status): void {
        global $DB;
        $now = time();
        $DB->insert_record('local_sentientia_evaluation_assign', (object) [
            'evaluationid'  => $evaluationid,
            'userid'        => $userid,
            'trigger_event' => 'manual',
            'source_id'     => 0,
            'status'        => $status,
            'responded_at'  => $status === 'responded' ? $now : null,
            'timecreated'   => $now,
            'timemodified'  => $now,
        ]);
    }

    private function evaluation(int $id): \stdClass {
        global $DB;
        return $DB->get_record('local_sentientia_evaluation', ['id' => $id], '*', MUST_EXIST);
    }

    private function assert_refused(callable $fn, string $errorcode, string $why): void {
        try {
            $fn();
            $this->fail($why);
        } catch (\moodle_exception $e) {
            $this->assertSame($errorcode, $e->errorcode, $why);
        }
    }

    /** A CSV 'Submitted' cell that carries the day only. */
    private function assert_day_only(string $cell, string $why): void {
        $this->assertMatchesRegularExpression('/^\d{4}-\d{2}-\d{2}$/', $cell, $why);
    }

    public function test_unticked_evaluation_keeps_respondents_hidden(): void {
        global $DB;
        $responder = $this->user_at('/1/5');
        $eid = $this->seed_evaluation('Culture pulse', 1);
        $qid = $this->seed_question($eid, 0);
        $anonresp = $this->seed_response($eid, 0, [$qid => 2]);
        $this->seed_assignment($eid, (int) $responder->id, 'responded');

        // The flag switched off after the fact - as any evaluation edited
        // before update() started refusing it may carry.
        $DB->set_field('local_sentientia_evaluation', 'anonymous', 0, ['id' => $eid]);
        // A later, named response (userid stored).
        $late = $this->user_at('/1/6');
        $namedresp = $this->seed_response($eid, (int) $late->id, [$qid => 4]);

        $this->setUser($this->tenant_admin('/1'));
        $eval = $this->evaluation($eid);
        $this->assertTrue(evaluation_manager::identity_protected($eval),
            'A userid-0 response means answers were collected anonymously.');
        $this->assertTrue(evaluation_manager::respondents_hidden($eval, 'responded'));
        $this->assertSame([], evaluation_manager::list_assignments_for_view($eval, 'responded'),
            'Unticking "Collect responses anonymously" must not bring back the names and '
            . 'minute-exact response times of the people who answered anonymously.');
        $this->assertCount(1, evaluation_manager::list_assignments($eid, 'responded'),
            'The tab badge still counts them.');

        $questions = evaluation_manager::get_questions($eid);
        foreach ([$anonresp, $namedresp] as $rid) {
            $row = evaluation_manager::response_to_csv_row(
                $DB->get_record('local_sentientia_evaluation_responses', ['id' => $rid]), $questions, $eval);
            $this->assertSame('(anonymous)', $row[1], 'No row of this evaluation names its respondent.');
            $this->assertSame('', $row[2]);
            $this->assertStringNotContainsString($late->email, implode(',', $row));
            $this->assert_day_only($row[0], 'exportcsv.php: the minute would match the answer to a name.');
        }
        // Passing the precomputed flag, as exportcsv.php does, gives the same row.
        $response = $DB->get_record('local_sentientia_evaluation_responses', ['id' => $namedresp]);
        $this->assertSame(evaluation_manager::response_to_csv_row($response, $questions, $eval),
            evaluation_manager::response_to_csv_row($response, $questions, $eval,
                evaluation_manager::identity_protected($eval)));
    }

    public function test_anonymity_cannot_be_switched_off_once_answered(): void {
        global $DB;
        $answered = $this->seed_evaluation('POSH pulse', 1);
        $qid = $this->seed_question($answered, 0);
        $this->seed_response($answered, 0, [$qid => 3]);
        $this->setUser($this->tenant_admin('/1'));

        $this->assertTrue(evaluation_manager::anonymity_locked($answered));
        $this->assert_refused(
            fn() => evaluation_manager::update($answered, (object) ['anonymous' => 0]),
            'error_anonymity_locked',
            'update() must refuse to make an answered anonymous evaluation named.');
        $this->assertSame(1, (int) $DB->get_field('local_sentientia_evaluation', 'anonymous',
            ['id' => $answered]));

        // Everything else stays editable, and re-affirming anonymity is fine.
        evaluation_manager::update($answered, (object) ['name' => 'Renamed', 'anonymous' => 1]);
        $this->assertSame('Renamed', $DB->get_field('local_sentientia_evaluation', 'name', ['id' => $answered]));

        // No response yet - only the trigger queue's pending shell row
        // (timesubmitted 0): nobody has answered, so the switch is free.
        $unanswered = $this->seed_evaluation('Draft pulse', 1);
        $DB->insert_record('local_sentientia_evaluation_responses', (object) [
            'evaluationid' => $unanswered, 'userid' => (int) $this->user_at('/1/7')->id,
            'response_data' => '{}', 'timesubmitted' => 0,
        ]);
        $this->assertFalse(evaluation_manager::anonymity_locked($unanswered));
        evaluation_manager::update($unanswered, (object) ['anonymous' => 0]);
        $this->assertSame(0, (int) $DB->get_field('local_sentientia_evaluation', 'anonymous',
            ['id' => $unanswered]));

        // A named evaluation with responses may still be made anonymous.
        $named = $this->seed_evaluation('Named', 0);
        $nq = $this->seed_question($named, 0);
        $this->seed_response($named, (int) $this->user_at('/1/8')->id, [$nq => 5]);
        evaluation_manager::update($named, (object) ['anonymous' => 1]);
        $this->assertSame(1, (int) $DB->get_field('local_sentientia_evaluation', 'anonymous', ['id' => $named]));
        $this->assert_refused(fn() => evaluation_manager::update($named, (object) ['anonymous' => 0]),
            'error_anonymity_locked', 'Once anonymous with answers in, it stays anonymous.');
    }

    public function test_edit_evaluation_form_refuses_unticking_anonymity(): void {
        $answered = $this->seed_evaluation('POSH pulse', 1);
        $qid = $this->seed_question($answered, 0);
        $this->seed_response($answered, 0, [$qid => 3]);
        $fresh = $this->seed_evaluation('Fresh', 1);
        $this->setUser($this->tenant_admin('/1'));

        $form = new form\edit_evaluation(null, null, 'post', '', null, true,
            form\edit_evaluation::mock_ajax_submit(['evaluationid' => $answered]), true);
        $base = ['name' => 'X', 'timeopen' => 0, 'timeclose' => 0];

        $errors = $form->validation($base + ['evaluationid' => $answered, 'anonymous' => 0], []);
        $this->assertSame(get_string('error_anonymity_locked', 'local_sentientia_evaluation'),
            $errors['anonymous'] ?? null, 'The form must say why the box cannot be unticked.');
        $this->assertArrayNotHasKey('anonymous',
            $form->validation($base + ['evaluationid' => $answered, 'anonymous' => 1], []));
        $this->assertArrayNotHasKey('anonymous',
            $form->validation($base + ['evaluationid' => $fresh, 'anonymous' => 0], []),
            'No response yet: anonymity may still be switched off.');
        $this->assertArrayNotHasKey('anonymous',
            $form->validation($base + ['evaluationid' => 0, 'anonymous' => 0], []));
    }

    public function test_anonymous_question_keeps_respondents_hidden(): void {
        global $DB;
        $responder = $this->user_at('/1/5');
        $eid = $this->seed_evaluation('Manager feedback', 0);
        $named = $this->seed_question($eid, 0, 0);
        $anonq = $this->seed_question($eid, 1, 1);
        $rid = $this->seed_response($eid, (int) $responder->id, [$named => 4, $anonq => 1]);
        $this->seed_assignment($eid, (int) $responder->id, 'responded');
        $this->setUser($this->tenant_admin('/1'));
        $eval = $this->evaluation($eid);

        $this->assertTrue(evaluation_manager::identity_protected($eval));
        $this->assertTrue(evaluation_manager::respondents_hidden($eval, 'responded'));
        $this->assertSame([], evaluation_manager::list_assignments_for_view($eval, 'responded'),
            'An anonymous question\'s answers could be matched to the Responded tab by time.');
        $row = evaluation_manager::response_to_csv_row(
            $DB->get_record('local_sentientia_evaluation_responses', ['id' => $rid]),
            evaluation_manager::get_questions($eid), $eval);
        $this->assertSame('(question-anonymous)', $row[1]);
        $this->assertStringNotContainsString($responder->email, implode(',', $row));
        $this->assert_day_only($row[0], 'The CSV must not give the minute next to an anonymous answer.');

        // The question's anonymity is locked once answered...
        $this->assertTrue(evaluation_manager::question_anonymity_locked($anonq));
        $this->assert_refused(
            fn() => evaluation_manager::update_question($anonq, (object) ['anonymous' => 0]),
            'error_question_anonymity_locked',
            'Unticking the question would put a name on every one of its answers.');
        $this->assertSame(1, (int) $DB->get_field('local_sentientia_evaluation_questions', 'anonymous',
            ['id' => $anonq]));
        // ...and it cannot be deleted either: that would drop the evaluation
        // out of identity_protected() and bring the Responded tab back.
        $this->assert_refused(
            fn() => evaluation_manager::delete_question($anonq),
            'error_question_anonymity_delete_locked',
            'Deleting the answered anonymous question would undo the lock.');
        $this->assertTrue($DB->record_exists('local_sentientia_evaluation_questions', ['id' => $anonq]));
        $this->assertTrue(evaluation_manager::identity_protected($this->evaluation($eid)));
        // ...but its text and the named question stay editable.
        evaluation_manager::update_question($anonq, (object) ['questiontext' => 'Reworded', 'anonymous' => 1]);
        $this->assertSame('Reworded', $DB->get_field('local_sentientia_evaluation_questions', 'questiontext',
            ['id' => $anonq]));
        evaluation_manager::update_question($named, (object) ['anonymous' => 1]);

        $form = new form\edit_question(null, null, 'post', '', null, true,
            form\edit_question::mock_ajax_submit(['questionid' => $anonq, 'evaluationid' => $eid]), true);
        $errors = $form->validation(['questionid' => $anonq, 'questiontype' => 'rating',
            'questiontext' => 'Reworded', 'anonymous' => 0], []);
        $this->assertSame(get_string('error_question_anonymity_locked', 'local_sentientia_evaluation'),
            $errors['anonymous'] ?? null);

        // An anonymous question nobody has answered yet can still be cleared.
        $other = $this->seed_evaluation('Unanswered', 0);
        $otherq = $this->seed_question($other, 1);
        $this->assertFalse(evaluation_manager::question_anonymity_locked($otherq));
        evaluation_manager::update_question($otherq, (object) ['anonymous' => 0]);
        $this->assertSame(0, (int) $DB->get_field('local_sentientia_evaluation_questions', 'anonymous',
            ['id' => $otherq]));
    }

    public function test_iso_submitted_label_is_zero_padded(): void {
        // A single-digit day: userdate() with its default $fixday strips the
        // zero ('2026-10-5'), which broke the ISO CSV column on days 1-9.
        $ts = make_timestamp(2026, 10, 5, 14, 31);
        $this->assertSame('2026-10-05', evaluation_manager::submitted_label($ts, true, true));
        $this->assertSame('2026-10-05 14:31', evaluation_manager::submitted_label($ts, false, true));
    }

    public function test_named_evaluation_is_unchanged(): void {
        global $DB;
        $responder = $this->user_at('/1/5');
        $eid = $this->seed_evaluation('POSH feedback', 0);
        $qid = $this->seed_question($eid, 0);
        $rid = $this->seed_response($eid, (int) $responder->id, [$qid => 5]);
        $this->seed_assignment($eid, (int) $responder->id, 'responded');
        $this->setUser($this->tenant_admin('/1'));
        $eval = $this->evaluation($eid);

        $this->assertFalse(evaluation_manager::identity_protected($eval));
        $this->assertFalse(evaluation_manager::respondents_hidden($eval, 'responded'));
        $this->assertSame([(int) $responder->id], array_values(array_map('intval',
            array_column(evaluation_manager::list_assignments_for_view($eval, 'responded'), 'userid'))));
        $row = evaluation_manager::response_to_csv_row(
            $DB->get_record('local_sentientia_evaluation_responses', ['id' => $rid]),
            evaluation_manager::get_questions($eid), $eval);
        $this->assertSame(fullname($responder), $row[1]);
        $this->assertSame($responder->email, $row[2]);
        $this->assertMatchesRegularExpression('/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}$/', $row[0],
            'A named evaluation keeps its minute-exact submission time.');
    }

    public function test_date_filters_are_whole_days(): void {
        $this->assertSame([
            'date_from' => strtotime('2026-09-25'),
            'date_to'   => strtotime('2026-09-25 23:59:59'),
        ], evaluation_manager::response_filter_days('2026-09-25', '2026-09-25'),
            'The documented YYYY-MM-DD filter is unchanged.');
        $this->assertSame(['date_from' => strtotime('2026-09-25 00:00:00')],
            evaluation_manager::response_filter_days('2026-09-25 14:31', ''),
            'A time in date_from must not narrow the export to the minute - that would recover '
            . 'the submission time the CSV withholds for a protected evaluation.');
        $this->assertSame(['date_to' => strtotime('2026-09-25 23:59:59')],
            evaluation_manager::response_filter_days('', '2026-09-25 14:32'));
        $this->assertSame([], evaluation_manager::response_filter_days('', ''));
        $this->assertSame([], evaluation_manager::response_filter_days('not a date', ''));
    }

    public function test_response_notification_hides_the_responder_of_an_anonymous_question(): void {
        global $DB;
        $responder = $this->user_at('/1/5');
        $eid = $this->seed_evaluation('Manager feedback', 0, ['notify_admin_on_response' => 1]);
        $named = $this->seed_question($eid, 0, 0);
        $anonq = $this->seed_question($eid, 1, 1);

        $sink = $this->redirectMessages();
        evaluation_manager::submit_response($eid, (int) $responder->id, [$named => 4, $anonq => 1]);
        $messages = $sink->get_messages();
        $sink->close();

        $this->assertNotEmpty($messages);
        foreach ($messages as $message) {
            $this->assertStringNotContainsString($responder->email, (string) $message->fullmessage,
                'A named notification stamped with the submission minute would name the anonymous answer.');
            $this->assertStringContainsString(
                get_string('eval_response_responder_anonymous', 'local_sentientia_evaluation'),
                (string) $message->fullmessage);
        }
        $this->assertSame((int) $responder->id, (int) $DB->get_field('local_sentientia_evaluation_responses',
            'userid', ['evaluationid' => $eid]), 'Only the eval-level flag stores userid 0.');
    }
}
