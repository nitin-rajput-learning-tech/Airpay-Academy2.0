<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

namespace local_sentientia_roles\external;

defined('MOODLE_INTERNAL') || die();

/**
 * WS tests for {@see get_role_caps}.
 *
 * @package    local_sentientia_roles
 * @category   test
 */
final class get_role_caps_test extends \advanced_testcase {

    public function test_view_capability_required(): void {
        $this->resetAfterTest();
        $u = $this->getDataGenerator()->create_user();
        $this->setUser($u);
        $this->expectException(\required_capability_exception::class);
        get_role_caps::execute(1);
    }

    public function test_admin_sees_paginated_list(): void {
        global $DB;
        $this->resetAfterTest();
        $this->setAdminUser();
        $managerid = (int) $DB->get_field('role', 'id', ['shortname' => 'manager'], MUST_EXIST);
        $page = get_role_caps::execute($managerid, '', 'all', 'capability', 'asc', 0, 25);
        $this->assertGreaterThan(0, $page['total']);
        $this->assertCount(25, $page['rows']);
    }

    public function test_each_row_has_perm_badge_css(): void {
        global $DB;
        $this->resetAfterTest();
        $this->setAdminUser();
        $managerid = (int) $DB->get_field('role', 'id', ['shortname' => 'manager'], MUST_EXIST);
        $page = get_role_caps::execute($managerid, '', 'allow', 'capability', 'asc', 0, 10);
        foreach ($page['rows'] as $row) {
            $this->assertSame(CAP_ALLOW, (int) $row['permission']);
            $this->assertStringContainsString('bg-success', $row['perm_css']);
            $this->assertTrue($row['perm_allow']);
            $this->assertFalse($row['perm_inherit']);
        }
    }

    public function test_admin_gets_edit_action_buttons(): void {
        global $DB;
        $this->resetAfterTest();
        $this->setAdminUser();
        $managerid = (int) $DB->get_field('role', 'id', ['shortname' => 'manager'], MUST_EXIST);
        $page = get_role_caps::execute($managerid, '', 'allow', 'capability', 'asc', 0, 5);
        foreach ($page['rows'] as $row) {
            $this->assertStringContainsString('edit-cap', $row['actions'],
                'admin must get the edit button on each cap row');
            $this->assertStringContainsString('reset-cap', $row['actions'],
                'admin must get the reset button on non-inherit rows');
        }
    }

    public function test_view_only_user_gets_no_actions(): void {
        global $DB;
        $this->resetAfterTest();

        $u = $this->getDataGenerator()->create_user();
        $sysctx = \context_system::instance();
        $roleid = $this->getDataGenerator()->create_role();
        role_change_permission($roleid, $sysctx, 'local/sentientia_roles:view', CAP_ALLOW);
        role_assign($roleid, $u->id, $sysctx->id);
        $this->setUser($u);

        $managerid = (int) $DB->get_field('role', 'id', ['shortname' => 'manager'], MUST_EXIST);
        $page = get_role_caps::execute($managerid, '', 'all', 'capability', 'asc', 0, 5);
        foreach ($page['rows'] as $row) {
            $this->assertSame('', $row['actions'],
                'view-only user must NOT see edit/reset buttons');
        }
    }

    public function test_search_filter(): void {
        global $DB;
        $this->resetAfterTest();
        $this->setAdminUser();
        $managerid = (int) $DB->get_field('role', 'id', ['shortname' => 'manager'], MUST_EXIST);

        $page = get_role_caps::execute($managerid, 'site:config', 'all', 'capability', 'asc', 0, 50);
        $this->assertGreaterThanOrEqual(1, $page['total']);
        foreach ($page['rows'] as $row) {
            $this->assertStringContainsString('site:config', $row['capability']);
        }
        $this->assertContains('moodle/site:config', array_column($page['rows'], 'capability'));
    }

    public function test_perm_filter_only_returns_matching(): void {
        global $DB;
        $this->resetAfterTest();
        $this->setAdminUser();
        $managerid = (int) $DB->get_field('role', 'id', ['shortname' => 'manager'], MUST_EXIST);

        $page = get_role_caps::execute($managerid, '', 'allow', 'capability', 'asc', 0, 50);
        foreach ($page['rows'] as $row) {
            $this->assertSame(CAP_ALLOW, (int) $row['permission']);
        }
    }
}
