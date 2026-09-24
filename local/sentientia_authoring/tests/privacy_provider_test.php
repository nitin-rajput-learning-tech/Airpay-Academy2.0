<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

namespace local_sentientia_authoring;

defined('MOODLE_INTERNAL') || die();

use core_privacy\local\request\approved_contextlist;
use core_privacy\local\request\approved_userlist;
use core_privacy\local\request\userlist;
use core_privacy\tests\provider_testcase;
use local_sentientia_authoring\privacy\provider;

/**
 * Privacy provider tests for local_sentientia_authoring.
 *
 * Written 2026-09-24. Until then delete_data_for_user() deleted every draft
 * (with its cards, questions and voiceovers) and every template the erased
 * person had authored, and get_contexts_for_userid() reported the system
 * context for everybody - so every DPDP erasure deleted tenant-shared work
 * that other authors and reviewers depend on. Erasure now anonymises the
 * author (ownerid 0) and the reviewer (reviewed_by NULL) and keeps the rows.
 * These tests assert on the erased person's rows AND on a second author's.
 *
 * Uses the platform open_path fixture trait: draft_manager / template_manager
 * read mdl_user.open_path, a BizLMS column absent from vanilla PHPUnit.
 *
 * @package    local_sentientia_authoring
 * @category   test
 * @covers     \local_sentientia_authoring\privacy\provider
 * @covers     \local_sentientia_authoring\draft_manager
 * @covers     \local_sentientia_authoring\template_manager
 */
final class privacy_provider_test extends provider_testcase {

    use \local_sentientia_platform\phpunit\open_path_fixture_trait;

    /** @var string */
    private const COMPONENT = 'local_sentientia_authoring';

    /**
     * A user pinned to a tenant root.
     *
     * @param string $openpath
     * @return \stdClass
     */
    private function tenant_user(string $openpath): \stdClass {
        global $DB;
        $u = $this->getDataGenerator()->create_user();
        $DB->set_field('user', 'open_path', $openpath, ['id' => $u->id]);
        $u->open_path = $openpath;
        return $u;
    }

    /**
     * A generated draft owned by $owner, with cards + questions.
     *
     * @param \stdClass $owner
     * @param string    $title
     * @return int Draft id.
     */
    private function generated_draft(\stdClass $owner, string $title): int {
        $did = draft_manager::create_pending((int) $owner->id, $title, 'Source text.',
            'prompt', 'en', 'claude-sonnet-4-6', 70);
        $mock = course_generator::call_mock('Source text.', 2, 1);
        $parsed = response_parser::parse($mock['body']);
        draft_manager::persist_generation($did, $parsed->cards, $parsed->questions, 10, 20, 'mock');
        return $did;
    }

    /**
     * Approve the first card of a draft with a note, then finalise the review.
     *
     * @param int       $draftid
     * @param \stdClass $reviewer
     * @return int The reviewed card's id.
     */
    private function review(int $draftid, \stdClass $reviewer): int {
        global $DB;
        $cards = $DB->get_records(draft_manager::CARD_TABLE, ['draftid' => $draftid], 'sortorder ASC');
        $first = reset($cards);
        draft_manager::review_card((int) $first->id, draft_manager::ITEM_APPROVED,
            ['reviewer_note' => 'Tighten the wording.']);
        draft_manager::finalise_review($draftid, (int) $reviewer->id);
        return (int) $first->id;
    }

    /**
     * @param \stdClass $user
     * @return int[]
     */
    private function contextids_for(\stdClass $user): array {
        return array_map('intval', provider::get_contexts_for_userid((int) $user->id)->get_contextids());
    }

    public function test_contexts_are_reported_only_for_people_with_data(): void {
        $syscontextid = (int) \context_system::instance()->id;
        $author = $this->tenant_user('/1');
        $reviewer = $this->tenant_user('/1');
        $templateauthor = $this->tenant_user('/1');
        $bystander = $this->tenant_user('/1');

        $did = $this->generated_draft($author, 'Author draft');
        $this->review($did, $reviewer);
        template_manager::create((int) $templateauthor->id, 'Their template', 'Body');

        $this->assertSame([$syscontextid], $this->contextids_for($author));
        $this->assertSame([$syscontextid], $this->contextids_for($reviewer),
            'Reviewing someone else\'s draft is still data about the reviewer.');
        $this->assertSame([$syscontextid], $this->contextids_for($templateauthor));
        $this->assertSame([], $this->contextids_for($bystander),
            'Someone who never used the studio holds no data here.');
        // 0 owns the built-in templates and every anonymised row; it is nobody.
        $this->assertSame([], array_map('intval',
            provider::get_contexts_for_userid(0)->get_contextids()));
    }

