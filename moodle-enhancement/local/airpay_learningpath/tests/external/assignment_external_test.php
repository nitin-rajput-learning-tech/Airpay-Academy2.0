<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

namespace local_airpay_learningpath\external;

defined('MOODLE_INTERNAL') || die();

/**
 * G-04 — capability + bound tests for the assignment / enrolment WS endpoints.
 *
 * Locks in:
 *   - assign_courses requires :update
 *   - unassign_course requires :update
 *   - reorder_courses requires :update + bounds (max 200)
 *   - enrol_users requires :enrol + bounds (max 500)
 *   - unenrol_user requires :enrol
 *   - list_path_courses requires :view + filterstoolong bound
 *   - list_path_users requires :view + filterstoolong bound + search escape
 *
 * @package    local_airpay_learningpath
 * @category   test
 */
final class assignment_external_test extends \advanced_testcase {

    private function seed_path(string $name = 'Test Path'): int {
        global $DB;
        $now = time();
        return (int) $DB->insert_record('local_airpay_learningpath', (object) [
            'name'         => $name,
            'description'  => '',
            'costcenterid' => 0,
            'open_path'    => '/1',
            'status'       => 1,
            'visible'      => 1,
            'timecreated'  => $now,
            'timemodified' => $now,
        ]);
    }

    // ── Capability gates ──────────────────────────────────────────────

    public function test_assign_courses_capability_required(): void {
        $this->resetAfterTest();
        $pid = $this->seed_path();
        $c = $this->getDataGenerator()->create_course();
        $u = $this->getDataGenerator()->create_user();   // no caps
        $this->setUser($u);

        $this->expectException(\required_capability_exception::class);
        assign_courses::execute($pid, [(int) $c->id]);
    }

    public function test_unassign_course_capability_required(): void {
        $this->resetAfterTest();
        $pid = $this->seed_path();
        $c = $this->getDataGenerator()->create_course();
        $u = $this->getDataGenerator()->create_user();
        $this->setUser($u);

        $this->expectException(\required_capability_exception::class);
        unassign_course::execute($pid, (int) $c->id);
    }

    public function test_reorder_courses_capability_required(): void {
        $this->resetAfterTest();
        $pid = $this->seed_path();
        $u = $this->getDataGenerator()->create_user();
        $this->setUser($u);

        $this->expectException(\required_capability_exception::class);
        reorder_courses::execute($pid, [1, 2, 3]);
    }

    public function test_enrol_users_capability_required(): void {
        $this->resetAfterTest();
        $pid = $this->seed_path();
        $target = $this->getDataGenerator()->create_user();
        $caller = $this->getDataGenerator()->create_user();
        $this->setUser($caller);

        $this->expectException(\required_capability_exception::class);
        enrol_users::execute($pid, [(int) $target->id]);
    }

    public function test_unenrol_user_capability_required(): void {
        $this->resetAfterTest();
        $pid = $this->seed_path();
        $target = $this->getDataGenerator()->create_user();
        $caller = $this->getDataGenerator()->create_user();
        $this->setUser($caller);

        $this->expectException(\required_capability_exception::class);
        unenrol_user::execute($pid, (int) $target->id);
    }

    public function test_list_path_courses_capability_required(): void {
        $this->resetAfterTest();
        $pid = $this->seed_path();
        $u = $this->getDataGenerator()->create_user();
        $this->setUser($u);

        $this->expectException(\required_capability_exception::class);
        list_path_courses::execute($pid);
    }

    public function test_list_path_users_capability_required(): void {
        $this->resetAfterTest();
        $pid = $this->seed_path();
        $u = $this->getDataGenerator()->create_user();
        $this->setUser($u);

        $this->expectException(\required_capability_exception::class);
        list_path_users::execute($pid);
    }

    // ── Bound checks ──────────────────────────────────────────────────

    public function test_assign_courses_rejects_oversize_payload(): void {
        $this->resetAfterTest();
        $this->setAdminUser();
        $pid = $this->seed_path();

        $bigarr = range(2, 102);   // 101 items > limit 100
        try {
            assign_courses::execute($pid, $bigarr);
            $this->fail('Expected toomanycourses');
        } catch (\moodle_exception $e) {
            $this->assertSame('toomanycourses', $e->errorcode);
        }
    }

