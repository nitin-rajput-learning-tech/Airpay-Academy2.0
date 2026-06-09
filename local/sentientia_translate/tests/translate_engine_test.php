<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

namespace local_sentientia_translate;

defined('MOODLE_INTERNAL') || die();

/**
 * PHPUnit tests for translate_engine — Phase T.0.
 *
 * Exercises the full create -> run (mock) -> brand post-process -> persist
 * -> accept/discard pipeline. No live API calls (live_api flag stays OFF,
 * so anthropic_client::generate dispatches to call_mock).
 *
 * @package    local_sentientia_translate
 * @covers     \local_sentientia_translate\translate_engine
 */
final class translate_engine_test extends \advanced_testcase {

    /** Kannada-script rendering of "Airpay". */
    private const AIRPAY_KN = 'ಏರ್‌ಪೇ';

    public function test_create_pending_inserts_row(): void {
        $this->resetAfterTest();
        $user = $this->getDataGenerator()->create_user();

        $id = translate_engine::create_pending(
            (int)$user->id, 'My doc', 'Hello world', 'hi', 'claude-sonnet-4-6');
        $this->assertGreaterThan(0, $id);

        global $DB;
        $row = $DB->get_record(translate_engine::TABLE, ['id' => $id], '*', MUST_EXIST);
        $this->assertSame('pending', $row->status);
        $this->assertSame('hi', $row->targetlang);
        $this->assertSame('en', $row->sourcelang);
        $this->assertSame(1, (int)$row->customerid);
        $this->assertSame(prompt_builder::VERSION, $row->prompt_version);
        $this->assertNotEmpty($row->sourcehash);
    }

    public function test_run_mock_produces_translated_status(): void {
        $this->resetAfterTest();
        $user = $this->getDataGenerator()->create_user();
        $id = translate_engine::create_pending(
            (int)$user->id, 'D', 'Welcome to Airpay training.', 'hi', 'claude-sonnet-4-6');

        $result = translate_engine::run($id, 'Welcome to Airpay training.', 'hi', 1, 'claude-sonnet-4-6');
        $this->assertSame(translate_engine::STATUS_TRANSLATED, $result->status);
        $this->assertSame('mock', $result->mode);
        $this->assertNotNull($result->translatedtext);
        // Mock echoes source with a banner.
        $this->assertStringContainsString('[MOCK hi]', $result->translatedtext);

        global $DB;
        $row = $DB->get_record(translate_engine::TABLE, ['id' => $id], '*', MUST_EXIST);
        $this->assertSame('translated', $row->status);
        $this->assertNotNull($row->translatedtext);
    }

    public function test_run_applies_brand_override_in_mock_pipeline(): void {
        $this->resetAfterTest();
        $user = $this->getDataGenerator()->create_user();
        // Configure a kn override for Airpay.
        brand_manager::set_override(1, 'Airpay', 'kn', self::AIRPAY_KN);

        $source = 'Airpay keeps you compliant. Trust Airpay.';
        $id = translate_engine::create_pending((int)$user->id, 'D', $source, 'kn', 'claude-sonnet-4-6');
        $result = translate_engine::run($id, $source, 'kn', 1, 'claude-sonnet-4-6');

        // The mock echoes the source, then brand post-processing substitutes.
        $this->assertSame(translate_engine::STATUS_TRANSLATED, $result->status);
        $this->assertSame(2, $result->brand_terms_applied);
        $this->assertStringContainsString(self::AIRPAY_KN, $result->translatedtext);
        $this->assertStringNotContainsString('Airpay', $result->translatedtext);

        global $DB;
        $row = $DB->get_record(translate_engine::TABLE, ['id' => $id], '*', MUST_EXIST);
        $this->assertSame(2, (int)$row->brand_terms_applied);
    }

    public function test_run_preserves_brand_without_override(): void {
        $this->resetAfterTest();
        $user = $this->getDataGenerator()->create_user();
        // No override for hi — Airpay must survive verbatim.
        $source = 'Airpay is here.';
        $id = translate_engine::create_pending((int)$user->id, 'D', $source, 'hi', 'claude-sonnet-4-6');
        $result = translate_engine::run($id, $source, 'hi', 1, 'claude-sonnet-4-6');

        $this->assertSame(0, $result->brand_terms_applied);
        $this->assertStringContainsString('Airpay', $result->translatedtext);
    }

    public function test_accept_flips_to_saved(): void {
        $this->resetAfterTest();
        $user = $this->getDataGenerator()->create_user();
        $id = translate_engine::create_pending((int)$user->id, 'D', 'Hello', 'hi', 'claude-sonnet-4-6');
        translate_engine::run($id, 'Hello', 'hi', 1, 'claude-sonnet-4-6');

        $this->assertTrue(translate_engine::accept($id, (int)$user->id));

        global $DB;
        $row = $DB->get_record(translate_engine::TABLE, ['id' => $id], '*', MUST_EXIST);
        $this->assertSame('saved', $row->status);
    }

