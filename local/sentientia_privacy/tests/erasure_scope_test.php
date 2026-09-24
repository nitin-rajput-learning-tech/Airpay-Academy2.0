<?php
// This file is part of Sentientia LMS.

/**
 * What a right-to-erasure must NOT do: touch another person's rows, or
 * destroy the learning and compliance records this flow promises to keep.
 *
 * Every case here is a defect an audit of the 38 Sentientia privacy providers
 * found on 2026-09-24, after process_deletion() began calling them all. Each
 * seeds rows that belong to SOMEONE ELSE next to the data subject's, erases
 * the subject, and asserts on the other person's rows - the thing no earlier
 * test looked at.
 *
 * @package    local_sentientia_privacy
 * @category   test
 * @copyright  2026 Airpay Payment Services
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_sentientia_privacy;

defined('MOODLE_INTERNAL') || die();

/**
 * @covers \local_sentientia_privacy\privacy_manager
 */
final class erasure_scope_test extends \advanced_testcase {

    use \local_sentientia_org\test\bizlms_fixture;

    /** @var \stdClass The person being erased. */
    private $subject;
    /** @var \stdClass Somebody else in the same tenant. */
    private $other;
    /** @var \stdClass A third person (a decider, an admin). */
    private $third;

    /**
     * Insert a row, filling every NOT NULL column without a default so the
     * test only has to name the columns it cares about.
     */
    private function row(string $table, array $values): int {
        global $DB;
        $record = [];
        foreach ($DB->get_columns($table) as $name => $col) {
            if ($name === 'id' || !$col->not_null || $col->has_default) {
                continue;
            }
            $record[$name] = in_array($col->meta_type, ['I', 'N', 'R', 'F'], true) ? 1 : 'x';
        }
        return $DB->insert_record($table, (object) ($values + $record));
    }

    private function erase_subject(): \stdClass {
        global $DB;
        $requestid = $DB->insert_record('local_privacy_requests', (object) [
            'userid' => $this->subject->id, 'request_type' => 'account_delete',
            'status' => 'pending', 'timecreated' => time(),
        ]);
        privacy_manager::process_deletion($requestid, (int) get_admin()->id);
        return $DB->get_record('local_privacy_requests', ['id' => $requestid], '*', MUST_EXIST);
    }

    protected function setUp(): void {
        parent::setUp();
        $this->resetAfterTest();
        $this->ensure_bizlms_schema();
        $gen = $this->getDataGenerator();
        $this->subject = $gen->create_user(['open_path' => '/1/2']);
        $this->other = $gen->create_user(['open_path' => '/1/2']);
        $this->third = $gen->create_user(['open_path' => '/1']);
    }

    public function test_xapi_redacts_only_the_subjects_statements(): void {
        global $DB;
        $unresolved = json_encode(['mbox' => 'mailto:external@example.com']);
        // An external client's statement whose actor never resolved to a
        // local user: actorid NULL. The old code redacted every one of these.
        $theirs = $this->row('local_sentientia_xapi_stmts', [
            'statementid' => 'other-stmt', 'actorid' => null, 'actor' => $unresolved,
        ]);
        $mine = $this->row('local_sentientia_xapi_stmts', [
            'statementid' => 'subject-stmt', 'actorid' => $this->subject->id,
            'actor' => json_encode(['mbox' => 'mailto:subject@example.com']),
        ]);
        $attempt = $this->row('local_sentientia_xapi_cmi5', [
            'userid' => $this->subject->id, 'status' => 'passed', 'launchtoken' => 'secret',
        ]);

        $this->erase_subject();

        $this->assertSame($unresolved, $DB->get_field('local_sentientia_xapi_stmts', 'actor', ['id' => $theirs]),
            'Another actor\'s identity must not be overwritten.');
        $this->assertStringNotContainsString('subject@example.com',
            $DB->get_field('local_sentientia_xapi_stmts', 'actor', ['id' => $mine]));
        // The DPDP flow keeps the cmi5 attempt record, minus its credentials.
        $kept = $DB->get_record('local_sentientia_xapi_cmi5', ['id' => $attempt], '*', MUST_EXIST);
        $this->assertSame('passed', $kept->status);
        $this->assertNull($kept->launchtoken);
    }

    public function test_evaluation_leaves_other_system_assigned_rows_alone(): void {
        global $DB;
        $this->row('local_sentientia_evaluation_assign', ['userid' => $this->subject->id, 'evaluationid' => 1]);
        $theirs = $this->row('local_sentientia_evaluation_assign', [
            'userid' => $this->other->id, 'evaluationid' => 1, 'assigned_by_userid' => null,
        ]);

        $this->erase_subject();

        $this->assertEquals(0, $DB->count_records('local_sentientia_evaluation_assign', ['userid' => $this->subject->id]));
        $this->assertNull($DB->get_field('local_sentientia_evaluation_assign', 'assigned_by_userid', ['id' => $theirs]));
    }

