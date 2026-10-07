<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

namespace local_sentientia_classroom;

defined('MOODLE_INTERNAL') || die();

use local_sentientia_classroom\bizlms\importer as classroom_importer;
use local_sentientia_classroom\tests\bizlms\fixture_xml;
use local_sentientia_classroom\tests\bizlms\org_stub;
use core_privacy\tests\provider_testcase;
use local_sentientia_platform\bizlms\decisions;
use local_sentientia_platform\bizlms\guard;
use local_sentientia_platform\bizlms\importer;
use local_sentientia_platform\bizlms\legacymap;
use local_sentientia_platform\bizlms\registry;
use local_sentientia_platform\bizlms\report;
use local_sentientia_platform\bizlms\runner;
use local_sentientia_platform\phpunit\importer_contract;
use local_sentientia_platform\phpunit\legacy_schema_fixture;

/**
 * The BizLMS classroom import (ADR-032, mapping doc section 15): the importer contract every feature passes,
 * and the classroom feature's own expectations, from the fixture section of the mapping doc.
 *
 * The seed is the one the mapping doc describes: organisations /1, /1/5, /77 and /177; learners A (/1/5) and B
 * (/77), a deleted learner D, trainers T1 and T2 and a tenant admin; institute I1 (225-character name) with rooms
 * R1, R2 and R3 (R3 has no institute), institute I2 (external, hidden); nine classrooms CR1 to CR9 in every state
 * and with every kind of path; sessions, roster, attendance and waiting-list rows that exercise every reason.
 * The legacy tables are the verbatim BizLMS install files (tests/fixtures/bizlms).
 *
 * @package    local_sentientia_classroom
 * @category   test
 * @copyright  2026 Airpay Payment Services
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 *
 * @covers \local_sentientia_classroom\bizlms\importer
 * @covers \local_sentientia_classroom\bizlms\classroom_step
 * @covers \local_sentientia_classroom\bizlms\session_step
 * @covers \local_sentientia_classroom\bizlms\user_step
 * @covers \local_sentientia_classroom\bizlms\attendance_step
 * @covers \local_sentientia_classroom\bizlms\waitlist_step
 *
 * @group local_sentientia_classroom
 * @group bizlms_import
 * @group tenant_isolation
 */
final class bizlms_import_test extends provider_testcase {
    use legacy_schema_fixture;
    use importer_contract;
    use \local_sentientia_org\test\bizlms_fixture;

    /** @var int Timestamp base of the seed. */
    private const T0 = 1700000000;

    /** @var \stdClass[] The users the seed created, by role in the story. */
    private array $u = [];

    /** @var int Stored file id of the classroom logo the seed creates. */
    private int $logoitemid = 11;

    protected static function legacy_fixture_definition(): array {
        return ['xml' => fixture_xml::combined()];
    }

    protected function contract_importer(): importer {
        return new classroom_importer();
    }

    /**
     * The classroom importer depends on org; the org importer is another feature's, so a stand-in is registered.
     *
     * @return importer
     */
    protected function contract_begin(): importer {
        $this->resetAfterTest();
        $importer = $this->contract_importer();
        registry::set_testing_importers([new org_stub(), $importer]);
        return $importer;
    }

    /**
     * The signed decisions, the file the rehearsal and the cutover run with.
     *
     * @return decisions
     */
    protected function contract_decisions(): decisions {
        return self::signed_decisions();
    }

    /**
     * @return decisions
     */
    private static function signed_decisions(): decisions {
        $dir = \core_component::get_component_directory('local_sentientia_platform');
        return decisions::load($dir . '/tests/fixtures/bizlms/bizlms-import-decisions.copy.json');
    }

    protected function contract_seed(): void {
        $this->seed_classroom_data();
    }

    protected function contract_mutate_source(): void {
        // The injected failure stops the run in the rooms step, after the institutes step is done: a new
        // institute changes that finished step's count and max id, which resume must notice.
        $this->legacy_institute(50, ['fullname' => 'Late institute']);
    }

    protected function contract_collision(): ?array {
        return ['table' => 'local_sentientia_classroom', 'row' => (object) [
            'id' => 1, 'name' => 'Somebody else', 'timecreated' => 5, 'timemodified' => 5]];
    }

    protected function contract_adoptable(): ?array {
        // What the retired migrate_all.php wrote: the same id, name and timecreated as the source row.
        return ['table' => 'local_sentientia_classroom', 'sourcetable' => 'local_classroom', 'row' => (object) [
            'id' => 9, 'name' => 'Header copy', 'status' => 4, 'timecreated' => self::T0 + 9, 'timemodified' => 7]];
    }

    protected function contract_user_columns(): array {
        return [
            'local_sentientia_classroom' => ['trainerid', 'createdby'],
            'local_sentientia_classroom_sessions' => ['trainerid'],
            'local_sentientia_classroom_trainers' => ['trainerid'],
            'local_sentientia_classroom_users' => ['userid', 'enrolledby', 'completion_status', 'timecompleted', 'hours'],
            'local_sentientia_classroom_attendance' => ['userid', 'markedby'],
            'local_sentientia_classroom_waitlist' => ['userid'],
        ];
    }

    // ─── Seed ─────────────────────────────────────────────────────────────────────────────────────────

    /**
     * Insert a legacy row with every NOT NULL column of its table filled.
     *
     * @param string $table
     * @param array $values
     * @param array $defaults
     * @return void
     */
    private function legacy_row(string $table, array $values, array $defaults): void {
        global $DB;
        $DB->import_record($table, (object) ($values + $defaults));
    }

    private function legacy_institute(int $id, array $values): void {
        $this->legacy_row('local_location_institutes', ['id' => $id] + $values, [
            'costcenter' => 1, 'fullname' => 'Institute ' . $id, 'shortnname' => 'i', 'address' => 'Address',
            'usercreated' => 2, 'visible' => 1, 'institute_type' => 1, 'timecreated' => self::T0 + $id,
            'timemodified' => 1,
        ]);
    }

    private function legacy_classroom(int $id, array $values = []): void {
        $this->legacy_row('local_classroom', ['id' => $id] + $values, [
            'name' => 'Classroom ' . $id, 'shortname' => 'cr' . $id, 'status' => 1, 'costcenter' => 1,
            'open_path' => '/1', 'capacity' => 30, 'visible' => 1, 'instituteid' => 0,
            'startdate' => self::T0 + 100, 'enddate' => self::T0 + 200, 'nomination_startdate' => 0,
            'nomination_enddate' => 0, 'completiondate' => 0, 'classroomlogo' => 0, 'description' => '',
            'usercreated' => 2, 'timecreated' => self::T0 + $id, 'timemodified' => 0,
            'open_categoryid' => 0, 'open_states' => '', 'open_district' => '', 'open_subdistrict' => '',
            'open_village' => '',
        ]);
    }

    private function legacy_session(int $id, array $values): void {
        $this->legacy_row('local_classroom_sessions', ['id' => $id] + $values, [
            'name' => '', 'trainerid' => 0, 'usercreated' => 2, 'timecreated' => self::T0 + $id,
            'timemodified' => 0, 'recordinglink' => '', 'messagelink' => '', 'description' => '',
            'timefinish' => 0,
        ]);
    }

    private function legacy_roster(int $id, array $values): void {
        $this->legacy_row('local_classroom_users', ['id' => $id] + $values, [
            'supervisorid' => 0, 'hours' => 0, 'completion_status' => 0, 'usercreated' => 2,
            'timecreated' => self::T0 + $id, 'timemodified' => 0, 'completiondate' => 0,
        ]);
    }

    private function legacy_attendance(int $id, array $values): void {
        $this->legacy_row('local_classroom_attendance', ['id' => $id] + $values, [
            'usercreated' => 2, 'timecreated' => self::T0 + $id, 'timemodified' => 0,
        ]);
    }

    private function legacy_waitlist(int $id, array $values): void {
        $this->legacy_row('local_classroom_waitlist', ['id' => $id] + $values, [
            'enroltype' => 0, 'enrolstatus' => 0, 'usercreated' => 2, 'timecreated' => self::T0 + $id,
            'timemodified' => 0,
        ]);
    }

