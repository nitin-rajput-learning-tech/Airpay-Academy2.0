<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

namespace local_sentientia_classroom;

defined('MOODLE_INTERNAL') || die();

/**
 * G-02 — Session CRUD + roster + attendance tests.
 *
 * Locks in:
 * - session_manager::create_session validates start/end time
 * - session_manager::update_session validates time range
 * - session_manager::delete_session cascades to attendance
 * - session_manager::enrol_users is idempotent
 * - session_manager::enrol_users rejects system + deleted users
 * - session_manager::unenrol_user cascades attendance
 * - session_manager::mark_attendance upserts (no duplicates)
 * - session_manager::mark_attendance rejects invalid status
 * - session_manager::bulk_mark_attendance is atomic
 * - session_manager::get_session_attendance includes unmarked roster members (default Absent)
 * - delete classroom cascades to new roster table
 *
 * @package    local_sentientia_classroom
 * @category   test
 */
final class sessions_test extends \advanced_testcase {

    private function seed_classroom(string $name = 'Test Classroom'): int {
        global $DB;
        if (!$DB->get_manager()->table_exists('local_sentientia_classroom')) {
            $this->markTestSkipped('local_sentientia_classroom table not present.');
        }
        $now = time();
        return (int) $DB->insert_record('local_sentientia_classroom', (object) [
            'name'         => $name,
            'description'  => '',
            'costcenterid' => 0,
            'open_path'    => '/1',
            'location'     => 'Test Lab',
            'capacity'     => 30,
            'status'       => 1,
            'visible'      => 1,
            'timecreated'  => $now,
            'timemodified' => $now,
        ]);
    }

    // ─── Session CRUD ────────────────────────────────────────────────────

    public function test_count_sessions_initially_zero(): void {
        $this->resetAfterTest();
        $this->setAdminUser();
        $cid = $this->seed_classroom();
        $this->assertSame(0, session_manager::count_sessions($cid));
    }

    public function test_create_session_persists(): void {
        $this->resetAfterTest();
        $this->setAdminUser();

        $cid = $this->seed_classroom();
        $start = strtotime('2026-06-01 10:00');
        $end   = strtotime('2026-06-01 14:00');

        $sid = session_manager::create_session($cid, (object) [
            'title'     => 'Day 1 — Onboarding',
            'starttime' => $start,
            'endtime'   => $end,
            'location'  => 'Mumbai HQ',
        ]);

        $this->assertGreaterThan(0, $sid);
        $this->assertSame(1, session_manager::count_sessions($cid));

        global $DB;
        $row = $DB->get_record('local_sentientia_classroom_sessions', ['id' => $sid], '*', MUST_EXIST);
        $this->assertSame('Day 1 — Onboarding', $row->title);
        $this->assertEquals($start, $row->starttime);
        $this->assertEquals($end, $row->endtime);
    }

    public function test_create_session_rejects_zero_start_time(): void {
        $this->resetAfterTest();
        $this->setAdminUser();

        $cid = $this->seed_classroom();
        try {
            session_manager::create_session($cid, (object) [
                'starttime' => 0,
                'endtime'   => strtotime('2026-06-01 14:00'),
            ]);
            $this->fail('Expected invalidsessiontime');
        } catch (\moodle_exception $e) {
            $this->assertSame('invalidsessiontime', $e->errorcode);
        }
    }

    public function test_create_session_rejects_end_before_start(): void {
        $this->resetAfterTest();
        $this->setAdminUser();

        $cid = $this->seed_classroom();
        try {
            session_manager::create_session($cid, (object) [
                'starttime' => strtotime('2026-06-01 14:00'),
                'endtime'   => strtotime('2026-06-01 10:00'),
            ]);
            $this->fail('Expected endbeforestart');
        } catch (\moodle_exception $e) {
            $this->assertSame('endbeforestart', $e->errorcode);
        }
    }

