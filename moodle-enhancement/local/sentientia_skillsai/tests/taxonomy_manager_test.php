<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

namespace local_sentientia_skillsai;

defined('MOODLE_INTERNAL') || die();

/**
 * PHPUnit tests for taxonomy_manager — extraction jobs, the candidate
 * human-review gate, promotion into the canonical taxonomy, and tenant
 * isolation.
 *
 * Uses anthropic_client::call_mock() output + response_parser; no live API.
 *
 * @package    local_sentientia_skillsai
 * @covers     \local_sentientia_skillsai\taxonomy_manager
 */
final class taxonomy_manager_test extends \advanced_testcase {

    use \local_sentientia_platform\phpunit\open_path_fixture_trait;

    /**
     * Helper: create a user on a given tenant + a mock-extracted job.
     *
     * @return array{0: \stdClass, 1: int} [user, jobid]
     */
    private function make_job(string $openpath = '/1', int $count = 3): array {
        global $DB;
        $user = $this->getDataGenerator()->create_user();
        $DB->set_field('user', 'open_path', $openpath, ['id' => $user->id]);
        $user = $DB->get_record('user', ['id' => $user->id]);

        $jobid = taxonomy_manager::create_pending(
            (int)$user->id, 0, 'Test job', 'sop', 'KYC SOP source text',
            anthropic_client::DEFAULT_MODEL, prompt_builder::VERSION_V1
        );
        $mock = anthropic_client::call_mock('KYC SOP source text', $count);
        $skills = response_parser::parse($mock['body']);
        taxonomy_manager::persist_candidates($jobid, $skills, 0, 0, 'mock');
        return [$user, $jobid];
    }

    public function test_create_pending_sets_tenant_and_defaults(): void {
        global $DB;
        [$user, $jobid] = $this->make_job('/77');
        $row = $DB->get_record(taxonomy_manager::JOB_TABLE, ['id' => $jobid], '*', MUST_EXIST);
        $this->assertSame('extracted', $row->status);
        $this->assertSame(77, (int)$row->costcenterid);
        $this->assertSame(1, (int)$row->customerid);
        $this->assertSame('sop', $row->sourcekind);
        $this->assertSame('v1', $row->prompt_version);
        $this->assertGreaterThan(0, $row->timecreated);
        $this->assertSame(3, (int)$row->num_extracted);
    }

    public function test_create_pending_coerces_bad_sourcekind(): void {
        global $DB;
        $user = $this->getDataGenerator()->create_user();
        $jobid = taxonomy_manager::create_pending((int)$user->id, 0, 'T', 'bogus', 'src',
            anthropic_client::DEFAULT_MODEL);
        $row = $DB->get_record(taxonomy_manager::JOB_TABLE, ['id' => $jobid], '*', MUST_EXIST);
        $this->assertSame('manual', $row->sourcekind);
    }

    public function test_persist_candidates_writes_rows_with_tenant(): void {
        global $DB;
        [$user, $jobid] = $this->make_job('/1', 4);
        $cands = $DB->get_records(taxonomy_manager::CAND_TABLE, ['jobid' => $jobid], 'sortorder ASC');
        $this->assertCount(4, $cands);
        $i = 1;
        foreach ($cands as $c) {
            $this->assertSame($i++, (int)$c->sortorder);
            $this->assertSame('proposed', $c->status);
            $this->assertSame(1, (int)$c->costcenterid);
            $this->assertNull($c->taxonomyid);
            $this->assertGreaterThan(0, $c->timecreated);
        }
    }

    public function test_persist_marks_failed_when_empty(): void {
        global $DB;
        $user = $this->getDataGenerator()->create_user();
        $jobid = taxonomy_manager::create_pending((int)$user->id, 0, 'T', 'manual', 'src',
            anthropic_client::DEFAULT_MODEL);
        taxonomy_manager::persist_candidates($jobid, [], 10, 0, 'live');
        $row = $DB->get_record(taxonomy_manager::JOB_TABLE, ['id' => $jobid], '*', MUST_EXIST);
        $this->assertSame('failed', $row->status);
        $this->assertStringContainsString('parser_no_skills', (string)$row->error_detail);
    }

    public function test_mark_failed_truncates_error(): void {
        global $DB;
        $user = $this->getDataGenerator()->create_user();
        $jobid = taxonomy_manager::create_pending((int)$user->id, 0, 'T', 'manual', 'src',
            anthropic_client::DEFAULT_MODEL);
        taxonomy_manager::mark_failed($jobid, str_repeat('x', 2000));
        $row = $DB->get_record(taxonomy_manager::JOB_TABLE, ['id' => $jobid], '*', MUST_EXIST);
        $this->assertSame('failed', $row->status);
        $this->assertLessThanOrEqual(1000, strlen($row->error_detail));
    }