    /**
     * Organisations /1, /1/5, /77 and /177 in the org engine, so tenant paths are checked against the tree.
     *
     * @return void
     */
    private function seed_orgs(): void {
        global $DB;
        foreach (['/1' => 'airpay', '/1/5' => 'finance', '/77' => 'public', '/177' => 'zeea'] as $path => $short) {
            $DB->insert_record('local_sentientia_org', (object) [
                'fullname' => 'Org ' . $short, 'shortname' => $short, 'parentid' => 0, 'path' => $path,
                'depth' => substr_count($path, '/'), 'visible' => 1, 'sortorder' => 0,
                'timecreated' => self::T0, 'timemodified' => self::T0,
            ]);
        }
    }

    /**
     * The whole fixture of mapping doc section 15.
     *
     * @return void
     */
    private function seed_classroom_data(): void {
        global $DB;
        $gen = $this->getDataGenerator();
        $t = self::T0;

        $this->seed_orgs();

        $this->u['a'] = $gen->create_user(['firstname' => 'Alice']);
        $this->u['b'] = $gen->create_user(['firstname' => 'Bob']);
        $this->u['d'] = $gen->create_user(['firstname' => 'Dora']);
        $DB->set_field('user', 'deleted', 1, ['id' => $this->u['d']->id]);
        $this->u['t1'] = $gen->create_user(['firstname' => 'Trainer1']);
        $this->u['t2'] = $gen->create_user(['firstname' => 'Trainer2']);
        $this->u['admin'] = $gen->create_user(['firstname' => 'Tenant']);
        $this->u['course'] = $gen->create_course();
        $a = (int) $this->u['a']->id;
        $b = (int) $this->u['b']->id;
        $d = (int) $this->u['d']->id;
        $t1 = (int) $this->u['t1']->id;
        $t2 = (int) $this->u['t2']->id;
        $admin = (int) $this->u['admin']->id;
        $courseid = (int) $this->u['course']->id;

        // Venues: I1 is internal with a 225-character name; I2 is external and hidden.
        $this->legacy_institute(1, ['costcenter' => 1, 'fullname' => str_repeat('I', 225), 'address' => 'Addr 1',
            'timecreated' => $t + 1, 'timemodified' => 1]);
        $this->legacy_institute(2, ['costcenter' => 77, 'fullname' => 'External Hall', 'institute_type' => 2,
            'visible' => 0, 'timecreated' => $t + 2, 'timemodified' => $t + 50]);
        $roomdefaults = ['address' => '', 'usercreated' => 2, 'visible' => 1];
        $this->legacy_row('local_location_room', ['id' => 1, 'instituteid' => 1, 'name' => 'Room A',
            'building' => 'Block 2', 'capacity' => 25, 'timecreated' => $t + 3, 'timemodified' => 0], $roomdefaults);
        $this->legacy_row('local_location_room', ['id' => 2, 'instituteid' => 1, 'name' => str_repeat('R', 45),
            'building' => 'NULL', 'capacity' => 0, 'timecreated' => $t + 4, 'timemodified' => 0], $roomdefaults);
        $this->legacy_row('local_location_room', ['id' => 3, 'instituteid' => 99, 'name' => 'Lost room',
            'building' => 'NULL', 'capacity' => 10, 'timecreated' => $t + 5, 'timemodified' => 0], $roomdefaults);

        // Classrooms.
        $this->legacy_classroom(1, ['name' => 'Completed Workshop', 'shortname' => 'CW', 'status' => 4,
            'open_path' => '/1/5', 'capacity' => 0, 'instituteid' => 1, 'nomination_startdate' => $t + 10,
            'nomination_enddate' => $t + 20, 'startdate' => $t + 100, 'enddate' => $t + 200,
            'completiondate' => $t + 300, 'usercreated' => $admin, 'classroomlogo' => $this->logoitemid,
            'description' => '<p>Hello</p>']);
        $this->legacy_classroom(2, ['status' => 1, 'open_path' => '/77', 'costcenter' => 77, 'capacity' => 25]);
        $this->legacy_classroom(3, ['status' => 3]);
        $this->legacy_classroom(4, ['status' => 0]);
        $this->legacy_classroom(5, ['status' => 2]);
        $this->legacy_classroom(6, ['open_path' => '', 'costcenter' => 177]);
        $this->legacy_classroom(7, ['open_path' => '1/5/']);
        $this->legacy_classroom(8, ['open_path' => '/9999/1', 'costcenter' => 9999]);
        $this->legacy_classroom(9, ['name' => 'Header copy', 'status' => 4]);

        // The classroom logo, in the BizLMS file area.
        get_file_storage()->create_file_from_string([
            'contextid' => \context_system::instance()->id, 'component' => 'local_classroom',
            'filearea' => 'classroomlogo', 'itemid' => $this->logoitemid, 'filepath' => '/', 'filename' => 'logo.png',
        ], 'not really a png');

        // Trainers: CR1 has T1 then T2 and T1 again; CR2's trainer is gone; one row has no classroom.
        $tdefaults = ['feedback_id' => 0, 'usercreated' => 2];
        $this->legacy_row('local_classroom_trainers', ['id' => 1, 'classroomid' => 1, 'trainerid' => $t1,
            'timecreated' => $t + 11], $tdefaults);
        $this->legacy_row('local_classroom_trainers', ['id' => 2, 'classroomid' => 1, 'trainerid' => $t2,
            'timecreated' => $t + 12], $tdefaults);
        $this->legacy_row('local_classroom_trainers', ['id' => 3, 'classroomid' => 1, 'trainerid' => $t1,
            'timecreated' => $t + 13, 'timemodified' => $t + 99], $tdefaults);
        $this->legacy_row('local_classroom_trainers', ['id' => 4, 'classroomid' => 2, 'trainerid' => 999999,
            'timecreated' => $t + 14], $tdefaults);
        $this->legacy_row('local_classroom_trainers', ['id' => 5, 'classroomid' => 77, 'trainerid' => $t1,
            'timecreated' => $t + 15], $tdefaults);

        // Linked courses: CR1 to C twice, to no course, and CR2 to a course that is gone.
        $cdefaults = ['usercreated' => 2, 'timemodified' => 0];
        $this->legacy_row('local_classroom_courses', ['id' => 1, 'classroomid' => 1, 'courseid' => $courseid,
            'timecreated' => $t + 21], $cdefaults);
        $this->legacy_row('local_classroom_courses', ['id' => 2, 'classroomid' => 1, 'courseid' => $courseid,
            'timecreated' => $t + 22], $cdefaults);
        $this->legacy_row('local_classroom_courses', ['id' => 3, 'classroomid' => 1, 'courseid' => 0,
            'timecreated' => $t + 23], $cdefaults);
        $this->legacy_row('local_classroom_courses', ['id' => 4, 'classroomid' => 2, 'courseid' => 987654,
            'timecreated' => $t + 24], $cdefaults);

        // Sessions.
        $this->legacy_session(1, ['classroomid' => 1, 'name' => 'Day 1', 'description' => '<p>Bring ID</p>',
            'roomid' => 1, 'instituteid' => 1, 'timestart' => $t + 1000, 'timefinish' => $t + 4600,
            'trainerid' => $t1, 'messagelink' => 'www.zoom.us/j/1', 'recordinglink' => 'ftp://x']);
        $this->legacy_session(2, ['classroomid' => 1, 'name' => '', 'timestart' => $t + 7000,
            'timefinish' => $t + 8000]);
        $this->legacy_session(3, ['classroomid' => 1, 'name' => 'Day 3', 'timestart' => $t + 5000,
            'timefinish' => $t + 4000, 'duration' => 90]);
        $this->legacy_session(4, ['classroomid' => 55, 'name' => 'Lost', 'timestart' => $t + 9000,
            'timefinish' => $t + 9600]);
        $this->legacy_session(5, ['classroomid' => 2, 'name' => 'External day', 'instituteid' => 2,
            'timestart' => $t + 6000, 'timefinish' => $t + 9600, 'trainerid' => $t2]);

        // Roster: A completed, B not (with a date that must not be kept), A again, deleted D, a missing user, A on CR2.
        $this->legacy_roster(1, ['classroomid' => 1, 'userid' => $a, 'completion_status' => 1,
            'completiondate' => $t + 300, 'hours' => 8, 'usercreated' => $admin]);
        $this->legacy_roster(2, ['classroomid' => 1, 'userid' => $b, 'completion_status' => 0,
            'completiondate' => $t + 400]);
        $this->legacy_roster(3, ['classroomid' => 1, 'userid' => $a, 'completion_status' => 0, 'hours' => 10]);
        $this->legacy_roster(4, ['classroomid' => 1, 'userid' => $d]);
        $this->legacy_roster(5, ['classroomid' => 1, 'userid' => 888888]);
        $this->legacy_roster(6, ['classroomid' => 2, 'userid' => $a]);

        // Attendance: A present on S1, B absent on S1, A unmarked on S2, A absent again on S1 (present wins), a row
        // filed under the wrong classroom, a missing session, a missing user.
        $this->legacy_attendance(1, ['classroomid' => 1, 'sessionid' => 1, 'userid' => $a, 'status' => 1,
            'usercreated' => $admin, 'usermodified' => $t1, 'timemodified' => $t + 1100]);
        $this->legacy_attendance(2, ['classroomid' => 1, 'sessionid' => 1, 'userid' => $b, 'status' => 2]);
        $this->legacy_attendance(3, ['classroomid' => 1, 'sessionid' => 2, 'userid' => $a, 'status' => 0]);
        $this->legacy_attendance(4, ['classroomid' => 1, 'sessionid' => 1, 'userid' => $a, 'status' => 2,
            'timemodified' => $t + 1200]);
        $this->legacy_attendance(5, ['classroomid' => 2, 'sessionid' => 1, 'userid' => $a, 'status' => 1]);
        $this->legacy_attendance(6, ['classroomid' => 1, 'sessionid' => 77, 'userid' => $a, 'status' => 1]);
        $this->legacy_attendance(7, ['classroomid' => 1, 'sessionid' => 1, 'userid' => 888888, 'status' => 1]);

        // Waiting list (the upgrade-era table: every column may be NULL).
        $this->legacy_waitlist(1, ['classroomid' => 2, 'userid' => $t1, 'sortorder' => 5]);
        $this->legacy_waitlist(2, ['classroomid' => 2, 'userid' => $t2, 'sortorder' => 9]);
        $this->legacy_waitlist(3, ['classroomid' => 1, 'userid' => $a, 'sortorder' => 1, 'enrolstatus' => 1]);
        $this->legacy_waitlist(4, ['classroomid' => 1, 'userid' => $t1, 'sortorder' => 2]);
        $this->legacy_waitlist(5, ['classroomid' => 2, 'userid' => $a, 'sortorder' => 3]);
        $this->legacy_waitlist(6, ['classroomid' => 2, 'userid' => null, 'sortorder' => 4]);
        $this->legacy_waitlist(7, ['classroomid' => null, 'userid' => $a, 'sortorder' => 1]);
        $this->legacy_waitlist(8, ['classroomid' => 4, 'userid' => $t2, 'sortorder' => 1]);
        $this->legacy_waitlist(9, ['classroomid' => 3, 'userid' => $t2, 'sortorder' => 1]);

        // One row each in the tables that are archived.
        $this->legacy_row('local_classroom_trainerfb', ['id' => 1, 'clrm_trainer_id' => 1, 'classroomid' => 1,
            'trainerid' => $t1, 'usercreated' => 2, 'timecreated' => $t + 40], []);
        $this->legacy_row('local_classroom_completion', ['id' => 1, 'classroomid' => 1, 'usercreated' => 2,
            'timecreated' => $t + 41], []);
    }