    public function test_update_session_persists(): void {
        $this->resetAfterTest();
        $this->setAdminUser();

        global $DB;
        $cid = $this->seed_classroom();
        $sid = session_manager::create_session($cid, (object) [
            'starttime' => strtotime('2026-06-01 10:00'),
            'endtime'   => strtotime('2026-06-01 14:00'),
            'location'  => 'Old',
        ]);

        session_manager::update_session($sid, (object) [
            'title'    => 'Updated Title',
            'location' => 'New Location',
        ]);

        $row = $DB->get_record('local_sentientia_classroom_sessions', ['id' => $sid], '*', MUST_EXIST);
        $this->assertSame('Updated Title', $row->title);
        $this->assertSame('New Location', $row->location);
    }

    public function test_update_session_validates_time_range(): void {
        $this->resetAfterTest();
        $this->setAdminUser();

        $cid = $this->seed_classroom();
        $sid = session_manager::create_session($cid, (object) [
            'starttime' => strtotime('2026-06-01 10:00'),
            'endtime'   => strtotime('2026-06-01 14:00'),
        ]);

        try {
            session_manager::update_session($sid, (object) [
                // Move endtime to BEFORE existing starttime.
                'endtime' => strtotime('2026-06-01 09:00'),
            ]);
            $this->fail('Expected endbeforestart');
        } catch (\moodle_exception $e) {
            $this->assertSame('endbeforestart', $e->errorcode);
        }
    }

    public function test_delete_session_cascades_attendance(): void {
        $this->resetAfterTest();
        $this->setAdminUser();

        global $DB;
        $cid = $this->seed_classroom();
        $sid = session_manager::create_session($cid, (object) [
            'starttime' => strtotime('2026-06-01 10:00'),
            'endtime'   => strtotime('2026-06-01 14:00'),
        ]);
        $u = $this->getDataGenerator()->create_user();
        session_manager::enrol_users($cid, [(int) $u->id]);
        session_manager::mark_attendance($sid, (int) $u->id, session_manager::ATT_PRESENT);

        $this->assertTrue($DB->record_exists('local_sentientia_classroom_attendance',
            ['sessionid' => $sid, 'userid' => $u->id]));

        session_manager::delete_session($sid);

        $this->assertFalse($DB->record_exists('local_sentientia_classroom_sessions', ['id' => $sid]));
        $this->assertFalse($DB->record_exists('local_sentientia_classroom_attendance',
            ['sessionid' => $sid, 'userid' => $u->id]));
    }

    // ─── Classroom roster (enrolment) ────────────────────────────────────

    public function test_enrol_users_persists(): void {
        $this->resetAfterTest();
        $this->setAdminUser();

        $cid = $this->seed_classroom();
        $u1 = $this->getDataGenerator()->create_user();
        $u2 = $this->getDataGenerator()->create_user();

        $count = session_manager::enrol_users($cid, [(int) $u1->id, (int) $u2->id]);
        $this->assertSame(2, $count);
        $this->assertSame(2, session_manager::count_enrolled($cid));
    }

    public function test_enrol_users_idempotent(): void {
        $this->resetAfterTest();
        $this->setAdminUser();

        $cid = $this->seed_classroom();
        $u1 = $this->getDataGenerator()->create_user();

        $first = session_manager::enrol_users($cid, [(int) $u1->id]);
        $second = session_manager::enrol_users($cid, [(int) $u1->id]);

        $this->assertSame(1, $first);
        $this->assertSame(0, $second);   // already enrolled — skipped
        $this->assertSame(1, session_manager::count_enrolled($cid));
    }

    public function test_enrol_users_skips_system_and_deleted(): void {
        $this->resetAfterTest();
        $this->setAdminUser();

        global $DB;
        $cid = $this->seed_classroom();
        $u = $this->getDataGenerator()->create_user();

        // Mark u as deleted.
        $DB->set_field('user', 'deleted', 1, ['id' => $u->id]);

        $count = session_manager::enrol_users($cid, [
            1,            // guest
            2,            // admin (system user — id<=2 is rejected)
            (int) $u->id, // deleted
        ]);

        $this->assertSame(0, $count);
        $this->assertSame(0, session_manager::count_enrolled($cid));
    }

