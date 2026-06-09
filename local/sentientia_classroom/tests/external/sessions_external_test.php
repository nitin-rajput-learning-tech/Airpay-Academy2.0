<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

namespace local_sentientia_classroom\external;

defined('MOODLE_INTERNAL') || die();

use local_sentientia_classroom\session_manager;

/**
 * G-02 — Capability + happy-path tests for the 7 new WS endpoints.
 *
 * Endpoints covered:
 * - list_classroom_sessions (read, :view)
 * - delete_session          (write, :update)
 * - list_classroom_users    (read, :view)
 * - unenrol_classroom_user  (write, :update)
 * - list_session_attendance (read, :view)
 * - mark_session_attendance (write, :attendance)
 * - bulk_mark_attendance    (write, :attendance — bound check)
 *
 * @package    local_sentientia_classroom
 * @category   test
 */
final class sessions_external_test extends \advanced_testcase {

    private function seed_classroom_and_session(): array {
        global $DB;
        if (!$DB->get_manager()->table_exists('local_sentientia_classroom')) {
            $this->markTestSkipped('local_sentientia_classroom not present.');
        }
        $now = time();
        $cid = (int) $DB->insert_record('local_sentientia_classroom', (object) [
            'name'         => 'Cap Test',
            'description'  => '',
            'costcenterid' => 0,
            'open_path'    => '/1',
            'location'     => 'Lab',
            'capacity'     => 30,
            'status'       => 1,
            'visible'      => 1,
            'timecreated'  => $now,
            'timemodified' => $now,
        ]);
        $sid = session_manager::create_session($cid, (object) [
            'starttime' => $now,
            'endtime'   => $now + 3600,
        ]);
        return ['classroomid' => $cid, 'sessionid' => $sid];
    }

    // ─── list_classroom_sessions ──────────────────────────────────────────

    public function test_list_sessions_requires_view(): void {
        $this->resetAfterTest();
        $ids = $this->seed_classroom_and_session();
        $this->setAdminUser();
        \local_sentientia_classroom\session_manager::create_session($ids['classroomid'], (object) [
            'starttime' => strtotime('2026-06-01 10:00'),
            'endtime'   => strtotime('2026-06-01 14:00'),
        ]);

        $u = $this->getDataGenerator()->create_user();
        $this->setUser($u);

        $this->expectException(\required_capability_exception::class);
        list_classroom_sessions::execute($ids['classroomid']);
    }

    public function test_list_sessions_admin_returns_rows(): void {
        $this->resetAfterTest();
        $this->setAdminUser();
        $ids = $this->seed_classroom_and_session();

        $resp = list_classroom_sessions::execute($ids['classroomid']);
        $this->assertGreaterThanOrEqual(1, $resp['total']);
        $this->assertNotEmpty($resp['rows']);
    }

    // ─── delete_session ────────────────────────────────────────────────────

    public function test_delete_session_requires_update(): void {
        $this->resetAfterTest();
        $ids = $this->seed_classroom_and_session();

        $u = $this->getDataGenerator()->create_user();
        $this->setUser($u);

        $this->expectException(\required_capability_exception::class);
        delete_session::execute($ids['sessionid']);
    }

    public function test_delete_session_admin_succeeds(): void {
        $this->resetAfterTest();
        $this->setAdminUser();
        $ids = $this->seed_classroom_and_session();

        $resp = delete_session::execute($ids['sessionid']);
        $this->assertSame($ids['sessionid'], $resp['sessionid']);
    }

    // ─── list_classroom_users ──────────────────────────────────────────────

    public function test_list_users_requires_view(): void {
        $this->resetAfterTest();
        $ids = $this->seed_classroom_and_session();

        $u = $this->getDataGenerator()->create_user();
        $this->setUser($u);

        $this->expectException(\required_capability_exception::class);
        list_classroom_users::execute($ids['classroomid']);
    }

    public function test_list_users_admin_returns_total(): void {
        $this->resetAfterTest();
        $this->setAdminUser();
        $ids = $this->seed_classroom_and_session();

        $u = $this->getDataGenerator()->create_user();
        session_manager::enrol_users($ids['classroomid'], [(int) $u->id]);

        $resp = list_classroom_users::execute($ids['classroomid']);
        $this->assertSame(1, $resp['total']);
    }

