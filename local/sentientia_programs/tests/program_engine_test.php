<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

namespace local_sentientia_programs;

defined('MOODLE_INTERNAL') || die();

use local_sentientia_org\test\bizlms_fixture;

/**
 * The engine and reader fixes the BizLMS import needs (ADR-032, mapping doc section 16, "Code fixes"), on data
 * the import did not create:
 *
 *  2  a level can complete on any ONE mandatory course (rule 'any')
 *  3  a level with no course is not completed and gates nothing
 *  4  a program with completion_required = 0 completes on any required level (the observer)
 *  5  the observer stores the completion, skips learners with no enrolment and never downgrades
 *  6  the learner state respects a stored level completion and a completed enrolment
 *  7  roster reads leave out deleted users
 *  10 history the import carried is not deleted from the admin actions
 *  11 the learner page lists only what a learner may see
 *
 * @package    local_sentientia_programs
 * @category   test
 * @copyright  2026 Airpay Payment Services
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \local_sentientia_programs\program_manager
 * @covers     \local_sentientia_programs\observer
 * @covers     \local_sentientia_programs\learner_view
 *
 * @group local_sentientia_programs
 * @group bizlms_import
 */
final class program_engine_test extends \advanced_testcase {
    use bizlms_fixture;

    /** A fixed time. */
    private const T0 = 1767225600;

    protected function setUp(): void {
        parent::setUp();
        $this->resetAfterTest();
        // The observer dedupes for 24 hours on (user, program); ids repeat from test to test.
        \cache::make_from_params(\cache_store::MODE_APPLICATION, 'local_sentientia_programs',
            'program_complete_dedupe')->purge();
    }

    // Builders.

    /**
     * @param array $o Overrides.
     * @return int
     */
    private function program(array $o = []): int {
        global $DB;
        return (int) $DB->insert_record('local_sentientia_programs', (object) ($o + [
            'name' => 'Program', 'costcenterid' => 1, 'status' => program_manager::STATUS_ACTIVE, 'visible' => 1,
            'completion_required' => 1, 'timecreated' => self::T0, 'timemodified' => self::T0,
        ]));
    }

    /**
     * @param int $programid
     * @param int $sortorder
     * @param array $o Overrides.
     * @return int
     */
    private function level(int $programid, int $sortorder, array $o = []): int {
        global $DB;
        return (int) $DB->insert_record('local_sentientia_programs_levels', (object) ($o + [
            'programid' => $programid, 'name' => 'Level ' . $sortorder, 'sortorder' => $sortorder,
            'completion_required' => 1, 'completion_rule' => 'all', 'timecreated' => self::T0,
        ]));
    }

    /**
     * A new course on a level.
     *
     * @param int $levelid
     * @param int $mandatory
     * @return int The course id.
     */
    private function course_on(int $levelid, int $mandatory = 1): int {
        global $DB;
        $course = $this->getDataGenerator()->create_course();
        $DB->insert_record('local_sentientia_programs_courses', (object) [
            'levelid' => $levelid, 'courseid' => $course->id,
            'sortorder' => $DB->count_records('local_sentientia_programs_courses', ['levelid' => $levelid]),
            'mandatory' => $mandatory, 'timecreated' => self::T0,
        ]);
        return (int) $course->id;
    }

    /**
     * @param int $userid
     * @param int $courseid
     * @param int $time
     * @return void
     */
    private function done(int $userid, int $courseid, int $time = self::T0 + 100): void {
        global $DB;
        $DB->insert_record('course_completions', (object) [
            'userid' => $userid, 'course' => $courseid, 'timeenrolled' => self::T0, 'timestarted' => self::T0,
            'timecompleted' => $time, 'reaggregate' => 0,
        ]);
    }

    /**
     * @param int $programid
     * @param int $userid
     * @param int $status
     * @param int|null $timecompleted
     * @return int The enrolment id.
     */
    private function enrol(int $programid, int $userid, int $status = 0, ?int $timecompleted = null): int {
        global $DB;
        return (int) $DB->insert_record('local_sentientia_programs_users', (object) [
            'programid' => $programid, 'userid' => $userid, 'currentlevelid' => null, 'status' => $status,
            'timecreated' => self::T0, 'timecompleted' => $timecompleted, 'enrolledby' => 0, 'timemodified' => self::T0,
        ]);
    }