    public function test_unenrol_user_clears_attendance(): void {
        $this->resetAfterTest();
        $this->setAdminUser();

        global $DB;
        $cid = $this->seed_classroom();
        $sid = session_manager::create_session($cid, (object) [
            'starttime' => strtotime('2026-06-01 10:00'),
            'endtime'   => strtotime('2026-06-01 14:00'),
        ]);
        $u = $this->getDataGenerator()->create_user();
        session_manager::enrol_users($cid, [(int) $u->id]);
        session_manager::mark_attendance($sid, (int) $u->id, session_manager::ATT_PRESENT);

        // Unenrol — should remove both roster row and attendance.
        session_manager::unenrol_user($cid, (int) $u->id);

        $this->assertFalse($DB->record_exists('local_sentientia_classroom_users',
            ['classroomid' => $cid, 'userid' => $u->id]));
        $this->assertFalse($DB->record_exists('local_sentientia_classroom_attendance',
            ['sessionid' => $sid, 'userid' => $u->id]));
    }

    public function test_get_enrolled_users_search(): void {
        $this->resetAfterTest();
        $this->setAdminUser();

        $cid = $this->seed_classroom();
        $a = $this->getDataGenerator()->create_user(['firstname' => 'Alice', 'lastname' => 'Anderson']);
        $b = $this->getDataGenerator()->create_user(['firstname' => 'Bob',   'lastname' => 'Brown']);
        session_manager::enrol_users($cid, [(int) $a->id, (int) $b->id]);

        $rows = session_manager::get_enrolled_users($cid, 'alice');
        $this->assertCount(1, $rows);
        $first = reset($rows);
        $this->assertSame('Alice', $first->firstname);
    }

    public function test_count_enrolled_filtered(): void {
        $this->resetAfterTest();
        $this->setAdminUser();

        $cid = $this->seed_classroom();
        $a = $this->getDataGenerator()->create_user(['firstname' => 'Alice', 'lastname' => 'Anderson']);
        $b = $this->getDataGenerator()->create_user(['firstname' => 'Bob',   'lastname' => 'Brown']);
        session_manager::enrol_users($cid, [(int) $a->id, (int) $b->id]);

        $this->assertSame(2, session_manager::count_enrolled_filtered($cid));
        $this->assertSame(1, session_manager::count_enrolled_filtered($cid, 'alice'));
    }

    // ─── Attendance ──────────────────────────────────────────────────────

    public function test_mark_attendance_creates_then_updates(): void {
        $this->resetAfterTest();
        $this->setAdminUser();

        global $DB;
        $cid = $this->seed_classroom();
        $sid = session_manager::create_session($cid, (object) [
            'starttime' => strtotime('2026-06-01 10:00'),
            'endtime'   => strtotime('2026-06-01 14:00'),
        ]);
        $u = $this->getDataGenerator()->create_user();
        session_manager::enrol_users($cid, [(int) $u->id]);

        // First mark — INSERT path.
        session_manager::mark_attendance($sid, (int) $u->id, session_manager::ATT_PRESENT);
        $this->assertSame(1, $DB->count_records('local_sentientia_classroom_attendance',
            ['sessionid' => $sid, 'userid' => $u->id]));
        $this->assertEquals(session_manager::ATT_PRESENT,
            (int) $DB->get_field('local_sentientia_classroom_attendance', 'status',
                ['sessionid' => $sid, 'userid' => $u->id]));

        // Second mark — UPDATE path (no duplicates).
        session_manager::mark_attendance($sid, (int) $u->id, session_manager::ATT_LATE);
        $this->assertSame(1, $DB->count_records('local_sentientia_classroom_attendance',
            ['sessionid' => $sid, 'userid' => $u->id]));
        $this->assertEquals(session_manager::ATT_LATE,
            (int) $DB->get_field('local_sentientia_classroom_attendance', 'status',
                ['sessionid' => $sid, 'userid' => $u->id]));
    }

