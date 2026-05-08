<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

namespace local_airpay_manager;

defined('MOODLE_INTERNAL') || die();

/**
 * Tests for approval_manager — request lifecycle + course allocation.
 *
 * Tests are written against stock Moodle (no open_supervisorid field), so
 * direct_report_ids() returns []. The defensive guards in
 * approval_manager allow allocation in that mode without enforcing the
 * direct-report constraint.
 *
 * @package    local_airpay_manager
 * @category   test
 */
final class approval_manager_test extends \advanced_testcase {

    private function seed_course(): \stdClass {
        $course = $this->getDataGenerator()->create_course();
        // Enable manual enrol on the course.
        global $DB;
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

    public function test_create_request_inserts_pending_row(): void {
        global $DB;
        $this->resetAfterTest();

        $u = $this->getDataGenerator()->create_user();
        $mgr = $this->getDataGenerator()->create_user();
        $course = $this->seed_course();

        $id = approval_manager::create_request((int) $u->id, (int) $course->id,
            (int) $mgr->id, 'Need this for Q3 OKRs');
        $this->assertGreaterThan(0, $id);

        $row = $DB->get_record('local_airpay_mgr_requests', ['id' => $id], '*', MUST_EXIST);
        $this->assertSame(approval_manager::STATUS_PENDING, $row->status);
        $this->assertSame((int) $mgr->id, (int) $row->managerid);
    }

    public function test_create_request_rejects_duplicate_pending(): void {
        $this->resetAfterTest();
        $u = $this->getDataGenerator()->create_user();
        $mgr = $this->getDataGenerator()->create_user();
        $course = $this->seed_course();
        approval_manager::create_request((int) $u->id, (int) $course->id, (int) $mgr->id);
        $this->expectException(\moodle_exception::class);
        approval_manager::create_request((int) $u->id, (int) $course->id, (int) $mgr->id);
    }

    public function test_create_request_rejects_zero_managerid(): void {
        $this->resetAfterTest();
        $u = $this->getDataGenerator()->create_user();
        $course = $this->seed_course();
        $this->expectException(\invalid_parameter_exception::class);
        approval_manager::create_request((int) $u->id, (int) $course->id, 0);
    }

    public function test_decide_request_approves_and_enrols(): void {
        global $DB;
        $this->resetAfterTest();
        $this->setAdminUser();

        $u = $this->getDataGenerator()->create_user();
        $mgr = $this->getDataGenerator()->create_user();
        $course = $this->seed_course();
        $reqid = approval_manager::create_request((int) $u->id, (int) $course->id,
            (int) $mgr->id, 'reason');

        $result = approval_manager::decide_request($reqid,
            approval_manager::STATUS_APPROVED, 'OK', (int) $mgr->id);
        $this->assertSame(approval_manager::STATUS_APPROVED, $result['decision']);

        $row = $DB->get_record('local_airpay_mgr_requests', ['id' => $reqid], '*', MUST_EXIST);
        $this->assertSame('approved', $row->status);
        $this->assertSame((int) $mgr->id, (int) $row->decided_by);
        $this->assertNotEmpty($row->decided_at);

        // User should now be enrolled in the course.
        $isenrolled = is_enrolled(\context_course::instance($course->id), $u);
        $this->assertTrue($isenrolled, 'approve must enrol the user via manual enrol plugin');
    }

    public function test_decide_request_rejects_does_not_enrol(): void {
        $this->resetAfterTest();
        $this->setAdminUser();

        $u = $this->getDataGenerator()->create_user();
        $mgr = $this->getDataGenerator()->create_user();
        $course = $this->seed_course();
        $reqid = approval_manager::create_request((int) $u->id, (int) $course->id,
            (int) $mgr->id);

        approval_manager::decide_request($reqid,
            approval_manager::STATUS_REJECTED, 'Already covered by another course');

        $isenrolled = is_enrolled(\context_course::instance($course->id), $u);
        $this->assertFalse($isenrolled, 'reject must NOT enrol the user');
    }

    public function test_decide_request_double_decision_rejected(): void {
        $this->resetAfterTest();
        $this->setAdminUser();

        $u = $this->getDataGenerator()->create_user();
        $mgr = $this->getDataGenerator()->create_user();
        $course = $this->seed_course();
        $reqid = approval_manager::create_request((int) $u->id, (int) $course->id,
            (int) $mgr->id);
        approval_manager::decide_request($reqid, approval_manager::STATUS_APPROVED);

        $this->expectException(\moodle_exception::class);
        approval_manager::decide_request($reqid, approval_manager::STATUS_REJECTED);
    }

    public function test_decide_request_invalid_decision_rejected(): void {
        $this->resetAfterTest();
        $this->setAdminUser();
        $u = $this->getDataGenerator()->create_user();
        $mgr = $this->getDataGenerator()->create_user();
        $course = $this->seed_course();
        $reqid = approval_manager::create_request((int) $u->id, (int) $course->id,
            (int) $mgr->id);

        $this->expectException(\invalid_parameter_exception::class);
        approval_manager::decide_request($reqid, 'maybe');
    }

    public function test_create_allocation_inserts_and_enrols(): void {
        global $DB;
        $this->resetAfterTest();
        $this->setAdminUser();

        $u = $this->getDataGenerator()->create_user();
        $mgr = $this->getDataGenerator()->create_user();
        $course = $this->seed_course();

        $id = approval_manager::create_allocation((int) $mgr->id, (int) $u->id,
            (int) $course->id, time() + 86400 * 7, 'Q3 priority');
        $this->assertGreaterThan(0, $id);

        $row = $DB->get_record('local_airpay_mgr_allocations', ['id' => $id], '*', MUST_EXIST);
        $this->assertSame(approval_manager::ALLOC_ASSIGNED, $row->status);

        $this->assertTrue(is_enrolled(\context_course::instance($course->id), $u));
    }

    public function test_create_allocation_rejects_duplicate(): void {
        $this->resetAfterTest();
        $this->setAdminUser();

        $u = $this->getDataGenerator()->create_user();
        $mgr = $this->getDataGenerator()->create_user();
        $course = $this->seed_course();

        approval_manager::create_allocation((int) $mgr->id, (int) $u->id, (int) $course->id);
        $this->expectException(\moodle_exception::class);
        approval_manager::create_allocation((int) $mgr->id, (int) $u->id, (int) $course->id);
    }

    public function test_list_requests_filters_by_status(): void {
        $this->resetAfterTest();
        $this->setAdminUser();

        $u = $this->getDataGenerator()->create_user();
        $mgr = $this->getDataGenerator()->create_user();
        $c1 = $this->seed_course();
        $c2 = $this->seed_course();
        $r1 = approval_manager::create_request((int) $u->id, (int) $c1->id, (int) $mgr->id);
        $r2 = approval_manager::create_request((int) $u->id, (int) $c2->id, (int) $mgr->id);
        approval_manager::decide_request($r1, approval_manager::STATUS_APPROVED);

        $pending  = approval_manager::list_requests((int) $mgr->id, 'pending');
        $approved = approval_manager::list_requests((int) $mgr->id, 'approved');
        $all      = approval_manager::list_requests((int) $mgr->id, 'all');

        $this->assertSame(1, $pending['total']);
        $this->assertSame(1, $approved['total']);
        $this->assertSame(2, $all['total']);
    }

    public function test_list_requests_scopes_by_managerid(): void {
        $this->resetAfterTest();
        $this->setAdminUser();

        $u = $this->getDataGenerator()->create_user();
        $mgr1 = $this->getDataGenerator()->create_user();
        $mgr2 = $this->getDataGenerator()->create_user();
        $course = $this->seed_course();

        approval_manager::create_request((int) $u->id, (int) $course->id, (int) $mgr1->id);

        $for_mgr1 = approval_manager::list_requests((int) $mgr1->id, 'pending');
        $for_mgr2 = approval_manager::list_requests((int) $mgr2->id, 'pending');

        $this->assertSame(1, $for_mgr1['total']);
        $this->assertSame(0, $for_mgr2['total'],
            'mgr2 must not see mgr1\'s assigned requests');
    }

    public function test_pending_request_count(): void {
        $this->resetAfterTest();
        $this->setAdminUser();

        $u = $this->getDataGenerator()->create_user();
        $mgr = $this->getDataGenerator()->create_user();
        $c1 = $this->seed_course();
        $c2 = $this->seed_course();
        approval_manager::create_request((int) $u->id, (int) $c1->id, (int) $mgr->id);
        approval_manager::create_request((int) $u->id, (int) $c2->id, (int) $mgr->id);

        $this->assertSame(2, approval_manager::pending_request_count((int) $mgr->id));
    }

    public function test_delete_allocation_removes_row(): void {
        global $DB;
        $this->resetAfterTest();
        $this->setAdminUser();

        $u = $this->getDataGenerator()->create_user();
        $mgr = $this->getDataGenerator()->create_user();
        $course = $this->seed_course();
        $id = approval_manager::create_allocation((int) $mgr->id, (int) $u->id,
            (int) $course->id);

        $this->assertTrue($DB->record_exists('local_airpay_mgr_allocations', ['id' => $id]));
        approval_manager::delete_allocation($id);
        $this->assertFalse($DB->record_exists('local_airpay_mgr_allocations', ['id' => $id]));
    }

    // ─────────────────────────────────────────────────────────────────
    // Notifications (v1.2.0) — close the dead-end where the learner had
    // no idea their request was decided / a course was assigned.
    // ─────────────────────────────────────────────────────────────────

    public function test_decide_request_sends_notification_to_requester(): void {
        $this->resetAfterTest();
        $this->setAdminUser();
        $this->preventResetByRollback();
        $sink = $this->redirectMessages();

        $u = $this->getDataGenerator()->create_user();
        $mgr = $this->getDataGenerator()->create_user();
        $course = $this->seed_course();
        $reqid = approval_manager::create_request((int) $u->id, (int) $course->id,
            (int) $mgr->id);

        approval_manager::decide_request($reqid,
            approval_manager::STATUS_APPROVED, 'OK', (int) $mgr->id);

        $messages = $sink->get_messages();
        $sink->close();

        // At least one message landed in the requester's inbox from our component.
        $found = false;
        foreach ($messages as $m) {
            if ($m->component === 'local_airpay_manager'
                && $m->eventtype === 'request_decided'
                && (int) $m->useridto === (int) $u->id) {
                $found = true;
                $this->assertStringContainsString('approved', $m->subject);
                break;
            }
        }
        $this->assertTrue($found,
            'decide_request must dispatch a request_decided message to the requester');
    }

    public function test_decide_request_rejected_notifies_with_negative_subject(): void {
        $this->resetAfterTest();
        $this->setAdminUser();
        $this->preventResetByRollback();
        $sink = $this->redirectMessages();

        $u = $this->getDataGenerator()->create_user();
        $mgr = $this->getDataGenerator()->create_user();
        $course = $this->seed_course();
        $reqid = approval_manager::create_request((int) $u->id, (int) $course->id,
            (int) $mgr->id);

        approval_manager::decide_request($reqid,
            approval_manager::STATUS_REJECTED, 'Already covered elsewhere', (int) $mgr->id);

        $messages = $sink->get_messages();
        $sink->close();

        $found = false;
        foreach ($messages as $m) {
            if ($m->component === 'local_airpay_manager'
                && $m->eventtype === 'request_decided'
                && (int) $m->useridto === (int) $u->id) {
                $found = true;
                $this->assertStringContainsString('not approved', $m->subject);
                $this->assertStringContainsString('Already covered elsewhere',
                    $m->fullmessage);
                break;
            }
        }
        $this->assertTrue($found, 'rejected decision must notify the requester');
    }

    public function test_create_allocation_sends_notification_to_assignee(): void {
        $this->resetAfterTest();
        $this->setAdminUser();
        $this->preventResetByRollback();
        $sink = $this->redirectMessages();

        $u = $this->getDataGenerator()->create_user();
        $mgr = $this->getDataGenerator()->create_user();
        $course = $this->seed_course();

        approval_manager::create_allocation((int) $mgr->id, (int) $u->id,
            (int) $course->id, time() + 86400 * 14, 'Q3 priority');

        $messages = $sink->get_messages();
        $sink->close();

        $found = false;
        foreach ($messages as $m) {
            if ($m->component === 'local_airpay_manager'
                && $m->eventtype === 'allocation_assigned'
                && (int) $m->useridto === (int) $u->id) {
                $found = true;
                $this->assertStringContainsString('Q3 priority', $m->fullmessage);
                $this->assertStringContainsString('Due:', $m->fullmessage);
                break;
            }
        }
        $this->assertTrue($found,
            'create_allocation must dispatch allocation_assigned message to the user');
    }

    // ─── Bulk allocation + CSV export (v1.2.0) ──────────────────────

    public function test_bulk_allocate_succeeds_skipped_failed_buckets(): void {
        global $DB;
        $this->resetAfterTest();
        $this->setAdminUser();

        $u1 = $this->getDataGenerator()->create_user();
        $u2 = $this->getDataGenerator()->create_user();
        $u3 = $this->getDataGenerator()->create_user();
        $mgr = $this->getDataGenerator()->create_user();
        $course = $this->seed_course();

        // u3 already has an allocation — should be skipped.
        approval_manager::create_allocation((int) $mgr->id, (int) $u3->id,
            (int) $course->id);

        $result = approval_manager::bulk_allocate((int) $mgr->id,
            [(int) $u1->id, (int) $u2->id, (int) $u3->id, 0, -5],
            (int) $course->id, null, 'Bulk Q3 push');

        $this->assertCount(2, $result['succeeded'],
            'u1 + u2 should succeed; u3 already-allocated should skip');
        $this->assertGreaterThanOrEqual(1, count($result['skipped']));
        $this->assertCount(0, $result['failed']);

        $this->assertTrue($DB->record_exists('local_airpay_mgr_allocations',
            ['userid' => $u1->id, 'courseid' => $course->id]));
        $this->assertTrue($DB->record_exists('local_airpay_mgr_allocations',
            ['userid' => $u2->id, 'courseid' => $course->id]));
    }

    public function test_bulk_allocate_dedupes_userid_array(): void {
        $this->resetAfterTest();
        $this->setAdminUser();
        $u = $this->getDataGenerator()->create_user();
        $mgr = $this->getDataGenerator()->create_user();
        $course = $this->seed_course();

        $result = approval_manager::bulk_allocate((int) $mgr->id,
            [(int) $u->id, (int) $u->id, (int) $u->id], (int) $course->id);

        $this->assertSame(1, count($result['succeeded']),
            'duplicates in userids array must be pruned by array_unique');
        $this->assertCount(0, $result['skipped']);
    }

    public function test_csv_iterator_decisions_yields_header_then_rows(): void {
        $this->resetAfterTest();
        $this->setAdminUser();

        $u  = $this->getDataGenerator()->create_user();
        $u2 = $this->getDataGenerator()->create_user();
        $mgr = $this->getDataGenerator()->create_user();
        $course = $this->seed_course();

        $reqid = approval_manager::create_request((int) $u->id, (int) $course->id,
            (int) $mgr->id);
        approval_manager::decide_request($reqid,
            approval_manager::STATUS_APPROVED, 'OK', (int) $mgr->id);
        approval_manager::create_allocation((int) $mgr->id, (int) $u2->id,
            (int) $course->id, null, 'manual push');

        $rows = [];
        foreach (approval_manager::csv_iterator_decisions((int) $mgr->id) as $row) {
            $rows[] = $row;
        }

        $this->assertGreaterThanOrEqual(3, count($rows),
            'header + at least one request + at least one allocation');
        $this->assertSame('Type', $rows[0][0]);
        $types = array_column(array_slice($rows, 1), 0);
        $this->assertContains('request', $types);
        $this->assertContains('allocation', $types);
    }

    public function test_csv_iterator_scopes_by_managerid(): void {
        $this->resetAfterTest();
        $this->setAdminUser();

        $u = $this->getDataGenerator()->create_user();
        $mgr1 = $this->getDataGenerator()->create_user();
        $mgr2 = $this->getDataGenerator()->create_user();
        $course = $this->seed_course();

        approval_manager::create_request((int) $u->id, (int) $course->id, (int) $mgr1->id);

        $for_mgr2 = [];
        foreach (approval_manager::csv_iterator_decisions((int) $mgr2->id) as $row) {
            $for_mgr2[] = $row;
        }
        $this->assertCount(1, $for_mgr2,
            'mgr2 must see only the header row — none of mgr1\'s decisions');
    }
}
