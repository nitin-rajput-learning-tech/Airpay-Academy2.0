<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

namespace local_sentientia_skillsai;

defined('MOODLE_INTERNAL') || die();

/**
 * ADR-031: :manage_all says WHAT, never WHERE.
 *
 * :manage_all defaulted to the manager archetype that every tenant admin
 * holds, and holding it opened every tenant's extraction jobs, review queue,
 * taxonomy and per-user gap feeds. Separately, a viewer whose open_path did
 * not resolve got tenant root 0, which gap_engine::tenant_summary() read as
 * "every tenant", and which matched the costcenterid-0 bucket of every other
 * no-tenant owner's jobs. These tests pin the fix.
 *
 * @package    local_sentientia_skillsai
 * @category   test
 * @copyright  2026 Airpay Payment Services
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \local_sentientia_skillsai\taxonomy_manager
 * @covers     \local_sentientia_skillsai\gap_engine
 * @group      tenant_isolation
 */
final class tenant_scope_test extends \advanced_testcase {

    use \local_sentientia_org\test\bizlms_fixture;

    protected function setUp(): void {
        parent::setUp();
        $this->resetAfterTest();
        $this->ensure_bizlms_schema();
    }

    private function user_at(string $path): \stdClass {
        global $DB;
        $u = $this->getDataGenerator()->create_user();
        $DB->set_field('user', 'open_path', $path, ['id' => $u->id]);
        return $DB->get_record('user', ['id' => $u->id], '*', MUST_EXIST);
    }

    /** A tenant admin: the stock manager-archetype role at system context. */
    private function tenant_admin(string $path): \stdClass {
        global $DB;
        $u = $this->user_at($path);
        $managerid = (int) $DB->get_field('role', 'id', ['shortname' => 'manager'], MUST_EXIST);
        role_assign($managerid, $u->id, \context_system::instance()->id);
        accesslib_clear_all_caches_for_unit_testing();
        return $u;
    }

    private function grant(\stdClass $user, string $capability): void {
        $sysctx = \context_system::instance();
        $roleid = $this->getDataGenerator()->create_role();
        assign_capability($capability, CAP_ALLOW, $roleid, $sysctx->id);
        role_assign($roleid, $user->id, $sysctx->id);
        accesslib_clear_all_caches_for_unit_testing();
    }

    private function job_for(\stdClass $owner): int {
        return taxonomy_manager::create_pending((int) $owner->id, 0, 'Job', 'sop', 'KYC SOP source text',
            anthropic_client::DEFAULT_MODEL, prompt_builder::VERSION_V1);
    }

    private function seed_gap(int $userid, int $tenant): int {
        global $DB;
        $now = time();
        $skillid = (int) $DB->insert_record('local_sentientia_skills', (object) [
            'categoryid' => 1, 'name' => 'Skill ' . $tenant . '-' . $userid, 'max_level' => 5,
            'sort_order' => 0, 'timecreated' => $now,
        ]);
        $DB->insert_record(gap_engine::GAP_TABLE, (object) [
            'userid' => $userid, 'customerid' => 1, 'costcenterid' => $tenant,
            'skillid' => $skillid, 'designation' => 'Teller', 'required_level' => 4,
            'held_level' => 1, 'gap_size' => 3, 'impact_weight' => 0, 'batchid' => 'b',
            'timecreated' => $now, 'timemodified' => $now,
        ]);
        return $skillid;
    }

    public function test_manage_all_has_no_default_holder(): void {
        global $DB;
        $this->assertFalse($DB->record_exists('role_capabilities',
            ['capability' => 'local/sentientia_skillsai:manage_all']),
            'No role may hold :manage_all by default - tenant admins hold manager-archetype roles.');

        $this->setUser($this->tenant_admin('/1'));
        $this->assertFalse(taxonomy_manager::can_manage_all());

        $this->setAdminUser();
        $this->assertTrue(taxonomy_manager::can_manage_all());
    }

    public function test_manage_all_unscopes_only_a_cross_tenant_caller(): void {
        $holder = $this->tenant_admin('/1');
        $this->grant($holder, 'local/sentientia_skillsai:manage_all');
        $this->setUser($holder);
        $this->assertFalse(taxonomy_manager::can_manage_all(),
            'A stray :manage_all grant must not make a tenant admin cross-tenant.');

        $this->grant($holder, \local_sentientia_platform\tenant::CROSS_TENANT_CAPABILITY);
        $this->setUser($holder);
        $this->assertTrue(taxonomy_manager::can_manage_all());
    }

    public function test_tenant_summary_zero_is_nothing_and_null_is_everything(): void {
        $this->seed_gap((int) $this->user_at('/1')->id, 1);
        $this->seed_gap((int) $this->user_at('/177')->id, 177);

        $this->assertSame([], gap_engine::tenant_summary(0),
            'Tenant root 0 (an unresolved open_path) used to mean every tenant.');
        $this->assertSame([], gap_engine::tenant_summary(-1));
        $this->assertCount(1, gap_engine::tenant_summary(1));
        $this->assertCount(1, gap_engine::tenant_summary(177));
        $this->assertCount(2, gap_engine::tenant_summary(null));
    }

    public function test_actor_with_no_tenant_reaches_only_their_own_jobs(): void {
        $me = $this->tenant_admin('');
        $other = $this->user_at('');
        $mine = $this->job_for($me);
        $theirs = $this->job_for($other);
        $airpay = $this->job_for($this->user_at('/1'));

        $ids = array_map(fn($j) => (int) $j->id, taxonomy_manager::list_for_actor($me, false));
        $this->assertSame([$mine], $ids,
            'Root 0 must not match the costcenterid-0 bucket of other no-tenant owners.');
        $this->assertNotNull(taxonomy_manager::load_for_actor($mine, $me, false));
        $this->assertNull(taxonomy_manager::load_for_actor($theirs, $me, false));
        $this->assertNull(taxonomy_manager::load_for_actor($airpay, $me, false));
    }

    public function test_scoped_actor_keeps_their_tenant(): void {
        $admin1 = $this->tenant_admin('/1');
        $colleague = $this->user_at('/1/4');
        $shared = $this->job_for($colleague);
        $foreign = $this->job_for($this->user_at('/177'));

        $this->assertNotNull(taxonomy_manager::load_for_actor($shared, $admin1, false));
        $this->assertNull(taxonomy_manager::load_for_actor($foreign, $admin1, false));
        $ids = array_map(fn($j) => (int) $j->id, taxonomy_manager::list_for_actor($admin1, false));
        $this->assertContains($shared, $ids);
        $this->assertNotContains($foreign, $ids);
    }
}
