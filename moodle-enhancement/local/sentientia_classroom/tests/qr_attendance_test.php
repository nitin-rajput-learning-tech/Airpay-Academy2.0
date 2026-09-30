<?php
// This file is part of Sentientia LMS.

/**
 * QR attendance scan (2026-09-30).
 *
 * local/sentientia_pages/qr_scan.php used to check and insert into
 * {local_classroom_attendance} and qr_attendance.php read
 * {local_classroom_sessions}: BizLMS tables a Sentientia install does not
 * have. The scan now goes through session_manager::record_qr_attendance(),
 * which writes the row session_manager::get_session_attendance() reads back
 * from {local_sentientia_classroom_attendance}.
 *
 * @package    local_sentientia_classroom
 * @category   test
 * @copyright  2026 Airpay Payment Services
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_sentientia_classroom;

defined('MOODLE_INTERNAL') || die();

/**
 * @covers \local_sentientia_classroom\session_manager::record_qr_attendance
 * @group tenant_isolation
 */
final class qr_attendance_test extends \advanced_testcase {

    use \local_sentientia_org\test\bizlms_fixture;

    protected function setUp(): void {
        parent::setUp();
        $this->resetAfterTest();
        $this->ensure_bizlms_schema();
    }

    private function user_at(?string $path): \stdClass {
        global $DB;
        $u = $this->getDataGenerator()->create_user();
        $DB->set_field('user', 'open_path', $path, ['id' => $u->id]);
        $DB->set_field('user', 'open_designation', 'qrdesig', ['id' => $u->id]);
        return $DB->get_record('user', ['id' => $u->id], '*', MUST_EXIST);
    }

    private function classroom(?string $path): int {
        global $DB;
        return (int) $DB->insert_record('local_sentientia_classroom', (object) [
            'name' => 'QR classroom', 'description' => '', 'costcenterid' => 0, 'open_path' => $path,
            'location' => 'Room', 'capacity' => 20, 'status' => session_manager::STATUS_ACTIVE,
            'visible' => 1, 'timecreated' => time(), 'timemodified' => time(),
        ]);
    }

    private function session(int $classroomid): int {
        $start = time();
        return session_manager::create_session($classroomid, (object) [
            'title' => 'Day 1', 'starttime' => $start, 'endtime' => $start + HOURSECS,
        ]);
    }

    /** @return \stdClass[] the attendance rows for a session, keyed by id */
    private function rows(int $sessionid): array {
        global $DB;
        return $DB->get_records('local_sentientia_classroom_attendance', ['sessionid' => $sessionid]);
    }

    public function test_a_scan_records_one_present_row_in_the_sentientia_table(): void {
        $classroomid = $this->classroom('/1');
        $sessionid = $this->session($classroomid);
        $learner = $this->user_at('/1/2');
        session_manager::enrol_users($classroomid, [(int) $learner->id]);
        $this->setUser($learner);

        $result = session_manager::record_qr_attendance($sessionid, (int) $learner->id);

        $this->assertSame(session_manager::SCAN_RECORDED, $result);
        $rows = $this->rows($sessionid);
        $this->assertCount(1, $rows);
        $row = reset($rows);
        $this->assertSame((int) $learner->id, (int) $row->userid);
        $this->assertSame(session_manager::ATT_PRESENT, (int) $row->status);
        $this->assertSame((int) $learner->id, (int) $row->markedby, 'A self-scan is marked by the learner.');
        $this->assertGreaterThan(0, (int) $row->timecreated);
        $this->assertGreaterThan(0, (int) $row->timemodified);

        // The attendance grid reads the same row back as Present.
        $grid = session_manager::get_session_attendance($sessionid);
        $this->assertCount(1, $grid);
        $mine = reset($grid);
        $this->assertSame((int) $learner->id, (int) $mine->userid);
        $this->assertSame(session_manager::ATT_PRESENT, (int) $mine->status);
        $this->assertSame('Present', $mine->status_label);
    }

    public function test_a_second_scan_does_not_duplicate_the_row(): void {
        $classroomid = $this->classroom('/1');
        $sessionid = $this->session($classroomid);
        $learner = $this->user_at('/1/2');
        session_manager::enrol_users($classroomid, [(int) $learner->id]);
        $this->setUser($learner);

        $this->assertSame(session_manager::SCAN_RECORDED,
            session_manager::record_qr_attendance($sessionid, (int) $learner->id));
        $before = $this->rows($sessionid);

        $this->assertSame(session_manager::SCAN_ALREADY,
            session_manager::record_qr_attendance($sessionid, (int) $learner->id));
        $this->assertSame(session_manager::SCAN_ALREADY,
            session_manager::record_qr_attendance($sessionid, (int) $learner->id));

        $after = $this->rows($sessionid);
        $this->assertCount(1, $after);
        $this->assertEquals($before, $after, 'A repeat scan must not touch the row.');
    }

    public function test_a_scan_leaves_a_late_or_excused_mark_alone(): void {
        $classroomid = $this->classroom('/1');
        $sessionid = $this->session($classroomid);
        $late = $this->user_at('/1/2');
        $excused = $this->user_at('/1/2');
        session_manager::enrol_users($classroomid, [(int) $late->id, (int) $excused->id]);
        $this->setUser($late);
        session_manager::mark_attendance($sessionid, (int) $late->id, session_manager::ATT_LATE);
        session_manager::mark_attendance($sessionid, (int) $excused->id, session_manager::ATT_EXCUSED, 'Leave');

        $this->assertSame(session_manager::SCAN_ALREADY,
            session_manager::record_qr_attendance($sessionid, (int) $late->id));
        $this->setUser($excused);
        $this->assertSame(session_manager::SCAN_ALREADY,
            session_manager::record_qr_attendance($sessionid, (int) $excused->id));

        $this->assertCount(2, $this->rows($sessionid));
        $grid = [];
        foreach (session_manager::get_session_attendance($sessionid) as $r) {
            $grid[(int) $r->userid] = (int) $r->status;
        }
        $this->assertSame(session_manager::ATT_LATE, $grid[(int) $late->id]);
        $this->assertSame(session_manager::ATT_EXCUSED, $grid[(int) $excused->id]);
    }

