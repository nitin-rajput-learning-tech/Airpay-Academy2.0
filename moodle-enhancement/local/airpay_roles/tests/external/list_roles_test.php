<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

namespace local_airpay_roles\external;

defined('MOODLE_INTERNAL') || die();

/**
 * WS tests for {@see list_roles}.
 *
 * Locks in: capability gate, paginated structure, action HTML emission
 * (the deep-link icons that show in the table).
 *
 * @package    local_airpay_roles
 * @category   test
 */
final class list_roles_test extends \advanced_testcase {

    public function test_view_capability_required(): void {
        $this->resetAfterTest();
        $u = $this->getDataGenerator()->create_user();
        $this->setUser($u);
        $this->expectException(\required_capability_exception::class);
        list_roles::execute();
    }

    public function test_admin_sees_all_roles(): void {
        $this->resetAfterTest();
        $this->setAdminUser();
        $result = list_roles::execute('', 'all', 'sortorder', 'asc', 0, 100);
        $this->assertGreaterThan(5, $result['total'],
            'Moodle ships at least 7 stock roles');
        $shortnames = array_column($result['rows'], 'shortname');
        $this->assertContains('manager', $shortnames);
        $this->assertContains('student', $shortnames);
    }

    public function test_each_row_has_action_html(): void {
        $this->resetAfterTest();
        $this->setAdminUser();
        $result = list_roles::execute('', 'all', 'sortorder', 'asc', 0, 100);
        foreach ($result['rows'] as $row) {
            $this->assertNotEmpty($row['actions'],
                'admin must see action buttons on every row');
            $this->assertStringContainsString('view.php',  $row['actions'],
                'view link is always present');
            $this->assertStringContainsString('fa-eye',    $row['actions'],
                'eye icon for view action');
            $this->assertStringContainsString('fa-pencil', $row['actions'],
                'pencil icon for edit (admin has manage cap)');
        }
    }

    public function test_view_only_user_sees_no_edit_actions(): void {
        global $DB;
        $this->resetAfterTest();

        $u = $this->getDataGenerator()->create_user();
        $sysctx = \context_system::instance();
        $roleid = $this->getDataGenerator()->create_role();
        role_change_permission($roleid, $sysctx, 'local/airpay_roles:view', CAP_ALLOW);
        role_assign($roleid, $u->id, $sysctx->id);
        $this->setUser($u);

        $result = list_roles::execute('', 'all', 'sortorder', 'asc', 0, 100);
        foreach ($result['rows'] as $row) {
            $this->assertStringContainsString('fa-eye', $row['actions']);
            $this->assertStringNotContainsString('fa-pencil', $row['actions'],
                'view-only user must NOT get the edit action');
            $this->assertStringNotContainsString('fa-history', $row['actions'],
                'view-only user must NOT get the audit action');
        }
    }

    public function test_search_filter_narrows_results(): void {
        $this->resetAfterTest();
        $this->setAdminUser();
        $result = list_roles::execute('manager', 'all', 'sortorder', 'asc', 0, 100);
        $this->assertGreaterThanOrEqual(1, $result['total']);
        foreach ($result['rows'] as $r) {
            $combined = strtolower($r['name'] . ' ' . $r['shortname']);
            $this->assertStringContainsString('manager', $combined);
        }
    }

    public function test_archetype_filter_excludes_others(): void {
        $this->resetAfterTest();
        $this->setAdminUser();
        $result = list_roles::execute('', 'student', 'sortorder', 'asc', 0, 100);
        foreach ($result['rows'] as $r) {
            $this->assertSame('student', $r['archetype']);
        }
    }

    public function test_pagination_structure(): void {
        $this->resetAfterTest();
        $this->setAdminUser();
        // perpage is clamped to a minimum of 5 (sanity floor); use 5 to test
        // honest pagination behaviour without fighting the floor.
        $result = list_roles::execute('', 'all', 'sortorder', 'asc', 0, 5);
        $this->assertSame(5, $result['perpage']);
        $this->assertSame(0, $result['page']);
        $this->assertGreaterThan(5, $result['total'],
            'Moodle ships > 5 stock roles');
        $this->assertLessThanOrEqual(5, count($result['rows']));
    }

    public function test_filterstoolong_rejected(): void {
        $this->resetAfterTest();
        $this->setAdminUser();
        $longblob = str_repeat('x', 5000);
        $this->expectException(\moodle_exception::class);
        list_roles::execute('', 'all', 'sortorder', 'asc', 0, 25, $longblob);
    }

    public function test_sortdir_desc_reverses(): void {
        $this->resetAfterTest();
        $this->setAdminUser();
        $asc  = list_roles::execute('', 'all', 'sortorder', 'asc',  0, 100);
        $desc = list_roles::execute('', 'all', 'sortorder', 'desc', 0, 100);
        // Both have same row count, but the first row of desc should be
        // the last row of asc.
        $this->assertSame(
            (int) end($asc['rows'])['id'],
            (int) reset($desc['rows'])['id']
        );
    }
}