    public function test_erasing_an_author_keeps_the_tenants_work_and_only_anonymises(): void {
        global $DB;
        $subject = $this->tenant_user('/1/2');
        $other = $this->tenant_user('/1/3');
        $builtins = $DB->count_records(template_manager::TABLE, ['is_builtin' => 1]);

        // The subject's own draft, reviewed by the other author (their note
        // and approval are on it), with a voiceover.
        $subjectdraft = $this->generated_draft($subject, 'Subject draft');
        $notedcard = $this->review($subjectdraft, $other);
        $voiceover = draft_manager::record_voiceover($subjectdraft, $notedcard,
            ['audio_ref' => 'mock://x', 'mode' => 'mock', 'voice_id' => 'mock', 'charcount' => 12],
            'en');
        $cards = $DB->count_records(draft_manager::CARD_TABLE, ['draftid' => $subjectdraft]);
        $questions = $DB->count_records(draft_manager::QUESTION_TABLE, ['draftid' => $subjectdraft]);
        // The other author's draft, reviewed by the subject.
        $otherdraft = $this->generated_draft($other, 'Other draft');
        $this->review($otherdraft, $subject);
        $otherreviewedat = (int) $DB->get_field(draft_manager::DRAFT_TABLE, 'reviewed_at', ['id' => $otherdraft]);
        // Templates: the subject's, the other author's, and a built-in the
        // subject somehow owns.
        $subjecttpl = template_manager::create((int) $subject->id, 'Subject template', 'Body');
        $othertpl = template_manager::create((int) $other->id, 'Other template', 'Body');
        $ownedbuiltin = template_manager::create((int) $subject->id, 'Owned built-in', 'Body',
            null, null, true);

        provider::delete_data_for_user(new approved_contextlist($subject, self::COMPONENT,
            [\context_system::instance()->id]));

        // The subject's draft survives whole, authored by nobody.
        $draft = $DB->get_record(draft_manager::DRAFT_TABLE, ['id' => $subjectdraft], '*', MUST_EXIST);
        $this->assertSame(0, (int) $draft->ownerid);
        $this->assertSame(1, (int) $draft->costcenterid, 'The draft stays with its tenant.');
        $this->assertSame('Subject draft', $draft->title);
        $this->assertSame(draft_manager::STATUS_APPROVED, $draft->status);
        $this->assertSame((int) $other->id, (int) $draft->reviewed_by,
            'The other person\'s review of the subject\'s draft is theirs, not the subject\'s.');
        $this->assertSame($cards, $DB->count_records(draft_manager::CARD_TABLE, ['draftid' => $subjectdraft]));
        $this->assertSame($questions,
            $DB->count_records(draft_manager::QUESTION_TABLE, ['draftid' => $subjectdraft]));
        $this->assertSame('Tighten the wording.',
            $DB->get_field(draft_manager::CARD_TABLE, 'reviewer_note', ['id' => $notedcard]));
        $this->assertTrue($DB->record_exists(draft_manager::VOICEOVER_TABLE, ['id' => $voiceover]));

        // The other author's draft: still theirs; the subject's review is anonymised.
        $draft = $DB->get_record(draft_manager::DRAFT_TABLE, ['id' => $otherdraft], '*', MUST_EXIST);
        $this->assertSame((int) $other->id, (int) $draft->ownerid);
        $this->assertNull($draft->reviewed_by);
        $this->assertSame($otherreviewedat, (int) $draft->reviewed_at);
        $this->assertSame(draft_manager::STATUS_APPROVED, $draft->status);

        // Templates: kept, the subject's anonymised, the other author's untouched.
        $this->assertSame(0, (int) $DB->get_field(template_manager::TABLE, 'ownerid', ['id' => $subjecttpl]));
        $this->assertSame(0, (int) $DB->get_field(template_manager::TABLE, 'ownerid', ['id' => $ownedbuiltin]));
        $this->assertSame((int) $other->id,
            (int) $DB->get_field(template_manager::TABLE, 'ownerid', ['id' => $othertpl]));
        $this->assertSame($builtins + 1, $DB->count_records(template_manager::TABLE, ['is_builtin' => 1]));

        // Nothing names the subject any more.
        $this->assertFalse($DB->record_exists_select(draft_manager::DRAFT_TABLE,
            'ownerid = :owner OR reviewed_by = :reviewer',
            ['owner' => $subject->id, 'reviewer' => $subject->id]));
        $this->assertFalse($DB->record_exists(template_manager::TABLE, ['ownerid' => $subject->id]));
        $this->assertSame([], $this->contextids_for($subject));
        // ...while the other author is still reported.
        $this->assertNotEmpty($this->contextids_for($other));
    }

