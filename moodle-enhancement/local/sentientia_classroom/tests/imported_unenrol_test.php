<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

namespace local_sentientia_classroom;

defined('MOODLE_INTERNAL') || die();

use local_sentientia_platform\bizlms\legacymap;

/**
 * An admin may unenrol an imported classroom learner who carries no history yet (owner decision
 * framework.protect_imported_history_pending_enrolments, 2026-10-07; LRN-10).
 *
 * The signed rule blocked every imported row. That would have stopped admins removing any of the learners the import
 * brought over as merely enrolled, which they can do in BizLMS today. A learner who has completed, has hours, or has
 * ANY attendance mark stays protected, and so does every learner of a classroom that is not active. "Imported" is what
 * the platform's map says, so these tests write map rows by hand instead of running the importer (bizlms_import_test
 * runs it).
 *
 * @package    local_sentientia_classroom
 * @category   test
 * @copyright  2026 Airpay Payment Services
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \local_sentientia_classroom\session_manager
 * @group local_sentientia_classroom
 * @group bizlms_import
 */
final class imported_unenrol_test extends \advanced_testcase {

    use \local_sentientia_org\test\bizlms_fixture;

    protected function setUp(): void {
        parent::setUp();
        $this->resetAfterTest();
        $this->ensure_bizlms_schema();
        $this->setAdminUser();
    }

    private function classroom(int $status = session_manager::STATUS_ACTIVE): int {
        global $DB;
        return (int) $DB->insert_record('local_sentientia_classroom', (object) [
            'name' => 'AML workshop', 'description' => '', 'costcenterid' => 0, 'open_path' => '/1',
            'location' => 'Room', 'capacity' => 20, 'status' => $status, 'visible' => 1,
            'timecreated' => time(), 'timemodified' => time(),
        ]);
    }

    /**
     * @return int The roster row's id.
     */
    private function roster(int $classroomid, \stdClass $user, array $extra = []): int {
        global $DB;
        return (int) $DB->insert_record('local_sentientia_classroom_users', (object) ($extra + [
            'classroomid' => $classroomid, 'userid' => $user->id, 'enrolledby' => 2, 'completion_status' => 0,
            'timecreated' => time(), 'timemodified' => time(),
        ]));
    }

    private function session(int $classroomid): int {
        $start = time() + DAYSECS;
        return session_manager::create_session($classroomid, (object) [
            'title' => 'S1', 'starttime' => $start, 'endtime' => $start + HOURSECS,
        ]);
    }

    private function attendance(int $sessionid, \stdClass $user, int $status): int {
        global $DB;
        return (int) $DB->insert_record('local_sentientia_classroom_attendance', (object) [
            'sessionid' => $sessionid, 'userid' => $user->id, 'status' => $status,
            'timecreated' => time(), 'timemodified' => time(),
        ]);
    }

    /**
     * Record that the import created a row.
     *
     * @param string $table Target table.
     * @param int $id Target id.
     * @return void
     */
    private function imported(string $table, int $id): void {
        global $DB;
        static $n = 0;
        $n++;
        $DB->insert_record(legacymap::TABLE, (object) ['feature' => 'classroom', 'sourcetable' => 'test_' . $table,
            'sourceid' => $id + 1000 * $n, 'subkey' => '', 'targettable' => $table, 'targetid' => $id,
            'outcome' => 'imported', 'reason' => null, 'detail' => null, 'runid' => 0, 'timecreated' => time()]);
    }

    private function refused(int $classroomid, \stdClass $user, string $why): void {
        global $DB;
        try {
            session_manager::unenrol_user($classroomid, (int) $user->id);
            $this->fail($why . ': history was removed');
        } catch (\moodle_exception $e) {
            $this->assertSame('error_protected_history', $e->errorcode, $why);
        }
        $this->assertTrue($DB->record_exists('local_sentientia_classroom_users',
            ['classroomid' => $classroomid, 'userid' => $user->id]), $why . ': the roster row is still there');
    }

    public function test_an_imported_learner_with_no_history_on_an_active_classroom_can_be_removed(): void {
        global $DB;
        $classroom = $this->classroom();
        $user = $this->getDataGenerator()->create_user();
        $rosterid = $this->roster($classroom, $user);
        $this->imported('local_sentientia_classroom_users', $rosterid);
        // A session exists but this learner has no mark in it.
        $this->session($classroom);

        $this->assertTrue(session_manager::is_imported('local_sentientia_classroom_users', $rosterid));
        $this->assertTrue(session_manager::unenrol_user($classroom, (int) $user->id));
        $this->assertFalse($DB->record_exists('local_sentientia_classroom_users', ['id' => $rosterid]));
        $this->assertTrue($DB->record_exists(legacymap::TABLE, ['targettable' => 'local_sentientia_classroom_users',
            'targetid' => $rosterid]), 'the BizLMS row and its map entry stay as the record of the import');
    }

