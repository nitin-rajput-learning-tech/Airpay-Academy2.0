<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

namespace local_airpay_roles\external;

defined('MOODLE_INTERNAL') || die();

use local_airpay_roles\role_manager;

/**
 * WS tests for {@see list_audit}.
 *
 * @package    local_airpay_roles
 * @category   test
 */
final class list_audit_test extends \advanced_testcase {

    private function seed_two_audit_entries(): array {
        $this->setAdminUser();
        $r1 = create_role('Audit Role 1', 'auditrole1', '');
        $r2 = create_role('Audit Role 2', 'auditrole2', '');
        role_manager::update_capability($r1, 'moodle/course:create', 'allow', 'r1 cap1');
        role_manager::update_capability($r2, 'moodle/course:create', 'prevent', 'r2 cap1');
        return [$r1, $r2];
    }

    public function test_audit_capability_required(): void {
        $this->resetAfterTest();
        $u = $this->getDataGenerator()->create_user();
        $this->setUser($u);
        $this->expectException(\required_capability_exception::class);
        list_audit::execute();
    }

    public function test_admin_sees_all_entries(): void {
        $this->resetAfterTest();
        $this->seed_two_audit_entries();
        // setAdminUser() was called inside seed_two_audit_entries.
        $result = list_audit::execute(0, '', '', 'timecreated', 'desc', 0, 50);
        $this->assertGreaterThanOrEqual(2, $result['total']);
    }

    public function test_filter_by_role(): void {
        $this->resetAfterTest();
        [$r1, $r2] = $this->seed_two_audit_entries();
        $page = list_audit::execute($r1, '', '', 'timecreated', 'desc', 0, 50);
        $this->assertSame(1, $page['total']);
        $this->assertSame($r1, $page['rows'][0]['roleid']);
    }

    public function test_filter_by_action(): void {
        $this->resetAfterTest();
        [$r1] = $this->seed_two_audit_entries();
        // Reset r1's cap → that creates a capability_unset entry.
        role_manager::update_capability($r1, 'moodle/course:create', 'inherit', '');

        $sets   = list_audit::execute(0, 'capability_set', '', 'timecreated', 'desc', 0, 50);
        $unsets = list_audit::execute(0, 'capability_unset', '', 'timecreated', 'desc', 0, 50);

        $this->assertGreaterThanOrEqual(2, $sets['total']);
        $this->assertSame(1, $unsets['total']);
    }

    public function test_filter_by_capability(): void {
        $this->resetAfterTest();
        [$r1] = $this->seed_two_audit_entries();
        // Add an entry on a different capability.
        role_manager::update_capability($r1, 'moodle/course:manageactivities', 'allow', '');

        $r = list_audit::execute(0, '', 'moodle/course:manageactivities', 'timecreated', 'desc', 0, 50);
        $this->assertSame(1, $r['total']);
        $this->assertSame('moodle/course:manageactivities', $r['rows'][0]['capability']);
    }

    public function test_each_row_has_change_cell(): void {
        $this->resetAfterTest();
        $this->seed_two_audit_entries();
        $r = list_audit::execute(0, '', '', 'timecreated', 'desc', 0, 50);
        foreach ($r['rows'] as $row) {
            $this->assertArrayHasKey('change', $row);
            $this->assertStringContainsString('→', $row['change'],
                'change cell renders old → new arrow for cap rows');
        }
    }

    public function test_each_row_has_localised_action_label(): void {
        $this->resetAfterTest();
        $this->seed_two_audit_entries();
        $r = list_audit::execute(0, '', '', 'timecreated', 'desc', 0, 50);
        foreach ($r['rows'] as $row) {
            $this->assertArrayHasKey('action_label', $row);
            $this->assertNotEmpty($row['action_label']);
            $this->assertNotSame($row['action'], $row['action_label'],
                'localised label must differ from the raw key');
        }
    }

    public function test_pagination_structure(): void {
        $this->resetAfterTest();
        // perpage is clamped to a min of 10 by the manager, so seed enough
        // entries to make pagination meaningful.
        $this->setAdminUser();
        for ($i = 0; $i < 12; $i++) {
            $r = create_role('Role ' . $i, 'paginr' . $i, '');
            \local_airpay_roles\role_manager::update_capability(
                $r, 'moodle/course:create', 'allow', 'pagination test');
        }

        $page = list_audit::execute(0, '', '', 'timecreated', 'desc', 0, 10);
        $this->assertSame(10, $page['perpage']);
        $this->assertCount(10, $page['rows']);
        $this->assertGreaterThanOrEqual(12, $page['total']);

        // Page 1 (0-based) should have the remaining rows.
        $page2 = list_audit::execute(0, '', '', 'timecreated', 'desc', 1, 10);
        $this->assertSame(1, $page2['page']);
        $this->assertGreaterThanOrEqual(2, count($page2['rows']));
    }

    public function test_filterstoolong_rejected(): void {
        $this->resetAfterTest();
        $this->setAdminUser();
        $longblob = str_repeat('x', 5000);
        $this->expectException(\moodle_exception::class);
        list_audit::execute(0, '', '', 'timecreated', 'desc', 0, 50, $longblob);
    }
}
