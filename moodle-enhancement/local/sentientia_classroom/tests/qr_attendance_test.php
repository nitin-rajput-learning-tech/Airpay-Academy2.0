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
 * Owner decisions of 2026-09-30, all covered here: the trainer's mark wins (a scan never
 * changes an existing row); a scan counts only from 30 minutes before the session starts to
 * 30 minutes after it ends; the trainer's grid Save does not overwrite a newer QR mark and
 * a QR insert that lands mid-save does not roll the save back; the QR token is an HMAC
 * signed with a per-site secret, not a hash of $CFG->passwordsaltmain.
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
 * @covers \local_sentientia_classroom\session_manager::scan_window_for
 * @covers \local_sentientia_classroom\session_manager::get_scan_window
 * @covers \local_sentientia_classroom\session_manager::qr_token
 * @covers \local_sentientia_classroom\session_manager::qr_token_is_valid
 * @covers \local_sentientia_classroom\session_manager::bulk_mark_attendance
 * @covers \local_sentientia_classroom\session_manager::mark_attendance
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

    public function test_the_trainers_mark_wins_a_scan_never_changes_an_absent_row(): void {
        $classroomid = $this->classroom('/1');
        $sessionid = $this->session($classroomid);
        $trainer = $this->user_at('/1/2');
        $learner = $this->user_at('/1/2');
        session_manager::enrol_users($classroomid, [(int) $learner->id]);
        // What the trainer, or the attendance grid for a learner nobody ticked, saves.
        $this->setUser($trainer);
        session_manager::mark_attendance($sessionid, (int) $learner->id, session_manager::ATT_ABSENT, 'No show');
        $before = $this->rows($sessionid);

        // Owner decision 2026-09-30: a scan never changes an existing row, Absent included.
        $this->setUser($learner);
        $this->assertSame(session_manager::SCAN_ALREADY,
            session_manager::record_qr_attendance($sessionid, (int) $learner->id));
        $this->assertSame(session_manager::SCAN_ALREADY,
            session_manager::record_qr_attendance($sessionid, (int) $learner->id));

        $rows = $this->rows($sessionid);
        $this->assertCount(1, $rows);
        $this->assertEquals($before, $rows, 'The Absent row must be left exactly as the trainer wrote it.');
        $row = reset($rows);
        $this->assertSame(session_manager::ATT_ABSENT, (int) $row->status);
        $this->assertSame((int) $trainer->id, (int) $row->markedby);
        $this->assertSame('No show', $row->notes);
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

    public function test_a_scan_into_a_cancelled_classroom_is_refused_and_nothing_is_written(): void {
        global $DB;
        $classroomid = $this->classroom('/1');
        $sessionid = $this->session($classroomid);
        $learner = $this->user_at('/1/2');
        session_manager::enrol_users($classroomid, [(int) $learner->id]);
        $this->setUser($learner);
        $DB->set_field('local_sentientia_classroom', 'status', session_manager::STATUS_CANCELLED,
            ['id' => $classroomid]);

        $this->assertSame(session_manager::SCAN_CANCELLED,
            session_manager::record_qr_attendance($sessionid, (int) $learner->id));
        $this->assertCount(0, $this->rows($sessionid));
    }

    public function test_a_cancelled_classroom_does_not_overturn_an_existing_mark(): void {
        global $DB;
        $classroomid = $this->classroom('/1');
        $sessionid = $this->session($classroomid);
        $learner = $this->user_at('/1/2');
        session_manager::enrol_users($classroomid, [(int) $learner->id]);
        $this->setUser($learner);
        session_manager::mark_attendance($sessionid, (int) $learner->id, session_manager::ATT_ABSENT);
        $DB->set_field('local_sentientia_classroom', 'status', session_manager::STATUS_CANCELLED,
            ['id' => $classroomid]);

        $this->assertSame(session_manager::SCAN_CANCELLED,
            session_manager::record_qr_attendance($sessionid, (int) $learner->id));
        $rows = $this->rows($sessionid);
        $this->assertCount(1, $rows);
        $this->assertSame(session_manager::ATT_ABSENT, (int) reset($rows)->status,
            'A cancelled classroom takes no attendance, so the Absent row must stay Absent.');
    }

    public function test_a_learner_not_on_the_roster_is_not_told_the_classroom_is_cancelled(): void {
        global $DB;
        $classroomid = $this->classroom('/1');
        $sessionid = $this->session($classroomid);
        $stranger = $this->user_at('/1/2');
        $this->setUser($stranger);
        $DB->set_field('local_sentientia_classroom', 'status', session_manager::STATUS_CANCELLED,
            ['id' => $classroomid]);

        $this->assertSame(session_manager::SCAN_NOT_ENROLLED,
            session_manager::record_qr_attendance($sessionid, (int) $stranger->id));
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

    // ═══ Session window: 30 minutes before the start to 30 minutes after the end ═══

    /** A session with explicit start and end times. */
    private function session_at(int $classroomid, int $start, int $end): int {
        return session_manager::create_session($classroomid, (object) [
            'title' => 'Timed', 'starttime' => $start, 'endtime' => $end,
        ]);
    }

    /** A learner in tenant /1 on the roster of the classroom. */
    private function enrolled_learner(int $classroomid): \stdClass {
        $learner = $this->user_at('/1/2');
        session_manager::enrol_users($classroomid, [(int) $learner->id]);
        return $learner;
    }

    public function test_a_scan_before_the_window_opens_is_refused_and_nothing_is_written(): void {
        $classroomid = $this->classroom('/1');
        $start = strtotime('2026-06-15 10:00:00');
        $sessionid = $this->session_at($classroomid, $start, $start + 2 * HOURSECS);
        $learner = $this->enrolled_learner($classroomid);
        $this->setUser($learner);

        $this->assertSame(session_manager::SCAN_TOO_EARLY,
            session_manager::record_qr_attendance($sessionid, (int) $learner->id, $start - 31 * MINSECS));
        $this->assertSame(session_manager::SCAN_TOO_EARLY,
            session_manager::record_qr_attendance($sessionid, (int) $learner->id, $start - DAYSECS),
            'A QR code shown for tomorrow must not record attendance today.');
        $this->assertCount(0, $this->rows($sessionid));
    }

    public function test_a_scan_inside_the_window_is_recorded_including_both_edges(): void {
        $classroomid = $this->classroom('/1');
        $start = strtotime('2026-06-15 10:00:00');
        $end = $start + 2 * HOURSECS;
        $sessionid = $this->session_at($classroomid, $start, $end);

        $times = [
            'exactly 30 minutes before the start' => $start - 30 * MINSECS,
            'at the start' => $start,
            'in the middle' => $start + HOURSECS,
            'at the end' => $end,
            'exactly 30 minutes after the end' => $end + 30 * MINSECS,
        ];
        foreach ($times as $label => $when) {
            $learner = $this->enrolled_learner($classroomid);
            $this->assertSame(session_manager::SCAN_RECORDED,
                session_manager::record_qr_attendance($sessionid, (int) $learner->id, $when), $label);
        }
        $this->assertCount(count($times), $this->rows($sessionid));
    }

    public function test_a_scan_after_the_window_closes_is_refused_and_nothing_is_written(): void {
        $classroomid = $this->classroom('/1');
        $start = strtotime('2026-06-15 10:00:00');
        $end = $start + 2 * HOURSECS;
        $sessionid = $this->session_at($classroomid, $start, $end);
        $learner = $this->enrolled_learner($classroomid);
        $this->setUser($learner);

        $this->assertSame(session_manager::SCAN_TOO_LATE,
            session_manager::record_qr_attendance($sessionid, (int) $learner->id, $end + 31 * MINSECS));
        $this->assertSame(session_manager::SCAN_TOO_LATE,
            session_manager::record_qr_attendance($sessionid, (int) $learner->id, $end + DAYSECS));
        $this->assertCount(0, $this->rows($sessionid));
    }

    public function test_the_default_clock_is_used_when_no_time_is_passed(): void {
        $classroomid = $this->classroom('/1');
        $past = $this->session_at($classroomid, time() - 5 * HOURSECS, time() - 4 * HOURSECS);
        $future = $this->session_at($classroomid, time() + 5 * HOURSECS, time() + 6 * HOURSECS);
        $learner = $this->enrolled_learner($classroomid);
        $this->setUser($learner);

        $this->assertSame(session_manager::SCAN_TOO_LATE,
            session_manager::record_qr_attendance($past, (int) $learner->id));
        $this->assertSame(session_manager::SCAN_TOO_EARLY,
            session_manager::record_qr_attendance($future, (int) $learner->id));
    }

    public function test_a_cancelled_classroom_is_refused_even_inside_the_window_and_outside_it(): void {
        global $DB;
        $classroomid = $this->classroom('/1');
        $learner = $this->enrolled_learner($classroomid);
        $this->setUser($learner);
        $start = strtotime('2026-06-15 10:00:00');
        $sessionid = $this->session_at($classroomid, $start, $start + HOURSECS);
        $DB->set_field('local_sentientia_classroom', 'status', session_manager::STATUS_CANCELLED,
            ['id' => $classroomid]);

        $this->assertSame(session_manager::SCAN_CANCELLED,
            session_manager::record_qr_attendance($sessionid, (int) $learner->id, $start + 10 * MINSECS));
        $this->assertSame(session_manager::SCAN_CANCELLED,
            session_manager::record_qr_attendance($sessionid, (int) $learner->id, $start + 5 * DAYSECS),
            'Cancelled is decided before the time window.');
        $this->assertCount(0, $this->rows($sessionid));
    }

    public function test_a_completed_classroom_still_takes_a_scan_inside_the_window(): void {
        global $DB;
        $classroomid = $this->classroom('/1');
        $learner = $this->enrolled_learner($classroomid);
        $this->setUser($learner);
        $sessionid = $this->session($classroomid);
        $DB->set_field('local_sentientia_classroom', 'status', session_manager::STATUS_COMPLETED,
            ['id' => $classroomid]);

        $this->assertSame(session_manager::SCAN_RECORDED,
            session_manager::record_qr_attendance($sessionid, (int) $learner->id));
    }

    public function test_an_existing_mark_is_reported_as_already_marked_whatever_the_time(): void {
        $classroomid = $this->classroom('/1');
        $start = strtotime('2026-06-15 10:00:00');
        $sessionid = $this->session_at($classroomid, $start, $start + HOURSECS);
        $trainer = $this->user_at('/1/2');
        $learner = $this->enrolled_learner($classroomid);
        $this->setUser($trainer);
        session_manager::mark_attendance($sessionid, (int) $learner->id, session_manager::ATT_EXCUSED, 'Leave');
        $before = $this->rows($sessionid);

        $this->setUser($learner);
        $this->assertSame(session_manager::SCAN_ALREADY,
            session_manager::record_qr_attendance($sessionid, (int) $learner->id, $start + 9 * DAYSECS));
        $this->assertEquals($before, $this->rows($sessionid));
    }

    public function test_a_learner_not_on_the_roster_is_told_so_not_that_the_window_is_closed(): void {
        $classroomid = $this->classroom('/1');
        $start = strtotime('2026-06-15 10:00:00');
        $sessionid = $this->session_at($classroomid, $start, $start + HOURSECS);
        $stranger = $this->user_at('/1/2');
        $this->setUser($stranger);

        $this->assertSame(session_manager::SCAN_NOT_ENROLLED,
            session_manager::record_qr_attendance($sessionid, (int) $stranger->id, $start + 5 * DAYSECS));
    }

    public function test_the_scan_window_is_30_minutes_either_side_of_the_session(): void {
        $start = strtotime('2026-06-15 10:00:00');
        $end = $start + 2 * HOURSECS;
        $window = session_manager::scan_window_for((object) [
            'starttime' => $start, 'endtime' => $end, 'sessiondate' => $start,
        ]);
        $this->assertSame([$start - 30 * MINSECS, $end + 30 * MINSECS], $window);
        $this->assertSame(30 * MINSECS, session_manager::SCAN_GRACE);
    }

    public function test_a_session_with_no_usable_end_runs_to_the_end_of_its_day(): void {
        $start = strtotime('2026-06-15 10:00:00');
        $endofday = usergetmidnight($start) + DAYSECS - 1;
        $expected = [$start - 30 * MINSECS, $endofday + 30 * MINSECS];

        $this->assertSame($expected, session_manager::scan_window_for((object) [
            'starttime' => $start, 'endtime' => 0, 'sessiondate' => $start]));
        $this->assertSame($expected, session_manager::scan_window_for((object) [
            'starttime' => $start, 'endtime' => $start - 60, 'sessiondate' => $start]),
            'An end that is not after the start is no end.');
    }

    public function test_a_session_with_only_a_date_counts_the_whole_day(): void {
        $date = strtotime('2026-06-15 15:00:00');
        $midnight = usergetmidnight($date);

        $this->assertSame([$midnight - 30 * MINSECS, $midnight + DAYSECS - 1 + 30 * MINSECS],
            session_manager::scan_window_for((object) [
                'starttime' => 0, 'endtime' => 0, 'sessiondate' => $date]));
    }

    public function test_a_session_with_no_time_at_all_cannot_be_scanned_and_nothing_is_written(): void {
        global $DB;
        $this->assertNull(session_manager::scan_window_for((object) [
            'starttime' => 0, 'endtime' => 0, 'sessiondate' => 0]));

        $classroomid = $this->classroom('/1');
        $learner = $this->enrolled_learner($classroomid);
        $this->setUser($learner);
        $sessionid = (int) $DB->insert_record('local_sentientia_classroom_sessions', (object) [
            'classroomid' => $classroomid, 'title' => 'Imported', 'sessiondate' => 0,
            'starttime' => 0, 'endtime' => 0, 'location' => '', 'notes' => '',
            'timecreated' => time(), 'timemodified' => time(),
        ]);

        $this->assertNull(session_manager::get_scan_window($sessionid));
        $this->assertSame(session_manager::SCAN_TOO_LATE,
            session_manager::record_qr_attendance($sessionid, (int) $learner->id));
        $this->assertCount(0, $this->rows($sessionid));
        $this->assertNull(session_manager::get_scan_window(987654), 'No such session, no window.');
    }

    // ═══ The QR token: HMAC with a per-site secret ═══

    /** A fixed mid-year moment, so no daylight-saving change falls inside the hours compared. */
    private function noon(): int {
        return strtotime('2026-06-15 12:30:00');
    }

    public function test_tokens_for_the_current_and_the_previous_hour_are_accepted(): void {
        $now = $this->noon();
        $this->assertTrue(session_manager::qr_token_is_valid(42,
            session_manager::qr_token(42, $now), $now));
        $this->assertTrue(session_manager::qr_token_is_valid(42,
            session_manager::qr_token(42, $now - HOURSECS), $now),
            'The previous hour is the grace period.');
        // Anywhere inside the current hour gives the same token.
        $this->assertSame(session_manager::qr_token(42, $now), session_manager::qr_token(42, $now + 20 * MINSECS));
    }

    public function test_a_token_from_two_hours_ago_or_from_the_future_is_refused(): void {
        $now = $this->noon();
        $this->assertFalse(session_manager::qr_token_is_valid(42,
            session_manager::qr_token(42, $now - 2 * HOURSECS), $now));
        $this->assertFalse(session_manager::qr_token_is_valid(42,
            session_manager::qr_token(42, $now - DAYSECS), $now));
        $this->assertFalse(session_manager::qr_token_is_valid(42,
            session_manager::qr_token(42, $now + HOURSECS), $now),
            'A token for next hour is not valid yet.');
    }

    public function test_the_old_salt_free_sha256_token_is_refused(): void {
        global $CFG;
        $now = $this->noon();
        // What qr_scan.php accepted on an install with no $CFG->passwordsaltmain: anybody could compute it.
        foreach ([$now, $now - HOURSECS] as $t) {
            $saltfree = hash('sha256', 42 . '|' . date('Y-m-d-H', $t) . '|');
            $this->assertFalse(session_manager::qr_token_is_valid(42, $saltfree, $now));
        }
        // ...and the salted form, once the salt is known, is refused too.
        $CFG->passwordsaltmain = 'legacy-site-salt';
        foreach ([$now, $now - HOURSECS] as $t) {
            $salted = hash('sha256', 42 . '|' . date('Y-m-d-H', $t) . '|' . $CFG->passwordsaltmain);
            $this->assertFalse(session_manager::qr_token_is_valid(42, $salted, $now));
        }
    }

    public function test_a_token_for_one_session_is_refused_for_another(): void {
        $now = $this->noon();
        $tokena = session_manager::qr_token(42, $now);
        $tokenb = session_manager::qr_token(43, $now);
        $this->assertNotSame($tokena, $tokenb);
        $this->assertTrue(session_manager::qr_token_is_valid(42, $tokena, $now));
        $this->assertFalse(session_manager::qr_token_is_valid(43, $tokena, $now));
        $this->assertFalse(session_manager::qr_token_is_valid(42, $tokenb, $now));
    }

    public function test_empty_short_and_tampered_tokens_are_refused(): void {
        $now = $this->noon();
        $token = session_manager::qr_token(42, $now);
        $this->assertFalse(session_manager::qr_token_is_valid(42, '', $now));
        $this->assertFalse(session_manager::qr_token_is_valid(42, 'abc123', $now));
        $this->assertFalse(session_manager::qr_token_is_valid(42, substr($token, 0, -1), $now));
        $tampered = substr($token, 0, -1) . ($token[strlen($token) - 1] === '0' ? '1' : '0');
        $this->assertFalse(session_manager::qr_token_is_valid(42, $tampered, $now));
        $this->assertFalse(session_manager::qr_token_is_valid(42, strtoupper($token), $now),
            'Hex case is part of the token.');
    }

    public function test_the_secret_is_made_once_kept_and_never_taken_from_the_password_salt(): void {
        global $CFG;
        $now = $this->noon();
        $this->assertEmpty(get_config('local_sentientia_classroom', 'qrsecret'));

        $token = session_manager::qr_token(42, $now);
        $secret = get_config('local_sentientia_classroom', 'qrsecret');
        $this->assertSame(64, strlen($secret), 'A random 64 character secret is created on first use.');
        $this->assertSame($token, session_manager::qr_token(42, $now), 'Created once, then reused.');
        $this->assertSame($secret, get_config('local_sentientia_classroom', 'qrsecret'));

        // The token is an HMAC with that secret...
        $this->assertSame(hash_hmac('sha256', 42 . '|' . date('Y-m-d-H', $now), $secret), $token);
        // ...and the Moodle password salt has no part in it, set or not.
        unset($CFG->passwordsaltmain);
        $this->assertSame($token, session_manager::qr_token(42, $now));
        $CFG->passwordsaltmain = 'some-other-salt';
        $this->assertSame($token, session_manager::qr_token(42, $now));
    }

    public function test_deleting_the_secret_rotates_every_token(): void {
        $now = $this->noon();
        $old = session_manager::qr_token(42, $now);
        unset_config('qrsecret', 'local_sentientia_classroom');

        $this->assertFalse(session_manager::qr_token_is_valid(42, $old, $now),
            'A QR code made with the old secret stops working.');
        $new = session_manager::qr_token(42, $now);
        $this->assertNotSame($old, $new);
        $this->assertTrue(session_manager::qr_token_is_valid(42, $new, $now));
    }

    // ═══ The trainer's grid Save must not overwrite a newer QR mark ═══

    /** The rows of a session as [userid => status]. */
    private function statuses(int $sessionid): array {
        $out = [];
        foreach ($this->rows($sessionid) as $r) {
            $out[(int) $r->userid] = (int) $r->status;
        }
        return $out;
    }

    public function test_a_grid_save_does_not_turn_a_newer_qr_mark_back_to_absent(): void {
        $classroomid = $this->classroom('/1');
        $sessionid = $this->session($classroomid);
        $trainer = $this->user_at('/1/2');
        $scanner = $this->enrolled_learner($classroomid);
        $untouched = $this->enrolled_learner($classroomid);
        $ticked = $this->enrolled_learner($classroomid);

        // The trainer opened the grid; a moment later one learner scanned the QR code.
        $loadedat = time() - 100;
        $this->assertSame(session_manager::SCAN_RECORDED,
            session_manager::record_qr_attendance($sessionid, (int) $scanner->id));

        // The grid still shows everyone as Absent, and the trainer ticks one learner and saves.
        $this->setUser($trainer);
        $kept = null;
        $keptusers = null;
        $count = session_manager::bulk_mark_attendance($sessionid, [
            ['userid' => (int) $scanner->id, 'status' => session_manager::ATT_ABSENT],
            ['userid' => (int) $untouched->id, 'status' => session_manager::ATT_ABSENT],
            ['userid' => (int) $ticked->id, 'status' => session_manager::ATT_PRESENT],
        ], $loadedat, $kept, $keptusers);

        $this->assertSame(2, $count, 'Two marks were written; the scanner\'s was kept.');
        $this->assertSame(1, $kept);
        $this->assertSame([(int) $scanner->id => session_manager::ATT_PRESENT], $keptusers);
        $this->assertSame([
            (int) $scanner->id => session_manager::ATT_PRESENT,
            (int) $untouched->id => session_manager::ATT_ABSENT,
            (int) $ticked->id => session_manager::ATT_PRESENT,
        ], $this->statuses($sessionid));
        foreach ($this->rows($sessionid) as $row) {
            if ((int) $row->userid === (int) $scanner->id) {
                $this->assertSame((int) $scanner->id, (int) $row->markedby, 'The scan is still the learner\'s.');
                $this->assertSame('Marked by QR scan', $row->notes);
            }
        }
    }

    public function test_a_deliberate_change_to_a_newer_row_is_written(): void {
        $classroomid = $this->classroom('/1');
        $sessionid = $this->session($classroomid);
        $trainer = $this->user_at('/1/2');
        $scanner = $this->enrolled_learner($classroomid);
        $loadedat = time() - 100;
        session_manager::record_qr_attendance($sessionid, (int) $scanner->id);

        // The trainer moves the learner (shown as Absent on their grid) to Late: a real edit.
        $this->setUser($trainer);
        $kept = null;
        $count = session_manager::bulk_mark_attendance($sessionid, [
            ['userid' => (int) $scanner->id, 'status' => session_manager::ATT_LATE, 'notes' => 'Came in late'],
        ], $loadedat, $kept);

        $this->assertSame(1, $count);
        $this->assertSame(0, $kept);
        $this->assertSame([(int) $scanner->id => session_manager::ATT_LATE], $this->statuses($sessionid));
    }

    public function test_absent_over_a_mark_older_than_the_grid_load_is_written(): void {
        global $DB;
        $classroomid = $this->classroom('/1');
        $sessionid = $this->session($classroomid);
        $trainer = $this->user_at('/1/2');
        $scanner = $this->enrolled_learner($classroomid);
        session_manager::record_qr_attendance($sessionid, (int) $scanner->id);
        // The scan was before the trainer opened the grid, so the grid showed Present; Absent is a real edit.
        $DB->set_field('local_sentientia_classroom_attendance', 'timemodified', time() - 500,
            ['sessionid' => $sessionid, 'userid' => $scanner->id]);

        $this->setUser($trainer);
        $kept = null;
        $count = session_manager::bulk_mark_attendance($sessionid, [
            ['userid' => (int) $scanner->id, 'status' => session_manager::ATT_ABSENT],
        ], time() - 100, $kept);

        $this->assertSame(1, $count);
        $this->assertSame(0, $kept);
        $this->assertSame([(int) $scanner->id => session_manager::ATT_ABSENT], $this->statuses($sessionid));
    }

    public function test_the_trainers_own_earlier_save_is_never_protected_from_a_later_one(): void {
        $classroomid = $this->classroom('/1');
        $sessionid = $this->session($classroomid);
        $trainer = $this->user_at('/1/2');
        $learner = $this->enrolled_learner($classroomid);
        $this->setUser($trainer);
        $loadedat = time() - 100;

        // First Save ticks the learner Present; the second, on the same page, unticks them.
        $kept = null;
        session_manager::bulk_mark_attendance($sessionid, [
            ['userid' => (int) $learner->id, 'status' => session_manager::ATT_PRESENT],
        ], $loadedat, $kept);
        $count = session_manager::bulk_mark_attendance($sessionid, [
            ['userid' => (int) $learner->id, 'status' => session_manager::ATT_ABSENT],
        ], $loadedat, $kept);

        $this->assertSame(1, $count);
        $this->assertSame(0, $kept);
        $this->assertSame([(int) $learner->id => session_manager::ATT_ABSENT], $this->statuses($sessionid));
    }

    public function test_another_trainers_newer_mark_is_protected_too(): void {
        $classroomid = $this->classroom('/1');
        $sessionid = $this->session($classroomid);
        $first = $this->user_at('/1/2');
        $second = $this->user_at('/1/2');
        $learner = $this->enrolled_learner($classroomid);
        $loadedat = time() - 100;

        $this->setUser($first);
        session_manager::mark_attendance($sessionid, (int) $learner->id, session_manager::ATT_EXCUSED, 'Leave');

        $this->setUser($second);   // has an old grid that shows the learner as Absent
        $kept = null;
        $keptusers = null;
        $count = session_manager::bulk_mark_attendance($sessionid, [
            ['userid' => (int) $learner->id, 'status' => session_manager::ATT_ABSENT],
        ], $loadedat, $kept, $keptusers);

        $this->assertSame(0, $count);
        $this->assertSame(1, $kept);
        $this->assertSame([(int) $learner->id => session_manager::ATT_EXCUSED], $keptusers);
        $this->assertSame([(int) $learner->id => session_manager::ATT_EXCUSED], $this->statuses($sessionid));
    }

    public function test_without_a_load_time_a_save_writes_every_mark_as_before(): void {
        $classroomid = $this->classroom('/1');
        $sessionid = $this->session($classroomid);
        $trainer = $this->user_at('/1/2');
        $scanner = $this->enrolled_learner($classroomid);
        session_manager::record_qr_attendance($sessionid, (int) $scanner->id);

        $this->setUser($trainer);
        $kept = null;
        $count = session_manager::bulk_mark_attendance($sessionid, [
            ['userid' => (int) $scanner->id, 'status' => session_manager::ATT_ABSENT],
        ]);
        $this->assertSame(1, $count, 'Callers that pass no load time (older clients) keep the old behaviour.');
        $this->assertSame([(int) $scanner->id => session_manager::ATT_ABSENT], $this->statuses($sessionid));

        $count = session_manager::bulk_mark_attendance($sessionid, [
            ['userid' => (int) $scanner->id, 'status' => session_manager::ATT_ABSENT],
        ], 0, $kept);
        $this->assertSame(1, $count);
        $this->assertSame(0, $kept);
    }

    public function test_an_invalid_status_in_a_save_still_rolls_the_whole_save_back(): void {
        $classroomid = $this->classroom('/1');
        $sessionid = $this->session($classroomid);
        $a = $this->enrolled_learner($classroomid);
        $b = $this->enrolled_learner($classroomid);

        try {
            session_manager::bulk_mark_attendance($sessionid, [
                ['userid' => (int) $a->id, 'status' => session_manager::ATT_PRESENT],
                ['userid' => (int) $b->id, 'status' => 9],
            ]);
            $this->fail('An invalid status was accepted.');
        } catch (\moodle_exception $e) {
            $this->assertSame('invalidattendancestatus', $e->errorcode);
        }
        $this->assertCount(0, $this->rows($sessionid), 'Nothing from a refused save may persist.');
    }

    public function test_an_insert_that_loses_the_race_falls_back_to_an_update_inside_the_transaction(): void {
        global $DB;
        $classroomid = $this->classroom('/1');
        $sessionid = $this->session($classroomid);
        $trainer = $this->user_at('/1/2');
        $scanner = $this->enrolled_learner($classroomid);
        $other = $this->enrolled_learner($classroomid);

        // The trainer's Save read the learner's row a moment ago and found none...
        // ...and then a QR scan wrote it, before the Save's insert.
        session_manager::record_qr_attendance($sessionid, (int) $scanner->id);
        $this->assertCount(1, $this->rows($sessionid));

        // The private writer is called the way bulk_mark_attendance() does, with the stale
        // "no row" it had read. PHPUnit cannot interleave two writers, so this is the race.
        $write = new \ReflectionMethod(session_manager::class, 'write_attendance_row');
        $this->setUser($trainer);
        $tx = $DB->start_delegated_transaction();
        $write->invoke(null, $sessionid, (int) $scanner->id, session_manager::ATT_LATE, 'Late by trainer', null);
        // The refused insert must not have broken the transaction: the next mark still saves.
        $write->invoke(null, $sessionid, (int) $other->id, session_manager::ATT_PRESENT, '', null);
        $tx->allow_commit();

        $rows = $this->rows($sessionid);
        $this->assertCount(2, $rows, 'One row per (session, learner): no duplicate, nothing lost.');
        $this->assertSame([
            (int) $scanner->id => session_manager::ATT_LATE,
            (int) $other->id => session_manager::ATT_PRESENT,
        ], $this->statuses($sessionid));
        foreach ($rows as $row) {
            if ((int) $row->userid === (int) $scanner->id) {
                $this->assertSame((int) $trainer->id, (int) $row->markedby);
                $this->assertSame('Late by trainer', $row->notes);
            }
        }
    }
}
