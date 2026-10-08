<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

namespace local_sentientia_programs;

defined('MOODLE_INTERNAL') || die();

use local_sentientia_org\test\bizlms_fixture;

/**
 * An admin may unenrol an imported program enrolment that carries no history yet (owner decision
 * framework.protect_imported_history_pending_enrolments, 2026-10-07; LRN-10).
 *
 * The signed rule blocked every imported row, which would have stopped admins removing any of the learners the import
 * brought over as merely enrolled, an action BizLMS allowed. A learner who is in progress or completed, has a current
 * level, or holds a stored level completion stays protected, and so does every learner of a program that is not
 * active. The roster offers the trash action exactly where the manager would accept the removal.
 *
 * @package    local_sentientia_programs
 * @category   test
 * @copyright  2026 Airpay Payment Services
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \local_sentientia_programs\program_manager
 * @group local_sentientia_programs
 * @group bizlms_import
 */
final class imported_unenrol_test extends \advanced_testcase {
    use bizlms_fixture;

    /** A fixed time. */
    private const T0 = 1767225600;

    protected function setUp(): void {
        parent::setUp();
        $this->resetAfterTest();
        $this->setAdminUser();
    }

    private function program(int $status = program_manager::STATUS_ACTIVE): int {
        global $DB;
        return (int) $DB->insert_record('local_sentientia_programs', (object) [
            'name' => 'Program', 'costcenterid' => 1, 'status' => $status, 'visible' => 1,
            'completion_required' => 1, 'timecreated' => self::T0, 'timemodified' => self::T0,
        ]);
    }

    private function level(int $programid): int {
        global $DB;
        return (int) $DB->insert_record('local_sentientia_programs_levels', (object) [
            'programid' => $programid, 'name' => 'Level 1', 'sortorder' => 0,
            'completion_required' => 1, 'completion_rule' => 'all', 'timecreated' => self::T0,
        ]);
    }

    private function enrol(int $programid, \stdClass $user, int $status = 0, ?int $timecompleted = null, ?int $level = null): int {
        global $DB;
        return (int) $DB->insert_record('local_sentientia_programs_users', (object) [
            'programid' => $programid, 'userid' => $user->id, 'currentlevelid' => $level, 'status' => $status,
            'timecreated' => self::T0, 'timecompleted' => $timecompleted, 'enrolledby' => 0, 'timemodified' => self::T0,
        ]);
    }

    private function stored(int $programid, int $levelid, \stdClass $user): void {
        global $DB;
        $DB->insert_record('local_sentientia_programs_lvlcomp', (object) [
            'programid' => $programid, 'levelid' => $levelid, 'userid' => $user->id, 'status' => 1,
            'timecompleted' => self::T0 + 50, 'completedcourseids' => null, 'source' => 'bizlms',
            'timecreated' => self::T0, 'timemodified' => self::T0,
        ]);
    }

    private function imported(int $enrolmentid): void {
        global $DB;
        $DB->insert_record('local_sentientia_legacymap', (object) [
            'feature' => 'program', 'sourcetable' => 'local_program_users', 'sourceid' => $enrolmentid, 'subkey' => '',
            'targettable' => 'local_sentientia_programs_users', 'targetid' => $enrolmentid, 'outcome' => 'imported',
            'runid' => 0, 'timecreated' => self::T0,
        ]);
    }

    private function refused(int $programid, \stdClass $user, string $why): void {
        global $DB;
        try {
            program_manager::unenrol_user($programid, (int) $user->id);
            $this->fail($why . ': history was removed');
        } catch (\moodle_exception $e) {
            $this->assertSame('error_history_protected', $e->errorcode, $why);
        }
        $this->assertTrue($DB->record_exists('local_sentientia_programs_users',
            ['programid' => $programid, 'userid' => $user->id]), $why . ': the row is still there');
    }

    public function test_an_imported_enrolment_with_no_history_on_an_active_program_can_be_removed(): void {
        global $DB;
        $p = $this->program();
        $user = $this->getDataGenerator()->create_user();
        $id = $this->enrol($p, $user);
        $this->imported($id);

        $this->assertTrue(program_manager::unenrol_user($p, (int) $user->id));
        $this->assertFalse($DB->record_exists('local_sentientia_programs_users', ['id' => $id]));
        $this->assertTrue($DB->record_exists('local_sentientia_legacymap', ['targettable' => 'local_sentientia_programs_users',
            'targetid' => $id]), 'the import\'s map entry stays as the record of what was imported');
    }