    /**
     * Seed and apply the signed decisions; return the result of the run.
     *
     * @param decisions|null $decisions
     * @param callable|null $extra Adds more legacy rows after the seed, before the run.
     * @return array
     */
    private function seed_and_apply(?decisions $decisions = null, ?callable $extra = null): array {
        $this->contract_begin();
        $this->seed_classroom_data();
        if ($extra !== null) {
            $extra();
        }
        $report = new report();
        $runner = new runner([
            'permit' => guard::test_permit(), 'apply' => true, 'decisions' => $decisions ?? self::signed_decisions(),
            'report' => $report, 'batch' => 3, 'atomic_threshold' => 50000,
        ]);
        $result = $runner->run([]);
        $this->assertContains($result['exit'], [0, 2], implode('; ', $result['blockers']));
        $result['report'] = $report;
        return $result;
    }

    private function target(string $table, array $conditions): \stdClass {
        global $DB;
        return $DB->get_record($table, $conditions, '*', MUST_EXIST);
    }

    /**
     * The map entry of a source row.
     *
     * @param string $sourcetable
     * @param int $sourceid
     * @return \stdClass
     */
    private function map(string $sourcetable, int $sourceid): \stdClass {
        global $DB;
        return $DB->get_record(legacymap::TABLE, ['sourcetable' => $sourcetable, 'sourceid' => $sourceid,
            'subkey' => ''], '*', MUST_EXIST);
    }

    // ─── Classrooms ───────────────────────────────────────────────────────────────────────────────────

    public function test_classrooms_keep_their_ids_and_get_the_mapped_status_path_and_dates(): void {
        global $DB;
        $this->seed_and_apply();
        $t = self::T0;

        $this->assertSame(9, $DB->count_records('local_sentientia_classroom'));
        $this->assertEqualsCanonicalizing(range(1, 9),
            array_map('intval', array_keys($DB->get_records('local_sentientia_classroom', null, '', 'id'))));

        // Statuses: 4 completed -> 2, 1 -> 1, 3 cancelled -> 0, new (0) -> draft 5, hold (2) -> 6.
        $status = $DB->get_records_menu('local_sentientia_classroom', null, '', 'id, status');
        $this->assertEquals([1 => 2, 2 => 1, 3 => 0, 4 => 5, 5 => 6], array_intersect_key($status, array_flip(range(1, 5))));

        // Paths: the signed decision is cross_tenant_only, so an unusable path is stored as NULL.
        $paths = $DB->get_records_menu('local_sentientia_classroom', null, '', 'id, open_path');
        $this->assertSame('/1/5', $paths[1]);
        $this->assertSame('/77', $paths[2]);
        $this->assertNull($paths[6], 'an empty path is not rebuilt from the cost centre');
        $this->assertSame('/1/5', $paths[7], "'1/5/' is normalised");
        $this->assertNull($paths[8], 'a root that is not a registered tenant is not kept');

        $cr1 = $this->target('local_sentientia_classroom', ['id' => 1]);
        $this->assertSame('Completed Workshop', $cr1->name);
        $this->assertSame('CW', $cr1->shortname);
        $this->assertSame(5, (int) $cr1->costcenterid, 'the last path segment');
        $this->assertSame(5, (int) $cr1->departmentid);
        $this->assertSame(0, (int) $cr1->capacity, '0 stays unlimited');
        $this->assertSame($t + 10, (int) $cr1->startdate, 'the nomination window is the enrolment window');
        $this->assertSame($t + 20, (int) $cr1->enddate);
        $this->assertSame($t + 100, (int) $cr1->trainingstart);
        $this->assertSame($t + 200, (int) $cr1->trainingend);
        $this->assertSame($t + 300, (int) $cr1->timecompleted);
        $this->assertSame((int) $this->u['admin']->id, (int) $cr1->createdby);
        $this->assertSame('<p>Hello</p>', $cr1->description);
        $this->assertSame($t + 1, (int) $cr1->timecreated, 'the source time is kept');
        $this->assertSame($t + 1, (int) $cr1->timemodified, 'a 0 timemodified reads as timecreated');
        $this->assertSame(str_repeat('I', 225), $cr1->location, 'the institute name is the location text');
        $this->assertSame((int) $this->map('local_location_institutes', 1)->targetid, (int) $cr1->locationid);

        // The nomination window is NULL, not 0, when BizLMS stored 0.
        $cr2 = $this->target('local_sentientia_classroom', ['id' => 2]);
        $this->assertNull($cr2->startdate);
        $this->assertNull($cr2->enddate);
        $this->assertSame(25, (int) $cr2->capacity);

        // A native insert after the import gets an id above the highest legacy id.
        $next = $DB->insert_record('local_sentientia_classroom', (object) [
            'name' => 'Native', 'timecreated' => time(), 'timemodified' => time()]);
        $this->assertGreaterThan(9, $next);
    }