    /**
     * A stored level completion, as the import writes it.
     *
     * @param int $programid
     * @param int $levelid
     * @param int $userid
     * @param int|null $timecompleted
     * @return int
     */
    private function stored(int $programid, int $levelid, int $userid, ?int $timecompleted = self::T0 + 50): int {
        global $DB;
        return (int) $DB->insert_record('local_sentientia_programs_lvlcomp', (object) [
            'programid' => $programid, 'levelid' => $levelid, 'userid' => $userid, 'status' => 1,
            'timecompleted' => $timecompleted, 'completedcourseids' => null, 'source' => 'bizlms',
            'timecreated' => self::T0, 'timemodified' => self::T0,
        ]);
    }

    /**
     * Mark a row as created by the import.
     *
     * @param string $targettable
     * @param int $targetid
     * @return void
     */
    private function mark_imported(string $targettable, int $targetid): void {
        global $DB;
        $DB->insert_record('local_sentientia_legacymap', (object) [
            'feature' => 'program', 'sourcetable' => 'local_program_users', 'sourceid' => $targetid, 'subkey' => '',
            'targettable' => $targettable, 'targetid' => $targetid, 'outcome' => 'imported', 'runid' => 0,
            'timecreated' => self::T0,
        ]);
    }

    /**
     * Run the observer for a course completion and return the program_completed events it fired.
     *
     * @param int $userid
     * @param int $courseid
     * @return \core\event\base[]
     */
    private function fire(int $userid, int $courseid): array {
        $sink = $this->redirectEvents();
        observer::course_completed(\core\event\course_completed::create([
            'objectid' => 1, 'relateduserid' => $userid, 'courseid' => $courseid,
            'context' => \context_course::instance($courseid), 'other' => ['relateduserid' => $userid],
        ]));
        $events = array_values(array_filter($sink->get_events(),
            static fn($event) => $event instanceof event\program_completed));
        $sink->close();
        return $events;
    }

    // Level completion: the rule, empty levels, stored history.

    public function test_a_level_with_no_courses_is_not_completed_and_holds_nothing_back(): void {
        $user = $this->getDataGenerator()->create_user();
        $p = $this->program();
        $l0 = $this->level($p, 0);
        $l1 = $this->level($p, 1);
        $l2 = $this->level($p, 2);
        $c0a = $this->course_on($l0);
        $c0b = $this->course_on($l0);
        $this->course_on($l2);

        $this->assertFalse(program_manager::is_level_completed_by_user($l1, (int) $user->id),
            'before, "no mandatory courses" counted as completed and inflated every count');
        $this->assertFalse(program_manager::is_level_unlocked_for_user($l2, (int) $user->id), 'level 0 is still open');

        $this->done((int) $user->id, $c0a);
        $this->done((int) $user->id, $c0b);
        $this->assertTrue(program_manager::is_level_completed_by_user($l0, (int) $user->id));
        $this->assertFalse(program_manager::is_level_completed_by_user($l1, (int) $user->id));
        $this->assertTrue(program_manager::is_level_unlocked_for_user($l2, (int) $user->id),
            'an empty required level is out of the required set: it gates nothing');

        $state = program_manager::get_user_program_state($p, (int) $user->id);
        $this->assertSame(2, $state['total_levels'], 'the empty level is not counted');
        $this->assertSame(1, $state['completed_levels']);
        $this->assertSame(50, $state['overall_pct']);
        $this->assertTrue($state['levels'][1]['empty']);
        $this->assertFalse($state['levels'][1]['completed']);
        $this->assertSame($l2, $state['current_level_id'], 'the next level with something to do');
    }

    public function test_a_level_whose_courses_are_all_optional_asks_for_nothing(): void {
        $user = $this->getDataGenerator()->create_user();
        $l = $this->level($this->program(), 0);
        $this->course_on($l, 0);
        $this->assertTrue(program_manager::is_level_completed_by_user($l, (int) $user->id),
            'it has courses, none required: the previous behaviour stands');
    }