    // ── Review gate ─────────────────────────────────────────────────────

    public function test_review_candidate_rejects_invalid_status(): void {
        [$user, $jobid] = $this->make_job();
        global $DB;
        $cid = (int)$DB->get_field_select(taxonomy_manager::CAND_TABLE, 'id',
            'jobid = :j', ['j' => $jobid], IGNORE_MULTIPLE);
        $this->expectException(\coding_exception::class);
        taxonomy_manager::review_candidate($cid, 'bogus');
    }

    public function test_review_candidate_applies_edits(): void {
        global $DB;
        [$user, $jobid] = $this->make_job();
        $cid = (int)$DB->get_field_select(taxonomy_manager::CAND_TABLE, 'id',
            'jobid = :j', ['j' => $jobid], IGNORE_MULTIPLE);
        taxonomy_manager::review_candidate($cid, taxonomy_manager::C_EDITED, [
            'skillname'          => 'Edited Skill Name',
            'suggested_level'    => 9, // out of range — clamps to 5
            'suggested_category' => 'Technical',
            'reviewer_note'      => 'tightened the name',
        ]);
        $c = $DB->get_record(taxonomy_manager::CAND_TABLE, ['id' => $cid]);
        $this->assertSame('edited', $c->status);
        $this->assertSame('Edited Skill Name', $c->skillname);
        $this->assertSame(5, (int)$c->suggested_level);
        $this->assertSame('Technical', $c->suggested_category);
        $this->assertSame('tightened the name', $c->reviewer_note);
    }

    public function test_promote_requires_approved_or_edited(): void {
        global $DB;
        [$user, $jobid] = $this->make_job();
        $cid = (int)$DB->get_field_select(taxonomy_manager::CAND_TABLE, 'id',
            'jobid = :j', ['j' => $jobid], IGNORE_MULTIPLE);
        // Still 'proposed' — promotion must be refused (the gate).
        $this->expectException(\moodle_exception::class);
        taxonomy_manager::promote_candidate($cid, (int)$user->id);
    }

    public function test_promote_approved_candidate_creates_taxonomy_node(): void {
        global $DB;
        [$user, $jobid] = $this->make_job();
        $cid = (int)$DB->get_field_select(taxonomy_manager::CAND_TABLE, 'id',
            'jobid = :j', ['j' => $jobid], IGNORE_MULTIPLE);

        taxonomy_manager::review_candidate($cid, taxonomy_manager::C_APPROVED, [
            'skillname' => 'Promoted Skill',
        ]);
        $taxid = taxonomy_manager::promote_candidate($cid, (int)$user->id);
        $this->assertGreaterThan(0, $taxid);

        $node = $DB->get_record(taxonomy_manager::TAXONOMY_TABLE, ['id' => $taxid], '*', MUST_EXIST);
        $this->assertSame('Promoted Skill', $node->name);
        $this->assertSame('active', $node->status);
        $this->assertSame($cid, (int)$node->origin_candidateid);
        $this->assertSame((int)$user->id, (int)$node->approved_by);
        $this->assertSame(1, (int)$node->costcenterid); // tenant from /1

        // Candidate now linked to the node.
        $c = $DB->get_record(taxonomy_manager::CAND_TABLE, ['id' => $cid]);
        $this->assertSame($taxid, (int)$c->taxonomyid);
    }

    public function test_promote_is_idempotent_on_tenant_and_name(): void {
        global $DB;
        [$user, $jobid] = $this->make_job('/1', 2);
        $cands = array_values($DB->get_records(taxonomy_manager::CAND_TABLE,
            ['jobid' => $jobid], 'sortorder ASC'));

        // Force both candidates to the same name + approve.
        foreach ($cands as $c) {
            taxonomy_manager::review_candidate((int)$c->id, taxonomy_manager::C_APPROVED,
                ['skillname' => 'Same Name']);
        }
        $tax1 = taxonomy_manager::promote_candidate((int)$cands[0]->id, (int)$user->id);
        $tax2 = taxonomy_manager::promote_candidate((int)$cands[1]->id, (int)$user->id);

        // Same (tenant, name) → one node, both candidates link to it.
        $this->assertSame($tax1, $tax2);
        $nodecount = $DB->count_records(taxonomy_manager::TAXONOMY_TABLE,
            ['costcenterid' => 1, 'name' => 'Same Name']);
        $this->assertSame(1, $nodecount);
    }