    public function test_reorder_courses_rejects_oversize_payload(): void {
        $this->resetAfterTest();
        $this->setAdminUser();
        $pid = $this->seed_path();

        $bigarr = range(2, 202);   // 201 items > limit 200
        try {
            reorder_courses::execute($pid, $bigarr);
            $this->fail('Expected toomanycourses');
        } catch (\moodle_exception $e) {
            $this->assertSame('toomanycourses', $e->errorcode);
        }
    }

    public function test_enrol_users_rejects_oversize_payload(): void {
        $this->resetAfterTest();
        $this->setAdminUser();
        $pid = $this->seed_path();

        $bigarr = range(3, 503);   // 501 items > limit 500
        try {
            enrol_users::execute($pid, $bigarr);
            $this->fail('Expected toomanyusers');
        } catch (\moodle_exception $e) {
            $this->assertSame('toomanyusers', $e->errorcode);
        }
    }

    // ── Filter bounds (list endpoints) ────────────────────────────────

    public function test_list_path_courses_rejects_oversize_filter(): void {
        $this->resetAfterTest();
        $this->setAdminUser();
        $pid = $this->seed_path();

        $bigjson = '{' . str_repeat('"k":"' . str_repeat('x', 100) . '",', 50) . '"end":1}';
        try {
            list_path_courses::execute($pid, '', 'sortorder', 'asc', 0, 25, $bigjson);
            $this->fail('Expected filterstoolong');
        } catch (\moodle_exception $e) {
            $this->assertSame('filterstoolong', $e->errorcode);
        }
    }

    public function test_list_path_users_rejects_oversize_filter(): void {
        $this->resetAfterTest();
        $this->setAdminUser();
        $pid = $this->seed_path();

        $bigjson = '{' . str_repeat('"k":"' . str_repeat('x', 100) . '",', 50) . '"end":1}';
        try {
            list_path_users::execute($pid, '', 'lastname', 'asc', 0, 25, $bigjson);
            $this->fail('Expected filterstoolong');
        } catch (\moodle_exception $e) {
            $this->assertSame('filterstoolong', $e->errorcode);
        }
    }

    // ── End-to-end happy paths (siteadmin) ────────────────────────────

    public function test_assign_courses_via_external_returns_inserted_count(): void {
        $this->resetAfterTest();
        $this->setAdminUser();

        $pid = $this->seed_path();
        $c1 = $this->getDataGenerator()->create_course();
        $c2 = $this->getDataGenerator()->create_course();

        $result = assign_courses::execute($pid, [(int) $c1->id, (int) $c2->id]);
        $this->assertSame(2, (int) $result['inserted']);
    }

    public function test_list_path_courses_returns_assigned_courses(): void {
        $this->resetAfterTest();
        $this->setAdminUser();

        $pid = $this->seed_path();
        $c1 = $this->getDataGenerator()->create_course(['fullname' => 'Course Alpha']);
        $c2 = $this->getDataGenerator()->create_course(['fullname' => 'Course Beta']);
        \local_airpay_learningpath\path_manager::assign_courses($pid, [(int) $c1->id, (int) $c2->id]);

        $result = list_path_courses::execute($pid);
        $this->assertSame(2, (int) $result['total']);
        $this->assertCount(2, $result['rows']);
    }

    public function test_enrol_users_via_external_returns_count(): void {
        $this->resetAfterTest();
        $this->setAdminUser();

        $pid = $this->seed_path();
        $u1 = $this->getDataGenerator()->create_user();
        $u2 = $this->getDataGenerator()->create_user();

        $result = enrol_users::execute($pid, [(int) $u1->id, (int) $u2->id]);
        $this->assertSame(2, (int) $result['enrolled']);
    }

    public function test_list_path_users_returns_enrolled_users(): void {
        $this->resetAfterTest();
        $this->setAdminUser();

        $pid = $this->seed_path();
        $u1 = $this->getDataGenerator()->create_user(['firstname' => 'Alice', 'lastname' => 'Anderson']);
        \local_airpay_learningpath\path_manager::enrol_users($pid, [(int) $u1->id]);

        $result = list_path_users::execute($pid);
        $this->assertSame(1, (int) $result['total']);
        $this->assertCount(1, $result['rows']);
    }
}