    // ─── unenrol_classroom_user ────────────────────────────────────────────

    public function test_unenrol_user_requires_update(): void {
        $this->resetAfterTest();
        $ids = $this->seed_classroom_and_session();

        $u = $this->getDataGenerator()->create_user();
        $this->setUser($u);

        $this->expectException(\required_capability_exception::class);
        unenrol_classroom_user::execute($ids['classroomid'], (int) $u->id);
    }

    // ─── list_session_attendance ───────────────────────────────────────────

    public function test_list_attendance_requires_view(): void {
        $this->resetAfterTest();
        $ids = $this->seed_classroom_and_session();

        $u = $this->getDataGenerator()->create_user();
        $this->setUser($u);

        $this->expectException(\required_capability_exception::class);
        list_session_attendance::execute($ids['sessionid']);
    }

    public function test_list_attendance_admin_returns_roster(): void {
        $this->resetAfterTest();
        $this->setAdminUser();
        $ids = $this->seed_classroom_and_session();

        $u = $this->getDataGenerator()->create_user();
        session_manager::enrol_users($ids['classroomid'], [(int) $u->id]);

        $resp = list_session_attendance::execute($ids['sessionid']);
        $this->assertSame(1, $resp['total']);
        $this->assertCount(1, $resp['rows']);
        $this->assertSame((int) $u->id, $resp['rows'][0]['userid']);
        $this->assertSame(0, $resp['rows'][0]['status']);   // default Absent
    }

    // ─── mark_session_attendance ───────────────────────────────────────────

    public function test_mark_attendance_requires_attendance_capability(): void {
        $this->resetAfterTest();
        $ids = $this->seed_classroom_and_session();

        $u = $this->getDataGenerator()->create_user();
        $target = $this->getDataGenerator()->create_user();
        $this->setUser($u);

        $this->expectException(\required_capability_exception::class);
        mark_session_attendance::execute($ids['sessionid'], (int) $target->id,
            session_manager::ATT_PRESENT, '');
    }

    public function test_mark_attendance_admin_persists(): void {
        $this->resetAfterTest();
        $this->setAdminUser();
        $ids = $this->seed_classroom_and_session();
        $u = $this->getDataGenerator()->create_user();

        $resp = mark_session_attendance::execute($ids['sessionid'], (int) $u->id,
            session_manager::ATT_PRESENT, '');
        $this->assertSame(session_manager::ATT_PRESENT, $resp['status']);
    }

    // ─── bulk_mark_attendance ──────────────────────────────────────────────

    public function test_bulk_mark_attendance_requires_attendance_capability(): void {
        $this->resetAfterTest();
        $ids = $this->seed_classroom_and_session();
        $target = $this->getDataGenerator()->create_user();

        $u = $this->getDataGenerator()->create_user();
        $this->setUser($u);

        $this->expectException(\required_capability_exception::class);
        bulk_mark_attendance::execute($ids['sessionid'], [
            ['userid' => (int) $target->id, 'status' => 1, 'notes' => ''],
        ]);
    }

    public function test_bulk_mark_attendance_rejects_too_many(): void {
        $this->resetAfterTest();
        $this->setAdminUser();
        $ids = $this->seed_classroom_and_session();

        $marks = [];
        for ($i = 0; $i < 1001; $i++) {
            $marks[] = ['userid' => $i + 100, 'status' => 1, 'notes' => ''];
        }

        try {
            bulk_mark_attendance::execute($ids['sessionid'], $marks);
            $this->fail('Expected toomanymarks');
        } catch (\moodle_exception $e) {
            $this->assertSame('toomanymarks', $e->errorcode);
        }
    }

    public function test_bulk_mark_attendance_admin_persists(): void {
        $this->resetAfterTest();
        $this->setAdminUser();
        $ids = $this->seed_classroom_and_session();
        $a = $this->getDataGenerator()->create_user();
        $b = $this->getDataGenerator()->create_user();
        session_manager::enrol_users($ids['classroomid'], [(int) $a->id, (int) $b->id]);

        $resp = bulk_mark_attendance::execute($ids['sessionid'], [
            ['userid' => (int) $a->id, 'status' => 1, 'notes' => ''],
            ['userid' => (int) $b->id, 'status' => 2, 'notes' => 'Late'],
        ]);
        $this->assertSame(2, $resp['marked']);
    }
}
