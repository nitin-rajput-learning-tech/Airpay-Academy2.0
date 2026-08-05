<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

namespace local_sentientia_authoring;

/**
 * Course-builder tests — the last gate-#3 stub replacement.
 *
 * Full real pipeline on a fresh site: mock generation → parse → persist →
 * review → BUILD → a real hidden course with a book of cards and a mastery
 * quiz whose questions live in the course's default shared question bank.
 * No live API calls anywhere.
 *
 * @package    local_sentientia_authoring
 * @covers     \local_sentientia_authoring\course_builder
 */
final class course_builder_test extends \advanced_testcase {

    use \local_sentientia_org\test\bizlms_fixture;

    protected function setUp(): void {
        parent::setUp();
        $this->ensure_bizlms_schema();
    }

    /**
     * Seed a draft through the real pipeline up to APPROVED.
     *
     * @param int $ownerid
     * @param int $masteryscore
     * @return int Draft id.
     */
    private function seed_approved_draft(int $ownerid, int $masteryscore = 70): int {
        global $DB;
        $did = draft_manager::create_pending($ownerid, 'POSH essentials',
            'Build a course about POSH escalation.', 'prompt', 'en',
            'claude-sonnet-4-6', $masteryscore);

        $mock = course_generator::call_mock('POSH escalation source.', 3, 2);
        $parsed = response_parser::parse($mock['body']);
        draft_manager::persist_generation($did, $parsed->cards, $parsed->questions, 0, 0, 'mock');

        $DB->set_field(draft_manager::CARD_TABLE, 'status',
            draft_manager::ITEM_APPROVED, ['draftid' => $did]);
        $DB->set_field(draft_manager::QUESTION_TABLE, 'status',
            draft_manager::ITEM_APPROVED, ['draftid' => $did]);
        $status = draft_manager::finalise_review($did, $ownerid);
        $this->assertSame(draft_manager::STATUS_APPROVED, $status);
        return $did;
    }

    /**
     * Happy path: hidden course, book with a chapter per card, mastery quiz
     * with gradepass and bank-imported questions, draft marked published
     * with the REAL course id.
     */
    public function test_build_creates_hidden_course_with_book_and_quiz(): void {
        global $DB, $USER;
        $this->resetAfterTest();
        $this->setAdminUser();

        $did = $this->seed_approved_draft((int) $USER->id, 80);
        $result = course_builder::build($did, $USER, true);

        $this->assertGreaterThan(0, $result->courseid);
        $this->assertSame(3, $result->cardcount);
        $this->assertSame(2, $result->questioncount);

        // Hidden course shell.
        $course = get_course($result->courseid);
        $this->assertSame(0, (int) $course->visible);
        $this->assertSame('POSH essentials', $course->fullname);

        // Book with 3 chapters.
        $bookcm = get_coursemodule_from_id('book', $result->bookcmid, $result->courseid,
            false, MUST_EXIST);
        $this->assertSame(3, (int) $DB->count_records('book_chapters',
            ['bookid' => $bookcm->instance]));

        // Mastery quiz: 2 slots, gradepass = 80% of grade 10 = 8.00.
        $this->assertGreaterThan(0, $result->quizid);
        $this->assertSame(2, (int) $DB->count_records('quiz_slots', ['quizid' => $result->quizid]));
        $gradepass = (float) $DB->get_field('grade_items', 'gradepass',
            ['itemtype' => 'mod', 'itemmodule' => 'quiz', 'iteminstance' => $result->quizid]);
        $this->assertEqualsWithDelta(8.0, $gradepass, 0.001);

        // Questions in the course's default shared bank.
        $qbankcm = \core_question\local\bank\question_bank_helper::get_default_open_instance_system_type(
            $course, false);
        $this->assertNotNull($qbankcm);
        $category = question_get_default_category($qbankcm->context->id, false);
        $this->assertSame(2, (int) $DB->count_records('question_bank_entries',
            ['questioncategoryid' => $category->id]));

        // Draft published with the REAL course id.
        $draft = $DB->get_record(draft_manager::DRAFT_TABLE, ['id' => $did], '*', MUST_EXIST);
        $this->assertSame(draft_manager::STATUS_PUBLISHED, $draft->status);
        $this->assertSame($result->courseid, (int) $draft->published_courseid);
    }

    /**
     * Rejected cards and questions stay behind.
     */
    public function test_build_skips_rejected_items(): void {
        global $DB, $USER;
        $this->resetAfterTest();
        $this->setAdminUser();

        $did = $this->seed_approved_draft((int) $USER->id);
        $cards = $DB->get_records(draft_manager::CARD_TABLE, ['draftid' => $did], 'sortorder ASC');
        $DB->set_field(draft_manager::CARD_TABLE, 'status',
            draft_manager::ITEM_REJECTED, ['id' => reset($cards)->id]);
        $questions = $DB->get_records(draft_manager::QUESTION_TABLE, ['draftid' => $did], 'sortorder ASC');
        $DB->set_field(draft_manager::QUESTION_TABLE, 'status',
            draft_manager::ITEM_REJECTED, ['id' => reset($questions)->id]);

        $result = course_builder::build($did, $USER, true);
        $this->assertSame(2, $result->cardcount);
        $this->assertSame(1, $result->questioncount);
        $bookcm = get_coursemodule_from_id('book', $result->bookcmid, $result->courseid,
            false, MUST_EXIST);
        $this->assertSame(2, (int) $DB->count_records('book_chapters', ['bookid' => $bookcm->instance]));
        $this->assertSame(1, (int) $DB->count_records('quiz_slots', ['quizid' => $result->quizid]));
    }

