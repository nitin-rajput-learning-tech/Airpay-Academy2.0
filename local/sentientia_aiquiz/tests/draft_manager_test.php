<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

namespace local_sentientia_aiquiz;

defined('MOODLE_INTERNAL') || die();

/**
 * PHPUnit tests for draft_manager — Phase G.0.
 *
 * Uses the mock anthropic_client::call_mock() output and validates the
 * full persist pipeline. No live API calls.
 *
 * @package    local_sentientia_aiquiz
 * @covers     \local_sentientia_aiquiz\draft_manager
 */
final class draft_manager_test extends \advanced_testcase {

    public function test_create_pending_inserts_row_with_defaults(): void {
        $this->resetAfterTest();
        $user = $this->getDataGenerator()->create_user();
        $user->open_path = '/1/2/3';
        $this->update_user_open_path($user);

        $did = draft_manager::create_pending(
            (int)$user->id, 0, 'My draft', 'Some source.', 'claude-sonnet-4-6', 10
        );
        $this->assertGreaterThan(0, $did);

        global $DB;
        $row = $DB->get_record(draft_manager::DRAFT_TABLE, ['id' => $did], '*', MUST_EXIST);
        $this->assertSame('pending', $row->status);
        $this->assertSame(1, (int)$row->customerid);
        $this->assertSame(1, (int)$row->costcenterid);  // tenant root from /1/2/3
        $this->assertSame('Some source.', $row->sourcetext);
        $this->assertSame(prompt_builder::VERSION, $row->prompt_version);
        $this->assertGreaterThan(0, $row->timecreated);
    }

    public function test_persist_questions_writes_rows_and_flips_status(): void {
        $this->resetAfterTest();
        $user = $this->getDataGenerator()->create_user();
        $user->open_path = '/77';
        $this->update_user_open_path($user);

        $did = draft_manager::create_pending(
            (int)$user->id, 0, 'D', 'src', 'claude-sonnet-4-6', 3
        );

        $mock = anthropic_client::call_mock('src', 3);
        $questions = response_parser::parse($mock['body']);
        $this->assertCount(3, $questions);

        draft_manager::persist_questions($did, $questions, 100, 200, 'mock');

        global $DB;
        $row = $DB->get_record(draft_manager::DRAFT_TABLE, ['id' => $did], '*', MUST_EXIST);
        $this->assertSame('generated', $row->status);
        $this->assertSame(3, (int)$row->num_generated);
        $this->assertSame(100, (int)$row->tokens_in);
        $this->assertSame(200, (int)$row->tokens_out);

        $persisted = $DB->get_records(draft_manager::QUESTION_TABLE,
            ['draftid' => $did], 'sortorder ASC');
        $this->assertCount(3, $persisted);

        // sortorder must be 1, 2, 3.
        $i = 1;
        foreach ($persisted as $q) {
            $this->assertSame($i, (int)$q->sortorder);
            $this->assertSame('multichoice', $q->qtype);
            $this->assertSame('generated', $q->status);
            $i++;
        }
    }

    public function test_persist_questions_marks_draft_failed_when_empty(): void {
        $this->resetAfterTest();
        $user = $this->getDataGenerator()->create_user();
        $did = draft_manager::create_pending(
            (int)$user->id, 0, 'D', 'src', 'claude-sonnet-4-6', 5
        );
        draft_manager::persist_questions($did, [], 50, 0, 'live');

        global $DB;
        $row = $DB->get_record(draft_manager::DRAFT_TABLE, ['id' => $did], '*', MUST_EXIST);
        $this->assertSame('failed', $row->status);
        $this->assertStringContainsString('parser_no_questions', (string)$row->error_detail);
        $this->assertSame(0, (int)$row->num_generated);
    }

    public function test_mark_failed_sets_status_and_truncates_error(): void {
        $this->resetAfterTest();
        $user = $this->getDataGenerator()->create_user();
        $did = draft_manager::create_pending(
            (int)$user->id, 0, 'D', 'src', 'claude-sonnet-4-6', 1
        );
        $long = str_repeat('x', 2000);
        draft_manager::mark_failed($did, $long);

        global $DB;
        $row = $DB->get_record(draft_manager::DRAFT_TABLE, ['id' => $did], '*', MUST_EXIST);
        $this->assertSame('failed', $row->status);
        $this->assertLessThanOrEqual(1000, strlen($row->error_detail));
    }