    public function test_the_any_rule_completes_a_level_on_one_mandatory_course_the_all_rule_needs_every_one(): void {
        $user = $this->getDataGenerator()->create_user();
        $p = $this->program();
        $any = $this->level($p, 0, ['completion_rule' => 'any']);
        $all = $this->level($p, 1, ['completion_rule' => 'all']);
        $anycourses = [$this->course_on($any), $this->course_on($any), $this->course_on($any)];
        $allcourses = [$this->course_on($all), $this->course_on($all)];

        $this->assertFalse(program_manager::is_level_completed_by_user($any, (int) $user->id));
        $this->done((int) $user->id, $anycourses[1]);
        $this->done((int) $user->id, $allcourses[0]);
        $this->assertTrue(program_manager::is_level_completed_by_user($any, (int) $user->id), 'one of three is enough');
        $this->assertFalse(program_manager::is_level_completed_by_user($all, (int) $user->id), 'one of two is not');
        $this->done((int) $user->id, $allcourses[1]);
        $this->assertTrue(program_manager::is_level_completed_by_user($all, (int) $user->id));

        $state = program_manager::get_user_program_state($p, (int) $user->id);
        $this->assertTrue($state['levels'][0]['rule_any']);
        $this->assertSame(1, $state['levels'][0]['mandatory_needed']);
        $this->assertSame(100, $state['levels'][0]['pct']);
        $this->assertSame(2, $state['levels'][1]['mandatory_needed']);
    }

    public function test_a_stored_completion_counts_and_opens_the_next_level(): void {
        $u = $this->getDataGenerator()->create_user();
        $other = $this->getDataGenerator()->create_user();
        $p = $this->program();
        $l0 = $this->level($p, 0);
        $l1 = $this->level($p, 1);
        $this->course_on($l0);
        $this->course_on($l1);
        $this->stored($p, $l0, (int) $u->id, self::T0 + 50);

        $this->assertTrue(program_manager::is_level_completed_by_user($l0, (int) $u->id), 'no course completion on record');
        $this->assertTrue(program_manager::is_level_unlocked_for_user($l1, (int) $u->id));
        $state = program_manager::get_user_program_state($p, (int) $u->id);
        $this->assertTrue($state['levels'][0]['completed']);
        $this->assertTrue($state['levels'][0]['stored']);
        $this->assertSame(self::T0 + 50, $state['levels'][0]['timecompleted']);
        $this->assertSame(100, $state['levels'][0]['pct']);
        $this->assertFalse($state['levels'][1]['locked']);
        $this->assertSame(1, $state['completed_levels']);

        // Somebody else's stored completion is nobody else's.
        $this->assertFalse(program_manager::is_level_completed_by_user($l0, (int) $other->id));
        $this->assertFalse(program_manager::is_level_unlocked_for_user($l1, (int) $other->id));
        $this->assertTrue(program_manager::get_user_program_state($p, (int) $other->id)['levels'][1]['locked']);
    }

    public function test_a_completed_enrolment_makes_the_program_complete_and_opens_every_level(): void {
        $u = $this->getDataGenerator()->create_user();
        $p = $this->program();
        $l0 = $this->level($p, 0);
        $l1 = $this->level($p, 1);
        $this->course_on($l0);
        $this->course_on($l1);
        $this->enrol($p, (int) $u->id, program_manager::ENROL_COMPLETED, self::T0 + 77);

        $state = program_manager::get_user_program_state($p, (int) $u->id);
        $this->assertTrue($state['completed']);
        $this->assertSame(self::T0 + 77, $state['timecompleted']);
        $this->assertSame(100, $state['overall_pct'], 'whatever the live course data shows');
        $this->assertNull($state['current_level_id']);
        $this->assertFalse($state['levels'][1]['locked']);
        $this->assertTrue(program_manager::is_level_unlocked_for_user($l1, (int) $u->id));

        $notdone = $this->getDataGenerator()->create_user();
        $this->enrol($p, (int) $notdone->id);
        $this->assertFalse(program_manager::get_user_program_state($p, (int) $notdone->id)['completed']);
    }