    public function test_bulk_erasure_anonymises_the_listed_people_only(): void {
        global $DB;
        $syscontext = \context_system::instance();
        $subject = $this->tenant_user('/77');
        $other = $this->tenant_user('/77');

        $subjectdraft = $this->generated_draft($subject, 'Subject draft');
        $otherdraft = $this->generated_draft($other, 'Other draft');
        $this->review($otherdraft, $subject);
        $this->review($subjectdraft, $other);
        $subjecttpl = template_manager::create((int) $subject->id, 'Subject template', 'Body');
        $othertpl = template_manager::create((int) $other->id, 'Other template', 'Body');

        provider::delete_data_for_users(new approved_userlist($syscontext, self::COMPONENT,
            [(int) $subject->id]));

        $this->assertSame(0, (int) $DB->get_field(draft_manager::DRAFT_TABLE, 'ownerid', ['id' => $subjectdraft]));
        $this->assertSame((int) $other->id,
            (int) $DB->get_field(draft_manager::DRAFT_TABLE, 'reviewed_by', ['id' => $subjectdraft]));
        $this->assertSame((int) $other->id,
            (int) $DB->get_field(draft_manager::DRAFT_TABLE, 'ownerid', ['id' => $otherdraft]));
        $this->assertNull($DB->get_field(draft_manager::DRAFT_TABLE, 'reviewed_by', ['id' => $otherdraft]));
        $this->assertSame(0, (int) $DB->get_field(template_manager::TABLE, 'ownerid', ['id' => $subjecttpl]));
        $this->assertSame((int) $other->id,
            (int) $DB->get_field(template_manager::TABLE, 'ownerid', ['id' => $othertpl]));
        $this->assertSame(2, $DB->count_records_select(draft_manager::DRAFT_TABLE,
            'id IN (:a, :b)', ['a' => $subjectdraft, 'b' => $otherdraft]));
    }

    public function test_users_in_context_never_lists_the_anonymised_author(): void {
        $syscontext = \context_system::instance();
        $subject = $this->tenant_user('/1');
        $other = $this->tenant_user('/1');
        $reviewer = $this->tenant_user('/1');

        $this->review($this->generated_draft($subject, 'Subject draft'), $reviewer);
        $this->generated_draft($other, 'Other draft');
        template_manager::create((int) $subject->id, 'Subject template', 'Body');

        provider::delete_data_for_user(new approved_contextlist($subject, self::COMPONENT,
            [$syscontext->id]));

        $userlist = new userlist($syscontext, self::COMPONENT);
        provider::get_users_in_context($userlist);
        $userids = array_map('intval', $userlist->get_userids());

        $this->assertNotContains(0, $userids);
        $this->assertNotContains((int) $subject->id, $userids);
        $this->assertContains((int) $other->id, $userids);
        $this->assertContains((int) $reviewer->id, $userids);
    }

    public function test_an_anonymised_row_grants_no_one_ownership(): void {
        $author = $this->tenant_user('/1');
        $colleague = $this->tenant_user('/1/5');
        $draftid = $this->generated_draft($author, 'Tenant 1 draft');
        $tplid = template_manager::create((int) $author->id, 'Tenant 1 template', 'Body');

        provider::delete_data_for_user(new approved_contextlist($author, self::COMPONENT,
            [\context_system::instance()->id]));

        // A caller whose id is 0 (CLI, no one logged in) in another tenant
        // must not match ownerid 0.
        $nobody = (object) ['id' => 0, 'open_path' => '/77'];
        $this->assertNull(draft_manager::load_for_actor($draftid, $nobody, false));
        $this->assertNotContains($draftid, array_map(static function ($d): int {
            return (int) $d->id;
        }, draft_manager::list_for_actor($nobody, false)));
        $this->assertNull(template_manager::load_for_actor($tplid, $nobody, false));
        $this->assertNotContains($tplid, array_map(static function ($t): int {
            return (int) $t->id;
        }, template_manager::list_for_actor($nobody, false)));

        // The tenant keeps its work: a colleague in tenant 1 still sees both.
        $this->assertNotNull(draft_manager::load_for_actor($draftid, $colleague, false));
        $this->assertContains($draftid, array_map(static function ($d): int {
            return (int) $d->id;
        }, draft_manager::list_for_actor($colleague, false)));
        $this->assertNotNull(template_manager::load_for_actor($tplid, $colleague, false));
    }
}