    public function test_review_question_status_transitions(): void {
        $this->resetAfterTest();
        $user = $this->getDataGenerator()->create_user();
        $did = draft_manager::create_pending(
            (int)$user->id, 0, 'D', 'src', 'claude-sonnet-4-6', 2
        );
        $mock = anthropic_client::call_mock('src', 2);
        $questions = response_parser::parse($mock['body']);
        draft_manager::persist_questions($did, $questions, 0, 0, 'mock');

        global $DB;
        $qids = $DB->get_fieldset_select(draft_manager::QUESTION_TABLE,
            'id', 'draftid = :did', ['did' => $did]);
        $this->assertCount(2, $qids);

        // Approve first, reject second.
        draft_manager::review_question($qids[0], draft_manager::Q_STATUS_APPROVED);
        draft_manager::review_question($qids[1], draft_manager::Q_STATUS_REJECTED,
            ['reviewer_note' => 'distractors too obvious']);

        $row0 = $DB->get_record(draft_manager::QUESTION_TABLE, ['id' => $qids[0]]);
        $row1 = $DB->get_record(draft_manager::QUESTION_TABLE, ['id' => $qids[1]]);
        $this->assertSame('approved', $row0->status);
        $this->assertSame('rejected', $row1->status);
        $this->assertSame('distractors too obvious', $row1->reviewer_note);
    }

    public function test_review_question_rejects_invalid_status(): void {
        $this->resetAfterTest();
        $user = $this->getDataGenerator()->create_user();
        $did = draft_manager::create_pending(
            (int)$user->id, 0, 'D', 'src', 'claude-sonnet-4-6', 1
        );
        $mock = anthropic_client::call_mock('src', 1);
        draft_manager::persist_questions($did, response_parser::parse($mock['body']), 0, 0, 'mock');
        global $DB;
        $qid = (int)$DB->get_field_select(draft_manager::QUESTION_TABLE, 'id',
            'draftid = :did', ['did' => $did]);

        $this->expectException(\coding_exception::class);
        draft_manager::review_question($qid, 'bogus_status');
    }

    public function test_finalise_review_sets_approved_when_any_question_usable(): void {
        $this->resetAfterTest();
        $user = $this->getDataGenerator()->create_user();
        $did = draft_manager::create_pending(
            (int)$user->id, 0, 'D', 'src', 'claude-sonnet-4-6', 3
        );
        $mock = anthropic_client::call_mock('src', 3);
        draft_manager::persist_questions($did, response_parser::parse($mock['body']), 0, 0, 'mock');
        global $DB;
        $qids = $DB->get_fieldset_select(draft_manager::QUESTION_TABLE,
            'id', 'draftid = :did', ['did' => $did]);

        draft_manager::review_question($qids[0], draft_manager::Q_STATUS_APPROVED);
        draft_manager::review_question($qids[1], draft_manager::Q_STATUS_REJECTED);
        draft_manager::review_question($qids[2], draft_manager::Q_STATUS_REJECTED);

        $new = draft_manager::finalise_review($did, (int)$user->id);
        $this->assertSame('approved', $new);

        $row = $DB->get_record(draft_manager::DRAFT_TABLE, ['id' => $did]);
        $this->assertSame('approved', $row->status);
        $this->assertSame((int)$user->id, (int)$row->reviewed_by);
        $this->assertGreaterThan(0, (int)$row->reviewed_at);
    }

    public function test_finalise_review_sets_rejected_when_no_question_usable(): void {
        $this->resetAfterTest();
        $user = $this->getDataGenerator()->create_user();
        $did = draft_manager::create_pending(
            (int)$user->id, 0, 'D', 'src', 'claude-sonnet-4-6', 2
        );
        $mock = anthropic_client::call_mock('src', 2);
        draft_manager::persist_questions($did, response_parser::parse($mock['body']), 0, 0, 'mock');
        global $DB;
        $qids = $DB->get_fieldset_select(draft_manager::QUESTION_TABLE,
            'id', 'draftid = :did', ['did' => $did]);

        draft_manager::review_question($qids[0], draft_manager::Q_STATUS_REJECTED);
        draft_manager::review_question($qids[1], draft_manager::Q_STATUS_REJECTED);

        $new = draft_manager::finalise_review($did, (int)$user->id);
        $this->assertSame('rejected', $new);
    }

