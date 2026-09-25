<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

namespace local_sentientia_gamification;

defined('MOODLE_INTERNAL') || die();

/**
 * ADR-031 rule 4: the tenant-scoped badges fail closed for a learner with no tenant.
 *
 * Two seeded badges read the learner's tenant: "Compliance Champion"
 * (compliance_complete - every mandatory course of the learner's tenant
 * completed) and "Team Player" (leaderboard_top10 - top 10 by points inside
 * the learner's tenant). When the learner's open_path gave no tenant (empty,
 * 'garbage', '/abc'), both fell back to SITE-WIDE scope: the mandatory count
 * was every tenant's courses and the rank was counted across every tenant.
 * Nothing was disclosed (the award is the learner's own), but it broke rule 4
 * as written, and leaderboard::get_rank() already refused the same fallback.
 *
 * Since the 2026-09-25 fix-forward (review item S6) only a cross-tenant user -
 * a site admin, or a holder of local/sentientia_platform:crosstenant - keeps
 * the site-wide scope; anyone else simply does not earn those two badges.
 * Badges that read no tenant (first_course, ...) are unaffected.
 *
 * badge_manager::check_badges() is called directly: the course_completed
 * observer chain around it is covered by badge_manager_test.
 *
 * @package    local_sentientia_gamification
 * @category   test
 * @copyright  2026 Airpay Payment Services
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \local_sentientia_gamification\badge_manager
 * @group      tenant_isolation
 */
final class badge_scope_test extends \advanced_testcase {

    use \local_sentientia_org\test\bizlms_fixture;

    /** The two criteria that read the learner's tenant. */
    private const SCOPED = ['compliance_complete', 'leaderboard_top10'];

    protected function setUp(): void {
        parent::setUp();
        $this->resetAfterTest();
        $this->ensure_bizlms_schema();
        require_once(__DIR__ . '/../lib.php');
        local_sentientia_gamification_seed_badges();
    }

    /** A learner at $path with $points total points (no streaks row when 0). */
    private function user_at(string $path, int $points = 0): \stdClass {
        global $DB;
        $u = $this->getDataGenerator()->create_user();
        $DB->set_field('user', 'open_path', $path, ['id' => $u->id]);
        if ($points > 0) {
            $this->give_points((int) $u->id, $points);
        }
        return $DB->get_record('user', ['id' => $u->id], '*', MUST_EXIST);
    }

    /** Set a user's cached points total (local_sentientia_streaks is unique per userid). */
    private function give_points(int $userid, int $points): void {
        global $DB;
        $DB->insert_record('local_sentientia_streaks', (object) [
            'userid' => $userid, 'current_streak' => 0, 'longest_streak' => 0,
            'total_points' => $points, 'timemodified' => time(),
        ]);
    }

    /** A mandatory (enddate > 0), visible course of tenant path $path. */
    private function mandatory_course(string $path): \stdClass {
        global $DB;
        $course = $this->getDataGenerator()->create_course(['enddate' => time() + YEARSECS]);
        $DB->set_field('course', 'open_path', $path, ['id' => $course->id]);
        return $course;
    }

    /** Record a completion directly (no event, so no observer runs). */
    private function complete(int $userid, \stdClass $course): void {
        global $DB;
        $now = time();
        $DB->insert_record('course_completions', (object) [
            'userid' => $userid, 'course' => $course->id, 'timeenrolled' => $now,
            'timestarted' => $now, 'timecompleted' => $now, 'reaggregate' => 0,
        ]);
    }

    /** Run the badge check and return the tenant-scoped badges the user now holds, sorted. */
    private function scoped_badges_after_check(int $userid): array {
        badge_manager::check_badges($userid);
        $types = array_values(array_intersect(
            array_column(badge_manager::get_user_badges($userid), 'criteria_type'), self::SCOPED));
        sort($types);
        return $types;
    }

    public function test_a_learner_with_no_tenant_earns_no_tenant_scoped_badge(): void {
        // The only mandatory course on the site - so a site-wide count would
        // call it "all mandatory courses" - and learners who outscore everyone.
        $course = $this->mandatory_course('/1');
        foreach (['', 'garbage', '/abc'] as $path) {
            $learner = $this->user_at($path, 5000);
            $this->complete((int) $learner->id, $course);

            $this->assertSame([], $this->scoped_badges_after_check((int) $learner->id),
                "open_path '{$path}' used to earn both badges on site-wide scope.");
            $this->assertContains('first_course',
                array_column(badge_manager::get_user_badges((int) $learner->id), 'criteria_type'),
                'Badges that read no tenant are still awarded.');
        }
    }

    public function test_a_tenant_learner_still_earns_both_inside_their_tenant(): void {
        $course = $this->mandatory_course('/1');
        // Another tenant's mandatory course, not completed: must not count.
        $this->mandatory_course('/177');
        // Ten tenant-177 learners outscore everyone: not in tenant 1's ranking.
        for ($i = 0; $i < 10; $i++) {
            $this->user_at('/177/' . (200 + $i), 9000 + $i);
        }
        $learner = $this->user_at('/1/5', 100);
        $this->complete((int) $learner->id, $course);

        $this->assertSame(self::SCOPED, $this->scoped_badges_after_check((int) $learner->id),
            'The fail-closed branch must not take the badges away from a learner who has a tenant.');
    }

    public function test_the_site_admin_keeps_the_site_wide_scope(): void {
        $admin = get_admin();
        $course = $this->mandatory_course('/1');
        $this->give_points((int) $admin->id, 5000);
        $this->complete((int) $admin->id, $course);

        $this->assertSame(self::SCOPED, $this->scoped_badges_after_check((int) $admin->id),
            'A cross-tenant user with no tenant is still evaluated site-wide.');
    }

    public function test_a_crosstenant_holder_with_no_tenant_keeps_the_site_wide_scope(): void {
        $holder = $this->user_at('', 5000);
        $roleid = $this->getDataGenerator()->create_role();
        assign_capability(\local_sentientia_platform\tenant::CROSS_TENANT_CAPABILITY, CAP_ALLOW, $roleid,
            \context_system::instance()->id);
        role_assign($roleid, $holder->id, \context_system::instance()->id);
        accesslib_clear_all_caches_for_unit_testing();
        $this->assertTrue(\local_sentientia_platform\tenant::is_cross_tenant((int) $holder->id));

        $course = $this->mandatory_course('/1');
        $this->complete((int) $holder->id, $course);

        $this->assertSame(self::SCOPED, $this->scoped_badges_after_check((int) $holder->id),
            'is_cross_tenant() decides the scope, not site admin alone.');
    }
}
