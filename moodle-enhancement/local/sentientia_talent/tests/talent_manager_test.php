<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

namespace local_sentientia_talent;

defined('MOODLE_INTERNAL') || die();

use local_sentientia_platform\feature_flags;

/**
 * Unit tests for local_sentientia_talent.
 *
 * Coverage:
 *   - capability gating (learner cannot read/write succession)
 *   - tenant isolation (Airpay manager can't see Public successors)
 *   - career-path CRUD
 *   - succession CRUD + duplicate guard + cross-tenant candidate guard
 *   - opportunity CRUD + register/withdraw interest
 *   - skills-dependency fallback (manual taxonomy match %)
 *   - feature-flag default-OFF no-op
 *
 * @package    local_sentientia_talent
 * @category   test
 * @covers     \local_sentientia_talent\talent_manager
 */
final class talent_manager_test extends \advanced_testcase {

    use \local_sentientia_platform\phpunit\open_path_fixture_trait;

    /** Airpay tenant root. */
    private const T_AIRPAY = 1;
    /** Public tenant root. */
    private const T_PUBLIC = 77;

    /** Turn the master + opportunity flags ON for the test body. */
    private function enable_flags(): void {
        // Global override (customer 0, tenant 0) so resolution is ON for
        // every tenant in the legacy 3-level path.
        feature_flags::set(talent_manager::FLAG_MASTER, 0, true);
        feature_flags::set(talent_manager::FLAG_OPPORTUNITIES, 0, true);
        feature_flags::invalidate_caches();
    }

    /** Create a user in a given tenant and log them in. */
    private function login_user_in_tenant(int $tenant, array $caps = [],
                                           string $designation = ''): \stdClass {
        global $DB;
        $user = $this->getDataGenerator()->create_user();
        $DB->set_field('user', 'open_path', '/' . $tenant, ['id' => $user->id]);
        if ($designation !== '') {
            // open_designation is added lazily by the trait's sibling logic;
            // ensure the column exists before setting it.
            $cols = $DB->get_columns('user');
            if (!isset($cols['open_designation'])) {
                $dbman = $DB->get_manager();
                $t = new \xmldb_table('user');
                $f = new \xmldb_field('open_designation', XMLDB_TYPE_CHAR, '200',
                    null, null, null, null);
                $dbman->add_field($t, $f);
            }
            $DB->set_field('user', 'open_designation', $designation, ['id' => $user->id]);
        }
        $user->open_path = '/' . $tenant;
        $this->setUser($user);

        if (!empty($caps)) {
            $roleid = $this->getDataGenerator()->create_role();
            $context = \context_system::instance();
            foreach ($caps as $cap) {
                assign_capability($cap, CAP_ALLOW, $roleid, $context->id, true);
            }
            role_assign($roleid, $user->id, $context->id);
            accesslib_clear_all_caches_for_unit_testing();
        }
        return $user;
    }

    // ─── Feature flag default-OFF no-op ──────────────────────────────

    public function test_flag_default_off_blocks_suite(): void {
        $this->login_user_in_tenant(self::T_AIRPAY,
            ['local/sentientia_talent:managesuccession']);
        // Default-OFF: is_enabled false, require_enabled throws.
        $this->assertFalse(talent_manager::is_enabled());
        $this->expectException(\moodle_exception::class);
        talent_manager::require_enabled();
    }

    public function test_flag_on_enables_suite(): void {
        $this->login_user_in_tenant(self::T_AIRPAY);
        $this->enable_flags();
        $this->assertTrue(talent_manager::is_enabled());
        $this->assertTrue(talent_manager::opportunities_enabled());
    }

    // ─── Career-path CRUD ────────────────────────────────────────────

    public function test_career_path_crud(): void {
        $this->enable_flags();
        $this->login_user_in_tenant(self::T_AIRPAY,
            ['local/sentientia_talent:managecareerpaths',
             'local/sentientia_talent:viewcareerpath']);

        $id = talent_manager::save_path((object) [
            'name' => 'Support to Lead',
            'from_designation' => 'Support Agent',
            'to_designation'   => 'Team Lead',
        ]);
        $this->assertGreaterThan(0, $id);

        $paths = talent_manager::list_paths(false);
        $this->assertCount(1, $paths);

        // paths_from filters by source designation.
        $from = talent_manager::paths_from('Support Agent');
        $this->assertCount(1, $from);
        $this->assertEmpty(talent_manager::paths_from('Nonexistent'));

        $this->assertTrue(talent_manager::delete_path($id));
        $this->assertEmpty(talent_manager::list_paths(false));
    }

