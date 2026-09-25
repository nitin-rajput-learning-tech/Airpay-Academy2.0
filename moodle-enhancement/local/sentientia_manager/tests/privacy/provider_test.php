<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

namespace local_sentientia_manager\privacy;

defined('MOODLE_INTERNAL') || die();

use core_privacy\local\request\approved_contextlist;
use core_privacy\local\request\writer;
use local_sentientia_manager\approval_manager;

/**
 * Privacy provider lock-in tests for local_sentientia_manager.
 *
 * @package    local_sentientia_manager
 * @category   test
 */
final class provider_test extends \core_privacy\tests\provider_testcase {

    use \local_sentientia_org\test\bizlms_fixture;

    /**
     * ADR-031: an allocation needs the target to be the manager's direct report
     * in the same tenant, and the course to be in that tenant. Returns [manager, report].
     *
     * @return \stdClass[]
     */
    private function manager_and_report(): array {
        global $DB;
        $this->ensure_bizlms_schema();
        $mgr = $this->getDataGenerator()->create_user();
        $u = $this->getDataGenerator()->create_user();
        $DB->set_field('user', 'open_path', '/1/2', ['id' => $mgr->id]);
        $DB->set_field('user', 'open_path', '/1/2', ['id' => $u->id]);
        $DB->set_field('user', 'open_supervisorid', $mgr->id, ['id' => $u->id]);
        return [$DB->get_record('user', ['id' => $mgr->id], '*', MUST_EXIST),
                $DB->get_record('user', ['id' => $u->id], '*', MUST_EXIST)];
    }

    private function seed_course(): \stdClass {
        global $DB;
        $course = $this->getDataGenerator()->create_course();
        if (!$DB->record_exists('enrol',
                ['courseid' => $course->id, 'enrol' => 'manual'])) {
            $DB->insert_record('enrol', (object) [
                'enrol' => 'manual', 'courseid' => $course->id,
                'status' => 0, 'sortorder' => 0, 'roleid' => 5,
                'timecreated' => time(), 'timemodified' => time(),
            ]);
        }
        return $course;
    }

    public function test_get_metadata_declares_two_tables(): void {
        $collection = new \core_privacy\local\metadata\collection('local_sentientia_manager');
        $collection = provider::get_metadata($collection);
        $names = array_map(fn($i) => $i->get_name(), $collection->get_collection());
        $this->assertContains('local_sentientia_mgr_requests', $names);
        $this->assertContains('local_sentientia_mgr_allocations', $names);
    }

    public function test_export_includes_requests_for_requester_and_manager(): void {
        $this->resetAfterTest();
        $this->setAdminUser();

        $u = $this->getDataGenerator()->create_user();
        $mgr = $this->getDataGenerator()->create_user();
        $course = $this->seed_course();
        approval_manager::create_request((int) $u->id, (int) $course->id, (int) $mgr->id);

        $sysctx = \context_system::instance();

        // Export for requester.
        $cl1 = new approved_contextlist($u, 'local_sentientia_manager', [$sysctx->id]);
        provider::export_user_data($cl1);
        $this->assertTrue(writer::with_context($sysctx)->has_any_data(),
            'requester must see their request in export');

        writer::reset();

        // Export for manager.
        $cl2 = new approved_contextlist($mgr, 'local_sentientia_manager', [$sysctx->id]);
        provider::export_user_data($cl2);
        $this->assertTrue(writer::with_context($sysctx)->has_any_data(),
            'manager must see requests assigned to them in export');
    }

    public function test_delete_data_for_user_removes_their_own_requests(): void {
        global $DB;
        $this->resetAfterTest();
        $this->setAdminUser();

        $u = $this->getDataGenerator()->create_user();
        $mgr = $this->getDataGenerator()->create_user();
        $course = $this->seed_course();
        approval_manager::create_request((int) $u->id, (int) $course->id, (int) $mgr->id);

        $this->assertTrue($DB->record_exists('local_sentientia_mgr_requests',
            ['userid' => $u->id]));

        $cl = new approved_contextlist($u, 'local_sentientia_manager',
            [\context_system::instance()->id]);
        provider::delete_data_for_user($cl);

        $this->assertFalse($DB->record_exists('local_sentientia_mgr_requests',
            ['userid' => $u->id]),
            'requester\'s own requests must be deleted');
    }

    public function test_delete_data_for_user_anonymises_manager_role(): void {
        global $DB;
        $this->resetAfterTest();
        $this->setAdminUser();

        $u = $this->getDataGenerator()->create_user();
        $mgr = $this->getDataGenerator()->create_user();
        $course = $this->seed_course();
        $reqid = approval_manager::create_request((int) $u->id, (int) $course->id, (int) $mgr->id);

        $this->assertSame((int) $mgr->id,
            (int) $DB->get_field('local_sentientia_mgr_requests', 'managerid', ['id' => $reqid]));

        // Delete the MANAGER's data — their request rows (where they are
        // the manager but someone else is the requester) must NOT be
        // deleted, only the managerid reference must be anonymised.
        $cl = new approved_contextlist($mgr, 'local_sentientia_manager',
            [\context_system::instance()->id]);
        provider::delete_data_for_user($cl);

        $row = $DB->get_record('local_sentientia_mgr_requests', ['id' => $reqid]);
        $this->assertNotEmpty($row,
            'request must NOT be deleted just because the manager exercised their GDPR right');
        $this->assertSame(0, (int) $row->managerid,
            'managerid must be anonymised to 0');
    }

    public function test_delete_allocation_recipient_removes_row(): void {
        global $DB;
        $this->resetAfterTest();
        $this->setAdminUser();

        [$mgr, $u] = $this->manager_and_report();
        $course = $this->seed_course();
        $DB->set_field('course', 'open_path', '/1', ['id' => $course->id]);
        approval_manager::create_allocation((int) $mgr->id, (int) $u->id,
            (int) $course->id);

        $this->assertTrue($DB->record_exists('local_sentientia_mgr_allocations',
            ['userid' => $u->id]));

        $cl = new approved_contextlist($u, 'local_sentientia_manager',
            [\context_system::instance()->id]);
        provider::delete_data_for_user($cl);

        $this->assertFalse($DB->record_exists('local_sentientia_mgr_allocations',
            ['userid' => $u->id]));
    }
}