    public function test_the_first_trainer_of_a_classroom_is_carried_so_the_trainer_is_not_locked_out(): void {
        global $DB;
        $this->seed_and_apply();
        $t1 = (int) $this->u['t1']->id;
        $t2 = (int) $this->u['t2']->id;

        $cr1 = $this->target('local_sentientia_classroom', ['id' => 1]);
        $this->assertSame($t1, (int) $cr1->trainerid, 'the lowest trainers row is the primary trainer');
        $cr2 = $this->target('local_sentientia_classroom', ['id' => 2]);
        $this->assertNull($cr2->trainerid, 'the trainer of CR2 no longer exists');

        // The attendance pages let a trainer in through the session's trainerid, the classroom's trainerid
        // or, for any other trainer of the classroom, a row of the trainers table.
        $s1 = $this->target('local_sentientia_classroom_sessions', ['id' => 1]);
        $s2 = $this->target('local_sentientia_classroom_sessions', ['id' => 2]);
        $s5 = $this->target('local_sentientia_classroom_sessions', ['id' => 5]);
        $cr2row = $this->target('local_sentientia_classroom', ['id' => 2]);
        $this->assertSame($t1, (int) $s1->trainerid);
        $this->assertNull($s2->trainerid);
        $this->assertTrue(session_manager::may_run_session($s1, $cr1, $t1));
        $this->assertTrue(session_manager::may_run_session($s2, $cr1, $t1), 'through the classroom trainer');
        $this->assertTrue(session_manager::may_run_session($s1, $cr1, $t2),
            'T2 is a co-trainer of CR1 (a trainers row) so S1, which names only T1, is still theirs to run');
        $this->assertFalse(session_manager::may_run_session($s5, $cr2row, $t1),
            'T1 is on no trainer row of CR2 and is not the trainer of S5');
        $this->assertTrue(session_manager::may_run_session($s5, $cr2row, $t2), 'through the session trainer');
    }

    public function test_the_pathless_decision_by_costcenter_files_an_unusable_path_under_the_cost_centre(): void {
        global $DB;
        $decisions = decisions::from_array([
            'classroom.status_new_hold' => 'add_5_6', 'classroom.waitlist_closed' => 'removed',
            'classroom.waitlist_open' => 'waiting_after_guard_else_removed', 'classroom.pathless' => 'by_costcenter',
            'classroom.costs_as_columns' => false, 'tenant.unresolved.classroom' => 'pathless',
        ]);
        $this->seed_and_apply($decisions);
        $this->assertSame('/177', $DB->get_field('local_sentientia_classroom', 'open_path', ['id' => 6]));
        $this->assertNull($DB->get_field('local_sentientia_classroom', 'open_path', ['id' => 8]),
            'cost centre 9999 is not a registered tenant either');
    }

    public function test_skip_unresolved_tenant_decision_skips_the_classroom(): void {
        global $DB;
        $decisions = decisions::from_array([
            'classroom.status_new_hold' => 'add_5_6', 'classroom.waitlist_closed' => 'removed',
            'classroom.waitlist_open' => 'waiting_after_guard_else_removed', 'classroom.pathless' => 'cross_tenant_only',
            'classroom.costs_as_columns' => false, 'tenant.unresolved.classroom' => 'skip',
        ]);
        $this->seed_and_apply($decisions);
        $this->assertFalse($DB->record_exists('local_sentientia_classroom', ['id' => 6]));
        $this->assertSame('skipped', $this->map('local_classroom', 6)->outcome);
        $this->assertSame('pathless_skipped', $this->map('local_classroom', 6)->reason);
        // The other classroom with no usable tenant is skipped too.
        $this->assertSame('skipped', $this->map('local_classroom', 8)->outcome);
    }

    public function test_collapse_active_keeps_new_and_hold_as_active(): void {
        global $DB;
        $decisions = decisions::from_array([
            'classroom.status_new_hold' => 'collapse_active', 'classroom.waitlist_closed' => 'removed',
            'classroom.waitlist_open' => 'waiting_after_guard_else_removed', 'classroom.pathless' => 'cross_tenant_only',
            'classroom.costs_as_columns' => false, 'tenant.unresolved.classroom' => 'pathless',
        ]);
        $this->seed_and_apply($decisions);
        $this->assertSame(1, (int) $DB->get_field('local_sentientia_classroom', 'status', ['id' => 4]));
        $this->assertSame(1, (int) $DB->get_field('local_sentientia_classroom', 'status', ['id' => 5]));
    }

    // ─── Venues ───────────────────────────────────────────────────────────────────────────────────────

    public function test_institutes_and_rooms_become_one_location_hierarchy(): void {
        global $DB;
        $this->seed_and_apply();

        $i1 = $this->target('local_sentientia_locations', ['id' => (int) $this->map('local_location_institutes', 1)->targetid]);
        $this->assertSame(200, \core_text::strlen($i1->name), 'the 225-character name is fitted to the column');
        $this->assertSame('Addr 1', $i1->address);
        $this->assertSame(1, (int) $i1->costcenterid);
        $this->assertSame(1, (int) $i1->venue_type);
        $this->assertNull($i1->parentid);
        $this->assertSame(1, (int) $i1->active);

        $i2 = $this->target('local_sentientia_locations', ['id' => (int) $this->map('local_location_institutes', 2)->targetid]);
        $this->assertSame('External Hall', $i2->name);
        $this->assertSame(77, (int) $i2->costcenterid);
        $this->assertSame(2, (int) $i2->venue_type);
        $this->assertSame(0, (int) $i2->active, 'a hidden institute is inactive');

        $r1 = $this->target('local_sentientia_locations', ['id' => (int) $this->map('local_location_room', 1)->targetid]);
        $this->assertSame((int) $i1->id, (int) $r1->parentid);
        $this->assertSame('Block 2', $r1->building);
        $this->assertSame(25, (int) $r1->capacity);
        $this->assertSame(1, (int) $r1->costcenterid, 'a room takes its institute\'s tenant');
        $this->assertSame(1, (int) $r1->venue_type);
        $this->assertSame('Addr 1', $r1->address, 'an empty room address is the institute\'s');
        $this->assertTrue(\core_text::strlen($r1->name) <= 200);
        $this->assertStringEndsWith(' - Room A', $r1->name, 'the room name stays whole');

        $r2 = $this->target('local_sentientia_locations', ['id' => (int) $this->map('local_location_room', 2)->targetid]);
        $this->assertNull($r2->building, "BizLMS's literal NULL default is no value");
        $this->assertSame(200, \core_text::strlen($r2->name));
        $this->assertStringEndsWith(' - ' . str_repeat('R', 45), $r2->name);

        $r3 = $this->target('local_sentientia_locations', ['id' => (int) $this->map('local_location_room', 3)->targetid]);
        $this->assertNull($r3->parentid, 'a room whose institute is missing has no parent');
        $this->assertSame(0, (int) $r3->costcenterid);
    }

    // ─── Trainers and linked courses ──────────────────────────────────────────────────────────────────

    public function test_trainers_and_linked_courses_are_deduplicated_and_orphans_are_skipped(): void {
        global $DB;
        $this->seed_and_apply();

        $this->assertSame(2, $DB->count_records('local_sentientia_classroom_trainers'), 'T1 and T2 on CR1');
        $this->assertTrue($DB->record_exists('local_sentientia_classroom_trainers',
            ['classroomid' => 1, 'trainerid' => $this->u['t1']->id]));
        $this->assertTrue($DB->record_exists('local_sentientia_classroom_trainers',
            ['classroomid' => 1, 'trainerid' => $this->u['t2']->id]));
        $this->assertSame('merged', $this->map('local_classroom_trainers', 3)->outcome);
        $this->assertSame('dup_natural_key', $this->map('local_classroom_trainers', 3)->reason);
        $this->assertSame((int) $this->map('local_classroom_trainers', 1)->targetid,
            (int) $this->map('local_classroom_trainers', 3)->targetid, 'the duplicate points at the row that won');
        $this->assertSame('orphan_trainer', $this->map('local_classroom_trainers', 4)->reason);
        $this->assertSame('orphan_classroom', $this->map('local_classroom_trainers', 5)->reason);
        // The duplicate's later change time is kept on the survivor.
        $this->assertSame(self::T0 + 99, (int) $DB->get_field('local_sentientia_classroom_trainers', 'timemodified',
            ['classroomid' => 1, 'trainerid' => $this->u['t1']->id]));

        $this->assertSame(1, $DB->count_records('local_sentientia_classroom_courses'));
        $this->assertSame('dup_natural_key', $this->map('local_classroom_courses', 2)->reason);
        $this->assertSame('orphan_course', $this->map('local_classroom_courses', 3)->reason);
        $this->assertSame('orphan_course', $this->map('local_classroom_courses', 4)->reason);
    }