    public function test_load_for_actor_returns_null_for_different_tenant(): void {
        $this->resetAfterTest();
        $owner = $this->getDataGenerator()->create_user();
        $owner->open_path = '/1/2/3';
        $this->update_user_open_path($owner);

        $intruder = $this->getDataGenerator()->create_user();
        $intruder->open_path = '/77';
        $this->update_user_open_path($intruder);
        $intruder = $this->reload_user($intruder->id);

        $did = draft_manager::create_pending(
            (int)$owner->id, 0, 'Airpay-tenant draft', 'src', 'claude-sonnet-4-6', 1
        );

        // Intruder (tenant 77, not owner, no manage_all cap) must not see it.
        $loaded = draft_manager::load_for_actor($did, $intruder, false);
        $this->assertNull($loaded);

        // Intruder WITH manage_all cap should see it.
        $loaded2 = draft_manager::load_for_actor($did, $intruder, true);
        $this->assertNotNull($loaded2);
        $this->assertSame($did, (int)$loaded2->draft->id);
    }

    public function test_load_for_actor_returns_own_draft(): void {
        $this->resetAfterTest();
        $owner = $this->getDataGenerator()->create_user();
        $owner->open_path = '/1';
        $this->update_user_open_path($owner);
        $owner = $this->reload_user($owner->id);

        $did = draft_manager::create_pending(
            (int)$owner->id, 0, 'D', 'src', 'claude-sonnet-4-6', 1
        );
        $loaded = draft_manager::load_for_actor($did, $owner, false);
        $this->assertNotNull($loaded);
        $this->assertSame($did, (int)$loaded->draft->id);
    }

    public function test_tokens_used_today_aggregates_recent_drafts(): void {
        $this->resetAfterTest();
        $user = $this->getDataGenerator()->create_user();
        $did1 = draft_manager::create_pending(
            (int)$user->id, 0, 'D1', 'src', 'claude-sonnet-4-6', 1
        );
        $did2 = draft_manager::create_pending(
            (int)$user->id, 0, 'D2', 'src', 'claude-sonnet-4-6', 1
        );
        $mock = anthropic_client::call_mock('src', 1);
        $questions = response_parser::parse($mock['body']);
        draft_manager::persist_questions($did1, $questions, 100, 50, 'live');
        draft_manager::persist_questions($did2, $questions, 200, 75, 'live');

        $used = draft_manager::tokens_used_today((int)$user->id);
        $this->assertSame(100 + 50 + 200 + 75, $used);
    }

    public function test_mark_pushed_updates_status_and_quizid(): void {
        $this->resetAfterTest();
        $user = $this->getDataGenerator()->create_user();
        $did = draft_manager::create_pending(
            (int)$user->id, 0, 'D', 'src', 'claude-sonnet-4-6', 1
        );
        draft_manager::mark_pushed($did, 4242);

        global $DB;
        $row = $DB->get_record(draft_manager::DRAFT_TABLE, ['id' => $did]);
        $this->assertSame('pushed', $row->status);
        $this->assertSame(4242, (int)$row->pushed_quizid);
    }

    public function test_tenant_root_for_extracts_first_segment(): void {
        $u = new \stdClass();
        $u->open_path = '/1/2/3';
        $this->assertSame(1, draft_manager::tenant_root_for($u));

        $u->open_path = '/77';
        $this->assertSame(77, draft_manager::tenant_root_for($u));

        $u->open_path = '';
        $this->assertSame(0, draft_manager::tenant_root_for($u));

        $u->open_path = '/177/4/9';
        $this->assertSame(177, draft_manager::tenant_root_for($u));
    }

    // ════════════════════════════════════════════════════════════════
    //  G.1 — prompt_version recording
    // ════════════════════════════════════════════════════════════════

    public function test_create_pending_defaults_prompt_version_to_v1(): void {
        $this->resetAfterTest();
        $user = $this->getDataGenerator()->create_user();
        // No prompt_version arg → must default to 'v1' (Phase G.0 contract).
        $did = draft_manager::create_pending(
            (int)$user->id, 0, 'D', 'src', 'claude-sonnet-4-6', 3
        );
        global $DB;
        $row = $DB->get_record(draft_manager::DRAFT_TABLE, ['id' => $did], '*', MUST_EXIST);
        $this->assertSame('v1', $row->prompt_version);
    }

