<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

namespace local_airpay_roles\privacy;

defined('MOODLE_INTERNAL') || die();

use core_privacy\local\request\approved_contextlist;
use core_privacy\local\request\writer;
use local_airpay_roles\role_manager;

/**
 * Privacy provider lock-in tests for local_airpay_roles.
 *
 * Validates GDPR Right-to-be-Forgotten + Right-of-Access through the
 * Moodle privacy subsystem. Covers metadata declaration, context
 * resolution, export, and the audit-retention-aware redact-on-delete.
 *
 * @package    local_airpay_roles
 * @category   test
 */
final class provider_test extends \core_privacy\tests\provider_testcase {

    public function test_get_metadata(): void {
        $collection = new \core_privacy\local\metadata\collection('local_airpay_roles');
        $collection = provider::get_metadata($collection);
        $items = $collection->get_collection();
        $this->assertCount(1, $items);
        $this->assertSame('local_airpay_roles_auditlog', $items[0]->get_name());
    }

    public function test_get_contexts_for_userid_finds_changedby(): void {
        $this->resetAfterTest();
        $this->setAdminUser();

        $u = $this->getDataGenerator()->create_user();
        $rid = create_role('Test', 'privrole', '');
        // role_manager::update_capability writes audit row with changedby = current user.
        $this->setUser($u);
        // Admin caller is needed for the cap; switch to admin context.
        $this->setAdminUser();
        // Re-trigger as $u — they need :manage cap, which requires assigning manager role.
        $sysctx = \context_system::instance();
        role_assign((int) $GLOBALS['DB']->get_field('role', 'id', ['shortname' => 'manager']),
            $u->id, $sysctx->id);
        $this->setUser($u);
        role_manager::update_capability($rid, 'moodle/course:create', 'allow');

        $contexts = provider::get_contexts_for_userid((int) $u->id);
        $this->assertContains($sysctx->id, $contexts->get_contextids());
    }

    public function test_export_user_data_includes_their_audit_rows(): void {
        $this->resetAfterTest();
        $this->setAdminUser();

        $u = $this->getDataGenerator()->create_user();
        $sysctx = \context_system::instance();
        $rid = create_role('Test', 'privexport', '');

        // Make $u the changedby for an audit row.
        role_assign((int) $GLOBALS['DB']->get_field('role', 'id', ['shortname' => 'manager']),
            $u->id, $sysctx->id);
        $this->setUser($u);
        role_manager::update_capability($rid, 'moodle/course:create', 'allow', 'GDPR test');

        $contextlist = new approved_contextlist($u, 'local_airpay_roles', [$sysctx->id]);
        provider::export_user_data($contextlist);

        // Writer should have a non-empty export for system context.
        $writer = writer::with_context($sysctx);
        $this->assertTrue($writer->has_any_data());
    }

    public function test_delete_data_for_user_redacts_changedby(): void {
        global $DB;
        $this->resetAfterTest();
        $this->setAdminUser();

        $u = $this->getDataGenerator()->create_user();
        $sysctx = \context_system::instance();
        $rid = create_role('Test', 'privdel', '');

        role_assign((int) $DB->get_field('role', 'id', ['shortname' => 'manager']),
            $u->id, $sysctx->id);
        $this->setUser($u);
        role_manager::update_capability($rid, 'moodle/course:create', 'allow');

        // Confirm the audit row references $u.
        $rows_before = $DB->count_records('local_airpay_roles_auditlog',
            ['changedby' => $u->id]);
        $this->assertGreaterThanOrEqual(1, $rows_before);

        // Switch back to admin to do the delete.
        $this->setAdminUser();
        $contextlist = new approved_contextlist($u, 'local_airpay_roles', [$sysctx->id]);
        provider::delete_data_for_user($contextlist);

        // After delete: changedby = 0 for those rows; row count unchanged
        // (audit retention).
        $rows_after_changedby = $DB->count_records('local_airpay_roles_auditlog',
            ['changedby' => $u->id]);
        $this->assertSame(0, $rows_after_changedby,
            'changedby references must be redacted to 0');

        // Audit row still exists with changedby=0.
        $redacted = $DB->count_records('local_airpay_roles_auditlog',
            ['changedby' => 0]);
        $this->assertGreaterThanOrEqual($rows_before, $redacted);
    }

    public function test_delete_data_for_user_redacts_targetuserid(): void {
        global $DB;
        $this->resetAfterTest();
        $this->setAdminUser();

        $u = $this->getDataGenerator()->create_user();
        $sysctx = \context_system::instance();
        $rid = create_role('Test', 'privtarget', '');

        // Make $u the TARGET of a role assignment (admin assigns u).
        role_manager::assign_user_to_role($rid, (int) $u->id);

        $rows_before = $DB->count_records('local_airpay_roles_auditlog',
            ['targetuserid' => $u->id]);
        $this->assertGreaterThanOrEqual(1, $rows_before);

        $contextlist = new approved_contextlist($u, 'local_airpay_roles', [$sysctx->id]);
        provider::delete_data_for_user($contextlist);

        // targetuserid redacted to NULL.
        $rows_after = $DB->count_records('local_airpay_roles_auditlog',
            ['targetuserid' => $u->id]);
        $this->assertSame(0, $rows_after);
    }
}
