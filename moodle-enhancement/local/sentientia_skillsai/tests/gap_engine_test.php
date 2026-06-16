<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

namespace local_sentientia_skillsai;

defined('MOODLE_INTERNAL') || die();

/**
 * PHPUnit tests for gap_engine — the role-required-vs-held gap maths and
 * feed persistence.
 *
 * Seeds the sentientia_skills schema (skills + role_skills + user_skills)
 * directly so the gap engine has data to reason over without depending on
 * that plugin's UI. Designation is passed explicitly so we don't need the
 * production user.open_designation column.
 *
 * @package    local_sentientia_skillsai
 * @covers     \local_sentientia_skillsai\gap_engine
 */
final class gap_engine_test extends \advanced_testcase {

    use \local_sentientia_platform\phpunit\open_path_fixture_trait;

    /**
     * Seed a skill + its role requirement + a held level for a user.
     *
     * @return int skillid
     */
    private function seed_skill(string $name, string $designation, int $required,
                                int $userid, ?int $held): int {
        global $DB;
        $now = time();
        $skillid = $DB->insert_record('local_sentientia_skills', (object)[
            'categoryid' => 1, 'name' => $name, 'max_level' => 5,
            'sort_order' => 0, 'timecreated' => $now,
        ]);
        $DB->insert_record('local_sentientia_role_skills', (object)[
            'designation' => $designation, 'skillid' => $skillid,
            'required_level' => $required, 'timecreated' => $now,
        ]);
        if ($held !== null) {
            $DB->insert_record('local_sentientia_user_skills', (object)[
                'userid' => $userid, 'skillid' => $skillid, 'current_level' => $held,
                'source' => 'manual', 'timecreated' => $now, 'timemodified' => $now,
            ]);
        }
        return $skillid;
    }

    public function test_compute_returns_only_deficient_skills(): void {
        global $DB;
        $user = $this->getDataGenerator()->create_user();
        $DB->set_field('user', 'open_path', '/1', ['id' => $user->id]);
        $des = 'Compliance Analyst';

        // Gap: required 4, held 2 → gap 2.
        $gapskill = $this->seed_skill('KYC Deep', $des, 4, (int)$user->id, 2);
        // Met: required 3, held 3 → no gap.
        $this->seed_skill('AML Basics', $des, 3, (int)$user->id, 3);
        // Exceeded: required 2, held 5 → no gap.
        $this->seed_skill('Onboarding', $des, 2, (int)$user->id, 5);
        // Missing entirely: required 3, held none (0) → gap 3.
        $missingskill = $this->seed_skill('Sanctions', $des, 3, (int)$user->id, null);

        $gaps = gap_engine::compute_for_user((int)$user->id, $des);
        $byskill = [];
        foreach ($gaps as $g) {
            $byskill[$g->skillid] = $g;
        }

        $this->assertCount(2, $gaps);
        $this->assertArrayHasKey($gapskill, $byskill);
        $this->assertArrayHasKey($missingskill, $byskill);
        $this->assertSame(2, $byskill[$gapskill]->gap_size);
        $this->assertSame(3, $byskill[$missingskill]->gap_size);
        $this->assertSame(0, $byskill[$missingskill]->held_level);
    }

    public function test_compute_empty_when_no_designation(): void {
        $user = $this->getDataGenerator()->create_user();
        $this->assertSame([], gap_engine::compute_for_user((int)$user->id, ''));
    }

    public function test_rebuild_persists_feed_and_replaces(): void {
        global $DB;
        $user = $this->getDataGenerator()->create_user();
        $DB->set_field('user', 'open_path', '/77', ['id' => $user->id]);
        $des = 'Ops Lead';
        $this->seed_skill('Reconciliation', $des, 5, (int)$user->id, 1);
        $this->seed_skill('Dispute Handling', $des, 4, (int)$user->id, 0);

        $n = gap_engine::rebuild_for_user((int)$user->id, $des);
        $this->assertSame(2, $n);

        $rows = $DB->get_records(gap_engine::GAP_TABLE, ['userid' => $user->id]);
        $this->assertCount(2, $rows);
        foreach ($rows as $r) {
            $this->assertSame(77, (int)$r->costcenterid);
            $this->assertGreaterThan(0, (int)$r->gap_size);
            $this->assertNotSame('', $r->batchid);
        }

        // Re-run replaces, not duplicates.
        $n2 = gap_engine::rebuild_for_user((int)$user->id, $des);
        $this->assertSame(2, $n2);
        $this->assertSame(2, $DB->count_records(gap_engine::GAP_TABLE, ['userid' => $user->id]));
    }

    public function test_feed_for_user_joins_skill_name(): void {
        global $DB;
        $user = $this->getDataGenerator()->create_user();
        $DB->set_field('user', 'open_path', '/1', ['id' => $user->id]);
        $des = 'Analyst';
        $this->seed_skill('Fraud Patterns', $des, 4, (int)$user->id, 1);
        gap_engine::rebuild_for_user((int)$user->id, $des);

        $feed = gap_engine::feed_for_user((int)$user->id);
        $this->assertCount(1, $feed);
        $this->assertSame('Fraud Patterns', $feed[0]->skillname);
    }

    public function test_tenant_summary_aggregates_and_scopes(): void {
        global $DB;
        $des = 'Teller';

        // Two users on tenant 1 with a gap on the SAME skill.
        $u1 = $this->getDataGenerator()->create_user();
        $DB->set_field('user', 'open_path', '/1', ['id' => $u1->id]);
        $u2 = $this->getDataGenerator()->create_user();
        $DB->set_field('user', 'open_path', '/1', ['id' => $u2->id]);

        $skillid = $this->seed_skill('Cash Handling', $des, 4, (int)$u1->id, 1);
        // u2 holds the same skill at 0 (missing) — share the role requirement.
        $DB->insert_record('local_sentientia_user_skills', (object)[
            'userid' => $u2->id, 'skillid' => $skillid, 'current_level' => 2,
            'source' => 'manual', 'timecreated' => time(), 'timemodified' => time(),
        ]);

        gap_engine::rebuild_for_user((int)$u1->id, $des);
        gap_engine::rebuild_for_user((int)$u2->id, $des);

        $summary = gap_engine::tenant_summary(1, 50);
        $this->assertCount(1, $summary);
        $this->assertSame(2, (int)$summary[0]->affected_users);
        $this->assertSame('Cash Handling', $summary[0]->skillname);

        // A different tenant sees nothing.
        $this->assertCount(0, gap_engine::tenant_summary(177, 50));
    }
}
