<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

namespace local_sentientia_authoring;

defined('MOODLE_INTERNAL') || die();

/**
 * draft_manager tests — persistence, review lifecycle, tenant isolation.
 *
 * Uses the platform open_path fixture trait so mdl_user.open_path exists in
 * the test schema (BizLMS column absent from vanilla Moodle PHPUnit). No live
 * API calls — generation output comes from course_generator::call_mock().
 *
 * @package    local_sentientia_authoring
 * @covers     \local_sentientia_authoring\draft_manager
 */
final class draft_manager_test extends \advanced_testcase {

    use \local_sentientia_platform\phpunit\open_path_fixture_trait;

    /**
     * Helper: create a user pinned to a tenant root.
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

    public function test_create_pending_sets_scope_and_timestamps(): void {
        global $DB;
        $u = $this->tenant_user('/1/2/3');
        $did = draft_manager::create_pending((int) $u->id, 'My module', 'Some source.',
            'prompt', 'en', 'claude-sonnet-4-6', 70);
        $row = $DB->get_record(draft_manager::DRAFT_TABLE, ['id' => $did], '*', MUST_EXIST);

        $this->assertSame('pending', $row->status);
        $this->assertSame(1, (int) $row->customerid);
        $this->assertSame(1, (int) $row->costcenterid); // tenant root from /1/2/3
        $this->assertSame(70, (int) $row->mastery_score);
        $this->assertGreaterThan(0, $row->timecreated);
        $this->assertGreaterThan(0, $row->timemodified);
    }

    public function test_persist_generation_writes_cards_and_questions(): void {
        global $DB;
        $u = $this->tenant_user('/77');
        $did = draft_manager::create_pending((int) $u->id, 'D', 'src', 'prompt', 'en', 'm', 70);

        $mock = course_generator::call_mock('src', 3, 3);
        $parsed = response_parser::parse($mock['body']);
        draft_manager::persist_generation($did, $parsed->cards, $parsed->questions, 100, 200, 'mock');

        $draft = $DB->get_record(draft_manager::DRAFT_TABLE, ['id' => $did], '*', MUST_EXIST);
        $this->assertSame('generated', $draft->status);
        $this->assertSame(3, (int) $draft->num_cards);
        $this->assertSame(3, (int) $draft->num_questions);
        $this->assertSame(100, (int) $draft->tokens_in);

        $this->assertCount(3, $DB->get_records(draft_manager::CARD_TABLE, ['draftid' => $did]));
        $this->assertCount(3, $DB->get_records(draft_manager::QUESTION_TABLE, ['draftid' => $did]));
    }

    public function test_empty_generation_flips_to_failed(): void {
        global $DB;
        $u = $this->tenant_user('/1');
        $did = draft_manager::create_pending((int) $u->id, 'D', 'src', 'prompt', 'en', 'm', 70);
        draft_manager::persist_generation($did, [], [], 0, 0, 'mock');
        $draft = $DB->get_record(draft_manager::DRAFT_TABLE, ['id' => $did], '*', MUST_EXIST);
        $this->assertSame('failed', $draft->status);
    }

    public function test_finalise_review_requires_an_approved_card(): void {
        $u = $this->tenant_user('/1');
        $did = draft_manager::create_pending((int) $u->id, 'D', 'src', 'prompt', 'en', 'm', 70);
        $mock = course_generator::call_mock('src', 2, 1);
        $parsed = response_parser::parse($mock['body']);
        draft_manager::persist_generation($did, $parsed->cards, $parsed->questions, 0, 0, 'mock');

        // No card approved → rejected.
        $status = draft_manager::finalise_review($did, (int) $u->id);
        $this->assertSame(draft_manager::STATUS_REJECTED, $status);
    }

    public function test_finalise_review_approves_when_card_approved(): void {
        global $DB;
        $u = $this->tenant_user('/1');
        $did = draft_manager::create_pending((int) $u->id, 'D', 'src', 'prompt', 'en', 'm', 70);
        $mock = course_generator::call_mock('src', 2, 1);
        $parsed = response_parser::parse($mock['body']);
        draft_manager::persist_generation($did, $parsed->cards, $parsed->questions, 0, 0, 'mock');

        $card = $DB->get_records(draft_manager::CARD_TABLE, ['draftid' => $did], 'sortorder ASC');
        $first = reset($card);
        draft_manager::review_card((int) $first->id, draft_manager::ITEM_APPROVED);

        $status = draft_manager::finalise_review($did, (int) $u->id);
        $this->assertSame(draft_manager::STATUS_APPROVED, $status);
    }

    public function test_mark_published_blocked_unless_approved(): void {
        $u = $this->tenant_user('/1');
        $did = draft_manager::create_pending((int) $u->id, 'D', 'src', 'prompt', 'en', 'm', 70);
        $this->expectException(\moodle_exception::class);
        // Draft is still 'pending' — publishing must be blocked (human-review gate).
        draft_manager::mark_published($did, 99);
    }

    public function test_tenant_isolation_blocks_cross_tenant_load(): void {
        $owner = $this->tenant_user('/1');
        $other = $this->tenant_user('/77'); // different tenant root
        $did = draft_manager::create_pending((int) $owner->id, 'D', 'src', 'prompt', 'en', 'm', 70);

        // A non-manager in tenant 77 cannot load a tenant-1 draft they don't own.
        $loaded = draft_manager::load_for_actor($did, $other, false);
        $this->assertNull($loaded);
    }

    public function test_owner_can_load_own_draft(): void {
        $owner = $this->tenant_user('/1');
        $did = draft_manager::create_pending((int) $owner->id, 'D', 'src', 'prompt', 'en', 'm', 70);
        $loaded = draft_manager::load_for_actor($did, $owner, false);
        $this->assertNotNull($loaded);
        $this->assertSame($did, (int) $loaded->draft->id);
    }

    public function test_manage_all_sees_cross_tenant_draft(): void {
        $owner = $this->tenant_user('/1');
        $admin = $this->tenant_user('/77');
        $did = draft_manager::create_pending((int) $owner->id, 'D', 'src', 'prompt', 'en', 'm', 70);
        // manage_all=true bypasses tenant scoping.
        $loaded = draft_manager::load_for_actor($did, $admin, true);
        $this->assertNotNull($loaded);
    }

    public function test_list_for_actor_scopes_to_tenant(): void {
        $a = $this->tenant_user('/1');
        $b = $this->tenant_user('/77');
        draft_manager::create_pending((int) $a->id, 'A', 'src', 'prompt', 'en', 'm', 70);
        draft_manager::create_pending((int) $b->id, 'B', 'src', 'prompt', 'en', 'm', 70);

        $alist = draft_manager::list_for_actor($a, false);
        foreach ($alist as $d) {
            $this->assertTrue((int) $d->ownerid === (int) $a->id || (int) $d->costcenterid === 1);
        }
    }

    public function test_record_voiceover_inherits_draft_scope(): void {
        global $DB;
        $u = $this->tenant_user('/1');
        $did = draft_manager::create_pending((int) $u->id, 'D', 'src', 'prompt', 'en', 'm', 70);
        $mock = course_generator::call_mock('src', 1, 0);
        $parsed = response_parser::parse($mock['body']);
        draft_manager::persist_generation($did, $parsed->cards, $parsed->questions, 0, 0, 'mock');
        $cards = $DB->get_records(draft_manager::CARD_TABLE, ['draftid' => $did]);
        $card = reset($cards);

        $result = ['audio_ref' => 'mock://x', 'mode' => 'mock', 'voice_id' => 'mock', 'charcount' => 42, 'error' => null];
        $void = draft_manager::record_voiceover($did, (int) $card->id, $result, 'en');
        $row = $DB->get_record(draft_manager::VOICEOVER_TABLE, ['id' => $void], '*', MUST_EXIST);
        $this->assertSame('ready', $row->status);
        $this->assertSame('mock', $row->mode);
        $this->assertSame(1, (int) $row->costcenterid);
    }
}
