<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

namespace local_sentientia_recommendations;

defined('MOODLE_INTERNAL') || die();

/**
 * ADR-031: recommendation generation stays inside the caller's tenant.
 *
 * :generate defaults to the manager archetype (kept: generating for one's own
 * learners is a legitimate tenant-admin action), but generate.php accepted any
 * target user id, so any tenant admin could expire and replace the dashboard
 * recommendations of any tenant's learner - and send that learner's history to
 * Anthropic when live. The candidate list was every visible course on the
 * site, so other tenants' course names landed in the prompt and on the
 * learner's dashboard.
 *
 * @package    local_sentientia_recommendations
 * @category   test
 * @copyright  2026 Airpay Payment Services
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \local_sentientia_recommendations\recommendation_engine
 * @group      tenant_isolation
 */
final class tenant_scope_test extends \advanced_testcase {

    use \local_sentientia_org\test\bizlms_fixture;

    private const MANAGE_ALL = 'local/sentientia_recommendations:manage_all';

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

    /** A tenant admin as UAT has them: a manager-archetype role at SYSTEM context. */
    private function tenant_admin_at(string $path): \stdClass {
        global $DB;
        $u = $this->user_at($path);
        $managerid = (int) $DB->get_field('role', 'id', ['shortname' => 'manager'], MUST_EXIST);
        role_assign($managerid, $u->id, \context_system::instance()->id);
        accesslib_clear_all_caches_for_unit_testing();
        return $u;
    }

    private function course_at(?string $path): int {
        global $DB;
        $course = $this->getDataGenerator()->create_course();
        $DB->set_field('course', 'open_path', $path, ['id' => $course->id]);
        return (int) $course->id;
    }

    /** @return int[] */
    private function candidate_ids(\stdClass $learner): array {
        $profile = recommendation_engine::build_profile((int) $learner->id);
        return array_map(fn($c) => (int) $c->id,
            recommendation_engine::build_candidate_list($profile, 100));
    }

    public function test_manage_all_has_no_default_grant_and_generate_keeps_its_own(): void {
        global $DB;
        foreach ($DB->get_records('role', ['archetype' => 'manager']) as $role) {
            $this->assertFalse($DB->record_exists('role_capabilities',
                ['roleid' => $role->id, 'capability' => self::MANAGE_ALL]),
                "Role {$role->shortname} (manager archetype) must not hold :manage_all by default.");
        }
        $admin = $this->tenant_admin_at('/1');
        $this->assertTrue(has_capability('local/sentientia_recommendations:generate',
            \context_system::instance(), $admin->id),
            ':generate stays a tenant-admin function; its TARGET is what is now checked.');
    }

    public function test_a_tenant_admin_may_target_only_their_own_tenant(): void {
        $admin = $this->tenant_admin_at('/1');
        $aid = (int) $admin->id;
        $this->assertTrue(recommendation_engine::can_target((int) $this->user_at('/1/2')->id, $aid));
        $this->assertTrue(recommendation_engine::can_target($aid, $aid), 'Anyone may target themselves.');
        $this->assertFalse(recommendation_engine::can_target((int) $this->user_at('/177/4')->id, $aid),
            'A ZEEA learner\'s feed is not an Airpay admin\'s to rewrite.');
        $this->assertFalse(recommendation_engine::can_target((int) $this->user_at('/10')->id, $aid),
            '/10 is not inside /1.');
        $this->assertFalse(recommendation_engine::can_target((int) $this->user_at('')->id, $aid),
            'A learner with no tenant cannot be shown to be in the admin\'s tenant.');
    }

    public function test_a_caller_with_no_tenant_may_target_no_one_else(): void {
        $nobody = $this->tenant_admin_at('');
        $this->assertFalse(recommendation_engine::can_target((int) $this->user_at('/1')->id, (int) $nobody->id));
        $this->assertFalse(recommendation_engine::can_target((int) $this->user_at('')->id, (int) $nobody->id));
        $this->assertFalse(recommendation_engine::can_target((int) $this->user_at('/1')->id, 0));
    }

    public function test_the_site_admin_may_target_anyone(): void {
        $adminid = (int) get_admin()->id;
        foreach (['/1', '/77/3', '/177', ''] as $path) {
            $this->assertTrue(recommendation_engine::can_target((int) $this->user_at($path)->id, $adminid));
        }
    }

    public function test_candidates_come_from_the_learners_tenant_only(): void {
        $own = $this->course_at('/77');
        $owndept = $this->course_at('/77/5');
        $legacy = $this->course_at(null);
        $airpay = $this->course_at('/1');
        $zeea = $this->course_at('/177');
        $sibling = $this->course_at('/770');

        // Whoever triggers it (the site admin included), a /77 learner is
        // offered /77 courses: the scope is the learner's, not the viewer's.
        $this->setAdminUser();
        $ids = $this->candidate_ids($this->user_at('/77/3'));
        $this->assertContains($own, $ids);
        $this->assertContains($owndept, $ids);
        $this->assertContains($legacy, $ids, 'Legacy NULL-path courses stay visible, as in the catalogue.');
        foreach ([$airpay, $zeea, $sibling] as $foreign) {
            $this->assertNotContains($foreign, $ids);
        }
    }

    public function test_a_learner_with_no_tenant_gets_no_candidates(): void {
        $this->course_at('/1');
        $this->course_at(null);
        $this->assertSame([], $this->candidate_ids($this->user_at('')),
            'Tenant 0 must never mean every tenant\'s catalogue.');

        // A hand-built profile without ->catalogue falls back to ->tenant.
        $this->assertSame([], recommendation_engine::build_candidate_list(
            (object) ['tenant' => 'unknown', 'completed' => []], 100));
    }

    public function test_a_cross_tenant_learner_keeps_the_whole_catalogue(): void {
        $airpay = $this->course_at('/1');
        $zeea = $this->course_at('/177');
        // The site admin generating for themselves (the form's default target).
        $ids = $this->candidate_ids(get_admin());
        $this->assertContains($airpay, $ids);
        $this->assertContains($zeea, $ids);
    }

    public function test_the_upgrade_revoke_removes_existing_grants(): void {
        global $CFG, $DB;
        require_once($CFG->dirroot . '/local/sentientia_recommendations/db/upgradelib.php');
        // What an install before 2026-09-25 (or reset_role_capabilities()) left behind.
        $managerid = (int) $DB->get_field('role', 'id', ['shortname' => 'manager'], MUST_EXIST);
        assign_capability(self::MANAGE_ALL, CAP_ALLOW, $managerid, \context_system::instance()->id);
        $holder = $this->user_at('/1');
        role_assign($managerid, $holder->id, \context_system::instance()->id);
        accesslib_clear_all_caches_for_unit_testing();
        $this->assertTrue(has_capability(self::MANAGE_ALL, \context_system::instance(), $holder->id));

        $this->assertGreaterThanOrEqual(1, local_sentientia_recommendations_revoke_manage_all());

        $this->assertFalse($DB->record_exists('role_capabilities', ['capability' => self::MANAGE_ALL]));
        accesslib_clear_all_caches_for_unit_testing();
        $this->assertFalse(has_capability(self::MANAGE_ALL, \context_system::instance(), $holder->id));
    }
}