    // ─── Capability gating ───────────────────────────────────────────

    public function test_learner_cannot_read_succession(): void {
        $this->enable_flags();
        // Learner: only the learner-facing caps, NOT viewsuccession.
        $this->login_user_in_tenant(self::T_AIRPAY,
            ['local/sentientia_talent:viewopportunities']);
        $this->expectException(\required_capability_exception::class);
        talent_manager::list_succession();
    }

    public function test_learner_cannot_write_succession(): void {
        $this->enable_flags();
        $this->login_user_in_tenant(self::T_AIRPAY,
            ['local/sentientia_talent:viewopportunities']);
        $this->expectException(\required_capability_exception::class);
        talent_manager::save_succession((object) [
            'designation' => 'Manager', 'candidateid' => 99, 'readiness' => 'developing']);
    }

    // ─── Succession CRUD + tenant isolation ──────────────────────────

    public function test_succession_crud_and_duplicate_guard(): void {
        global $DB;
        $this->enable_flags();
        $this->login_user_in_tenant(self::T_AIRPAY,
            ['local/sentientia_talent:managesuccession',
             'local/sentientia_talent:viewsuccession']);

        // A candidate in the same (Airpay) tenant.
        $cand = $this->getDataGenerator()->create_user();
        $DB->set_field('user', 'open_path', '/' . self::T_AIRPAY, ['id' => $cand->id]);

        $id = talent_manager::save_succession((object) [
            'designation' => 'Branch Manager',
            'candidateid' => $cand->id,
            'readiness'   => 'ready_1y',
        ]);
        $this->assertGreaterThan(0, $id);

        $list = talent_manager::list_succession('Branch Manager');
        $this->assertCount(1, $list);
        $this->assertEquals($cand->id, $list[0]['candidateid']);

        // Duplicate (same tenant, designation, candidate) is rejected.
        $this->expectException(\moodle_exception::class);
        talent_manager::save_succession((object) [
            'designation' => 'Branch Manager',
            'candidateid' => $cand->id,
            'readiness'   => 'developing',
        ]);
    }

    public function test_succession_rejects_cross_tenant_candidate(): void {
        global $DB;
        $this->enable_flags();
        // Manager is in Airpay tenant.
        $this->login_user_in_tenant(self::T_AIRPAY,
            ['local/sentientia_talent:managesuccession']);
        // Candidate is in the Public tenant.
        $cand = $this->getDataGenerator()->create_user();
        $DB->set_field('user', 'open_path', '/' . self::T_PUBLIC, ['id' => $cand->id]);

        $this->expectException(\moodle_exception::class);
        talent_manager::save_succession((object) [
            'designation' => 'X', 'candidateid' => $cand->id, 'readiness' => 'developing']);
    }

    public function test_succession_tenant_isolation_on_list(): void {
        global $DB;
        $this->enable_flags();

        // Airpay manager records an Airpay nomination.
        $airpaymgr = $this->login_user_in_tenant(self::T_AIRPAY,
            ['local/sentientia_talent:managesuccession',
             'local/sentientia_talent:viewsuccession']);
        $acand = $this->getDataGenerator()->create_user();
        $DB->set_field('user', 'open_path', '/' . self::T_AIRPAY, ['id' => $acand->id]);
        talent_manager::save_succession((object) [
            'designation' => 'Role A', 'candidateid' => $acand->id, 'readiness' => 'developing']);

        // Public manager records a Public nomination.
        $publicmgr = $this->login_user_in_tenant(self::T_PUBLIC,
            ['local/sentientia_talent:managesuccession',
             'local/sentientia_talent:viewsuccession']);
        $pcand = $this->getDataGenerator()->create_user();
        $DB->set_field('user', 'open_path', '/' . self::T_PUBLIC, ['id' => $pcand->id]);
        talent_manager::save_succession((object) [
            'designation' => 'Role B', 'candidateid' => $pcand->id, 'readiness' => 'developing']);

        // The Public manager sees ONLY the Public nomination.
        $publiclist = talent_manager::list_succession();
        $this->assertCount(1, $publiclist);
        $this->assertEquals($pcand->id, $publiclist[0]['candidateid']);

        // Switch back to the Airpay manager — sees ONLY the Airpay one.
        $this->setUser($airpaymgr);
        $airpaylist = talent_manager::list_succession();
        $this->assertCount(1, $airpaylist);
        $this->assertEquals($acand->id, $airpaylist[0]['candidateid']);
    }

