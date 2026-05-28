<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

namespace local_sentientia_recommendations;

defined('MOODLE_INTERNAL') || die();

/**
 * PHPUnit tests for recommendation_engine — Phase H.0.
 *
 * Uses the mock anthropic_client::call_mock() output and validates the
 * full persist + retrieve pipeline. No live API calls.
 *
 * @package    local_sentientia_recommendations
 * @covers     \local_sentientia_recommendations\recommendation_engine
 */
final class recommendation_engine_test extends \advanced_testcase {

    private function parsed_recs(array $cids): array {
        $out = [];
        $score = 90;
        foreach ($cids as $cid) {
            $o = new \stdClass();
            $o->course_id = $cid;
            $o->score     = $score;
            $o->reasoning = "Reason for {$cid}";
            $out[] = $o;
            $score -= 10;
        }
        return $out;
    }

    public function test_persist_batch_inserts_rows(): void {
        $this->resetAfterTest();
        $user = $this->getDataGenerator()->create_user();

        $batchid = recommendation_engine::persist_batch(
            (int)$user->id, $this->parsed_recs([12, 13, 14]), 100, 50, 'mock');

        $this->assertNotSame('', $batchid);

        global $DB;
        $rows = $DB->get_records(recommendation_engine::TABLE,
            ['userid' => $user->id, 'batchid' => $batchid], 'rank_order ASC');
        $this->assertCount(3, $rows);

        $i = 1;
        foreach ($rows as $r) {
            $this->assertSame($i, (int)$r->rank_order);
            $this->assertSame('active', $r->status);
            $this->assertSame(1, (int)$r->customerid);
            $this->assertSame(prompt_builder::VERSION, $r->prompt_version);
            $i++;
        }
    }

    public function test_persist_batch_returns_empty_on_no_recs(): void {
        $this->resetAfterTest();
        $user = $this->getDataGenerator()->create_user();
        $batchid = recommendation_engine::persist_batch((int)$user->id, [], 0, 0, 'mock');
        $this->assertSame('', $batchid);

        global $DB;
        $this->assertSame(0, $DB->count_records(recommendation_engine::TABLE, ['userid' => $user->id]));
    }

    public function test_persist_batch_expires_prior_active_batch(): void {
        $this->resetAfterTest();
        $user = $this->getDataGenerator()->create_user();

        $batch1 = recommendation_engine::persist_batch(
            (int)$user->id, $this->parsed_recs([12, 13]), 0, 0, 'mock');
        $batch2 = recommendation_engine::persist_batch(
            (int)$user->id, $this->parsed_recs([14, 15]), 0, 0, 'mock');

        $this->assertNotSame($batch1, $batch2);

        global $DB;
        // Batch 1 rows now expired.
        $active1 = $DB->count_records(recommendation_engine::TABLE,
            ['userid' => $user->id, 'batchid' => $batch1, 'status' => 'active']);
        $this->assertSame(0, $active1);
        $expired1 = $DB->count_records(recommendation_engine::TABLE,
            ['userid' => $user->id, 'batchid' => $batch1, 'status' => 'expired']);
        $this->assertSame(2, $expired1);
        // Batch 2 rows active.
        $active2 = $DB->count_records(recommendation_engine::TABLE,
            ['userid' => $user->id, 'batchid' => $batch2, 'status' => 'active']);
        $this->assertSame(2, $active2);
    }

    public function test_latest_for_user_returns_newest_active_batch(): void {
        $this->resetAfterTest();
        $user = $this->getDataGenerator()->create_user();

        recommendation_engine::persist_batch((int)$user->id, $this->parsed_recs([12, 13]), 0, 0, 'mock');
        $batch2 = recommendation_engine::persist_batch((int)$user->id, $this->parsed_recs([14, 15, 16]), 0, 0, 'mock');

        $latest = recommendation_engine::latest_for_user((int)$user->id, 5);
        $this->assertCount(3, $latest);
        foreach ($latest as $r) {
            $this->assertSame($batch2, $r->batchid);
            $this->assertSame('active', $r->status);
        }
        // Ordered by rank.
        $this->assertSame(14, (int)$latest[0]->courseid);
    }

    public function test_latest_for_user_respects_limit(): void {
        $this->resetAfterTest();
        $user = $this->getDataGenerator()->create_user();
        recommendation_engine::persist_batch((int)$user->id, $this->parsed_recs([12, 13, 14, 15, 16]), 0, 0, 'mock');

        $latest = recommendation_engine::latest_for_user((int)$user->id, 3);
        $this->assertCount(3, $latest);
    }

    public function test_latest_for_user_empty_when_none(): void {
        $this->resetAfterTest();
        $user = $this->getDataGenerator()->create_user();
        $this->assertSame([], recommendation_engine::latest_for_user((int)$user->id, 5));
    }

    public function test_update_status_dismiss(): void {
        $this->resetAfterTest();
        $user = $this->getDataGenerator()->create_user();
        recommendation_engine::persist_batch((int)$user->id, $this->parsed_recs([12]), 0, 0, 'mock');

        global $DB;
        $rec = $DB->get_record(recommendation_engine::TABLE, ['userid' => $user->id], '*', MUST_EXIST);

        $ok = recommendation_engine::update_status((int)$rec->id, (int)$user->id,
            recommendation_engine::STATUS_DISMISSED);
        $this->assertTrue($ok);

        $reloaded = $DB->get_record(recommendation_engine::TABLE, ['id' => $rec->id]);
        $this->assertSame('dismissed', $reloaded->status);
    }