    public function test_create_pending_records_hindi_prompt_version(): void {
        $this->resetAfterTest();
        $user = $this->getDataGenerator()->create_user();
        $did = draft_manager::create_pending(
            (int)$user->id, 0, 'D', 'स्रोत', 'claude-sonnet-4-6', 3,
            prompt_builder::VERSION_V2_HINDI
        );
        global $DB;
        $row = $DB->get_record(draft_manager::DRAFT_TABLE, ['id' => $did], '*', MUST_EXIST);
        $this->assertSame('v2-hindi', $row->prompt_version);
    }

    public function test_create_pending_records_custom_prompt_version(): void {
        $this->resetAfterTest();
        $user = $this->getDataGenerator()->create_user();
        $version = prompt_builder::resolve_prompt_version(prompt_builder::VERSION_V2_HINDI, true);
        $did = draft_manager::create_pending(
            (int)$user->id, 0, 'D', 'स्रोत', 'claude-sonnet-4-6', 3, $version
        );
        global $DB;
        $row = $DB->get_record(draft_manager::DRAFT_TABLE, ['id' => $did], '*', MUST_EXIST);
        $this->assertSame('custom:v2-hindi', $row->prompt_version);
    }

    public function test_create_pending_clamps_blank_prompt_version_to_v1(): void {
        $this->resetAfterTest();
        $user = $this->getDataGenerator()->create_user();
        $did = draft_manager::create_pending(
            (int)$user->id, 0, 'D', 'src', 'claude-sonnet-4-6', 3, '   '
        );
        global $DB;
        $row = $DB->get_record(draft_manager::DRAFT_TABLE, ['id' => $did], '*', MUST_EXIST);
        $this->assertSame('v1', $row->prompt_version);
    }

    public function test_create_pending_hindi_draft_persists_devanagari_questions(): void {
        // End-to-end mock-mode Hindi: create pending → mock generate →
        // parse → persist. Proves Devanagari survives the whole pipeline.
        $this->resetAfterTest();
        $user = $this->getDataGenerator()->create_user();
        $did = draft_manager::create_pending(
            (int)$user->id, 0, 'D', 'अनुपालन स्रोत', 'claude-sonnet-4-6', 3,
            prompt_builder::VERSION_V2_HINDI
        );
        $mock = anthropic_client::call_mock('अनुपालन स्रोत', 3,
            ['version' => prompt_builder::VERSION_V2_HINDI, 'template' => null]);
        $questions = response_parser::parse($mock['body']);
        $this->assertCount(3, $questions);
        draft_manager::persist_questions($did, $questions, 0, 0, 'mock');

        global $DB;
        $persisted = $DB->get_records(draft_manager::QUESTION_TABLE,
            ['draftid' => $did], 'sortorder ASC');
        $this->assertCount(3, $persisted);
        foreach ($persisted as $q) {
            $this->assertMatchesRegularExpression('/\p{Devanagari}/u', $q->qtext);
            $decoded = json_decode($q->qoptions_json, true);
            $this->assertCount(4, $decoded);
            $this->assertMatchesRegularExpression('/\p{Devanagari}/u', $decoded[0]);
        }
    }

    // ── helpers ───────────────────────────────────────────────────────

    /**
     * The user table has open_path as a custom column in production —
     * Moodle's data generator doesn't set it, so we update directly.
     */
    private function update_user_open_path(\stdClass $user): void {
        global $DB;
        // Some test environments may not have open_path in {user}; tolerate gracefully.
        $manager = $DB->get_manager();
        $table = new \xmldb_table('user');
        $field = new \xmldb_field('open_path');
        if (!$manager->field_exists($table, $field)) {
            // No open_path column — tests that depend on tenant root use the
            // helper anyway; the column is just missing in the unit-test sandbox.
            return;
        }
        $DB->set_field('user', 'open_path', $user->open_path, ['id' => $user->id]);
    }

    private function reload_user(int $id): \stdClass {
        global $DB;
        $row = $DB->get_record('user', ['id' => $id]);
        if (!isset($row->open_path)) {
            $row->open_path = '';
        }
        return $row;
    }
}