    // ─── Sessions ─────────────────────────────────────────────────────────────────────────────────────

    public function test_sessions_keep_ids_times_places_links_and_trainers(): void {
        global $DB;
        $this->seed_and_apply();
        $t = self::T0;

        $this->assertEqualsCanonicalizing([1, 2, 3, 5],
            array_map('intval', array_keys($DB->get_records('local_sentientia_classroom_sessions', null, '', 'id'))));
        $this->assertSame('orphan_classroom', $this->map('local_classroom_sessions', 4)->reason);

        $s1 = $this->target('local_sentientia_classroom_sessions', ['id' => 1]);
        $this->assertSame(1, (int) $s1->classroomid);
        $this->assertSame('Day 1', $s1->title);
        $this->assertSame($t + 1000, (int) $s1->sessiondate);
        $this->assertSame($t + 1000, (int) $s1->starttime);
        $this->assertSame($t + 4600, (int) $s1->endtime);
        $this->assertSame('<p>Bring ID</p>', $s1->notes);
        $this->assertSame('https://www.zoom.us/j/1', $s1->meeting_url, "a bare www. address gets https://");
        $this->assertNull($s1->recording_url, 'ftp:// is refused by the rule a typed link goes through');
        $this->assertSame((int) $this->map('local_location_room', 1)->targetid, (int) $s1->locationid);
        $this->assertStringEndsWith(' - Room A', $s1->location);
        $this->assertTrue(\core_text::strlen($s1->location) <= 254);
        $this->assertSame($t + 2, (int) $s1->timecreated);
        $this->assertSame($t + 2, (int) $s1->timemodified);

        $s2 = $this->target('local_sentientia_classroom_sessions', ['id' => 2]);
        $this->assertSame('', $s2->title, 'an empty title is allowed');
        $this->assertNull($s2->location);
        $this->assertNull($s2->locationid);
        $this->assertNull($s2->trainerid, 'BizLMS stored 0 for no trainer');
        $this->assertNull($s2->meeting_url);
        $this->assertNull($s2->notes);

        $s3 = $this->target('local_sentientia_classroom_sessions', ['id' => 3]);
        $this->assertSame($t + 5000 + 90 * 60, (int) $s3->endtime, 'a finish before the start is rebuilt from the duration');

        $s5 = $this->target('local_sentientia_classroom_sessions', ['id' => 5]);
        $this->assertSame('External Hall', $s5->location, 'an institute with no room is the place');
        $this->assertSame((int) $this->map('local_location_institutes', 2)->targetid, (int) $s5->locationid);
    }

    public function test_a_native_session_at_a_legacy_session_id_blocks_the_feature(): void {
        global $DB;
        $this->contract_begin();
        $this->seed_classroom_data();
        $DB->import_record('local_sentientia_classroom_sessions', (object) [
            'id' => 1, 'classroomid' => 1, 'title' => 'Somebody else', 'sessiondate' => 5, 'starttime' => 5,
            'endtime' => 6, 'timecreated' => 5, 'timemodified' => 5]);
        $report = new report();
        $runner = new runner(['permit' => guard::test_permit(), 'apply' => true, 'decisions' => self::signed_decisions(),
            'report' => $report, 'atomic_threshold' => 0]);
        $result = $runner->run([]);
        $this->assertSame(1, $result['exit']);
        $this->assertStringContainsString('preserve_collision:classroom.sessions', implode(' ', $result['blockers']));
    }

    // ─── Roster ───────────────────────────────────────────────────────────────────────────────────────

    public function test_the_roster_collapses_duplicates_and_keeps_completion_only_when_completed(): void {
        global $DB;
        $this->seed_and_apply();
        $t = self::T0;
        $a = (int) $this->u['a']->id;
        $b = (int) $this->u['b']->id;

        $rowa = $this->target('local_sentientia_classroom_users', ['classroomid' => 1, 'userid' => $a]);
        $this->assertSame(1, (int) $rowa->completion_status, 'completed wins over pending');
        $this->assertSame($t + 300, (int) $rowa->timecompleted);
        $this->assertSame(10, (int) $rowa->hours, 'the most hours');
        $this->assertSame((int) $this->u['admin']->id, (int) $rowa->enrolledby);
        $this->assertSame($t + 1, (int) $rowa->timecreated);
        $this->assertSame('merged', $this->map('local_classroom_users', 3)->outcome);

        $rowb = $this->target('local_sentientia_classroom_users', ['classroomid' => 1, 'userid' => $b]);
        $this->assertSame(0, (int) $rowb->completion_status);
        $this->assertNull($rowb->timecompleted, 'BizLMS stamps a date on every recompute; only a completed row keeps it');

        // Deleted users are imported as history; a user that does not exist is skipped.
        $this->assertTrue($DB->record_exists('local_sentientia_classroom_users',
            ['classroomid' => 1, 'userid' => $this->u['d']->id]));
        $this->assertSame('orphan_user', $this->map('local_classroom_users', 5)->reason);
        $this->assertSame(3, $DB->count_records('local_sentientia_classroom_users', ['classroomid' => 1]),
            'A, B and D on CR1');
        $this->assertTrue($DB->record_exists('local_sentientia_classroom_users', ['classroomid' => 2, 'userid' => $a]));

        // The roster readers do not show the deleted learner, and the count matches the list.
        $this->assertSame(2, session_manager::count_enrolled(1));
        $this->assertCount(2, session_manager::get_enrolled_users(1));
    }

    // ─── Attendance ───────────────────────────────────────────────────────────────────────────────────

    public function test_attendance_maps_statuses_dedupes_and_archives_the_placeholder(): void {
        global $DB;
        $this->seed_and_apply();
        $a = (int) $this->u['a']->id;
        $b = (int) $this->u['b']->id;

        $this->assertSame(2, $DB->count_records('local_sentientia_classroom_attendance'));
        $present = $this->target('local_sentientia_classroom_attendance', ['sessionid' => 1, 'userid' => $a]);
        $this->assertSame(1, (int) $present->status, 'present beats absent');
        $this->assertSame((int) $this->u['t1']->id, (int) $present->markedby, 'usermodified is who marked it');
        $this->assertSame(self::T0 + 1, (int) $present->timecreated);
        $this->assertSame(self::T0 + 1100, (int) $present->timemodified);
        $absent = $this->target('local_sentientia_classroom_attendance', ['sessionid' => 1, 'userid' => $b]);
        $this->assertSame(0, (int) $absent->status, 'BizLMS absent (2) is Sentientia absent (0)');
        $this->assertSame(2, (int) $absent->markedby, 'usercreated when nobody modified it');

        $this->assertSame('archived', $this->map('local_classroom_attendance', 3)->outcome);
        $this->assertSame('unmarked_placeholder', $this->map('local_classroom_attendance', 3)->reason);
        $this->assertSame('merged', $this->map('local_classroom_attendance', 4)->outcome);
        $this->assertSame('classroom_mismatch', $this->map('local_classroom_attendance', 5)->reason);
        $this->assertSame('orphan_session', $this->map('local_classroom_attendance', 6)->reason);
        $this->assertSame('orphan_user', $this->map('local_classroom_attendance', 7)->reason);
    }

    // ─── Waiting list ─────────────────────────────────────────────────────────────────────────────────