    public function test_update_status_rejects_foreign_owner(): void {
        $this->resetAfterTest();
        $owner = $this->getDataGenerator()->create_user();
        $intruder = $this->getDataGenerator()->create_user();
        recommendation_engine::persist_batch((int)$owner->id, $this->parsed_recs([12]), 0, 0, 'mock');

        global $DB;
        $rec = $DB->get_record(recommendation_engine::TABLE, ['userid' => $owner->id], '*', MUST_EXIST);

        // Intruder cannot dismiss the owner's recommendation.
        $ok = recommendation_engine::update_status((int)$rec->id, (int)$intruder->id,
            recommendation_engine::STATUS_DISMISSED);
        $this->assertFalse($ok);

        $reloaded = $DB->get_record(recommendation_engine::TABLE, ['id' => $rec->id]);
        $this->assertSame('active', $reloaded->status);
    }

    public function test_update_status_rejects_invalid_status(): void {
        $this->resetAfterTest();
        $user = $this->getDataGenerator()->create_user();
        recommendation_engine::persist_batch((int)$user->id, $this->parsed_recs([12]), 0, 0, 'mock');
        global $DB;
        $rec = $DB->get_record(recommendation_engine::TABLE, ['userid' => $user->id], '*', MUST_EXIST);

        $this->expectException(\coding_exception::class);
        recommendation_engine::update_status((int)$rec->id, (int)$user->id, 'bogus');
    }

    public function test_tokens_used_today_for_customer_aggregates(): void {
        $this->resetAfterTest();
        $user = $this->getDataGenerator()->create_user();
        // 100 in + 50 out, divided pro-rata across 2 rows = 50/25 each = 150 total.
        recommendation_engine::persist_batch((int)$user->id, $this->parsed_recs([12, 13]), 100, 50, 'live');

        $used = recommendation_engine::tokens_used_today_for_customer(1);
        // floor(100/2)=50 each in, floor(50/2)=25 each out -> 2*(50+25) = 150.
        $this->assertSame(150, $used);
    }

    public function test_tenant_root_for_extracts_first_segment(): void {
        $u = new \stdClass();
        $u->open_path = '/1/2/3';
        $this->assertSame(1, recommendation_engine::tenant_root_for($u));
        $u->open_path = '/77';
        $this->assertSame(77, recommendation_engine::tenant_root_for($u));
        $u->open_path = '';
        $this->assertSame(0, recommendation_engine::tenant_root_for($u));
        $u->open_path = '/177/4/9';
        $this->assertSame(177, recommendation_engine::tenant_root_for($u));
    }

    public function test_build_candidate_list_excludes_completed(): void {
        $this->resetAfterTest();
        $gen = $this->getDataGenerator();
        $c1 = $gen->create_course();
        $c2 = $gen->create_course();
        $c3 = $gen->create_course();

        $profile = new \stdClass();
        $profile->completed = [(int)$c2->id];

        $candidates = recommendation_engine::build_candidate_list($profile, 100);
        $ids = array_map(fn($c) => (int)$c->id, $candidates);

        $this->assertContains((int)$c1->id, $ids);
        $this->assertNotContains((int)$c2->id, $ids);
        $this->assertContains((int)$c3->id, $ids);
    }

    /**
     * Tenant scoping (2026-05-28 hotfix follow-up to db5242c9a): when a
     * profile carries a tenant id that the accesslib resolver can't map to
     * a course_categories.id, build_candidate_list MUST return [] rather
     * than fall through to "all visible courses." This is the fail-closed
     * guard that prevents Claude from being fed a cross-tenant catalog
     * when the BizLMS↔Sentientia resolver chain breaks.
     */
    public function test_build_candidate_list_fails_closed_on_unresolvable_tenant(): void {
        $this->resetAfterTest();
        $gen = $this->getDataGenerator();
        $gen->create_course();
        $gen->create_course();

        $profile = new \stdClass();
        $profile->completed = [];
        // 9999 is intentionally not a tenant on this Moodle — neither
        // local_costcenter.path='/9999' nor local_airpay_org id=9999 exist,
        // so the resolver returns null and we expect [].
        $profile->tenant = 9999;

        $candidates = recommendation_engine::build_candidate_list($profile, 100);
        $this->assertSame([], $candidates,
            'unresolvable tenant must fail closed, not fall through to all courses');
    }

    /**
     * No-tenant profile (siteadmin trigger or test fixture without
     * open_path) keeps the legacy unscoped behaviour — important so the
     * rare admin-runs-it-for-debugging path still works.
     */
    public function test_build_candidate_list_unscoped_when_tenant_zero(): void {
        $this->resetAfterTest();
        $gen = $this->getDataGenerator();
        $c1 = $gen->create_course();

        $profile = new \stdClass();
        $profile->completed = [];
        $profile->tenant = 0;

        $candidates = recommendation_engine::build_candidate_list($profile, 100);
        $ids = array_map(fn($c) => (int)$c->id, $candidates);
        $this->assertContains((int)$c1->id, $ids);
    }
}
