<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

namespace local_sentientia_leaderboard;

defined('MOODLE_INTERNAL') || die();

use local_sentientia_platform\feature_flags;

/**
 * ADR-031: leaderboards stay inside the viewer's tenant.
 *
 * :viewall defaulted to the manager archetype, which every tenant admin holds
 * at system context, and every surface (index.php, list_boards, get_board,
 * view.php, stream.php, the block and its board picker) skipped the tenant
 * gate for a holder and bypassed learners' opt-outs. :promoteboard - the
 * customer-wide board right - had the same default, and create() silently
 * made a board customer-wide when its owner had no tenant.
 *
 * Each case: a scoped tenant admin (manager-archetype role at system context,
 * open_path /1) is refused / sees nothing of tenant 177; a viewer with no
 * tenant gets nothing; the site admin still sees everything.
 *
 * @package    local_sentientia_leaderboard
 * @category   test
 * @copyright  2026 Airpay Payment Services
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \local_sentientia_leaderboard\board_manager
 * @covers     \local_sentientia_leaderboard\external\get_board
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

    /** A tenant admin as UAT has them: manager-archetype role at SYSTEM context. */
    private function tenant_admin_at(string $path): \stdClass {
        global $DB;
        $u = $this->user_at($path);
        $managerid = (int) $DB->get_field('role', 'id', ['shortname' => 'manager'], MUST_EXIST);
        role_assign($managerid, $u->id, \context_system::instance()->id);
        return $u;
    }

    /** A completion board of tenant $tenant (0 = customer-wide), created by the site admin. */
    private function board_of(int $tenant, string $name): int {
        $this->setAdminUser();
        $course = $this->getDataGenerator()->create_course();
        return board_manager::create([
            'name' => $name, 'type' => board_manager::TYPE_COMPLETION,
            'courseid' => (int) $course->id, 'ownerid' => (int) get_admin()->id,
            'tenantid' => $tenant,
        ]);
    }

    /** @return int[] ids of the boards list_for_viewer() gives the current user */
    private function visible_ids(): array {
        $ids = array_map(fn($b) => (int) $b->id, board_manager::list_for_viewer());
        sort($ids);
        return $ids;
    }

    private function assert_refused(callable $fn, string $errorcode, string $why): void {
        try {
            $fn();
            $this->fail($why);
        } catch (\moodle_exception $e) {
            $this->assertSame($errorcode, $e->errorcode, $why);
        }
    }

    public function test_viewall_and_promoteboard_have_no_default_grant(): void {
        global $DB;
        foreach (['local/sentientia_leaderboard:viewall', 'local/sentientia_leaderboard:promoteboard'] as $cap) {
            $this->assertFalse($DB->record_exists('role_capabilities', ['capability' => $cap]),
                "{$cap} must not be granted by default: tenant admins hold manager-archetype roles.");
        }
    }

    public function test_a_tenant_admin_lists_only_their_tenant_and_customer_wide_boards(): void {
        $mine = $this->board_of(1, 'Mine');
        $wide = $this->board_of(0, 'Customer-wide');
        $theirs = $this->board_of(177, 'Theirs');

        $this->setUser($this->tenant_admin_at('/1'));
        $expected = [$mine, $wide];
        sort($expected);
        $this->assertSame($expected, $this->visible_ids(),
            'A /1 tenant admin must not list tenant 177 boards.');
        $this->assertFalse(board_manager::viewer_can_see(board_manager::get($theirs)));
        $this->assertTrue(board_manager::viewer_can_see(board_manager::get($mine)));
        $this->assertTrue(board_manager::viewer_can_see(board_manager::get($wide)));
    }

    public function test_a_viewer_with_no_tenant_sees_no_board(): void {
        $wide = $this->board_of(0, 'Customer-wide');
        $this->board_of(1, 'Mine');
        foreach (['', 'garbage'] as $path) {
            $this->setUser($this->tenant_admin_at($path));
            $this->assertSame([], $this->visible_ids(), "open_path '{$path}' must see no boards.");
            $this->assertFalse(board_manager::viewer_can_see(board_manager::get($wide)));
        }
    }

    public function test_the_site_admin_still_sees_every_board(): void {
        $ids = [$this->board_of(1, 'A'), $this->board_of(0, 'B'), $this->board_of(177, 'C')];
        sort($ids);
        $this->setAdminUser();
        $this->assertSame($ids, $this->visible_ids());
    }

    public function test_get_board_refuses_another_tenants_board_and_honours_opt_outs(): void {
        global $DB;
        $this->setAdminUser();
        feature_flags::set('sentientia.leaderboards.enabled', 0, true, (int) get_admin()->id, 'phpunit');
        feature_flags::invalidate_caches();

        $theirs = $this->board_of(177, 'Theirs');
        $mine = $this->board_of(1, 'Mine');
        $shown = $this->user_at('/1/2');
        $hidden = $this->user_at('/1/2');
        foreach ([[$shown, 1], [$hidden, 2]] as [$u, $rank]) {
            $DB->insert_record('local_sentientia_lb_entries', (object) [
                'boardid' => $mine, 'userid' => $u->id, 'points' => 100 - $rank,
                'userrank' => $rank, 'costcenterid' => 1, 'last_recomputed' => time(),
            ]);
        }
        $DB->insert_record('local_sentientia_lb_optouts', (object) [
            'userid' => $hidden->id, 'customerid' => 1, 'timeoptedout' => time(),
        ]);

        $this->setUser($this->tenant_admin_at('/1'));
        $this->assert_refused(fn() => external\get_board::execute($theirs, 10), 'error_outoftenant',
            'A /1 tenant admin must not open a tenant 177 board.');
        $ids = array_column(external\get_board::execute($mine, 10)['rows'], 'userid');
        $this->assertSame([(int) $shown->id], array_map('intval', $ids),
            'A tenant admin must not see learners who opted out - :viewall used to bypass that.');

        $this->setAdminUser();
        $this->assertCount(2, external\get_board::execute($mine, 10)['rows'],
            'The site admin keeps the opt-out bypass they always had.');
        $this->assertSame((int) $theirs, (int) external\get_board::execute($theirs, 10)['boardid']);
    }

    public function test_create_keeps_scoped_actors_inside_their_tenant(): void {
        global $DB;
        $course = $this->getDataGenerator()->create_course();
        $make = fn(array $extra) => board_manager::create(['name' => 'B', 'type' => board_manager::TYPE_COMPLETION,
            'courseid' => (int) $course->id] + $extra);

        $actor = $this->tenant_admin_at('/1');
        $this->setUser($actor);
        $this->assert_refused(fn() => $make(['tenantid' => 177]), 'error_outoftenant',
            'A scoped actor must not create a board for another tenant.');
        $this->assert_refused(fn() => $make(['tenantid' => 0]), 'error_cantpromote',
            'A customer-wide board ranks every tenant: cross-tenant actors only.');
        $this->assertSame(1, (int) $DB->get_field('local_sentientia_lb_boards', 'tenantid',
            ['id' => $make([])]), 'Own tenant, resolved from the owner (the actor).');

        $nobody = $this->tenant_admin_at('');
        $this->setUser($nobody);
        $this->assert_refused(fn() => $make([]), 'error_outoftenant',
            'An owner with no tenant used to yield a silent customer-wide board.');

        $this->setAdminUser();
        $this->assert_refused(fn() => $make(['ownerid' => (int) $nobody->id]), 'error_outoftenant',
            'Even for the site admin, customer-wide must be asked for explicitly.');
        $this->assertSame(0, (int) $DB->get_field('local_sentientia_lb_boards', 'tenantid',
            ['id' => $make(['tenantid' => 0])]));
    }

    public function test_the_upgrade_revoke_removes_existing_grants(): void {
        global $CFG, $DB;
        require_once($CFG->dirroot . '/local/sentientia_leaderboard/db/upgradelib.php');
        $sys = \context_system::instance();
        $managerid = (int) $DB->get_field('role', 'id', ['shortname' => 'manager'], MUST_EXIST);
        // What an install before 2026-09-25 left behind.
        assign_capability('local/sentientia_leaderboard:viewall', CAP_ALLOW, $managerid, $sys->id);
        assign_capability('local/sentientia_leaderboard:promoteboard', CAP_ALLOW, $managerid, $sys->id);

        $this->assertSame(2, local_sentientia_leaderboard_revoke_cross_tenant_caps());
        foreach (['viewall', 'promoteboard'] as $cap) {
            $this->assertFalse($DB->record_exists('role_capabilities',
                ['capability' => "local/sentientia_leaderboard:{$cap}"]));
        }
    }
}