    // The observer.

    public function test_a_learner_with_no_enrolment_is_skipped(): void {
        global $DB;
        $u = $this->getDataGenerator()->create_user();
        $p = $this->program();
        $c = $this->course_on($this->level($p, 0));
        $this->done((int) $u->id, $c);

        $this->assertSame([], $this->fire((int) $u->id, $c), 'before, the event fired for anyone');
        $this->assertSame(0, $DB->count_records('local_sentientia_programs_users'), 'no enrolment is created');
    }

    public function test_the_last_required_course_completes_the_enrolment_and_stores_it(): void {
        global $DB;
        $u = $this->getDataGenerator()->create_user();
        $p = $this->program();
        $l0 = $this->level($p, 0);
        $l1 = $this->level($p, 1);
        $c0 = $this->course_on($l0);
        $c1 = $this->course_on($l1);
        $enrolment = $this->enrol($p, (int) $u->id);

        $this->done((int) $u->id, $c0);
        $this->assertSame([], $this->fire((int) $u->id, $c0), 'one of two levels');
        $this->assertEquals(0, $DB->get_field('local_sentientia_programs_users', 'status', ['id' => $enrolment]));

        $before = time();
        $this->done((int) $u->id, $c1);
        $events = $this->fire((int) $u->id, $c1);
        $this->assertCount(1, $events);
        $this->assertEquals($p, $events[0]->objectid);
        $this->assertEquals($u->id, $events[0]->relateduserid);
        $row = $DB->get_record('local_sentientia_programs_users', ['id' => $enrolment], '*', MUST_EXIST);
        $this->assertEquals(2, $row->status, 'the completion is stored: before, only the event fired');
        $this->assertGreaterThanOrEqual($before, (int) $row->timecompleted);
        $this->assertEquals($l1, $row->currentlevelid, 'the last level');
        $this->assertGreaterThanOrEqual($before, (int) $row->timemodified);
    }

    public function test_a_completed_enrolment_is_never_downgraded_or_announced_twice(): void {
        global $DB;
        $u = $this->getDataGenerator()->create_user();
        $p = $this->program();
        $c = $this->course_on($this->level($p, 0));
        $enrolment = $this->enrol($p, (int) $u->id, program_manager::ENROL_COMPLETED, self::T0 + 1);
        $this->done((int) $u->id, $c);

        $this->assertSame([], $this->fire((int) $u->id, $c));
        $row = $DB->get_record('local_sentientia_programs_users', ['id' => $enrolment], '*', MUST_EXIST);
        $this->assertEquals(2, $row->status);
        $this->assertEquals(self::T0 + 1, $row->timecompleted, 'the original completion date stands');
    }

    public function test_completion_required_zero_completes_the_program_on_any_required_level(): void {
        global $DB;
        $anyone = $this->getDataGenerator()->create_user();
        $everyone = $this->getDataGenerator()->create_user();

        $or = $this->program(['completion_required' => 0]);
        $or0 = $this->course_on($this->level($or, 0));
        $this->course_on($this->level($or, 1));
        $enrolor = $this->enrol($or, (int) $anyone->id);

        $and = $this->program(['completion_required' => 1]);
        $and0 = $this->course_on($this->level($and, 0));
        $this->course_on($this->level($and, 1));
        $enroland = $this->enrol($and, (int) $everyone->id);

        $this->done((int) $anyone->id, $or0);
        $this->done((int) $everyone->id, $and0);
        $this->assertCount(1, $this->fire((int) $anyone->id, $or0), 'any level completes an OR program');
        $this->assertSame([], $this->fire((int) $everyone->id, $and0), 'an AND program needs every level');
        $this->assertEquals(2, $DB->get_field('local_sentientia_programs_users', 'status', ['id' => $enrolor]));
        $this->assertEquals(0, $DB->get_field('local_sentientia_programs_users', 'status', ['id' => $enroland]));
    }