    public function test_finalise_review_marks_job_reviewed(): void {
        global $DB;
        [$user, $jobid] = $this->make_job();
        taxonomy_manager::finalise_review($jobid, (int)$user->id);
        $row = $DB->get_record(taxonomy_manager::JOB_TABLE, ['id' => $jobid]);
        $this->assertSame('reviewed', $row->status);
        $this->assertSame((int)$user->id, (int)$row->reviewed_by);
        $this->assertGreaterThan(0, (int)$row->reviewed_at);
    }

    public function test_update_taxonomy_can_retire_node(): void {
        global $DB;
        [$user, $jobid] = $this->make_job();
        $cid = (int)$DB->get_field_select(taxonomy_manager::CAND_TABLE, 'id',
            'jobid = :j', ['j' => $jobid], IGNORE_MULTIPLE);
        taxonomy_manager::review_candidate($cid, taxonomy_manager::C_APPROVED);
        $taxid = taxonomy_manager::promote_candidate($cid, (int)$user->id);

        taxonomy_manager::update_taxonomy($taxid, ['status' => taxonomy_manager::TAX_RETIRED]);
        $node = $DB->get_record(taxonomy_manager::TAXONOMY_TABLE, ['id' => $taxid]);
        $this->assertSame('retired', $node->status);

        // Retired nodes excluded from list_taxonomy.
        $this->assertCount(0, taxonomy_manager::list_taxonomy(1));
    }

    // ── Tenant isolation ────────────────────────────────────────────────

    public function test_load_for_actor_blocks_other_tenant(): void {
        [$owner, $jobid] = $this->make_job('/1');

        global $DB;
        $intruder = $this->getDataGenerator()->create_user();
        $DB->set_field('user', 'open_path', '/77', ['id' => $intruder->id]);
        $intruder = $DB->get_record('user', ['id' => $intruder->id]);

        // No access without manage_all.
        $this->assertNull(taxonomy_manager::load_for_actor($jobid, $intruder, false));
        // manage_all grants access.
        $this->assertNotNull(taxonomy_manager::load_for_actor($jobid, $intruder, true));
    }

    public function test_load_for_actor_allows_owner(): void {
        [$owner, $jobid] = $this->make_job('/1');
        $loaded = taxonomy_manager::load_for_actor($jobid, $owner, false);
        $this->assertNotNull($loaded);
        $this->assertSame($jobid, (int)$loaded->job->id);
        $this->assertNotEmpty($loaded->candidates);
    }

    public function test_list_for_actor_scopes_to_tenant(): void {
        global $DB;
        // Owner A on tenant 1.
        [$ownerA, $jobA] = $this->make_job('/1');
        // Owner B on tenant 77.
        [$ownerB, $jobB] = $this->make_job('/77');

        // A sees only their own / tenant-1 jobs.
        $listA = taxonomy_manager::list_for_actor($ownerA, false, 50);
        $idsA = array_map(fn($j) => (int)$j->id, $listA);
        $this->assertContains($jobA, $idsA);
        $this->assertNotContains($jobB, $idsA);

        // manage_all sees both.
        $listAll = taxonomy_manager::list_for_actor($ownerA, true, 50);
        $idsAll = array_map(fn($j) => (int)$j->id, $listAll);
        $this->assertContains($jobA, $idsAll);
        $this->assertContains($jobB, $idsAll);
    }

    public function test_tenant_root_extraction(): void {
        $u = new \stdClass();
        $u->open_path = '/1/2/3';
        $this->assertSame(1, taxonomy_manager::tenant_root_for($u));
        $u->open_path = '/177/9';
        $this->assertSame(177, taxonomy_manager::tenant_root_for($u));
        $u->open_path = '';
        $this->assertSame(0, taxonomy_manager::tenant_root_for($u));
    }

    public function test_tokens_used_today_aggregates(): void {
        global $DB;
        $user = $this->getDataGenerator()->create_user();
        $j1 = taxonomy_manager::create_pending((int)$user->id, 0, 'A', 'manual', 'src',
            anthropic_client::DEFAULT_MODEL);
        $j2 = taxonomy_manager::create_pending((int)$user->id, 0, 'B', 'manual', 'src',
            anthropic_client::DEFAULT_MODEL);
        $mock = anthropic_client::call_mock('src', 1);
        $sk = response_parser::parse($mock['body']);
        taxonomy_manager::persist_candidates($j1, $sk, 100, 50, 'live');
        taxonomy_manager::persist_candidates($j2, $sk, 200, 75, 'live');
        $this->assertSame(425, taxonomy_manager::tokens_used_today((int)$user->id));
    }
}