    public function test_waiting_list_positions_statuses_and_reasons(): void {
        global $DB;
        $this->seed_and_apply();

        // CR2 (active): two places still waiting, renumbered 1 and 2 by sort order; A is on the roster already.
        $w1 = $this->target('local_sentientia_classroom_waitlist', ['classroomid' => 2, 'userid' => $this->u['t1']->id]);
        $w2 = $this->target('local_sentientia_classroom_waitlist', ['classroomid' => 2, 'userid' => $this->u['t2']->id]);
        $this->assertSame(['waiting', 1], [$w1->status, (int) $w1->position]);
        $this->assertSame(['waiting', 2], [$w2->status, (int) $w2->position]);
        $w5 = $this->target('local_sentientia_classroom_waitlist', ['classroomid' => 2, 'userid' => $this->u['a']->id]);
        $this->assertSame('promoted', $w5->status);
        $this->assertSame('Imported from BizLMS: already enrolled', $w5->reason);
        $this->assertNotNull($w5->promoted_at);

        // CR1 (completed): moved-to-roster stays promoted; a waiting place is removed with a reason.
        $w3 = $this->target('local_sentientia_classroom_waitlist', ['classroomid' => 1, 'userid' => $this->u['a']->id]);
        $this->assertSame('promoted', $w3->status);
        $w4 = $this->target('local_sentientia_classroom_waitlist', ['classroomid' => 1, 'userid' => $this->u['t1']->id]);
        $this->assertSame('removed', $w4->status);
        $this->assertSame('Imported from BizLMS: classroom closed before promotion', $w4->reason);
        $this->assertNotNull($w4->removed_at);

        // CR4 (draft) keeps a waiting place once the auto-promote guard is in; CR3 (cancelled) does not.
        $w8 = $this->target('local_sentientia_classroom_waitlist', ['classroomid' => 4]);
        $this->assertSame(['waiting', 1], [$w8->status, (int) $w8->position]);
        $w9 = $this->target('local_sentientia_classroom_waitlist', ['classroomid' => 3]);
        $this->assertSame('removed', $w9->status);

        // Rows with no classroom or no learner are skipped (the upgrade-era table allowed NULL).
        $this->assertSame('incomplete_row', $this->map('local_classroom_waitlist', 6)->reason);
        $this->assertSame('incomplete_row', $this->map('local_classroom_waitlist', 7)->reason);
    }

    public function test_a_deleted_learners_place_is_removed_and_a_repeated_place_is_merged(): void {
        global $DB;
        $t = self::T0;
        $this->seed_and_apply(null, function () use ($t): void {
            // CR2 is active. Its queue (seed): T1 (sort 5), T2 (sort 9). Added: the deleted user at the head, and
            // T1 again further back (a later creation and change time that the surviving place keeps).
            $this->legacy_waitlist(10, ['classroomid' => 2, 'userid' => $this->u['d']->id, 'sortorder' => 1,
                'timecreated' => $t + 500]);
            $this->legacy_waitlist(11, ['classroomid' => 2, 'userid' => $this->u['t1']->id, 'sortorder' => 8,
                'timecreated' => $t + 400, 'timemodified' => $t + 450]);
        });
        $t1 = (int) $this->u['t1']->id;
        $t2 = (int) $this->u['t2']->id;

        // The deleted learner would have headed the queue; their place is removed, with a reason, and not counted.
        $gone = $this->target('local_sentientia_classroom_waitlist',
            ['classroomid' => 2, 'userid' => $this->u['d']->id]);
        $this->assertSame('removed', $gone->status);
        $this->assertSame('Imported from BizLMS: the learner no longer exists', $gone->reason);
        $this->assertNotNull($gone->removed_at);
        $this->assertNull($gone->promoted_at);
        $waiting = $DB->get_records_menu('local_sentientia_classroom_waitlist',
            ['classroomid' => 2, 'status' => 'waiting'], 'position ASC', 'userid, position');
        $this->assertSame([$t1 => 1, $t2 => 2], array_map('intval', $waiting), 'no gap where the deleted user was');

        // T1 waits once: the earlier place (source id 1) survives and the later one is merged into it.
        $this->assertSame(1, $DB->count_records('local_sentientia_classroom_waitlist',
            ['classroomid' => 2, 'userid' => $t1]));
        $this->assertSame('merged', $this->map('local_classroom_waitlist', 11)->outcome);
        $this->assertSame('dup_waiting_place', $this->map('local_classroom_waitlist', 11)->reason);
        $this->assertSame((int) $this->map('local_classroom_waitlist', 1)->targetid,
            (int) $this->map('local_classroom_waitlist', 11)->targetid, 'the duplicate points at the place that stayed');
        $kept = $this->target('local_sentientia_classroom_waitlist', ['classroomid' => 2, 'userid' => $t1]);
        $this->assertSame($t + 1, (int) $kept->timecreated, 'the earliest creation time');
        $this->assertSame($t + 450, (int) $kept->timemodified, 'the latest change time');
    }

    public function test_the_auto_promote_guard_keeps_an_imported_waiting_place_from_enrolling_anyone(): void {
        global $DB;
        $this->seed_and_apply();
        // CR4 is a draft with one waiting place, and capacity to spare: nothing may promote the learner.
        $this->assertSame(0, waitlist_manager::auto_promote(4));
        $this->assertFalse($DB->record_exists('local_sentientia_classroom_users', ['classroomid' => 4]));
        $this->assertSame('waiting', $DB->get_field('local_sentientia_classroom_waitlist', 'status', ['classroomid' => 4]));
    }

    // ─── Archived tables, blockers, enums ──────────────────────────────────────────────────────────────

    public function test_trainer_feedback_and_completion_rules_are_archived(): void {
        $this->seed_and_apply();
        $this->assertSame('archived', $this->map('local_classroom_trainerfb', 1)->outcome);
        $this->assertSame('submission_marker', $this->map('local_classroom_trainerfb', 1)->reason);
        $this->assertSame('archived', $this->map('local_classroom_completion', 1)->outcome);
        $this->assertSame('completion_rule_config', $this->map('local_classroom_completion', 1)->reason);
    }

    public function test_every_source_row_has_one_primary_map_row(): void {
        global $DB;
        $this->seed_and_apply();
        $counts = [
            'local_location_institutes' => 2, 'local_location_room' => 3, 'local_classroom' => 9,
            'local_classroom_trainers' => 5, 'local_classroom_courses' => 4, 'local_classroom_sessions' => 5,
            'local_classroom_users' => 6, 'local_classroom_attendance' => 7, 'local_classroom_waitlist' => 9,
            'local_classroom_trainerfb' => 1, 'local_classroom_completion' => 1,
        ];
        foreach ($counts as $table => $expected) {
            $this->assertSame($expected, $DB->count_records($table), $table . ' seed');
            $this->assertSame($expected, $DB->count_records(legacymap::TABLE,
                ['sourcetable' => $table, 'subkey' => '']), $table . ' primary map rows');
        }
    }

    public function test_the_skipped_reasons_are_counted_in_the_report(): void {
        $result = $this->seed_and_apply();
        $steps = $result['report']->to_array()['features']['classroom']['steps'];
        $this->assertEquals(['orphan_trainer' => 1, 'orphan_classroom' => 1, 'dup_natural_key' => 1],
            $steps['classroom.trainers']['skipped_by_reason'] ?? [], 'trainers not imported, by reason');
        $this->assertSame(1, $steps['classroom.sessions']['skipped_by_reason']['orphan_classroom']);
        $this->assertSame(1, $steps['classroom.users']['skipped_by_reason']['orphan_user']);
        $this->assertSame(1, $steps['classroom.attendance']['skipped_by_reason']['classroom_mismatch']);
        $this->assertSame(1, $steps['classroom.attendance']['skipped_by_reason']['unmarked_placeholder']);
        $this->assertSame(2, $steps['classroom.waitlist']['skipped_by_reason']['incomplete_row']);
        // Tenant methods: exact, normalised for '1/5/', unresolved for the two unusable paths.
        $methods = $steps['classroom.classrooms']['tenant_methods'];
        $this->assertSame(2, $methods['unresolved'] ?? 0);
        $this->assertSame(1, $methods['normalised'] ?? 0);
    }

    public function test_a_test_score_row_blocks_the_feature(): void {
        global $DB;
        $this->contract_begin();
        $this->seed_classroom_data();
        // The table is declared by BizLMS and was never written by any code; a row means somebody has to look.
        $DB->import_record('local_classroom_test_score', (object) ['id' => 1, 'classroomid' => 1, 'courseid' => 2,
            'testid' => 't1', 'usercreated' => 2, 'timecreated' => self::T0]);
        $runner = new runner(['decisions' => self::signed_decisions(), 'report' => new report()]);
        $result = $runner->run([]);
        $this->assertSame(1, $result['exit']);
        $this->assertStringContainsString('classroom_test_score_has_rows', implode(' ', $result['blockers']));
    }

    public function test_an_unknown_classroom_status_blocks_the_feature(): void {
        $this->contract_begin();
        $this->seed_classroom_data();
        $this->legacy_classroom(60, ['status' => 7]);
        $runner = new runner(['decisions' => self::signed_decisions(), 'report' => new report()]);
        $result = $runner->run([]);
        $this->assertSame(1, $result['exit']);
        $this->assertStringContainsString('unknown_enum:local_classroom.status=7', implode(' ', $result['blockers']));
    }

