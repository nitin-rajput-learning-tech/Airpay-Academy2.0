<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

/**
 * ADR-031: a team-member drill-down is bounded to the viewer's tenant.
 *
 * can_view_member() returned true for any target once the viewer held
 * local/sentientia_users:view, which every tenant admin does (manager
 * archetype, system context). member.php then showed that user's name, email,
 * employee id, org, courses, progress and certificate codes - in any tenant.
 *
 * @package    local_sentientia_manager
 * @category   test
 * @copyright  2026 Airpay Payment Services
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_sentientia_manager;

defined('MOODLE_INTERNAL') || die();

/**
 * @covers \local_sentientia_manager\team_manager
 * @group tenant_isolation
 */
final class tenant_scope_test extends \advanced_testcase {

    use \local_sentientia_org\test\bizlms_fixture;

    protected function setUp(): void {
        parent::setUp();
        $this->resetAfterTest();
        $this->ensure_bizlms_schema();
        // Legacy org seam (the default): the supervisor walk reads open_supervisorid.
        set_config('org_legacy', 1, 'local_sentientia_core');
    }

    /** A user at $path, reloaded so the record carries open_path. */
    private function user_at(string $path): \stdClass {
        global $DB;
        $u = $this->getDataGenerator()->create_user();
        $DB->set_field('user', 'open_path', $path, ['id' => $u->id]);
        return $DB->get_record('user', ['id' => $u->id], '*', MUST_EXIST);
    }

    /** A tenant admin as UAT has them: a manager-archetype role at system context. */
    private function tenant_admin(string $path): \stdClass {
        global $DB;
        $admin = $this->user_at($path);
        $managerroleid = (int) $DB->get_field('role', 'id', ['shortname' => 'manager'], MUST_EXIST);
        role_assign($managerroleid, $admin->id, \context_system::instance()->id);
        return $admin;
    }

    public function test_a_tenant_admin_sees_only_their_own_tenants_members(): void {
        $admin = $this->tenant_admin('/1');
        $this->assertTrue(has_capability('local/sentientia_users:view', \context_system::instance(), $admin->id),
            'Precondition: tenant admins hold sentientia_users:view by default.');
        $mine = $this->user_at('/1/2');
        $zeea = $this->user_at('/177/178');
        $public = $this->user_at('/77');
        $prefixtrap = $this->user_at('/10');

        $this->assertTrue(team_manager::can_view_member((int) $admin->id, (int) $mine->id));
        $this->assertFalse(team_manager::can_view_member((int) $admin->id, (int) $zeea->id),
            'Holding :view must not open another tenant\'s member page.');
        $this->assertFalse(team_manager::can_view_member((int) $admin->id, (int) $public->id));
        $this->assertFalse(team_manager::can_view_member((int) $admin->id, (int) $prefixtrap->id),
            '/1 never matches /10.');
        $this->assertFalse(team_manager::can_view_member((int) $admin->id, 999999), 'Nor a missing id.');
    }

    public function test_a_viewer_with_no_tenant_sees_no_other_member(): void {
        $target = $this->user_at('/1/2');
        foreach (['', 'garbage'] as $path) {
            $nobody = $this->tenant_admin($path);
            $this->assertFalse(team_manager::can_view_member((int) $nobody->id, (int) $target->id),
                "open_path '{$path}' must not open anybody's member page.");
            $this->assertTrue(team_manager::can_view_member((int) $nobody->id, (int) $nobody->id),
                'Their own page is still theirs.');
        }
    }

    public function test_the_supervisor_chain_and_the_site_admin_are_unchanged(): void {
        global $DB;
        $manager = $this->user_at('/1/2');
        $report = $this->user_at('/1/2');
        $DB->set_field('user', 'open_supervisorid', $manager->id, ['id' => $report->id]);
        $zeea = $this->user_at('/177/178');

        $this->assertTrue(team_manager::can_view_member((int) $manager->id, (int) $report->id),
            'A direct supervisor still sees their report.');
        $this->assertFalse(team_manager::can_view_member((int) $report->id, (int) $manager->id));
        $this->assertTrue(team_manager::can_view_member((int) get_admin()->id, (int) $zeea->id),
            'The site admin still sees every tenant.');
    }
}