    public function test_an_empty_required_level_is_not_part_of_the_required_set(): void {
        global $DB;
        $u = $this->getDataGenerator()->create_user();
        $p = $this->program();
        $c = $this->course_on($this->level($p, 0));
        $this->level($p, 1);
        $this->level($p, 2);
        $enrolment = $this->enrol($p, (int) $u->id);
        $this->done((int) $u->id, $c);

        $this->assertCount(1, $this->fire((int) $u->id, $c), 'BizLMS auto-creates empty levels; they must not block or count');
        $this->assertEquals(2, $DB->get_field('local_sentientia_programs_users', 'status', ['id' => $enrolment]));
    }

    public function test_a_program_with_nothing_required_never_completes(): void {
        $u = $this->getDataGenerator()->create_user();
        $p = $this->program();
        $c = $this->course_on($this->level($p, 0, ['completion_required' => 0]));
        $this->enrol($p, (int) $u->id);
        $this->done((int) $u->id, $c);
        $this->assertSame([], $this->fire((int) $u->id, $c));
    }

    // Rosters.

    public function test_roster_reads_leave_out_deleted_users(): void {
        global $DB;
        $p = $this->program();
        $live = $this->getDataGenerator()->create_user(['firstname' => 'Live']);
        $gone = $this->getDataGenerator()->create_user(['firstname' => 'Gone']);
        $this->enrol($p, (int) $live->id);
        $this->enrol($p, (int) $gone->id);
        $DB->set_field('user', 'deleted', 1, ['id' => $gone->id]);

        $this->assertSame(1, program_manager::count_enrolled($p));
        $this->assertSame(1, program_manager::count_enrolled_filtered($p));
        $this->assertSame(0, program_manager::count_enrolled_filtered($p, 'Gone'));
        $users = program_manager::get_enrolled_users($p);
        $this->assertSame([(int) $live->id], array_map(static fn($r) => (int) $r->userid, array_values($users)));
    }

    public function test_enrolling_records_who_did_it_and_when(): void {
        global $DB;
        $u = $this->getDataGenerator()->create_user();
        $p = $this->program();
        $this->setAdminUser();
        $before = time();
        $this->assertSame(1, program_manager::enrol_users($p, [(int) $u->id]));
        $row = $DB->get_record('local_sentientia_programs_users', ['programid' => $p, 'userid' => $u->id], '*', MUST_EXIST);
        $this->assertEquals(get_admin()->id, $row->enrolledby);
        $this->assertGreaterThanOrEqual($before, (int) $row->timemodified);

        $this->setUser(0);
        $v = $this->getDataGenerator()->create_user();
        program_manager::enrol_users($p, [(int) $v->id]);
        $this->assertEquals(0, $DB->get_field('local_sentientia_programs_users', 'enrolledby',
            ['programid' => $p, 'userid' => $v->id]), 'no acting user: 0');
    }

    // Protected history.

    public function test_a_native_enrolment_is_removed_with_its_stored_completions(): void {
        global $DB;
        $u = $this->getDataGenerator()->create_user();
        $p = $this->program();
        $l = $this->level($p, 0);
        $this->enrol($p, (int) $u->id);
        $this->stored($p, $l, (int) $u->id);

        $this->assertTrue(program_manager::unenrol_user($p, (int) $u->id));
        $this->assertSame(0, $DB->count_records('local_sentientia_programs_users'));
        $this->assertSame(0, $DB->count_records('local_sentientia_programs_lvlcomp'), 'none is left without an enrolment');
    }

