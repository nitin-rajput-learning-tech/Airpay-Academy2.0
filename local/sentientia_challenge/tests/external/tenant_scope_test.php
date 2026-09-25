<?php
// This file is part of Sentientia LMS.

/**
 * Challenge leaderboards and lists stay inside the caller's tenant (2026-09-25).
 *
 * Two leaks closed together: :viewall defaulted to the manager archetype, so
 * every tenant admin (a manager-archetype role) could read every tenant's
 * leaderboard with names; and a scoped caller whose open_path did not resolve
 * to a tenant got tenant 0, which both queries read as "every tenant".
 *
 * @package    local_sentientia_challenge
 * @category   test
 * @copyright  2026 Airpay Payment Services
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_sentientia_challenge\external;

defined('MOODLE_INTERNAL') || die();

use local_sentientia_challenge\challenge_engine;

/**
 * @covers \local_sentientia_challenge\external\get_leaderboard
 * @covers \local_sentientia_challenge\external\list_challenges
 * @group tenant_isolation
 */
final class tenant_scope_test extends \advanced_testcase {

    use \local_sentientia_org\test\bizlms_fixture;

    /** @var int */
    private $cid;

    private function user_at(string $path): \stdClass {
        global $DB;
        $u = $this->getDataGenerator()->create_user();
        $DB->set_field('user', 'open_path', $path, ['id' => $u->id]);
        return $DB->get_record('user', ['id' => $u->id], '*', MUST_EXIST);
    }

    private function board_row(int $userid, int $costcenterid, int $points): void {
        global $DB;
        $DB->insert_record('local_sentientia_challenge_leaderboard', (object) [
            'challengeid' => $this->cid, 'userid' => $userid, 'costcenterid' => $costcenterid,
            'points' => $points, 'userrank' => 1, 'attemptscompleted' => 1, 'lastrecomputed' => time(),
        ]);
    }

    private function leaderboard_ids(): array {
        $r = get_leaderboard::execute(challengeid: $this->cid, tenantmode: 'all');
        $ids = array_map('intval', array_column($r['rows'], 'userid'));
        sort($ids);
        return $ids;
    }

    protected function setUp(): void {
        parent::setUp();
        $this->resetAfterTest();
        $this->ensure_bizlms_schema();
        $this->setAdminUser();
        $this->cid = challenge_engine::create_challenge(['name' => 'Tenant', 'shortname' => 'tnt',
            'status' => challenge_engine::STATUS_ACTIVE, 'targetcount' => 1, 'pointsreward' => 10]);
    }

    public function test_viewall_is_not_granted_to_manager_archetype_roles(): void {
        global $DB;
        foreach ($DB->get_records('role', ['archetype' => 'manager']) as $role) {
            $this->assertFalse($DB->record_exists('role_capabilities', [
                'roleid' => $role->id, 'capability' => 'local/sentientia_challenge:viewall',
            ]), "Role {$role->shortname} (manager archetype) must not hold :viewall by default.");
        }
    }

    public function test_a_tenant_admin_asking_for_all_sees_only_their_tenant(): void {
        global $DB;
        // A manager-archetype tenant admin, as UAT's tenant admins are.
        $admin = $this->user_at('/1');
        $managerroleid = (int) $DB->get_field('role', 'id', ['shortname' => 'manager']);
        role_assign($managerroleid, $admin->id, \context_system::instance()->id);
        $mine = $this->user_at('/1/2');
        $theirs = $this->user_at('/177/178');
        $this->board_row((int) $mine->id, 1, 50);
        $this->board_row((int) $theirs->id, 177, 90);

        $this->setUser($admin);
        $this->assertSame([(int) $mine->id], $this->leaderboard_ids(),
            'tenantmode=all without :viewall is scoped to the caller\'s tenant.');
    }

    public function test_a_caller_with_no_tenant_gets_nothing(): void {
        $a = $this->user_at('/1/2');
        $b = $this->user_at('/177/178');
        $this->board_row((int) $a->id, 1, 50);
        $this->board_row((int) $b->id, 177, 90);

        foreach (['', 'garbage'] as $path) {
            $nobody = $this->user_at($path);
            $this->setUser($nobody);
            $this->assertSame([], $this->leaderboard_ids(), "open_path '{$path}' must not unlock every tenant.");
            $list = list_challenges::execute('', 'all', 'timecreated', 'desc', 0, 25);
            $this->assertSame(0, $list['total']);
        }
    }

    public function test_the_site_admin_still_sees_every_tenant(): void {
        $a = $this->user_at('/1/2');
        $b = $this->user_at('/177/178');
        $this->board_row((int) $a->id, 1, 50);
        $this->board_row((int) $b->id, 177, 90);

        $this->setAdminUser();
        $expected = [(int) $a->id, (int) $b->id];
        sort($expected);
        $this->assertSame($expected, $this->leaderboard_ids());
    }
}