    public function test_a_scan_raises_an_absent_row_to_present_without_a_second_row(): void {
        $classroomid = $this->classroom('/1');
        $sessionid = $this->session($classroomid);
        $learner = $this->user_at('/1/2');
        session_manager::enrol_users($classroomid, [(int) $learner->id]);
        $this->setUser($learner);
        // What the attendance grid saves for a learner nobody ticked.
        session_manager::mark_attendance($sessionid, (int) $learner->id, session_manager::ATT_ABSENT);

        $this->assertSame(session_manager::SCAN_RECORDED,
            session_manager::record_qr_attendance($sessionid, (int) $learner->id));
        $rows = $this->rows($sessionid);
        $this->assertCount(1, $rows);
        $this->assertSame(session_manager::ATT_PRESENT, (int) reset($rows)->status);

        $this->assertSame(session_manager::SCAN_ALREADY,
            session_manager::record_qr_attendance($sessionid, (int) $learner->id));
        $this->assertCount(1, $this->rows($sessionid));
    }

    public function test_a_learner_who_is_not_on_the_roster_is_refused_and_nothing_is_written(): void {
        $classroomid = $this->classroom('/1');
        $sessionid = $this->session($classroomid);
        $stranger = $this->user_at('/1/2');
        $this->setUser($stranger);

        $this->assertSame(session_manager::SCAN_NOT_ENROLLED,
            session_manager::record_qr_attendance($sessionid, (int) $stranger->id));
        $this->assertCount(0, $this->rows($sessionid));
    }

    public function test_an_unknown_session_is_reported_and_nothing_is_written(): void {
        global $DB;
        $learner = $this->user_at('/1/2');
        $this->setUser($learner);

        $this->assertSame(session_manager::SCAN_NO_SESSION,
            session_manager::record_qr_attendance(987654, (int) $learner->id));
        $this->assertSame(0, $DB->count_records('local_sentientia_classroom_attendance'));
    }

    public function test_a_session_whose_classroom_is_gone_is_reported_as_missing(): void {
        global $DB;
        $classroomid = $this->classroom('/1');
        $sessionid = $this->session($classroomid);
        $learner = $this->user_at('/1/2');
        session_manager::enrol_users($classroomid, [(int) $learner->id]);
        $this->setUser($learner);
        $DB->delete_records('local_sentientia_classroom', ['id' => $classroomid]);

        $this->assertSame(session_manager::SCAN_NO_SESSION,
            session_manager::record_qr_attendance($sessionid, (int) $learner->id));
        $this->assertCount(0, $this->rows($sessionid));
    }

    public function test_a_learner_cannot_scan_into_another_tenants_classroom(): void {
        $theirs = $this->classroom('/177/178');
        $sessionid = $this->session($theirs);
        // On the roster (a site admin or an approval flow put them there), but in tenant /1.
        $learner = $this->user_at('/1/2');
        session_manager::enrol_users($theirs, [(int) $learner->id]);
        $this->setUser($learner);

        try {
            session_manager::record_qr_attendance($sessionid, (int) $learner->id);
            $this->fail("Another tenant's classroom was not refused.");
        } catch (\moodle_exception $e) {
            $this->assertSame('error_outoftenant', $e->errorcode);
        }
        $this->assertCount(0, $this->rows($sessionid));
    }

    public function test_a_classroom_with_no_path_is_refused_to_a_tenant_learner(): void {
        $classroomid = $this->classroom(null);
        $sessionid = $this->session($classroomid);
        $learner = $this->user_at('/1/2');
        session_manager::enrol_users($classroomid, [(int) $learner->id]);
        $this->setUser($learner);

        try {
            session_manager::record_qr_attendance($sessionid, (int) $learner->id);
            $this->fail('A pathless classroom opened to a tenant learner.');
        } catch (\moodle_exception $e) {
            $this->assertSame('error_outoftenant', $e->errorcode);
        }
        $this->assertCount(0, $this->rows($sessionid));
    }

    public function test_a_learner_with_no_tenant_is_refused(): void {
        $classroomid = $this->classroom('/1');
        $sessionid = $this->session($classroomid);
        $learner = $this->user_at(null);
        session_manager::enrol_users($classroomid, [(int) $learner->id]);
        $this->setUser($learner);

        try {
            session_manager::record_qr_attendance($sessionid, (int) $learner->id);
            $this->fail('A learner with no tenant was let in.');
        } catch (\moodle_exception $e) {
            $this->assertSame('error_outoftenant', $e->errorcode);
        }
        $this->assertCount(0, $this->rows($sessionid));
    }

    public function test_a_scan_never_touches_the_legacy_bizlms_attendance_table(): void {
        global $DB;
        $legacy = 'local_classroom_attendance';
        $present = $DB->get_manager()->table_exists($legacy);
        $before = $present ? $DB->count_records($legacy) : 0;

        $classroomid = $this->classroom('/1');
        $sessionid = $this->session($classroomid);
        $learner = $this->user_at('/1/2');
        session_manager::enrol_users($classroomid, [(int) $learner->id]);
        $this->setUser($learner);
        session_manager::record_qr_attendance($sessionid, (int) $learner->id);

        if ($present) {
            $this->assertSame($before, $DB->count_records($legacy));
        }
        $this->assertCount(1, $this->rows($sessionid));
    }
}