    public function test_an_enrolment_the_import_created_is_history_and_is_not_removed(): void {
        global $DB;
        $u = $this->getDataGenerator()->create_user();
        $v = $this->getDataGenerator()->create_user();
        $p = $this->program();
        $imported = $this->enrol($p, (int) $u->id, program_manager::ENROL_COMPLETED, self::T0);
        $native = $this->enrol($p, (int) $v->id);
        $this->mark_imported('local_sentientia_programs_users', $imported);

        $this->assertTrue(program_manager::is_imported_row('local_sentientia_programs_users', $imported));
        $this->assertFalse(program_manager::is_imported_row('local_sentientia_programs_users', $native));
        $this->assertSame([$imported => true], program_manager::imported_enrolment_ids([$imported, $native, 0]));

        try {
            program_manager::unenrol_user($p, (int) $u->id);
            $this->fail('an imported enrolment must not be deleted');
        } catch (\moodle_exception $e) {
            $this->assertSame('error_history_protected', $e->errorcode);
        }
        $this->assertTrue($DB->record_exists('local_sentientia_programs_users', ['id' => $imported]));
        $this->assertTrue(program_manager::unenrol_user($p, (int) $v->id), 'the native one goes as before');
        $this->assertTrue(program_manager::program_has_imported_history($p));
        try {
            program_manager::delete($p);
            $this->fail('archive instead');
        } catch (\moodle_exception $e) {
            $this->assertSame('error_history_protected', $e->errorcode);
        }
        $this->assertSame(program_manager::STATUS_ARCHIVED, program_manager::change_status($p, program_manager::STATUS_ARCHIVED));
    }

    public function test_deleting_a_program_takes_the_adr032_tables_with_it(): void {
        global $DB;
        $trainer = $this->getDataGenerator()->create_user();
        $p = $this->program();
        $l = $this->level($p, 0);
        $this->course_on($l);
        $row = $DB->insert_record('local_sentientia_programs_trainers', (object) [
            'programid' => $p, 'userid' => $trainer->id, 'feedbackid' => 0, 'feedback_score' => '0', 'assignedby' => 0,
            'timecreated' => self::T0, 'timemodified' => self::T0,
        ]);
        $DB->insert_record('local_sentientia_programs_trainerfb', (object) [
            'programtrainerid' => $row, 'programid' => $p, 'trainerid' => $trainer->id, 'userid' => null, 'score' => '5',
            'timecreated' => self::T0, 'timemodified' => self::T0,
        ]);
        $this->assertFalse(program_manager::program_has_imported_history($p), 'trainers alone are not completion history');

        $this->assertTrue(program_manager::delete($p));
        foreach (['local_sentientia_programs', 'local_sentientia_programs_levels', 'local_sentientia_programs_courses',
                'local_sentientia_programs_trainers', 'local_sentientia_programs_trainerfb'] as $table) {
            $this->assertSame(0, $DB->count_records($table), $table);
        }
    }

    public function test_a_level_with_a_stored_completion_is_not_deleted_one_without_is(): void {
        global $DB;
        $u = $this->getDataGenerator()->create_user();
        $p = $this->program();
        $done = $this->level($p, 0);
        $free = $this->level($p, 1);
        $this->stored($p, $done, (int) $u->id);

        try {
            program_manager::delete_level($done);
            $this->fail('the completion would go with the level');
        } catch (\moodle_exception $e) {
            $this->assertSame('error_history_protected', $e->errorcode);
        }
        $this->assertTrue(program_manager::delete_level($free));
        $this->assertSame(1, $DB->count_records('local_sentientia_programs_levels'));
        $this->assertSame(1, $DB->count_records('local_sentientia_programs_lvlcomp'));
    }

    // The learner page and the flags.

    public function test_both_flags_are_registered_and_default_off(): void {
        \local_sentientia_platform\feature_flags::invalidate_caches();
        $registry = \local_sentientia_platform\feature_flags::load_registry();
        foreach ([learner_view::FLAG_LEARNER, learner_view::FLAG_HISTORY] as $key) {
            $this->assertArrayHasKey($key, $registry);
            $this->assertFalse((bool) $registry[$key]['default'], $key);
            $this->assertFalse(\local_sentientia_platform\feature_flags::is_enabled($key), $key);
        }
        $this->assertFalse(learner_view::enabled());
    }