    public function test_mark_attendance_rejects_invalid_status(): void {
        $this->resetAfterTest();
        $this->setAdminUser();

        $cid = $this->seed_classroom();
        $sid = session_manager::create_session($cid, (object) [
            'starttime' => strtotime('2026-06-01 10:00'),
            'endtime'   => strtotime('2026-06-01 14:00'),
        ]);
        $u = $this->getDataGenerator()->create_user();

        try {
            session_manager::mark_attendance($sid, (int) $u->id, 99);
            $this->fail('Expected invalidattendancestatus');
        } catch (\moodle_exception $e) {
            $this->assertSame('invalidattendancestatus', $e->errorcode);
        }
    }

    public function test_bulk_mark_attendance(): void {
        $this->resetAfterTest();
        $this->setAdminUser();

        $cid = $this->seed_classroom();
        $sid = session_manager::create_session($cid, (object) [
            'starttime' => strtotime('2026-06-01 10:00'),
            'endtime'   => strtotime('2026-06-01 14:00'),
        ]);
        $a = $this->getDataGenerator()->create_user();
        $b = $this->getDataGenerator()->create_user();
        session_manager::enrol_users($cid, [(int) $a->id, (int) $b->id]);

        $count = session_manager::bulk_mark_attendance($sid, [
            ['userid' => (int) $a->id, 'status' => session_manager::ATT_PRESENT, 'notes' => ''],
            ['userid' => (int) $b->id, 'status' => session_manager::ATT_LATE, 'notes' => 'Got stuck in traffic'],
        ]);

        $this->assertSame(2, $count);

        $rows = session_manager::get_session_attendance($sid);
        $by_user = [];
        foreach ($rows as $r) { $by_user[(int) $r->userid] = (int) $r->status; }
        $this->assertSame(session_manager::ATT_PRESENT, $by_user[(int) $a->id]);
        $this->assertSame(session_manager::ATT_LATE,    $by_user[(int) $b->id]);
    }

    public function test_get_session_attendance_includes_unmarked_as_absent(): void {
        $this->resetAfterTest();
        $this->setAdminUser();

        $cid = $this->seed_classroom();
        $sid = session_manager::create_session($cid, (object) [
            'starttime' => strtotime('2026-06-01 10:00'),
            'endtime'   => strtotime('2026-06-01 14:00'),
        ]);
        $a = $this->getDataGenerator()->create_user(['firstname' => 'Alice', 'lastname' => 'A']);
        $b = $this->getDataGenerator()->create_user(['firstname' => 'Bob',   'lastname' => 'B']);
        session_manager::enrol_users($cid, [(int) $a->id, (int) $b->id]);

        // Only mark Alice; Bob should still appear as Absent (default 0).
        session_manager::mark_attendance($sid, (int) $a->id, session_manager::ATT_PRESENT);

        $rows = session_manager::get_session_attendance($sid);
        $this->assertCount(2, $rows);

        $by_user = [];
        foreach ($rows as $r) { $by_user[(int) $r->userid] = (int) $r->status; }
        $this->assertSame(session_manager::ATT_PRESENT, $by_user[(int) $a->id]);
        $this->assertSame(session_manager::ATT_ABSENT,  $by_user[(int) $b->id]);
    }

    // ─── Cascade-delete classroom ────────────────────────────────────────

    public function test_delete_classroom_cascades_to_roster(): void {
        $this->resetAfterTest();
        $this->setAdminUser();

        global $DB;
        $cid = $this->seed_classroom();
        $u = $this->getDataGenerator()->create_user();
        session_manager::enrol_users($cid, [(int) $u->id]);

        $this->assertTrue($DB->record_exists('local_sentientia_classroom_users',
            ['classroomid' => $cid, 'userid' => $u->id]));

        session_manager::delete($cid);

        $this->assertFalse($DB->record_exists('local_sentientia_classroom_users',
            ['classroomid' => $cid, 'userid' => $u->id]));
    }
}