    public function test_whatsapp_keeps_other_peoples_consent_provenance(): void {
        global $DB;
        // The subject, as an admin, changed somebody else's opt-in.
        $theirs = $this->row('local_sentientia_user_channel_audit', [
            'userid' => $this->other->id, 'changed_by' => $this->subject->id,
            'field_name' => 'whatsapp_optin', 'old_value' => '0', 'new_value' => '1',
            'ip_address' => '10.0.0.1',
        ]);

        $this->erase_subject();

        $row = $DB->get_record('local_sentientia_user_channel_audit', ['id' => $theirs]);
        $this->assertNotFalse($row, 'The other person\'s consent record must survive.');
        $this->assertSame('1', $row->new_value);
        $this->assertNull($row->changed_by);
        $this->assertNull($row->ip_address, 'The actor\'s IP address is the subject\'s data.');
    }

    public function test_manager_keeps_a_third_partys_decision_note(): void {
        global $DB;
        $theirs = $this->row('local_sentientia_mgr_requests', [
            'userid' => $this->other->id, 'managerid' => $this->subject->id,
            'decided_by' => $this->third->id, 'decision_reason' => 'Refused by the site admin',
        ]);

        $this->erase_subject();

        $row = $DB->get_record('local_sentientia_mgr_requests', ['id' => $theirs], '*', MUST_EXIST);
        $this->assertEquals(0, $row->managerid);
        $this->assertSame('Refused by the site admin', $row->decision_reason);
    }

    public function test_shared_records_survive_with_the_actor_anonymised(): void {
        global $DB;
        $request = $this->row('local_sentientia_courses_requests', ['requester_userid' => $this->subject->id]);
        $draft = $this->row('local_sentientia_aiquiz_draft', ['ownerid' => $this->subject->id]);
        $ledger = $this->row('local_sentientia_ai_ledger', [
            'userid' => $this->subject->id, 'component' => 'local_sentientia_assistant',
            'mode' => 'mock', 'estcost' => 0.25,
        ]);

        $this->erase_subject();

        $this->assertEquals(0, $DB->get_field('local_sentientia_courses_requests', 'requester_userid', ['id' => $request]),
            'A tenant\'s course-share request must stay in the inbox.');
        $this->assertEquals(0, $DB->get_field('local_sentientia_aiquiz_draft', 'ownerid', ['id' => $draft]),
            'A tenant-shared quiz draft must not be deleted.');
        $this->assertEquals(0, $DB->get_field('local_sentientia_ai_ledger', 'userid', ['id' => $ledger]),
            'AI spend must stay on the ledger.');
    }

    public function test_a_reviewer_is_anonymised_and_the_candidates_review_kept(): void {
        global $DB;
        // The subject reviewed SOMEONE ELSE's proctored attempt.
        $session = $this->row('local_sentientia_proctor_sessions', [
            'userid' => $this->other->id, 'quizid' => 1, 'consent_given_at' => time(),
        ]);
        $review = $this->row('local_sentientia_proctor_reviews', [
            'sessionid' => $session, 'reviewer_userid' => $this->subject->id, 'decision' => 'fail',
        ]);

        $this->erase_subject();

        $kept = $DB->get_record('local_sentientia_proctor_reviews', ['id' => $review]);
        $this->assertNotFalse($kept, 'The candidate\'s verdict must survive the reviewer\'s erasure.');
        $this->assertEquals(0, $kept->reviewer_userid);
        $this->assertNotNull($DB->get_field('local_sentientia_proctor_sessions', 'consent_given_at', ['id' => $session]),
            'Another person\'s session is untouched.');
    }

    public function test_a_deciders_note_goes_with_them(): void {
        global $DB;
        $request = $this->row('local_sentientia_courses_requests', [
            'requester_userid' => $this->other->id, 'decided_by' => $this->subject->id,
            'status' => 'rejected', 'decision_reason' => 'written by the subject',
        ]);

        $this->erase_subject();

        $row = $DB->get_record('local_sentientia_courses_requests', ['id' => $request], '*', MUST_EXIST);
        $this->assertEquals(0, $row->decided_by);
        $this->assertNull($row->decision_reason);
        $this->assertEquals($this->other->id, $row->requester_userid, 'The requester is not the subject.');
    }

    public function test_learning_and_compliance_records_are_kept(): void {
        global $DB;
        $completion = $this->row('local_sentientia_learningpath_users', [
            'userid' => $this->subject->id, 'pathid' => 1, 'status' => 2, 'timecompleted' => time(),
        ]);
        $attendance = $this->row('local_sentientia_classroom_attendance', [
            'userid' => $this->subject->id, 'sessionid' => 1, 'status' => 1, 'notes' => 'arrived late',
        ]);
        $exemption = $this->row('local_compliance_exemptions', [
            'userid' => $this->subject->id, 'courseid' => 1, 'reason' => 'medical leave',
            'approved_by' => $this->third->id,
        ]);

        $request = $this->erase_subject();

        $this->assertSame('completed', $request->status, (string) $request->admin_notes);
        $this->assertEquals(2, $DB->get_field('local_sentientia_learningpath_users', 'status', ['id' => $completion]));
        $this->assertEquals(1, $DB->get_field('local_sentientia_classroom_attendance', 'status', ['id' => $attendance]));
        $this->assertNull($DB->get_field('local_sentientia_classroom_attendance', 'notes', ['id' => $attendance]));
        $kept = $DB->get_record('local_compliance_exemptions', ['id' => $exemption], '*', MUST_EXIST);
        $this->assertSame('', $kept->reason, 'Free text that can name a circumstance goes.');
        $this->assertEquals($this->third->id, $kept->approved_by, 'The approver is not the subject.');
    }
}