    public function test_accept_only_from_translated_state(): void {
        $this->resetAfterTest();
        $user = $this->getDataGenerator()->create_user();
        // Pending (never run) cannot be accepted.
        $id = translate_engine::create_pending((int)$user->id, 'D', 'Hello', 'hi', 'claude-sonnet-4-6');
        $this->assertFalse(translate_engine::accept($id, (int)$user->id));
    }

    public function test_discard_flips_to_discarded(): void {
        $this->resetAfterTest();
        $user = $this->getDataGenerator()->create_user();
        $id = translate_engine::create_pending((int)$user->id, 'D', 'Hello', 'hi', 'claude-sonnet-4-6');
        translate_engine::run($id, 'Hello', 'hi', 1, 'claude-sonnet-4-6');

        $this->assertTrue(translate_engine::discard($id, (int)$user->id));

        global $DB;
        $row = $DB->get_record(translate_engine::TABLE, ['id' => $id], '*', MUST_EXIST);
        $this->assertSame('discarded', $row->status);
    }

    public function test_mark_failed_truncates_error(): void {
        $this->resetAfterTest();
        $user = $this->getDataGenerator()->create_user();
        $id = translate_engine::create_pending((int)$user->id, 'D', 'Hello', 'hi', 'claude-sonnet-4-6');
        translate_engine::mark_failed($id, str_repeat('x', 2000));

        global $DB;
        $row = $DB->get_record(translate_engine::TABLE, ['id' => $id], '*', MUST_EXIST);
        $this->assertSame('failed', $row->status);
        $this->assertLessThanOrEqual(1000, strlen($row->error_detail));
    }

    public function test_load_for_actor_rejects_foreign_tenant(): void {
        $this->resetAfterTest();
        $owner = $this->getDataGenerator()->create_user();
        $owner->open_path = '/1/2/3';
        $this->set_open_path($owner);

        $intruder = $this->getDataGenerator()->create_user();
        $intruder->open_path = '/77';
        $this->set_open_path($intruder);
        $intruder = $this->reload($intruder->id);

        $id = translate_engine::create_pending((int)$owner->id, 'D', 'Hello', 'hi', 'claude-sonnet-4-6');

        // Intruder (tenant 77, not owner, no manage_all) cannot load.
        $this->assertNull(translate_engine::load_for_actor($id, $intruder, false));
        // With manage_all they can.
        $this->assertNotNull(translate_engine::load_for_actor($id, $intruder, true));
    }

    public function test_load_for_actor_returns_own_row(): void {
        $this->resetAfterTest();
        $owner = $this->getDataGenerator()->create_user();
        $owner->open_path = '/1';
        $this->set_open_path($owner);
        $owner = $this->reload($owner->id);

        $id = translate_engine::create_pending((int)$owner->id, 'D', 'Hello', 'hi', 'claude-sonnet-4-6');
        $loaded = translate_engine::load_for_actor($id, $owner, false);
        $this->assertNotNull($loaded);
        $this->assertSame($id, (int)$loaded->id);
    }

    public function test_tokens_used_today_for_customer(): void {
        $this->resetAfterTest();
        $user = $this->getDataGenerator()->create_user();
        $id = translate_engine::create_pending((int)$user->id, 'D', 'Hello', 'hi', 'claude-sonnet-4-6');
        translate_engine::store_translation($id, 'नमस्ते', 0, 100, 200, 'live');

        $used = translate_engine::tokens_used_today_for_customer(1);
        $this->assertSame(300, $used);
    }

    public function test_source_hash_stable_and_lang_sensitive(): void {
        $h1 = translate_engine::source_hash('Hello', 'hi');
        $h2 = translate_engine::source_hash('Hello', 'hi');
        $h3 = translate_engine::source_hash('Hello', 'kn');
        $this->assertSame($h1, $h2);
        $this->assertNotSame($h1, $h3);
    }

    public function test_tenant_root_for_extracts_first_segment(): void {
        $u = new \stdClass();
        $u->open_path = '/1/2/3';
        $this->assertSame(1, translate_engine::tenant_root_for($u));
        $u->open_path = '/177/4';
        $this->assertSame(177, translate_engine::tenant_root_for($u));
        $u->open_path = '';
        $this->assertSame(0, translate_engine::tenant_root_for($u));
    }

    // ── helpers ───────────────────────────────────────────────────────

    private function set_open_path(\stdClass $user): void {
        global $DB;
        $manager = $DB->get_manager();
        $table = new \xmldb_table('user');
        $field = new \xmldb_field('open_path');
        if (!$manager->field_exists($table, $field)) {
            return;
        }
        $DB->set_field('user', 'open_path', $user->open_path, ['id' => $user->id]);
    }

    private function reload(int $id): \stdClass {
        global $DB;
        $row = $DB->get_record('user', ['id' => $id]);
        if (!isset($row->open_path)) {
            $row->open_path = '';
        }
        return $row;
    }
}
