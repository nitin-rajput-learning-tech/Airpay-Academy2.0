<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

/**
 * ADR-031 follow-up (2026-09-25 review): :view is taken back from every
 * learner role, not only from roles whose archetype is 'student'.
 *
 * :view opens view.php, whose Users tab and exportcsv.php list path rosters
 * with names, emails and employee ids. Upgrade step 2026092500 revoked it by
 * archetype, so a learner role cloned from Student without its archetype, or
 * the BizLMS 'employee' role created without one, kept the rosters. Step
 * 2026092502 (db/upgradelib.php local_sentientia_learningpath_revoke_learner_view)
 * revokes it from every learner role and leaves deliberate grants alone.
 *
 * @package    local_sentientia_learningpath
 * @category   test
 * @copyright  2026 Airpay Payment Services
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_sentientia_learningpath;

defined('MOODLE_INTERNAL') || die();

/**
 * @covers ::local_sentientia_learningpath_revoke_learner_view
 * @covers ::xmldb_local_sentientia_learningpath_upgrade
 * @group tenant_isolation
 */
final class learner_view_revoke_test extends \advanced_testcase {

    private const CAP = 'local/sentientia_learningpath:view';

    protected function setUp(): void {
        global $CFG;
        parent::setUp();
        $this->resetAfterTest();
        require_once($CFG->dirroot . '/local/sentientia_learningpath/db/upgradelib.php');
    }

    /** A role with this shortname and archetype ('' = None), created if missing. */
    private function role(string $shortname, string $archetype): int {
        global $DB;
        $id = $DB->get_field('role', 'id', ['shortname' => $shortname]);
        if ($id) {
            $DB->set_field('role', 'archetype', $archetype, ['id' => $id]);
            return (int) $id;
        }
        return (int) create_role(ucfirst($shortname), $shortname, '', $archetype);
    }

    /** Give $roleid :view at system context with $permission. */
    private function grant(int $roleid, int $permission = CAP_ALLOW): void {
        assign_capability(self::CAP, $permission, $roleid, \context_system::instance()->id, true);
    }

    /** A user holding $roleid at system context. */
    private function holder(int $roleid): \stdClass {
        $u = $this->getDataGenerator()->create_user();
        role_assign($roleid, $u->id, \context_system::instance()->id);
        return $u;
    }

    private function holds(int $roleid): bool {
        global $DB;
        return $DB->record_exists('role_capabilities', [
            'roleid' => $roleid, 'capability' => self::CAP,
            'contextid' => \context_system::instance()->id, 'permission' => CAP_ALLOW,
        ]);
    }

    public function test_a_learner_role_without_the_student_archetype_loses_view(): void {
        $employee = $this->role('employee', '');
        $this->grant($employee);
        $learner = $this->holder($employee);
        accesslib_clear_all_caches_for_unit_testing();
        $this->assertTrue(has_capability(self::CAP, \context_system::instance(), $learner->id),
            'Precondition: the employee role holds :view, as step 2026092500 left it.');

        $result = local_sentientia_learningpath_revoke_learner_view();

        $this->assertContains('employee', $result['revoked']);
        $this->assertFalse($this->holds($employee));
        accesslib_clear_all_caches_for_unit_testing();
        $this->assertFalse(has_capability(self::CAP, \context_system::instance(), $learner->id),
            'A learner must no longer reach the path rosters.');
    }

    public function test_student_archetype_clones_lose_view_whatever_their_shortname(): void {
        $clone = $this->role('learnerclone', 'student');
        $this->grant($clone);
        $studentnamed = $this->role('student', '');
        $this->grant($studentnamed);

        $result = local_sentientia_learningpath_revoke_learner_view();

        $this->assertContains('learnerclone', $result['revoked']);
        $this->assertContains('student', $result['revoked']);
        $this->assertFalse($this->holds($clone));
        $this->assertFalse($this->holds($studentnamed));
    }

    public function test_manager_and_deliberate_grants_are_kept_and_reported(): void {
        global $DB;
        $managerid = (int) $DB->get_field('role', 'id', ['shortname' => 'manager'], MUST_EXIST);
        $this->grant($managerid);
        $auditor = $this->role('ldauditor', '');
        $this->grant($auditor);
        $viewer = $this->holder($auditor);

        $result = local_sentientia_learningpath_revoke_learner_view();

        $this->assertNotContains('manager', $result['revoked']);
        $this->assertNotContains('ldauditor', $result['revoked']);
        $this->assertTrue($this->holds($managerid), 'Tenant admins keep :view; the code scopes them.');
        $this->assertTrue($this->holds($auditor), 'A deliberate non-learner grant is left alone.');
        $this->assertContains('manager (manager)', $result['kept']);
        $this->assertContains('ldauditor (none)', $result['kept']);
        accesslib_clear_all_caches_for_unit_testing();
        $this->assertTrue(has_capability(self::CAP, \context_system::instance(), $viewer->id));
    }

    public function test_a_prevent_or_prohibit_on_a_learner_role_is_left_alone(): void {
        global $DB;
        $employee = $this->role('employee', '');
        $this->grant($employee, CAP_PROHIBIT);

        $result = local_sentientia_learningpath_revoke_learner_view();

        $this->assertNotContains('employee', $result['revoked']);
        $this->assertTrue($DB->record_exists('role_capabilities', [
            'roleid' => $employee, 'capability' => self::CAP, 'permission' => CAP_PROHIBIT,
        ]), 'Removing a prohibit would weaken the role, not revoke anything.');
    }

    public function test_it_is_idempotent(): void {
        $employee = $this->role('employee', '');
        $this->grant($employee);
        $this->assertContains('employee', local_sentientia_learningpath_revoke_learner_view()['revoked']);
        $this->assertSame([], local_sentientia_learningpath_revoke_learner_view()['revoked'],
            'A second run finds no learner role left to revoke.');
    }

    public function test_upgrade_step_2026092502_revokes_and_prints_the_holders_left(): void {
        global $CFG;
        require_once($CFG->libdir . '/upgradelib.php');
        require_once($CFG->dirroot . '/local/sentientia_learningpath/db/upgrade.php');

        $employee = $this->role('employee', '');
        $this->grant($employee);
        $auditor = $this->role('ldauditor', '');
        $this->grant($auditor);

        // A site that already ran 2026092501 (the version this range shipped).
        set_config('version', 2026092501, 'local_sentientia_learningpath');
        ob_start();
        $ok = xmldb_local_sentientia_learningpath_upgrade(2026092501);
        $out = (string) ob_get_clean();

        $this->assertTrue($ok);
        $this->assertFalse($this->holds($employee));
        $this->assertTrue($this->holds($auditor));
        $this->assertStringContainsString('employee', $out);
        $this->assertStringContainsString('ldauditor (none)', $out,
            'The upgrade must name every remaining holder so an admin can review it.');
        $this->assertEquals(2026092502, get_config('local_sentientia_learningpath', 'version'));
    }
}