    public function test_an_imported_enrolment_that_carries_history_stays_protected(): void {
        $p = $this->program();
        $l = $this->level($p);

        $completed = $this->getDataGenerator()->create_user();
        $this->imported($this->enrol($p, $completed, program_manager::ENROL_COMPLETED, self::T0 + 10));
        $this->refused($p, $completed, 'a completed learner');

        $inprogress = $this->getDataGenerator()->create_user();
        $this->imported($this->enrol($p, $inprogress, program_manager::ENROL_INPROGRESS));
        $this->refused($p, $inprogress, 'a learner in progress');

        $onlevel = $this->getDataGenerator()->create_user();
        $this->imported($this->enrol($p, $onlevel, program_manager::ENROL_NEW, null, $l));
        $this->refused($p, $onlevel, 'a learner with a current level');

        $stored = $this->getDataGenerator()->create_user();
        $this->imported($this->enrol($p, $stored));
        $this->stored($p, $l, $stored);
        $this->refused($p, $stored, 'a learner holding a stored level completion');

        $stamped = $this->getDataGenerator()->create_user();
        $this->imported($this->enrol($p, $stamped, program_manager::ENROL_NEW, self::T0 + 10));
        $this->refused($p, $stamped, 'a completion date on a row that says not started');
    }

    public function test_an_imported_enrolment_of_a_program_that_is_not_active_stays_protected(): void {
        foreach ([program_manager::STATUS_DRAFT, program_manager::STATUS_ARCHIVED] as $status) {
            $p = $this->program($status);
            $user = $this->getDataGenerator()->create_user();
            $this->imported($this->enrol($p, $user));
            $this->refused($p, $user, 'a pending learner of a program in status ' . $status);
        }
    }

    public function test_a_native_enrolment_is_removed_as_before_with_its_stored_completions(): void {
        global $DB;
        $p = $this->program();
        $l = $this->level($p);
        $user = $this->getDataGenerator()->create_user();
        $this->enrol($p, $user, program_manager::ENROL_COMPLETED, self::T0 + 10);
        $this->stored($p, $l, $user);

        $this->assertTrue(program_manager::unenrol_user($p, (int) $user->id));
        $this->assertSame(0, $DB->count_records('local_sentientia_programs_users'));
        $this->assertSame(0, $DB->count_records('local_sentientia_programs_lvlcomp'));
    }

    public function test_the_roster_offers_the_trash_action_exactly_where_the_manager_would_accept_the_removal(): void {
        $p = $this->program();
        $l = $this->level($p);

        $pending = $this->getDataGenerator()->create_user();
        $this->imported($this->enrol($p, $pending));
        $completed = $this->getDataGenerator()->create_user();
        $this->imported($this->enrol($p, $completed, program_manager::ENROL_COMPLETED, self::T0 + 10));
        $stored = $this->getDataGenerator()->create_user();
        $this->imported($this->enrol($p, $stored));
        $this->stored($p, $l, $stored);
        $native = $this->getDataGenerator()->create_user();
        $this->enrol($p, $native, program_manager::ENROL_INPROGRESS);

        $records = program_manager::get_enrolled_users($p);
        $protected = program_manager::protected_enrolment_ids($p, $records);
        $byuser = [];
        foreach ($records as $rec) {
            $byuser[(int) $rec->userid] = !empty($protected[(int) $rec->id]);
        }
        $this->assertEquals([
            (int) $pending->id => false,
            (int) $completed->id => true,
            (int) $stored->id => true,
            (int) $native->id => false,
        ], $byuser);
        $this->assertSame([], program_manager::protected_enrolment_ids($p, []));

        // The same decision on an archived program protects the pending row too.
        global $DB;
        $DB->set_field('local_sentientia_programs', 'status', program_manager::STATUS_ARCHIVED, ['id' => $p]);
        $protected = program_manager::protected_enrolment_ids($p, program_manager::get_enrolled_users($p));
        $this->assertCount(3, $protected, 'every imported row; the native one is still removable');
    }

    public function test_the_pure_pending_rule_and_the_batch_lookup(): void {
        $p = $this->program();
        $l = $this->level($p);
        $user = $this->getDataGenerator()->create_user();
        $other = $this->getDataGenerator()->create_user();
        $this->stored($p, $l, $user);

        $program = (object) ['status' => program_manager::STATUS_ACTIVE];
        $row = (object) ['programid' => $p, 'userid' => $other->id, 'status' => program_manager::ENROL_NEW,
            'currentlevelid' => null, 'timecompleted' => null];
        $this->assertTrue(program_manager::imported_enrolment_is_pending($program, $row));
        $this->assertTrue(program_manager::imported_enrolment_is_pending($program, $row, false), 'an explicit "none"');
        $this->assertFalse(program_manager::imported_enrolment_is_pending($program, $row, true), 'an explicit "has one"');
        $this->assertFalse(program_manager::imported_enrolment_is_pending((object) ['status' => program_manager::STATUS_DRAFT], $row));
        $mine = clone $row;
        $mine->userid = $user->id;
        $this->assertFalse(program_manager::imported_enrolment_is_pending($program, $mine), 'read from the stored completions');

        $this->assertSame([(int) $user->id => true],
            program_manager::users_with_stored_completion($p, [(int) $user->id, (int) $other->id, 0]));
        $this->assertSame([], program_manager::users_with_stored_completion($p, []));
    }
}