    public function test_a_column_a_step_reads_but_the_source_schema_lacks_blocks_the_feature(): void {
        global $DB;
        $this->contract_begin();
        $this->seed_classroom_data();
        $dbman = $DB->get_manager();

        // One column from each kind of table; none is in an index, so the fixture can drop it.
        $missing = [
            'local_classroom' => 'nomination_startdate',
            'local_classroom_sessions' => 'messagelink',
            'local_classroom_attendance' => 'usermodified',
            'local_location_room' => 'building',
        ];
        try {
            foreach ($missing as $table => $column) {
                $dbman->drop_field(new \xmldb_table($table), new \xmldb_field($column));
            }
            $runner = new runner(['decisions' => self::signed_decisions(), 'report' => new report()]);
            $result = $runner->run([]);
            $this->assertSame(1, $result['exit'], 'a missing source column blocks the run, it does not default to 0');
            $blockers = implode(' ', $result['blockers']);
            foreach ($missing as $table => $column) {
                $this->assertStringContainsString("missing_column:{$table}.{$column}", $blockers);
            }
        } finally {
            // The next test gets the tables back whole (the trait recreates a dropped table).
            foreach (array_keys($missing) as $table) {
                self::drop_legacy_table($table);
            }
        }
    }

    // ─── Logo ─────────────────────────────────────────────────────────────────────────────────────────

    public function test_the_logo_is_copied_to_the_sentientia_file_area_and_the_original_stays(): void {
        $this->seed_and_apply();
        $fs = get_file_storage();
        $systemid = \context_system::instance()->id;
        $this->assertTrue($fs->file_exists($systemid, 'local_sentientia_classroom', 'classroomlogo', 1, '/', 'logo.png'));
        $this->assertTrue($fs->file_exists($systemid, 'local_classroom', 'classroomlogo', $this->logoitemid, '/', 'logo.png'),
            'the original is never touched');
        $this->assertFalse($fs->file_exists($systemid, 'local_sentientia_classroom', 'classroomlogo', 2, '/', 'logo.png'));
        $count = count($fs->get_area_files($systemid, 'local_sentientia_classroom', 'classroomlogo', 1, 'id', false));
        $this->assertSame(1, $count);

        // A second run copies nothing more.
        $runner = new runner(['permit' => guard::test_permit(), 'apply' => true, 'decisions' => self::signed_decisions(),
            'report' => new report(), 'atomic_threshold' => 0]);
        $runner->run([]);
        $this->assertCount($count, $fs->get_area_files($systemid, 'local_sentientia_classroom', 'classroomlogo', 1, 'id', false));
    }

    // ─── Tenant isolation: what a tenant admin sees after the import ──────────────────────────────────

    public function test_a_tenant_admin_sees_their_tenants_imported_classrooms_and_attendance_only(): void {
        global $DB;
        $this->ensure_bizlms_schema();
        $this->seed_and_apply();

        $a = (int) $this->u['a']->id;
        $b = (int) $this->u['b']->id;
        $DB->set_field('user', 'open_path', '/1/5', ['id' => $a]);
        $DB->set_field('user', 'open_path', '/77', ['id' => $b]);
        $admin = $this->u['admin'];
        $DB->set_field('user', 'open_path', '/1', ['id' => $admin->id]);
        $managerid = (int) $DB->get_field('role', 'id', ['shortname' => 'manager'], MUST_EXIST);
        role_assign($managerid, $admin->id, \context_system::instance()->id);
        $this->setUser($DB->get_record('user', ['id' => $admin->id], '*', MUST_EXIST));

        $listed = external\list_classrooms::execute('', 'name', 'asc', 0, 50);
        $names = array_map(fn($row) => strip_tags($row['name']), $listed['rows']);
        $this->assertContains('Completed Workshop', $names);
        $this->assertNotContains('Classroom 2', $names, "the /77 tenant's classroom");
        $this->assertNotContains('Classroom 6', $names, 'a pathless classroom is cross-tenant only');
        $this->assertNotContains('Classroom 8', $names);
        $byname = array_column($listed['rows'], null, 'statuslabel');
        $this->assertArrayHasKey('Completed', $byname, 'CR1 reads Completed');
        $this->assertArrayHasKey('Draft', $byname);
        $this->assertArrayHasKey('On hold', $byname);

        // The other tenant's classroom cannot be opened by id; the pathless one neither.
        foreach ([2, 6] as $id) {
            try {
                session_manager::require_classroom_access($id);
                $this->fail("classroom {$id} opened across tenants");
            } catch (\moodle_exception $e) {
                $this->assertSame('error_outoftenant', $e->errorcode);
            }
        }

        // The attendance of S1 lists A as Present and never the other tenant's learner B.
        $attendance = external\list_session_attendance::execute(1);
        $byuser = array_column($attendance['rows'], null, 'userid');
        $this->assertSame('Present', $byuser[$a]['status_label']);
        $this->assertArrayNotHasKey($b, $byuser);
    }

    // ─── Protect imported history ─────────────────────────────────────────────────────────────────────

    public function test_imported_history_cannot_be_deleted_or_unenrolled_from_the_pages(): void {
        global $DB;
        $this->seed_and_apply();
        $this->setAdminUser();
        $a = (int) $this->u['a']->id;

        foreach ([
            'delete_classroom' => fn() => session_manager::delete(1),
            'delete_session' => fn() => session_manager::delete_session(1),
            'unenrol_user' => fn() => session_manager::unenrol_user(1, $a),
        ] as $what => $call) {
            try {
                $call();
                $this->fail($what . ' removed imported history');
            } catch (\moodle_exception $e) {
                $this->assertSame('error_protected_history', $e->errorcode, $what);
            }
        }
        $this->assertTrue($DB->record_exists('local_sentientia_classroom', ['id' => 1]));
        $this->assertTrue($DB->record_exists('local_sentientia_classroom_sessions', ['id' => 1]));
        $this->assertTrue($DB->record_exists('local_sentientia_classroom_users', ['classroomid' => 1, 'userid' => $a]));
        $this->assertTrue($DB->record_exists('local_sentientia_classroom_attendance', ['sessionid' => 1, 'userid' => $a]));

        // A classroom somebody created after the import is deletable, and its waiting list and trainer rows go too.
        $native = session_manager::create((object) ['name' => 'Native', 'capacity' => 5]);
        $DB->insert_record('local_sentientia_classroom_trainers', (object) [
            'classroomid' => $native, 'trainerid' => $this->u['t1']->id, 'timecreated' => time(), 'timemodified' => time()]);
        $DB->insert_record('local_sentientia_classroom_waitlist', (object) [
            'classroomid' => $native, 'userid' => $a, 'position' => 1, 'status' => 'waiting',
            'timecreated' => time(), 'timemodified' => time()]);
        session_manager::delete($native);
        $this->assertFalse($DB->record_exists('local_sentientia_classroom', ['id' => $native]));
        $this->assertFalse($DB->record_exists('local_sentientia_classroom_trainers', ['classroomid' => $native]));
        $this->assertFalse($DB->record_exists('local_sentientia_classroom_waitlist', ['classroomid' => $native]));
    }

    public function test_unenrol_also_refuses_a_learner_whose_attendance_is_imported_history(): void {
        global $DB;
        $this->seed_and_apply();
        $this->setAdminUser();
        $b = (int) $this->u['b']->id;

        // B is on CR1's roster with completion_status 0. Take the roster row's provenance away, as if the
        // import had skipped it and B was put on the roster since: only B's attendance (S1) is history now.
        $rosterid = (int) $DB->get_field('local_sentientia_classroom_users', 'id', ['classroomid' => 1, 'userid' => $b],
            MUST_EXIST);
        $DB->set_field_select(\local_sentientia_platform\bizlms\legacymap::TABLE, 'outcome', 'skipped',
            'targettable = :t AND targetid = :i', ['t' => 'local_sentientia_classroom_users', 'i' => $rosterid]);
        $this->assertFalse(session_manager::is_imported('local_sentientia_classroom_users', $rosterid));
        $attendanceid = (int) $DB->get_field('local_sentientia_classroom_attendance', 'id',
            ['sessionid' => 1, 'userid' => $b], MUST_EXIST);
        $this->assertTrue(session_manager::is_imported('local_sentientia_classroom_attendance', $attendanceid));

        try {
            session_manager::unenrol_user(1, $b);
            $this->fail('unenrol removed imported attendance');
        } catch (\moodle_exception $e) {
            $this->assertSame('error_protected_history', $e->errorcode);
        }
        $this->assertTrue($DB->record_exists('local_sentientia_classroom_users', ['id' => $rosterid]));
        $this->assertTrue($DB->record_exists('local_sentientia_classroom_attendance', ['id' => $attendanceid]));
    }