    /**
     * A draft with approved cards but zero approved questions builds a
     * course WITHOUT a quiz (quizid 0) rather than failing.
     */
    public function test_build_without_questions_skips_quiz(): void {
        global $DB, $USER;
        $this->resetAfterTest();
        $this->setAdminUser();

        $did = $this->seed_approved_draft((int) $USER->id);
        $DB->set_field(draft_manager::QUESTION_TABLE, 'status',
            draft_manager::ITEM_REJECTED, ['draftid' => $did]);

        $result = course_builder::build($did, $USER, true);
        $this->assertSame(0, $result->quizid);
        $this->assertSame(0, (int) $DB->count_records('quiz', []));
        $this->assertGreaterThan(0, $result->courseid);
    }

    /**
     * Non-approved drafts are refused (errorcode identity).
     */
    public function test_build_requires_approved_status(): void {
        global $USER;
        $this->resetAfterTest();
        $this->setAdminUser();

        $did = draft_manager::create_pending((int) $USER->id, 'D', 'src', 'prompt', 'en',
            'claude-sonnet-4-6', 70);
        try {
            course_builder::build($did, $USER, true);
            $this->fail('Expected moodle_exception err_publish_not_approved');
        } catch (\moodle_exception $e) {
            $this->assertSame('err_publish_not_approved', $e->errorcode);
        }
    }

    /**
     * Capability gate: no moodle/course:create → refused, and NOTHING is
     * created (transactional).
     */
    public function test_build_denies_without_capability(): void {
        global $DB;
        $this->resetAfterTest();

        $user = $this->getDataGenerator()->create_user();
        $this->setUser($user);
        $did = $this->seed_approved_draft((int) $user->id);
        $before = (int) $DB->count_records('course', []);

        try {
            course_builder::build($did, $user, true);
            $this->fail('Expected a capability exception');
        } catch (\required_capability_exception $e) {
            $this->assertSame($before, (int) $DB->count_records('course', []));
            $draft = $DB->get_record(draft_manager::DRAFT_TABLE, ['id' => $did], '*', MUST_EXIST);
            $this->assertSame(draft_manager::STATUS_APPROVED, $draft->status);
        }
    }

    /**
     * GIFT dispatch per qtype: mrq uses canonical weighted all-tilde syntax,
     * match uses '=left -> right' pairs.
     */
    public function test_question_gift_qtypes(): void {
        $draft = (object) ['id' => 7];

        $mrq = (object) [
            'qtype' => 'mrq', 'qtext' => 'Pick two.',
            'qoptions_json' => json_encode(['A', 'B', 'C']),
            'qanswer' => json_encode([0, 2]),
            'qfeedback_correct' => 'Yes', 'qfeedback_incorrect' => 'No',
            'qexplanation' => '',
        ];
        $gift = course_builder::question_gift($draft, $mrq, 1);
        $this->assertStringContainsString('~%50%A#Yes', $gift);
        $this->assertStringContainsString('~%-100%B#No', $gift);
        $this->assertStringContainsString('~%50%C#Yes', $gift);

        $match = (object) [
            'qtype' => 'match', 'qtext' => 'Match them.',
            'qoptions_json' => json_encode([
                ['left' => 'One', 'right' => 'Ek'],
                ['left' => 'Two', 'right' => 'Do'],
            ]),
            'qanswer' => '', 'qfeedback_correct' => '', 'qfeedback_incorrect' => '',
            'qexplanation' => 'Numbers.',
        ];
        $gift = course_builder::question_gift($draft, $match, 2);
        $this->assertStringContainsString('=One -> Ek', $gift);
        $this->assertStringContainsString('=Two -> Do', $gift);
        $this->assertStringContainsString('####Numbers.', $gift);

        // Canonical MRQ weights (33.33 would fail matchgrades='error').
        $this->assertSame('33.33333', course_builder::mrq_weight(3));
        $this->assertSame('16.66667', course_builder::mrq_weight(6));
    }

    /**
     * Card HTML composition: flip-back callout + narration transcript,
     * with HTML in the flip-back escaped.
     */
    public function test_card_html(): void {
        $card = (object) [
            'body'      => "Line one.\n\nLine two.",
            'flip_back' => 'Remember <this>',
            'narration' => 'Spoken version.',
        ];
        $html = course_builder::card_html($card);
        $this->assertStringContainsString('Line one.', $html);
        $this->assertStringContainsString('alert-info', $html);
        $this->assertStringContainsString('Remember &lt;this&gt;', $html);
        $this->assertStringContainsString('<details', $html);
        $this->assertStringContainsString('Spoken version.', $html);
    }
}
