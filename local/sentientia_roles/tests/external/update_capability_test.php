<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

namespace local_sentientia_roles\external;

defined('MOODLE_INTERNAL') || die();

/**
 * WS tests for {@see update_capability}.
 *
 * Critical lock-in: this is the write path that mutates production
 * security posture. Tests guard the cap gate, sesskey, and the
 * end-to-end audit-trail invariant.
 *
 * @package    local_sentientia_roles
 * @category   test
 */
final class update_capability_test extends \advanced_testcase {

    public function test_manage_capability_required(): void {
        global $DB;
        $this->resetAfterTest();

        $u = $this->getDataGenerator()->create_user();
        $sysctx = \context_system::instance();
        $roleid = $this->getDataGenerator()->create_role();
        role_change_permission($roleid, $sysctx, 'local/sentientia_roles:view', CAP_ALLOW);
        role_assign($roleid, $u->id, $sysctx->id);
        $this->setUser($u);

        // sesskey check happens AFTER cap check, so the cap exception fires first.
        $this->expectException(\required_capability_exception::class);
        update_capability::execute(1, 'moodle/course:create', 'allow', '');
    }

    public function test_admin_can_set_allow(): void {
        global $DB;
        $this->resetAfterTest();
        $this->setAdminUser();

        $roleid = create_role('Test', 'wsupdtest', '');

        // Inject sesskey for the require_sesskey() gate.
        $_POST['sesskey'] = sesskey();

        $r = update_capability::execute($roleid, 'moodle/course:create', 'allow', 'unit-test');
        $this->assertSame(CAP_INHERIT, $r['oldpermission']);
        $this->assertSame(CAP_ALLOW,    $r['newpermission']);
        $this->assertSame('inherit',    $r['oldlabel']);
        $this->assertSame('allow',      $r['newlabel']);

        // Audit row was written.
        $entry = $DB->get_record('local_sentientia_roles_auditlog', ['id' => $r['id']], '*', MUST_EXIST);
        $this->assertSame('capability_set', $entry->action);
        $this->assertSame('unit-test',      $entry->reason);
    }

    public function test_reason_truncated_to_1kb(): void {
        global $DB;
        $this->resetAfterTest();
        $this->setAdminUser();
        $_POST['sesskey'] = sesskey();

        $roleid = create_role('Test', 'wsupdtest2', '');
        $longreason = str_repeat('x', 2000);
        $r = update_capability::execute($roleid, 'moodle/course:create', 'allow', $longreason);
        $entry = $DB->get_record('local_sentientia_roles_auditlog', ['id' => $r['id']], '*', MUST_EXIST);
        $this->assertLessThanOrEqual(1024, mb_strlen($entry->reason),
            'reason must be truncated to 1024 chars to avoid log bloat');
    }

    public function test_invalid_permission_throws(): void {
        $this->resetAfterTest();
        $this->setAdminUser();
        $_POST['sesskey'] = sesskey();

        $roleid = create_role('Test', 'wsupdtest3', '');
        $this->expectException(\invalid_parameter_exception::class);
        update_capability::execute($roleid, 'moodle/course:create', 'godmode', '');
    }

    public function test_unknown_role_throws(): void {
        $this->resetAfterTest();
        $this->setAdminUser();
        $_POST['sesskey'] = sesskey();

        $this->expectException(\moodle_exception::class);
        update_capability::execute(999999, 'moodle/course:create', 'allow', '');
    }

    public function test_unknown_capability_throws(): void {
        $this->resetAfterTest();
        $this->setAdminUser();
        $_POST['sesskey'] = sesskey();

        $roleid = create_role('Test', 'wsupdtest4', '');
        $this->expectException(\moodle_exception::class);
        update_capability::execute($roleid, 'made/up:capability', 'allow', '');
    }

    public function test_admin_lockout_blocked(): void {
        global $DB;
        $this->resetAfterTest();
        $this->setAdminUser();
        $_POST['sesskey'] = sesskey();

        $managerid = (int) $DB->get_field('role', 'id', ['shortname' => 'manager'], MUST_EXIST);
        $this->expectException(\moodle_exception::class);
        update_capability::execute($managerid, 'moodle/site:config', 'prohibit', '');
    }

    public function test_round_trip_inherit_allow_inherit(): void {
        global $DB;
        $this->resetAfterTest();
        $this->setAdminUser();
        $_POST['sesskey'] = sesskey();

        $roleid = create_role('Test', 'wsupdtest5', '');
        $context = \context_system::instance();

        // 1. inherit → allow
        $r1 = update_capability::execute($roleid, 'moodle/course:create', 'allow', '');
        $this->assertSame(CAP_INHERIT, $r1['oldpermission']);
        $this->assertSame(CAP_ALLOW,    $r1['newpermission']);

        // Verify the cap is actually written.
        $perm = $DB->get_field('role_capabilities', 'permission',
            ['roleid' => $roleid, 'capability' => 'moodle/course:create',
             'contextid' => $context->id]);
        $this->assertSame((string) CAP_ALLOW, (string) $perm);

        // 2. allow → inherit (capability_unset)
        $r2 = update_capability::execute($roleid, 'moodle/course:create', 'inherit', '');
        $this->assertSame(CAP_ALLOW,    $r2['oldpermission']);
        $this->assertSame(CAP_INHERIT, $r2['newpermission']);

        // The audit row records the unset action.
        $entry = $DB->get_record('local_sentientia_roles_auditlog', ['id' => $r2['id']]);
        $this->assertSame('capability_unset', $entry->action);
    }
}