    // ─── Privacy ──────────────────────────────────────────────────────────────────────────────────────

    public function test_privacy_exports_the_imported_rows_and_anonymise_keeps_the_roster(): void {
        global $DB;
        $this->seed_and_apply();
        $a = $this->u['a'];
        $contextlist = new \core_privacy\local\request\approved_contextlist($a, 'local_sentientia_classroom',
            [\context_system::instance()->id]);

        privacy\provider::export_user_data($contextlist);
        $data = \core_privacy\local\request\writer::with_context(\context_system::instance())
            ->get_data(['sentientia_classroom']);
        $this->assertSame(2, $data->roster_count, 'CR1 and CR2');
        $this->assertGreaterThanOrEqual(1, $data->attendance_count);

        // The trainer is found and exported.
        $t1 = $this->u['t1'];
        $this->assertNotEmpty(privacy\provider::get_contexts_for_userid((int) $t1->id)->get_contextids());
        $trainerlist = new \core_privacy\local\request\approved_contextlist($t1, 'local_sentientia_classroom',
            [\context_system::instance()->id]);
        privacy\provider::export_user_data($trainerlist);
        $tdata = \core_privacy\local\request\writer::with_context(\context_system::instance())
            ->get_data(['sentientia_classroom']);
        $this->assertSame(1, $tdata->trainer_count);

        // DPDP anonymise: the completion on the roster row and the attendance stay.
        privacy\provider::anonymise_data_for_user($contextlist);
        $this->assertTrue($DB->record_exists('local_sentientia_classroom_users', ['classroomid' => 1, 'userid' => $a->id]));
        $this->assertSame(1, (int) $DB->get_field('local_sentientia_classroom_users', 'completion_status',
            ['classroomid' => 1, 'userid' => $a->id]));
        $this->assertTrue($DB->record_exists('local_sentientia_classroom_attendance', ['sessionid' => 1, 'userid' => $a->id]));

        // Full erasure of the trainer removes the trainer rows and the columns that name them; the classroom stays.
        privacy\provider::delete_data_for_user($trainerlist);
        $this->assertFalse($DB->record_exists('local_sentientia_classroom_trainers', ['trainerid' => $t1->id]));
        $this->assertNull($DB->get_field('local_sentientia_classroom', 'trainerid', ['id' => 1]));
        $this->assertNull($DB->get_field('local_sentientia_classroom_sessions', 'trainerid', ['id' => 1]));
        $this->assertTrue($DB->record_exists('local_sentientia_classroom', ['id' => 1]));
    }

    public function test_privacy_finds_exports_and_clears_the_actor_columns(): void {
        global $DB;
        $this->seed_and_apply();
        $actor = $this->getDataGenerator()->create_user();
        $bystander = $this->getDataGenerator()->create_user();
        $system = \context_system::instance();

        // The import (usercreated, usermodified) and every native enrolment and mark fill these two columns
        // with a person who is not the learner; one row of each names the actor here.
        $rosterid = (int) $DB->get_field_sql('SELECT MIN(id) FROM {local_sentientia_classroom_users}');
        $attid = (int) $DB->get_field_sql('SELECT MIN(id) FROM {local_sentientia_classroom_attendance}');
        $DB->set_field('local_sentientia_classroom_users', 'enrolledby', $actor->id, ['id' => $rosterid]);
        $DB->set_field('local_sentientia_classroom_attendance', 'markedby', $actor->id, ['id' => $attid]);

        // Found: by the context list and by the user list; a user named nowhere is not.
        $this->assertNotEmpty(privacy\provider::get_contexts_for_userid((int) $actor->id)->get_contextids());
        $this->assertEmpty(privacy\provider::get_contexts_for_userid((int) $bystander->id)->get_contextids());
        $userlist = new \core_privacy\local\request\userlist($system, 'local_sentientia_classroom');
        privacy\provider::get_users_in_context($userlist);
        $this->assertContains((int) $actor->id, array_map('intval', $userlist->get_userids()));
        $this->assertNotContains((int) $bystander->id, array_map('intval', $userlist->get_userids()));

        // Exported for the actor: the rows they touched, not the learners on them.
        $list = new \core_privacy\local\request\approved_contextlist($actor, 'local_sentientia_classroom',
            [$system->id]);
        privacy\provider::export_user_data($list);
        $data = \core_privacy\local\request\writer::with_context($system)->get_data(['sentientia_classroom']);
        $this->assertSame(1, $data->enrolled_count);
        $this->assertSame(1, $data->marked_count);
        $this->assertSame($rosterid, (int) $data->enrolled_by[0]->id);
        $this->assertSame($attid, (int) $data->marked_by[0]->id);
        $this->assertFalse(property_exists($data->enrolled_by[0], 'userid'), 'the learner is not exported to the actor');
        $this->assertFalse(property_exists($data->marked_by[0], 'userid'), 'the learner is not exported to the actor');
        $this->assertTrue(property_exists($data->marked_by[0], 'markedat'), 'the time is exported under its metadata name');
        $this->assertSame((int) $DB->get_field('local_sentientia_classroom_attendance', 'timemodified', ['id' => $attid]),
            (int) $data->marked_by[0]->markedat, 'markedat is the real column timemodified (the table has no markedat)');
        $this->assertSame(0, $data->roster_count, 'the actor is on no roster themselves');

        // DPDP anonymise keeps the columns: they point at the user row that is anonymised in place.
        privacy\provider::anonymise_data_for_user($list);
        $this->assertSame((int) $actor->id, (int) $DB->get_field('local_sentientia_classroom_users', 'enrolledby',
            ['id' => $rosterid]));
        $this->assertSame((int) $actor->id, (int) $DB->get_field('local_sentientia_classroom_attendance', 'markedby',
            ['id' => $attid]));

        // Full erasure clears the actor; the learner's rows stay.
        privacy\provider::delete_data_for_user($list);
        $this->assertNull($DB->get_field('local_sentientia_classroom_users', 'enrolledby', ['id' => $rosterid]));
        $this->assertNull($DB->get_field('local_sentientia_classroom_attendance', 'markedby', ['id' => $attid]));
        $this->assertTrue($DB->record_exists('local_sentientia_classroom_users', ['id' => $rosterid]));
        $this->assertTrue($DB->record_exists('local_sentientia_classroom_attendance', ['id' => $attid]));
        $this->assertEmpty(privacy\provider::get_contexts_for_userid((int) $actor->id)->get_contextids());

        // The bulk path (delete_data_for_users) clears them too.
        $DB->set_field('local_sentientia_classroom_users', 'enrolledby', $actor->id, ['id' => $rosterid]);
        $DB->set_field('local_sentientia_classroom_attendance', 'markedby', $actor->id, ['id' => $attid]);
        privacy\provider::delete_data_for_users(new \core_privacy\local\request\approved_userlist($system,
            'local_sentientia_classroom', [(int) $actor->id]));
        $this->assertNull($DB->get_field('local_sentientia_classroom_users', 'enrolledby', ['id' => $rosterid]));
        $this->assertNull($DB->get_field('local_sentientia_classroom_attendance', 'markedby', ['id' => $attid]));
    }

    // ─── Registry and the importer's declarations ──────────────────────────────────────────────────────

    public function test_the_registry_accepts_the_importer_with_org_registered(): void {
        $importer = $this->contract_begin();
        $loaded = registry::load();
        $this->assertArrayHasKey('classroom', $loaded);
        $this->assertSame(['org'], $importer->depends());
        $this->assertSame('local_sentientia_classroom', $importer->component());
        $this->assertTrue($importer->atomic());
        $this->assertSame([], $importer->core_writes());
        $this->assertSame(['local_sentientia_classroom' => 'open_path'], $importer->tenant_columns());
    }

    public function test_the_declared_decisions_accept_exactly_the_signed_values(): void {
        $decisions = self::signed_decisions();
        $importer = new classroom_importer();
        foreach ($importer->decisions() as $declared) {
            $this->assertTrue($decisions->has($declared->key), $declared->key . ' is in the signed file');
            if ($declared->allowed !== null) {
                $this->assertContains($decisions->get($declared->key), $declared->allowed, $declared->key);
            }
        }
    }
}