    public function test_an_imported_learner_who_carries_history_stays_protected(): void {
        $classroom = $this->classroom();
        $sid = $this->session($classroom);

        $completed = $this->getDataGenerator()->create_user();
        $this->imported('local_sentientia_classroom_users',
            $this->roster($classroom, $completed, ['completion_status' => 1, 'timecompleted' => time() - DAYSECS]));
        $this->refused($classroom, $completed, 'a completed learner');

        $hours = $this->getDataGenerator()->create_user();
        $this->imported('local_sentientia_classroom_users', $this->roster($classroom, $hours, ['hours' => 4]));
        $this->refused($classroom, $hours, 'a learner with training hours');

        // ANY mark counts, an absence included: it is the record that the learner was expected and did not attend.
        foreach ([session_manager::ATT_PRESENT, session_manager::ATT_ABSENT] as $mark) {
            $marked = $this->getDataGenerator()->create_user();
            $this->imported('local_sentientia_classroom_users', $this->roster($classroom, $marked));
            $this->attendance($sid, $marked, $mark);
            $this->refused($classroom, $marked, 'a learner with an attendance mark of ' . $mark);
        }
    }

    public function test_an_imported_learner_of_a_classroom_that_is_not_active_stays_protected(): void {
        foreach ([session_manager::STATUS_DRAFT, session_manager::STATUS_ON_HOLD, session_manager::STATUS_CANCELLED,
                session_manager::STATUS_COMPLETED] as $status) {
            $classroom = $this->classroom($status);
            $user = $this->getDataGenerator()->create_user();
            $this->imported('local_sentientia_classroom_users', $this->roster($classroom, $user));
            $this->refused($classroom, $user, 'a pending learner of a classroom in status ' . $status);
        }
    }

    public function test_a_learner_enrolled_on_the_site_is_removed_as_before_and_a_completed_one_is_not(): void {
        global $DB;
        $classroom = $this->classroom();
        $native = $this->getDataGenerator()->create_user();
        $this->roster($classroom, $native);
        $this->assertTrue(session_manager::unenrol_user($classroom, (int) $native->id));
        $this->assertFalse($DB->record_exists('local_sentientia_classroom_users', ['userid' => $native->id]));

        // A completed learner is refused whether imported or not.
        $done = $this->getDataGenerator()->create_user();
        $this->roster($classroom, $done, ['completion_status' => 1, 'timecompleted' => time() - DAYSECS]);
        $this->refused($classroom, $done, 'a completed learner enrolled on the site');
    }

    public function test_a_learner_whose_attendance_alone_is_imported_stays_protected(): void {
        // The roster row is not imported (the import skipped it and the learner was added since); the attendance is.
        $classroom = $this->classroom();
        $sid = $this->session($classroom);
        $user = $this->getDataGenerator()->create_user();
        $this->roster($classroom, $user);
        $this->imported('local_sentientia_classroom_attendance', $this->attendance($sid, $user, session_manager::ATT_PRESENT));
        $this->refused($classroom, $user, 'imported attendance');
    }

    public function test_the_pending_rule_reads_the_row_and_the_classroom_only(): void {
        $classroom = $this->classroom();
        $user = $this->getDataGenerator()->create_user();
        $row = (object) ['classroomid' => $classroom, 'userid' => $user->id, 'completion_status' => 0,
            'timecompleted' => null, 'hours' => null];
        $active = (object) ['status' => session_manager::STATUS_ACTIVE];

        $this->assertTrue(session_manager::imported_roster_is_pending($active, $row));
        $this->assertFalse(session_manager::imported_roster_is_pending(false, $row), 'no classroom: nothing is pending');
        $this->assertFalse(session_manager::imported_roster_is_pending((object) ['status' => session_manager::STATUS_DRAFT], $row));
        foreach (['completion_status' => 1, 'timecompleted' => 5, 'hours' => 2] as $column => $value) {
            $changed = clone $row;
            $changed->$column = $value;
            $this->assertFalse(session_manager::imported_roster_is_pending($active, $changed), $column);
        }
    }
}