    public function test_the_learner_page_lists_only_what_a_learner_may_see(): void {
        $this->ensure_bizlms_schema();
        $u = $this->getDataGenerator()->create_user(['open_path' => '/1']);
        $active = $this->program(['name' => 'Active', 'open_path' => '/1']);
        $archived = $this->program(['name' => 'Archived', 'open_path' => '/1', 'status' => program_manager::STATUS_ARCHIVED]);
        $hidden = $this->program(['name' => 'Hidden', 'open_path' => '/1', 'visible' => 0]);
        $draft = $this->program(['name' => 'Draft', 'open_path' => '/1', 'status' => program_manager::STATUS_DRAFT]);
        $elsewhere = $this->program(['name' => 'Other tenant', 'open_path' => '/77']);
        $pathless = $this->program(['name' => 'No tenant', 'open_path' => null]);
        $notenrolled = $this->program(['name' => 'Not mine', 'open_path' => '/1']);
        foreach ([$active, $archived, $hidden, $draft, $elsewhere, $pathless] as $programid) {
            $this->enrol($programid, (int) $u->id);
        }
        $this->setUser(\core_user::get_user($u->id));

        $seen = learner_view::programs_for_user((int) $u->id);
        $this->assertSame([$active], array_map('intval', array_keys($seen)),
            'archived and hidden programs are switched off, drafts are not open, other tenants and pathless programs are not theirs');
        $this->assertArrayNotHasKey($notenrolled, $seen);

        $data = learner_view::page_data((int) $u->id);
        $this->assertTrue($data['has_programs']);
        $this->assertSame('Active', $data['programs'][0]['name']);
        $this->setUser(\core_user::get_user(get_admin()->id));
        $this->assertSame(0, count(learner_view::programs_for_user((int) get_admin()->id)), 'the admin is enrolled nowhere');
    }

    public function test_decorating_the_state_drops_empty_levels_and_formats_dates(): void {
        $u = $this->getDataGenerator()->create_user();
        $p = $this->program();
        $l0 = $this->level($p, 0);
        $this->level($p, 1);
        $this->course_on($l0);
        $this->stored($p, $l0, (int) $u->id, self::T0 + 50);
        $state = learner_view::decorate_state(program_manager::get_user_program_state($p, (int) $u->id));
        $this->assertCount(1, $state['levels'], 'the empty level is dropped from the learner view');
        $this->assertNotSame('', $state['levels'][0]['timecompleted_human']);
        $this->assertFalse($state['levels'][0]['has_counter'], 'a stored completion shows its date, not a counter');
    }

    public function test_decorating_the_state_without_the_history_flag_is_what_the_page_showed_before(): void {
        $u = $this->getDataGenerator()->create_user();
        $p = $this->program();
        $l0 = $this->level($p, 0);
        $this->level($p, 1);
        $this->course_on($l0);
        $this->stored($p, $l0, (int) $u->id, self::T0 + 50);
        $state = learner_view::decorate_state(program_manager::get_user_program_state($p, (int) $u->id), false);
        $this->assertCount(2, $state['levels'], 'the empty level stays in the list while the history flag is OFF');
        $this->assertTrue($state['levels'][0]['completed'], 'the engine result is untouched');
        $this->assertFalse($state['levels'][0]['stored'], 'the stored-completion wording needs the flag');
        $this->assertSame('', $state['levels'][0]['timecompleted_human'], 'and so does its date');
        $this->assertTrue($state['levels'][0]['has_counter'], 'the live course counter is what shows');
    }

    public function test_the_pluginfile_callback_refuses_what_it_should(): void {
        global $CFG;
        require_once($CFG->dirroot . '/local/sentientia_programs/lib.php');
        $this->setAdminUser();
        $system = \context_system::instance();
        $p = $this->program();
        $this->assertFalse(local_sentientia_programs_pluginfile(null, null, $system, 'other', [$p, 'logo.png'], false),
            'another file area');
        $this->assertFalse(local_sentientia_programs_pluginfile(null, null, \context_course::instance(SITEID), 'programlogo',
            [$p, 'logo.png'], false), 'another context');
        $this->assertFalse(local_sentientia_programs_pluginfile(null, null, $system, 'programlogo', [0, 'logo.png'], false),
            'no program id');
        $this->assertFalse(local_sentientia_programs_pluginfile(null, null, $system, 'programlogo', [$p, 'logo.png'], false),
            'both flags are OFF: not even an admin');
    }
}
