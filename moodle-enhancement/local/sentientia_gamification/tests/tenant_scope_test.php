<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

namespace local_sentientia_gamification;

defined('MOODLE_INTERNAL') || die();

/**
 * ADR-031: the points leaderboard fails closed for a caller with no tenant.
 *
 * leaderboard::get_global() resolved the caller's tenant path and, when it came
 * back '' (no open_path, or one that does not parse), applied no filter at
 * all: the top users of EVERY tenant, with names. get_department() fell back to
 * get_global() for a target without a tenant, and an explicit $orgpath was
 * trusted as given. No web surface calls these today (latent), which is why
 * they are closed now, before one does.
 *
 * @package    local_sentientia_gamification
 * @category   test
 * @copyright  2026 Airpay Payment Services
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \local_sentientia_gamification\leaderboard
 * @group      tenant_isolation
 */
final class tenant_scope_test extends \advanced_testcase {

    use \local_sentientia_org\test\bizlms_fixture;

    /** @var array<string, \stdClass> learners keyed by label */
    private array $learners = [];

    protected function setUp(): void {
        parent::setUp();
        $this->resetAfterTest();
        $this->ensure_bizlms_schema();
        foreach (['airpay' => ['/1/2', 50], 'airpay2' => ['/1', 30],
                  'zeea' => ['/177/5', 90], 'nowhere' => ['', 70]] as $label => [$path, $points]) {
            $this->learners[$label] = $this->user_at($path, $points);
        }
    }

    private function user_at(string $path, int $points = 0): \stdClass {
        global $DB;
        $u = $this->getDataGenerator()->create_user();
        $DB->set_field('user', 'open_path', $path, ['id' => $u->id]);
        if ($points > 0) {
            $DB->insert_record('local_sentientia_streaks', (object) [
                'userid' => $u->id, 'total_points' => $points, 'timemodified' => time(),
            ]);
        }
        return $DB->get_record('user', ['id' => $u->id], '*', MUST_EXIST);
    }

    /** A tenant admin as UAT has them: manager-archetype role at SYSTEM context. */
    private function tenant_admin_at(string $path): \stdClass {
        global $DB;
        $u = $this->user_at($path);
        $managerid = (int) $DB->get_field('role', 'id', ['shortname' => 'manager'], MUST_EXIST);
        role_assign($managerid, $u->id, \context_system::instance()->id);
        return $u;
    }

    /** @param \stdClass[] $rows */
    private function ids(array $rows): array {
        $ids = array_map(fn($r) => (int) $r->userid, $rows);
        sort($ids);
        return $ids;
    }

    private function expected(string ...$labels): array {
        $ids = array_map(fn($l) => (int) $this->learners[$l]->id, $labels);
        sort($ids);
        return $ids;
    }

    public function test_a_tenant_admin_sees_only_their_tenant(): void {
        $this->setUser($this->tenant_admin_at('/1'));
        $this->assertSame($this->expected('airpay', 'airpay2'), $this->ids(leaderboard::get_global()));
        $this->assertSame([], leaderboard::get_global(10, '/177'),
            'An explicit orgpath of another tenant must not be honoured for a scoped caller.');
        $this->assertSame($this->expected('airpay'), $this->ids(leaderboard::get_global(10, '/1/2')),
            'Narrowing inside the caller\'s own tenant still works.');
        $this->assertSame([], leaderboard::get_department((int) $this->learners['zeea']->id),
            'Another tenant\'s department board is not the caller\'s to read.');
    }

    public function test_a_caller_with_no_tenant_gets_nothing(): void {
        foreach (['', 'garbage'] as $path) {
            $this->setUser($this->tenant_admin_at($path));
            $this->assertSame([], leaderboard::get_global(), "open_path '{$path}' used to see every tenant.");
            $this->assertSame([], leaderboard::get_global(10, '/1'));
        }
        $this->setUser($this->learners['nowhere']);
        $this->assertSame([], leaderboard::get_department((int) $this->learners['nowhere']->id),
            'get_department() used to fall back to the unscoped global board.');
        $this->assertSame(0, leaderboard::get_rank((int) $this->learners['nowhere']->id),
            'A rank counted across every tenant is no rank.');
    }

    public function test_the_site_admin_still_sees_every_tenant(): void {
        $this->setAdminUser();
        $this->assertSame($this->expected('airpay', 'airpay2', 'zeea', 'nowhere'),
            $this->ids(leaderboard::get_global()));
        $this->assertSame($this->expected('zeea'), $this->ids(leaderboard::get_global(10, '/177')));
    }

    public function test_ranks_stay_inside_the_learners_tenant(): void {
        // zeea (90) and nowhere (70) outrank airpay (50) site-wide, but not in tenant 1.
        $this->assertSame(1, leaderboard::get_rank((int) $this->learners['airpay']->id));
        $this->assertSame(2, leaderboard::get_rank((int) $this->learners['airpay2']->id));
    }
}
