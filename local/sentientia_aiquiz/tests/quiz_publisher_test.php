<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

namespace local_sentientia_aiquiz;

/**
 * Phase G.4 publisher tests — the "quiz id 0" stub replacement.
 *
 * Exercises the full real pipeline on a fresh site: mock generation →
 * parse → persist → review → publish → a REAL quiz activity with REAL
 * question-bank questions (5.x default shared mod_qbank instance).
 * No live API calls anywhere (mock body via anthropic_client::call_mock).
 *
 * @package    local_sentientia_aiquiz
 * @covers     \local_sentientia_aiquiz\quiz_publisher
 */
final class quiz_publisher_test extends \advanced_testcase {

    use \local_sentientia_org\test\bizlms_fixture;

    protected function setUp(): void {
        parent::setUp();
        $this->ensure_bizlms_schema();
    }

    /**
     * Seed a draft through the real pipeline up to APPROVED status.
     *
     * @param int $ownerid
     * @param int $courseid
     * @param int $numquestions
     * @return int Draft id.
     */
    private function seed_approved_draft(int $ownerid, int $courseid, int $numquestions = 3): int {
        global $DB;
        $did = draft_manager::create_pending(
            $ownerid, $courseid, 'POSH refresher quiz', 'POSH escalation matrix source.',
            'claude-sonnet-4-6', $numquestions);

        $mock = anthropic_client::call_mock('POSH escalation matrix source.', $numquestions);
        $questions = response_parser::parse($mock['body']);
        draft_manager::persist_questions($did, $questions, 0, 0, 'mock');

        // Approve every question, then finalise (sets draft APPROVED).
        $DB->set_field(draft_manager::QUESTION_TABLE, 'status',
            draft_manager::Q_STATUS_APPROVED, ['draftid' => $did]);
        $status = draft_manager::finalise_review($did, $ownerid);
        $this->assertSame(draft_manager::STATUS_APPROVED, $status);

        return $did;
    }

    /**
     * Happy path: a real, hidden quiz with all approved questions attached.
     */
    public function test_publish_creates_real_quiz(): void {
        global $DB;
        $this->resetAfterTest();
        $this->setAdminUser();
        global $USER;

        $course = $this->getDataGenerator()->create_course();
        $did = $this->seed_approved_draft((int) $USER->id, (int) $course->id, 3);

        $result = quiz_publisher::publish($did, (int) $course->id, $USER, true);

        $this->assertGreaterThan(0, $result->quizid);
        $this->assertSame(3, $result->count);

        // Real quiz record + course module, created HIDDEN.
        $quiz = $DB->get_record('quiz', ['id' => $result->quizid], '*', MUST_EXIST);
        $this->assertSame('POSH refresher quiz', $quiz->name);
        $cm = get_coursemodule_from_instance('quiz', $result->quizid, $course->id, false, MUST_EXIST);
        $this->assertSame(0, (int) $cm->visible);

        // All three questions attached as slots; sumgrades follows.
        $this->assertSame(3, (int) $DB->count_records('quiz_slots', ['quizid' => $quiz->id]));
        $this->assertGreaterThan(0, (float) $DB->get_field('quiz', 'sumgrades', ['id' => $quiz->id]));

        // Draft flipped to pushed with the REAL quiz id (the G.0 stub wrote 0).
        $draft = $DB->get_record(draft_manager::DRAFT_TABLE, ['id' => $did], '*', MUST_EXIST);
        $this->assertSame(draft_manager::STATUS_PUSHED, $draft->status);
        $this->assertSame((int) $quiz->id, (int) $draft->pushed_quizid);

        // Questions exist in the course's default shared bank category.
        $qbankcm = \core_question\local\bank\question_bank_helper::get_default_open_instance_system_type(
            $course, false);
        $this->assertNotNull($qbankcm);
        $category = question_get_default_category($qbankcm->context->id, false);
        $this->assertNotEmpty($category);
        $entries = $DB->count_records('question_bank_entries',
            ['questioncategoryid' => $category->id]);
        $this->assertSame(3, (int) $entries);
    }

    /**
     * Rejected questions stay behind — only approved/edited are pushed.
     */
    public function test_publish_skips_rejected_questions(): void {
        global $DB;
        $this->resetAfterTest();
        $this->setAdminUser();
        global $USER;

        $course = $this->getDataGenerator()->create_course();
        $did = $this->seed_approved_draft((int) $USER->id, (int) $course->id, 3);

        // Reject one of the three.
        $rows = $DB->get_records(draft_manager::QUESTION_TABLE, ['draftid' => $did], 'sortorder ASC');
        $first = reset($rows);
        $DB->set_field(draft_manager::QUESTION_TABLE, 'status',
            draft_manager::Q_STATUS_REJECTED, ['id' => $first->id]);

        $result = quiz_publisher::publish($did, (int) $course->id, $USER, true);
        $this->assertSame(2, $result->count);
        $this->assertSame(2, (int) $DB->count_records('quiz_slots', ['quizid' => $result->quizid]));
    }

    /**
     * A draft that hasn't been finalised cannot be pushed.
     */
    public function test_publish_requires_approved_status(): void {
        global $DB, $USER;
        $this->resetAfterTest();
        $this->setAdminUser();

        $course = $this->getDataGenerator()->create_course();
        $did = draft_manager::create_pending(
            (int) $USER->id, (int) $course->id, 'D', 'src', 'claude-sonnet-4-6', 1);

        try {
            quiz_publisher::publish($did, (int) $course->id, $USER, true);
            $this->fail('Expected moodle_exception push_err_notapproved');
        } catch (\moodle_exception $e) {
            // moodle_exception renders the localised message; the stable
            // machine-readable identity is errorcode.
            $this->assertSame('push_err_notapproved', $e->errorcode);
        }
    }

    /**
     * Capability gate: an actor without course:manageactivities is refused,
     * and NOTHING is created (transactional).
     */
    public function test_publish_denies_without_capability(): void {
        global $DB;
        $this->resetAfterTest();

        $course = $this->getDataGenerator()->create_course();
        $student = $this->getDataGenerator()->create_and_enrol($course, 'student');
        $this->setUser($student);

        $did = $this->seed_approved_draft((int) $student->id, (int) $course->id, 1);

        try {
            quiz_publisher::publish($did, (int) $course->id, $student, true);
            $this->fail('Expected a capability exception');
        } catch (\required_capability_exception $e) {
            // Expected.
            $this->assertSame(0, (int) $DB->count_records('quiz', []));
        }
    }

    /**
     * GIFT escaping neutralises every control character.
     */
    public function test_gift_escape(): void {
        $this->assertSame(
            'a\\~b\\=c\\#d\\{e\\}f\\:g\\\\h',
            quiz_publisher::gift_escape('a~b=c#d{e}f:g\\h'));
    }
}
