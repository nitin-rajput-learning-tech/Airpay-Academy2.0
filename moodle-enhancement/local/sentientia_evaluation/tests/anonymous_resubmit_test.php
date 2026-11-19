<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

namespace local_sentientia_evaluation;

defined('MOODLE_INTERNAL') || die();

/**
 * V25 (11-18 surface walk): an anonymous evaluation could be answered twice.
 *
 * has_user_responded() returned false for every anonymous form, because the
 * response row is stored with userid 0, and submit_response() skipped the
 * check for anonymous forms altogether. An assigned user could answer the
 * same anonymous form again and again, and every answer was counted.
 *
 * Locks in:
 *   - an assigned user's second answer to an anonymous form is refused
 *     (alreadyresponded): the assignment row, marked 'responded' with the
 *     real userid, is the per-user marker;
 *   - multiple_submit (pulse surveys) still allows it;
 *   - with no assignment row there is no per-user marker, so
 *     has_user_responded() is false (the documented limit);
 *   - userid 0 is never "responded".
 *
 * @package    local_sentientia_evaluation
 * @category   test
 */
final class anonymous_resubmit_test extends \advanced_testcase {

    /**
     * An active anonymous form with one required yes/no question.
     *
     * @param int $multiplesubmit 1 for a pulse survey
     * @return int[] [evaluation id, question id]
     */
    private function seed_anonymous_form(int $multiplesubmit = 0): array {
        $eid = (int) evaluation_manager::create((object) [
            'name'              => 'V25 anonymous form',
            'kirkpatrick_level' => 1,
            'trigger_event'     => 'manual',
            'status'            => evaluation_manager::STATUS_ACTIVE,
            'anonymous'         => 1,
            'multiple_submit'   => $multiplesubmit,
        ]);
        $qid = (int) evaluation_manager::create_question((object) [
            'evaluationid' => $eid,
            'questiontype' => 'yesno',
            'questiontext' => 'Was it useful?',
            'required'     => 1,
        ]);
        return [$eid, $qid];
    }

    public function test_second_anonymous_submission_is_refused(): void {
        global $DB;
        $this->resetAfterTest();
        [$eid, $qid] = $this->seed_anonymous_form();
        $u = $this->getDataGenerator()->create_user();
        evaluation_manager::ensure_assignment($eid, (int) $u->id);

        $this->assertFalse(evaluation_manager::has_user_responded($eid, (int) $u->id), 'not answered yet');
        evaluation_manager::submit_response($eid, (int) $u->id, [$qid => 'yes']);

        // The answer stays anonymous; the assignment row carries the marker.
        $this->assertSame(1, $DB->count_records('local_sentientia_evaluation_responses',
            ['evaluationid' => $eid, 'userid' => 0]));
        $this->assertSame(0, $DB->count_records('local_sentientia_evaluation_responses',
            ['evaluationid' => $eid, 'userid' => $u->id]));
        $this->assertSame('responded', $DB->get_field('local_sentientia_evaluation_assign', 'status',
            ['evaluationid' => $eid, 'userid' => $u->id]));
        $this->assertTrue(evaluation_manager::has_user_responded($eid, (int) $u->id));

        try {
            evaluation_manager::submit_response($eid, (int) $u->id, [$qid => 'no']);
            $this->fail('A second answer to an anonymous form should be refused.');
        } catch (\moodle_exception $e) {
            $this->assertSame('alreadyresponded', $e->errorcode);
        }
        $this->assertSame(1, $DB->count_records('local_sentientia_evaluation_responses', ['evaluationid' => $eid]),
            'the refused answer was not stored');
    }

    public function test_multiple_submit_allows_a_second_anonymous_submission(): void {
        global $DB;
        $this->resetAfterTest();
        [$eid, $qid] = $this->seed_anonymous_form(1);
        $u = $this->getDataGenerator()->create_user();
        evaluation_manager::ensure_assignment($eid, (int) $u->id);

        evaluation_manager::submit_response($eid, (int) $u->id, [$qid => 'yes']);
        $this->assertFalse(evaluation_manager::has_user_responded($eid, (int) $u->id),
            'a pulse survey never blocks a fresh answer');
        evaluation_manager::submit_response($eid, (int) $u->id, [$qid => 'no']);
        $this->assertSame(2, $DB->count_records('local_sentientia_evaluation_responses', ['evaluationid' => $eid]));
    }

    public function test_anonymous_form_without_an_assignment_has_no_per_user_marker(): void {
        $this->resetAfterTest();
        [$eid, $qid] = $this->seed_anonymous_form();
        $u = $this->getDataGenerator()->create_user();

        // Answered from a direct link, never assigned: nothing ties the answer to the user.
        evaluation_manager::submit_response($eid, (int) $u->id, [$qid => 'yes']);
        $this->assertFalse(evaluation_manager::has_user_responded($eid, (int) $u->id));
    }

    public function test_userid_zero_is_never_responded(): void {
        $this->resetAfterTest();
        [$eid, $qid] = $this->seed_anonymous_form();
        $u = $this->getDataGenerator()->create_user();
        evaluation_manager::ensure_assignment($eid, (int) $u->id);
        evaluation_manager::submit_response($eid, (int) $u->id, [$qid => 'yes']);

        $this->assertFalse(evaluation_manager::has_user_responded($eid, 0));
        $this->assertFalse(evaluation_manager::has_user_responded($eid, -1));
    }
}
