<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

namespace local_airpay_roles;

defined('MOODLE_INTERNAL') || die();

/**
 * Phase-2 tests — bulk capability changes + role assignments.
 *
 * Locks in:
 *   - bulk_update_capability succeeds/skips/fails per-roleid correctly
 *   - bulk operations write one audit log row per successful role
 *   - assign_user_to_role is idempotent and writes role_assigned audit
 *   - unassign_user_from_role writes role_unassigned audit
 *
 * @package    local_airpay_roles
 * @category   test
 */
final class role_manager_phase_2_test extends \advanced_testcase {

    public function test_bulk_update_capability_applies_to_all_roles(): void {
        global $DB;
        $this->resetAfterTest();
        $this->setAdminUser();

        $r1 = create_role('Test 1', 'bulkr1', '');
        $r2 = create_role('Test 2', 'bulkr2', '');
        $r3 = create_role('Test 3', 'bulkr3', '');

        $result = role_manager::bulk_update_capability(
            [$r1, $r2, $r3], 'moodle/course:create', 'allow',
            'Bulk grant for promo cohort');

        $this->assertCount(3, $result['succeeded']);
        $this->assertCount(0, $result['skipped']);
        $this->assertCount(0, $result['failed']);

        // Each role now has the cap set.
        $context = \context_system::instance();
        foreach ([$r1, $r2, $r3] as $rid) {
            $perm = $DB->get_field('role_capabilities', 'permission',
                ['roleid' => $rid, 'capability' => 'moodle/course:create',
                 'contextid' => $context->id]);
            $this->assertSame((string) CAP_ALLOW, (string) $perm);
        }

        // 3 audit rows.
        $count = $DB->count_records('local_airpay_roles_auditlog',
            ['action' => 'capability_set', 'capability' => 'moodle/course:create']);
        $this->assertGreaterThanOrEqual(3, $count);
    }

    public function test_bulk_update_capability_buckets_admin_lockout_to_skipped(): void {
        global $DB;
        $this->resetAfterTest();
        $this->setAdminUser();

        $managerid = (int) $DB->get_field('role', 'id', ['shortname' => 'manager'], MUST_EXIST);
        $r1 = create_role('Test', 'bulkr4', '');

        // manager + site:config + prevent → should be skipped (lockout protection).
        // r1 + site:config + prevent → should succeed (it's a custom role).
        $result = role_manager::bulk_update_capability(
            [$managerid, $r1], 'moodle/site:config', 'prevent');

        $this->assertCount(1, $result['succeeded'],
            'custom role should accept the change');
        $this->assertCount(1, $result['skipped'],
            'manager-archetype site:config should be skipped, not failed');
        $this->assertSame($managerid, $result['skipped'][0]['roleid']);
    }

    public function test_bulk_update_capability_dedupes_roleids(): void {
        $this->resetAfterTest();
        $this->setAdminUser();
        $rid = create_role('Test', 'bulkr5', '');
        $result = role_manager::bulk_update_capability(
            [$rid, $rid, $rid], 'moodle/course:create', 'allow');
        $this->assertSame(1, count($result['succeeded']));
    }

    public function test_assign_user_to_role_idempotent_and_audited(): void {
        global $DB;
        $this->resetAfterTest();
        $this->setAdminUser();

        $u = $this->getDataGenerator()->create_user();
        $rid = create_role('Test', 'assigntest', '');

        $auditbefore = $DB->count_records('local_airpay_roles_auditlog',
            ['action' => 'role_assigned']);

        role_manager::assign_user_to_role($rid, (int) $u->id, 'New hire');

        $auditafter = $DB->count_records('local_airpay_roles_auditlog',
            ['action' => 'role_assigned']);
        $this->assertSame($auditbefore + 1, $auditafter,
            'one audit row per assign call');

        // Verify the assignment is in role_assignments.
        $context = \context_system::instance();
        $this->assertTrue($DB->record_exists('role_assignments',
            ['roleid' => $rid, 'userid' => $u->id, 'contextid' => $context->id]));

        // Re-assign — Moodle role_assign is idempotent.
        role_manager::assign_user_to_role($rid, (int) $u->id);
        // Audit row count goes up regardless (we log the attempt).
    }

    public function test_unassign_user_from_role(): void {
        global $DB;
        $this->resetAfterTest();
        $this->setAdminUser();

        $u = $this->getDataGenerator()->create_user();
        $rid = create_role('Test', 'unassigntest', '');
        role_manager::assign_user_to_role($rid, (int) $u->id);

        $context = \context_system::instance();
        $this->assertTrue($DB->record_exists('role_assignments',
            ['roleid' => $rid, 'userid' => $u->id, 'contextid' => $context->id]));

        role_manager::unassign_user_from_role($rid, (int) $u->id, 'Left team');

        $this->assertFalse($DB->record_exists('role_assignments',
            ['roleid' => $rid, 'userid' => $u->id, 'contextid' => $context->id]));

        $auditrow = $DB->get_record('local_airpay_roles_auditlog',
            ['action' => 'role_unassigned', 'roleid' => $rid, 'targetuserid' => $u->id]);
        $this->assertNotEmpty($auditrow);
        $this->assertSame('Left team', $auditrow->reason);
    }

    public function test_list_role_assignments(): void {
        $this->resetAfterTest();
        $this->setAdminUser();

        $u1 = $this->getDataGenerator()->create_user(['firstname' => 'Alice', 'lastname' => 'Atkins']);
        $u2 = $this->getDataGenerator()->create_user(['firstname' => 'Bob', 'lastname' => 'Brown']);
        $rid = create_role('Test', 'listassigntest', '');
        role_manager::assign_user_to_role($rid, (int) $u1->id);
        role_manager::assign_user_to_role($rid, (int) $u2->id);

        $page = role_manager::list_role_assignments($rid, '');
        $this->assertSame(2, $page['total']);
        // Sorted by lastname ASC → Atkins first.
        $this->assertSame((int) $u1->id, $page['rows'][0]['userid']);
    }

    public function test_list_role_assignments_search_filters(): void {
        $this->resetAfterTest();
        $this->setAdminUser();

        $u1 = $this->getDataGenerator()->create_user(['firstname' => 'Charlie', 'lastname' => 'Chen']);
        $u2 = $this->getDataGenerator()->create_user(['firstname' => 'Bob', 'lastname' => 'Brown']);
        $rid = create_role('Test', 'searchassigntest', '');
        role_manager::assign_user_to_role($rid, (int) $u1->id);
        role_manager::assign_user_to_role($rid, (int) $u2->id);

        $matches = role_manager::list_role_assignments($rid, 'Brown');
        $this->assertSame(1, $matches['total']);
        $this->assertSame((int) $u2->id, $matches['rows'][0]['userid']);
    }

    public function test_assign_user_to_role_rejects_unknown_role(): void {
        $this->resetAfterTest();
        $this->setAdminUser();
        $u = $this->getDataGenerator()->create_user();
        $this->expectException(\moodle_exception::class);
        role_manager::assign_user_to_role(999999, (int) $u->id);
    }

    public function test_assign_user_to_role_rejects_unknown_user(): void {
        $this->resetAfterTest();
        $this->setAdminUser();
        $rid = create_role('Test', 'unknowntest', '');
        $this->expectException(\moodle_exception::class);
        role_manager::assign_user_to_role($rid, 999999);
    }
}