    // ─── Opportunity CRUD + interest ─────────────────────────────────

    public function test_opportunity_and_interest_flow(): void {
        $this->enable_flags();
        $mgr = $this->login_user_in_tenant(self::T_AIRPAY,
            ['local/sentientia_talent:manageopportunities',
             'local/sentientia_talent:viewopportunities']);

        $oppid = talent_manager::save_opportunity((object) [
            'title' => 'Internal: Risk Analyst',
            'designation' => 'Risk Analyst',
            'status' => 'open',
        ]);
        $this->assertGreaterThan(0, $oppid);
        $this->assertCount(1, talent_manager::list_opportunities());

        // A learner in the same tenant registers interest.
        $learner = $this->login_user_in_tenant(self::T_AIRPAY,
            ['local/sentientia_talent:viewopportunities',
             'local/sentientia_talent:registerinterest']);
        $intid = talent_manager::register_interest($oppid, 'Keen to apply');
        $this->assertGreaterThan(0, $intid);

        // Feed marks it registered for this learner.
        $feed = talent_manager::opportunity_feed();
        $this->assertCount(1, $feed);
        $this->assertTrue($feed[0]['registered']);

        // Manager sees the applicant.
        $this->setUser($mgr);
        $applicants = talent_manager::list_interest($oppid);
        $this->assertCount(1, $applicants);
        $this->assertEquals($learner->id, $applicants[0]['userid']);

        // Learner withdraws.
        $this->setUser($learner);
        $this->assertTrue(talent_manager::withdraw_interest($oppid));
        $this->setUser($mgr);
        $this->assertEmpty(talent_manager::list_interest($oppid));
    }

    // ─── Skills-dependency fallback ──────────────────────────────────

    public function test_match_percentage_uses_manual_fallback(): void {
        global $DB;
        $this->enable_flags();
        $this->login_user_in_tenant(self::T_AIRPAY);

        // skillsai is NOT installed in the unit env — bridge must use the
        // manual local_sentientia_skills tables.
        $this->assertSame('manual', skills_bridge::source());
        $this->assertFalse(skills_bridge::skillsai_active());

        // Seed a manual skill + role requirement + user level.
        $catid = $DB->insert_record('local_sentientia_skill_cats', (object) [
            'name' => 'Tech', 'icon' => 'fa-cogs', 'color' => '#0066A7',
            'sort_order' => 1, 'timecreated' => time()]);
        $skillid = $DB->insert_record('local_sentientia_skills', (object) [
            'categoryid' => $catid, 'name' => 'SQL', 'max_level' => 5,
            'sort_order' => 1, 'timecreated' => time()]);
        $DB->insert_record('local_sentientia_role_skills', (object) [
            'designation' => 'Analyst', 'skillid' => $skillid,
            'required_level' => 4, 'timecreated' => time()]);

        $user = $this->getDataGenerator()->create_user();
        // No user-skill row yet → 0% match.
        $this->assertSame(0, skills_bridge::match_percentage($user->id, 'Analyst'));

        // Grant level 2 of 4 → 50% coverage.
        $DB->insert_record('local_sentientia_user_skills', (object) [
            'userid' => $user->id, 'skillid' => $skillid, 'current_level' => 2,
            'source' => 'manual', 'timecreated' => time(), 'timemodified' => time()]);
        $this->assertSame(50, skills_bridge::match_percentage($user->id, 'Analyst'));

        // Unknown designation with no required skills → 0 (no data).
        $this->assertSame(0, skills_bridge::match_percentage($user->id, 'Ghost'));
    }
}
