<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

namespace local_sentientia_programs;

defined('MOODLE_INTERNAL') || die();

use local_sentientia_org\test\bizlms_fixture;
use local_sentientia_platform\bizlms\context;
use local_sentientia_platform\bizlms\copies_files;
use local_sentientia_platform\bizlms\decisions;
use local_sentientia_platform\bizlms\idpolicy;
use local_sentientia_platform\bizlms\importer;
use local_sentientia_platform\bizlms\legacymap;
use local_sentientia_platform\bizlms\registry;
use local_sentientia_platform\bizlms\sideeffect_guard;
use local_sentientia_platform\phpunit\importer_contract;
use local_sentientia_platform\phpunit\legacy_schema_fixture;
use local_sentientia_programs\bizlms\importer as program_importer;
use local_sentientia_programs\tests\bizlms\org_stub_importer;

/**
 * The program importer (ADR-032, mapping doc section 16): the importer contract plus the feature's own world.
 *
 * The world it imports (legacy ids are deliberately unrelated to the target ids of the MAP tables):
 *
 *  programs      41 P1  '  Payments Certification  ', /1/5, seven-plus levels, criteria AND '101,103,104'
 *                42 P2  ' 77/ ' (normalised), visible 0            -> Archived
 *                43 P3  no path, creator in /77, status 2          -> Archived, tenant from the creator
 *                44 P4  no name at all                             -> skipped no_name (needs the owner)
 *                45 P5  path '0', creator without a tenant         -> imported with no path, OR criteria
 *  levels        101 Foundation (ALL)   102 empty (front page only)   103 Advanced (OR c2,c3; c4 optional)
 *                104-107 empty          108 Optional (not in the list)   110 (of P2)   190 (program 88 is gone)
 *  enrolments    uA completed (date)  uB completed, no date  uC in progress  uD not started  uE twice
 *                uF deleted user  uD2 progress only through a course completion  missing user  missing program
 *                uA also on P2 (archived): stored as not completed, but c1 (done for P1) is P2's only level course
 *  completions   uA 101 twice (dup) and 103  uB 101 and 103  uC 101 not completed  uG without an enrolment
 *                a level that is gone, a program that is gone
 *  trainers      one trainer, three feedback rows (one orphan, one with a giver that no longer exists)
 *  backup tables one row each in completions_bk, level_comp_bk and test_score (archived, needs the owner)
 *
 * @package    local_sentientia_programs
 * @category   test
 * @covers     \local_sentientia_programs\bizlms\importer
 * @covers     \local_sentientia_programs\bizlms\program_step
 * @covers     \local_sentientia_programs\bizlms\level_step
 * @covers     \local_sentientia_programs\bizlms\level_course_step
 * @covers     \local_sentientia_programs\bizlms\user_step
 * @covers     \local_sentientia_programs\bizlms\level_completion_step
 * @covers     \local_sentientia_programs\bizlms\criteria_step
 * @covers     \local_sentientia_programs\bizlms\trainer_step
 * @covers     \local_sentientia_programs\bizlms\archive_step
 * @covers     \local_sentientia_programs\bizlms\current_level_recompute
 * @covers     \local_sentientia_programs\bizlms\legacy_data
 *
 * @group local_sentientia_programs
 * @group bizlms_import
 * @group tenant_isolation
 */
final class bizlms_import_test extends \advanced_testcase {
    use legacy_schema_fixture;
    use importer_contract;
    use bizlms_fixture;

    /** Timestamp base of the seed. */
    private const T0 = 1700000000;

    /** Legacy id of the first program: the one the collision and adoption contract tests use. */
    private const P1 = 41;

    /** @var array<string, int> Named ids of the seeded world. */
    private array $w = [];

    protected static function legacy_fixture_definition(): array {
        return ['xml' => __DIR__ . '/fixtures/bizlms/program.install.xml'];
    }

    protected function contract_importer(): importer {
        return new program_importer();
    }

    /**
     * The org feature is a separate deliverable; its stand-in lets the registry accept a feature that depends on it.
     *
     * @return importer
     */
    protected function contract_begin(): importer {
        $this->resetAfterTest();
        $importer = $this->contract_importer();
        registry::set_testing_importers([new org_stub_importer(), $importer]);
        return $importer;
    }

    protected function contract_decisions(): decisions {
        return decisions::from_array([
            'program.completed_without_date' => 'completed_flagged',
            'program.inactive' => 'archived',
            'program.empty_levels' => 'skip',
            'program.deleted_users' => 'import',
            'program.pathless' => 'creator_root',
            'program.bk_tables' => 'archive_only',
            'tenant.unresolved.program' => 'pathless',
        ]);
    }

    protected function contract_user_columns(): array {
        return [
            'local_sentientia_programs_users' => ['userid', 'enrolledby'],
            'local_sentientia_programs_lvlcomp' => ['userid'],
            'local_sentientia_programs_trainers' => ['userid', 'assignedby'],
            'local_sentientia_programs_trainerfb' => ['trainerid', 'userid'],
        ];
    }

    protected function contract_collision(): ?array {
        return ['table' => 'local_sentientia_programs', 'row' => (object) [
            'id' => self::P1, 'name' => 'A native program that happens to sit on the legacy id',
            'description' => '', 'descriptionformat' => 1, 'costcenterid' => 0, 'status' => 1, 'visible' => 1,
            'completion_required' => 1, 'timecreated' => self::T0 + 999, 'timemodified' => self::T0 + 999,
        ]];
    }

    protected function contract_adoptable(): ?array {
        // What the retired migrate_all.php wrote: the header at the same id, same name, same creation time.
        return ['table' => 'local_sentientia_programs', 'sourcetable' => 'local_program', 'row' => (object) [
            'id' => self::P1, 'name' => '  Payments Certification  ',
            'description' => '', 'descriptionformat' => 1, 'costcenterid' => 0, 'status' => 1, 'visible' => 1,
            'completion_required' => 1, 'timecreated' => self::T0 + 1, 'timemodified' => self::T0 + 1,
        ]];
    }

    protected function contract_mutate_source(): void {
        // A new row changes the count and the max id on every engine, so detection does not depend on the CRC.
        $this->add_program(99, ['name' => 'Late program']);
    }

    // The world.

    /**
     * @param string $table
     * @param int $id
     * @param array $fields
     * @return void
     */
    private function legacy(string $table, int $id, array $fields): void {
        global $DB;
        $DB->import_record($table, (object) (['id' => $id] + $fields));
    }

    /**
     * @param int $id
     * @param array $o Overrides.
     * @return void
     */
    private function add_program(int $id, array $o): void {
        $this->legacy('local_program', $id, $o + [
            'name' => 'Program ' . $id, 'shortname' => 'prg' . $id, 'description' => '', 'visible' => 1, 'status' => 0,
            'startdate' => 0, 'enddate' => 0, 'nomination_startdate' => 0, 'nomination_enddate' => 0,
            'open_path' => '/1/5', 'open_categoryid' => 0, 'open_states' => '', 'open_district' => '',
            'open_subdistrict' => '', 'open_village' => '', 'timecreated' => self::T0, 'timemodified' => 0,
            'usercreated' => $this->w['admin'],
        ]);
    }

    /**
     * @param int $id
     * @param int $programid
     * @param string $name
     * @param int $position BizLMS' own position: never used for the order.
     * @return void
     */
    private function add_level(int $id, int $programid, string $name, int $position = 0): void {
        $this->legacy('local_program_levels', $id, [
            'programid' => $programid, 'level' => $name, 'description' => null, 'position' => $position,
            'timecreated' => self::T0 + 2, 'usercreated' => $this->w['admin'],
        ]);
    }

    /**
     * @param int $id
     * @param int $programid
     * @param int $levelid
     * @param int $courseid
     * @return void
     */
    private function add_level_course(int $id, int $programid, int $levelid, int $courseid): void {
        $this->legacy('local_program_level_courses', $id, [
            'programid' => $programid, 'levelid' => $levelid, 'courseid' => $courseid,
            'timecreated' => self::T0 + 3, 'usercreated' => $this->w['admin'],
        ]);
    }

    /**
     * @param int $id
     * @param int $programid
     * @param int $userid
     * @param int $status
     * @param int $date
     * @param string $levelids
     * @param int $created
     * @param int $modified
     * @return void
     */
    private function add_enrolment(int $id, int $programid, int $userid, int $status, int $date, string $levelids,
                                   int $created, int $modified): void {
        $this->legacy('local_program_users', $id, [
            'programid' => $programid, 'userid' => $userid, 'supervisorid' => 0, 'completion_status' => $status,
            'completiondate' => $date, 'levelids' => $levelids, 'usercreated' => $this->w['admin'],
            'timecreated' => $created, 'timemodified' => $modified,
        ]);
    }

    /**
     * @param int $id
     * @param int $programid
     * @param int $levelid
     * @param int $userid
     * @param int $status
     * @param int $date
     * @param string $courses
     * @param int $created
     * @param int $modified
     * @return void
     */
    private function add_level_completion(int $id, int $programid, int $levelid, int $userid, int $status, int $date,
                                          string $courses, int $created, int $modified): void {
        $this->legacy('local_bc_level_completions', $id, [
            'programid' => $programid, 'levelid' => $levelid, 'userid' => $userid, 'completion_status' => $status,
            'completiondate' => $date, 'bclcids' => $courses === '' ? null : $courses,
            'usercreated' => $this->w['admin'], 'timecreated' => $created, 'timemodified' => $modified,
        ]);
    }

    /**
     * A core course completion.
     *
     * @param int $userid
     * @param int $courseid
     * @param int $time
     * @return void
     */
    private function complete(int $userid, int $courseid, int $time): void {
        global $DB;
        $DB->insert_record('course_completions', (object) [
            'userid' => $userid, 'course' => $courseid, 'timeenrolled' => self::T0, 'timestarted' => self::T0,
            'timecompleted' => $time, 'reaggregate' => 0,
        ]);
    }

    /**
     * Put the world in place.
     *
     * @return void
     */
    protected function contract_seed(): void {
        global $DB;
        $this->ensure_bizlms_schema();
        $gen = $this->getDataGenerator();
        $t = self::T0;

        // The organisation tree the tenant resolver validates against (the org importer is not part of this test).
        foreach ([[1, 'Airpay', '/1', 0, 1], [77, 'Public', '/77', 0, 1], [177, 'ZEEA', '/177', 0, 1],
                [5, 'Payments', '/1/5', 1, 2]] as [$id, $name, $path, $parent, $depth]) {
            if (!$DB->record_exists('local_sentientia_org', ['id' => $id])) {
                $DB->import_record('local_sentientia_org', (object) [
                    'id' => $id, 'fullname' => $name, 'shortname' => strtolower($name), 'parentid' => $parent,
                    'path' => $path, 'depth' => $depth, 'visible' => 1, 'sortorder' => $id * 10,
                    'timecreated' => $t, 'timemodified' => $t,
                ]);
            }
        }

        // People. Everybody but the admin and uG sits in /1/5.
        $admin = $gen->create_user();
        $this->w = ['admin' => (int) $admin->id];
        foreach (['uA', 'uB', 'uC', 'uD', 'uD2', 'uE', 'uF', 'uH'] as $key) {
            $this->w[$key] = (int) $gen->create_user(['open_path' => '/1/5', 'firstname' => $key])->id;
        }
        $this->w['uG'] = (int) $gen->create_user(['open_path' => '/77', 'firstname' => 'uG'])->id;
        $DB->set_field('user', 'deleted', 1, ['id' => $this->w['uF']]);
        $this->w['c1'] = (int) $gen->create_course()->id;
        $this->w['c2'] = (int) $gen->create_course()->id;
        $this->w['c3'] = (int) $gen->create_course()->id;
        $this->w['c4'] = (int) $gen->create_course()->id;
        [$c1, $c2, $c3, $c4] = [$this->w['c1'], $this->w['c2'], $this->w['c3'], $this->w['c4']];

        // Programs.
        $this->add_program(41, ['name' => '  Payments Certification  ', 'shortname' => 'PAY',
            'description' => '<p>Intro</p>', 'open_path' => '/1/5', 'nomination_startdate' => $t + 100,
            'nomination_enddate' => $t + 200, 'timecreated' => $t + 1, 'timemodified' => 0, 'programlogo' => 9001]);
        $this->add_program(42, ['name' => 'Hidden program', 'visible' => 0, 'open_path' => ' 77/ ',
            'timemodified' => $t + 50]);
        $this->add_program(43, ['name' => 'Legacy program', 'status' => 2, 'open_path' => '',
            'usercreated' => $this->w['uG'], 'timemodified' => $t + 60]);
        $this->add_program(44, ['name' => '', 'shortname' => '', 'open_path' => '/1']);
        $this->add_program(45, ['name' => 'Orphan tenant program', 'open_path' => '0', 'timemodified' => 0]);

        // Levels of P1 (ids in order; positions in reverse: the order is by id). Five of them stay empty.
        $this->add_level(101, 41, 'Foundation', 9);
        $this->add_level(102, 41, 'Empty A', 8);
        $this->add_level(103, 41, 'Advanced', 1);
        foreach ([104 => 'Empty B', 105 => 'Empty C', 106 => 'Empty D', 107 => 'Empty E'] as $id => $name) {
            $this->add_level($id, 41, $name, 0);
        }
        $this->add_level(108, 41, 'Optional', 0);
        $this->add_level(110, 42, 'Only level', 0);
        $this->add_level(190, 88, 'Level of a deleted program', 0);

        // Courses of the levels.
        $this->add_level_course(201, 41, 101, $c1);
        $this->add_level_course(202, 41, 101, $c1);
        $this->add_level_course(203, 41, 101, 1);
        $this->add_level_course(204, 41, 101, 987655);
        $this->add_level_course(205, 41, 103, $c2);
        $this->add_level_course(206, 41, 103, $c3);
        $this->add_level_course(207, 41, 103, $c4);
        $this->add_level_course(208, 41, 102, 1);
        $this->add_level_course(209, 42, 110, $c1);
        $this->add_level_course(210, 41, 108, $c4);
        $this->add_level_course(211, 41, 999, $c1);

        // Criteria. Duplicates: the lowest id counts. Orphans: a level and a program that are gone.
        $crit = fn(int $programid, int $levelid, string $tracking, string $ids) => [
            'programid' => $programid, 'levelid' => $levelid, 'coursetracking' => $tracking, 'courseids' => $ids,
            'usercreated' => $this->w['admin'], 'timecreated' => self::T0 + 4,
        ];
        $this->legacy('local_bcl_cmplt_criteria', 401, $crit(41, 101, 'ALL', ''));
        $this->legacy('local_bcl_cmplt_criteria', 402, $crit(41, 103, 'OR', "{$c2}, {$c3}"));
        $this->legacy('local_bcl_cmplt_criteria', 403, $crit(41, 103, 'ALL', ''));
        $this->legacy('local_bcl_cmplt_criteria', 404, $crit(41, 998, 'ALL', ''));
        $this->legacy('local_bcl_cmplt_criteria', 405, $crit(88, 190, 'ALL', ''));
        $pcrit = fn(int $programid, string $tracking, string $ids) => [
            'programid' => $programid, 'leveltracking' => $tracking, 'levelids' => $ids,
            'usercreated' => $this->w['admin'], 'timecreated' => self::T0 + 4,
        ];
        $this->legacy('local_bc_completion_criteria', 501, $pcrit(41, 'AND', '101,103,104'));
        $this->legacy('local_bc_completion_criteria', 502, $pcrit(41, 'OR', ''));
        $this->legacy('local_bc_completion_criteria', 503, $pcrit(45, 'OR', ''));
        $this->legacy('local_bc_completion_criteria', 504, $pcrit(88, 'ALL', ''));

        // Enrolments.
        $this->add_enrolment(601, 41, $this->w['uA'], 1, $t + 500, '101,103', $t + 5, $t + 510);
        $this->add_enrolment(602, 41, $this->w['uB'], 1, 0, '101,103', $t + 6, $t + 700);
        $this->add_enrolment(603, 41, $this->w['uC'], 0, 0, '101', $t + 7, 0);
        $this->add_enrolment(604, 41, $this->w['uD'], 0, 0, '', $t + 8, 0);
        $this->add_enrolment(605, 41, $this->w['uE'], 0, 0, '', $t + 10, $t + 11);
        $this->add_enrolment(606, 41, $this->w['uE'], 1, $t + 600, '101,103', $t + 20, $t + 610);
        $this->add_enrolment(607, 41, $this->w['uF'], 0, 0, '', $t + 9, $t + 12);
        $this->add_enrolment(608, 41, 987654, 0, 0, '', $t + 9, $t + 12);
        $this->add_enrolment(609, 41, $this->w['uD2'], 0, 0, '', $t + 9, $t + 13);
        $this->add_enrolment(610, 88, $this->w['uA'], 0, 0, '', $t + 9, $t + 13);
        $this->add_enrolment(611, 42, $this->w['uA'], 0, 0, '', $t + 11, $t + 14);

        // Stored level completions.
        $this->add_level_completion(301, 41, 101, $this->w['uA'], 1, $t + 400, '201', $t + 30, $t + 400);
        $this->add_level_completion(302, 41, 101, $this->w['uA'], 1, $t + 410, '201', $t + 40, $t + 410);
        $this->add_level_completion(303, 41, 103, $this->w['uA'], 1, $t + 500, '205,206', $t + 31, $t + 510);
        $this->add_level_completion(304, 41, 101, $this->w['uB'], 1, $t + 350, '', $t + 32, $t + 355);
        $this->add_level_completion(305, 41, 103, $this->w['uB'], 1, $t + 360, '', $t + 33, 0);
        $this->add_level_completion(306, 41, 101, $this->w['uC'], 0, 0, '', $t + 34, $t + 35);
        $this->add_level_completion(307, 41, 101, $this->w['uG'], 1, $t + 300, '', $t + 36, $t + 37);
        $this->add_level_completion(308, 41, 998, $this->w['uA'], 1, $t + 300, '', $t + 38, $t + 39);
        $this->add_level_completion(309, 88, 190, $this->w['uA'], 1, $t + 300, '', $t + 38, $t + 39);

        // Real course completions: one is earlier than the stored level date, one is later (a reset and a redo).
        $this->complete($this->w['uA'], $c1, $t + 300);
        $this->complete($this->w['uA'], $c2, $t + 450);
        $this->complete($this->w['uA'], $c3, $t + 900);
        $this->complete($this->w['uB'], $c1, $t + 700);
        $this->complete($this->w['uD2'], $c1, $t + 250);

        // Trainers: expected empty in production, carried if they are not.
        $this->legacy('local_program_trainers', 701, [
            'programid' => 41, 'trainerid' => $this->w['uH'], 'feedback_id' => 5, 'feedback_score' => '4.5',
            'usercreated' => $this->w['admin'], 'timecreated' => $t + 70, 'timemodified' => $t + 71,
        ]);
        $this->legacy('local_program_trainers', 702, [
            'programid' => 88, 'trainerid' => $this->w['uH'], 'feedback_id' => 5,
            'usercreated' => $this->w['admin'], 'timecreated' => $t + 70,
        ]);
        $this->legacy('local_program_trainers', 703, [
            'programid' => 41, 'trainerid' => 987653, 'feedback_id' => 5,
            'usercreated' => $this->w['admin'], 'timecreated' => $t + 70,
        ]);
        $fb = fn(int $trainerrow, ?int $userid, string $score) => [
            'bc_trainer_id' => $trainerrow, 'programid' => 41, 'trainerid' => $this->w['uH'], 'userid' => $userid,
            'score' => $score, 'usercreated' => $this->w['admin'], 'timecreated' => self::T0 + 80,
            'timemodified' => self::T0 + 81,
        ];
        $this->legacy('local_program_trainerfb', 801, $fb(701, $this->w['uC'], '5'));
        $this->legacy('local_program_trainerfb', 802, $fb(799, $this->w['uC'], '3'));
        $this->legacy('local_program_trainerfb', 803, $fb(701, 987652, '4'));

        // The tables kept only in the archive.
        $this->legacy('local_program_completions_bk', 901, [
            'programid' => 41, 'userid' => $this->w['uA'], 'usercreated' => $this->w['admin'], 'timecreated' => $t + 90,
        ]);
        $this->legacy('local_bc_level_comp_bk', 902, [
            'programid' => 41, 'levelid' => 101, 'userid' => $this->w['uA'], 'usercreated' => $this->w['admin'],
            'timecreated' => $t + 90,
        ]);
        $this->legacy('local_program_test_score', 903, [
            'programid' => 41, 'levelid' => 101, 'courseid' => $c1, 'testid' => 't1',
            'usercreated' => $this->w['admin'], 'timecreated' => $t + 90,
        ]);

        // A BizLMS program logo: a draft item id in a category context (program.php:70,786-797).
        $category = $gen->create_category();
        $this->w['logocontext'] = \context_coursecat::instance($category->id)->id;
        get_file_storage()->create_file_from_string([
            'contextid' => $this->w['logocontext'], 'component' => 'local_program', 'filearea' => 'programlogo',
            'itemid' => 9001, 'filepath' => '/', 'filename' => 'logo.png',
        ], 'not really a png');
    }

    /**
     * Seed, apply and return the report.
     *
     * @param array $options Overrides for the runner options.
     * @return array{0: array, 1: \local_sentientia_platform\bizlms\report}
     */
    private function apply(array $options = []): array {
        $this->contract_begin();
        $this->contract_seed();
        return $this->contract_run(true, $options);
    }

    /**
     * @param string $sourcetable
     * @param int $id
     * @return \stdClass The primary map row.
     */
    private function map(string $sourcetable, int $id): \stdClass {
        global $DB;
        return $DB->get_record(legacymap::TABLE,
            ['sourcetable' => $sourcetable, 'sourceid' => $id, 'subkey' => ''], '*', MUST_EXIST);
    }

    /**
     * @param string $sourcetable
     * @param int $id
     * @return int The target id of an imported row.
     */
    private function target(string $sourcetable, int $id): int {
        $row = $this->map($sourcetable, $id);
        $this->assertContains($row->outcome, ['imported', 'adopted'], "{$sourcetable} {$id}");
        return (int) $row->targetid;
    }

    /**
     * @param int $programid
     * @param string $user World key.
     * @return \stdClass|false The imported enrolment.
     */
    private function enrolment(int $programid, string $user) {
        global $DB;
        return $DB->get_record('local_sentientia_programs_users',
            ['programid' => $programid, 'userid' => $this->w[$user]]);
    }

    /**
     * @param array $report
     * @param string $step
     * @return array
     */
    private function step(array $report, string $step): array {
        return $report['features']['program']['steps'][$step];
    }

    // Tests.

    public function test_the_programs_get_the_tenant_status_and_dates_the_map_describes(): void {
        global $DB;
        [$result, $report] = $this->apply();
        $this->assertContains($result['exit'], [0, 2], implode('; ', $result['blockers']));
        $t = self::T0;

        $p1 = $DB->get_record('local_sentientia_programs', ['id' => 41], '*', MUST_EXIST);
        $this->assertSame('Payments Certification', $p1->name, 'trimmed');
        $this->assertSame('<p>Intro</p>', $p1->description);
        $this->assertEquals(FORMAT_HTML, $p1->descriptionformat);
        $this->assertSame('/1/5', $p1->open_path);
        $this->assertEquals(5, $p1->costcenterid, 'the organisation whose path it is');
        $this->assertEquals(1, $p1->status);
        $this->assertEquals(1, $p1->visible);
        $this->assertEquals($t + 100, $p1->startdate, 'the nomination window is the enrolment window');
        $this->assertEquals($t + 200, $p1->enddate);
        $this->assertEquals(1, $p1->completion_required, 'criteria AND: every level that counts');
        $this->assertEquals($t + 1, $p1->timecreated);
        $this->assertEquals($t + 1, $p1->timemodified, 'no modified date: the created date');

        $p2 = $DB->get_record('local_sentientia_programs', ['id' => 42], '*', MUST_EXIST);
        $this->assertSame('/77', $p2->open_path, "' 77/ ' is normalised");
        $this->assertEquals(77, $p2->costcenterid);
        $this->assertEquals(2, $p2->status, 'visible = 0 is the off switch: Archived, never Draft');
        $this->assertEquals(0, $p2->visible);
        $this->assertNull($p2->startdate, '0 means no window');
        $this->assertNull($p2->enddate);
        $this->assertEquals($t + 50, $p2->timemodified);

        $p3 = $DB->get_record('local_sentientia_programs', ['id' => 43], '*', MUST_EXIST);
        $this->assertSame('/77', $p3->open_path, 'no path of its own: the root of its creator');
        $this->assertEquals(2, $p3->status, 'BizLMS status 2');

        $this->assertFalse($DB->record_exists('local_sentientia_programs', ['id' => 44]), 'no name, no shortname');
        $p5 = $DB->get_record('local_sentientia_programs', ['id' => 45], '*', MUST_EXIST);
        $this->assertNull($p5->open_path, 'nothing places it: cross-tenant only');
        $this->assertEquals(0, $p5->costcenterid);
        $this->assertEquals(0, $p5->completion_required, 'criteria OR: any level completes the program');
        $this->assertSame(4, $DB->count_records('local_sentientia_programs'));

        // PRESERVE: the legacy id is the target id.
        foreach ([41, 42, 43, 45] as $id) {
            $this->assertSame($id, $this->target('local_program', $id));
        }
        $skipped = $this->map('local_program', 44);
        $this->assertSame('skipped', $skipped->outcome);
        $this->assertSame('no_name', $skipped->reason);

        $step = $this->step($report->to_array(), 'program.program');
        $this->assertEquals(['exact' => 1, 'normalised' => 1, 'fallback:creator' => 1, 'unresolved' => 1],
            $step['tenant_methods']);
        $this->assertEquals(['derived_timestamp' => 2, 'tenant_unresolved' => 1], $step['warnings']);
        $this->assertEquals(['no_name' => 1], $step['skipped_by_reason']);
    }

    public function test_levels_rank_by_id_and_empty_ones_are_skipped_and_dropped_from_the_required_set(): void {
        global $DB;
        [$result, $report] = $this->apply();
        $this->assertContains($result['exit'], [0, 2], implode('; ', $result['blockers']));

        $l101 = $DB->get_record('local_sentientia_programs_levels', ['id' => $this->target('local_program_levels', 101)]);
        $l103 = $DB->get_record('local_sentientia_programs_levels', ['id' => $this->target('local_program_levels', 103)]);
        $l108 = $DB->get_record('local_sentientia_programs_levels', ['id' => $this->target('local_program_levels', 108)]);
        $l110 = $DB->get_record('local_sentientia_programs_levels', ['id' => $this->target('local_program_levels', 110)]);

        $this->assertSame('Foundation', $l101->name);
        $this->assertEquals(41, $l101->programid);
        $this->assertEquals(0, $l101->sortorder, 'by id, although BizLMS position said 9');
        $this->assertEquals(1, $l103->sortorder, 'dense: the skipped empty level 102 leaves no gap');
        $this->assertEquals(2, $l108->sortorder);
        $this->assertEquals(0, $l110->sortorder, 'a rank inside its own program');
        $this->assertEquals(self::T0 + 2, $l101->timecreated);

        // Required: in the program criteria list (101,103; 104 is empty and was dropped), else not.
        $this->assertEquals(1, $l101->completion_required);
        $this->assertEquals(1, $l103->completion_required);
        $this->assertEquals(0, $l108->completion_required, 'not in the list of an AND program');
        $this->assertEquals(1, $l110->completion_required, 'no criteria row: required');
        // Rule: any only for coursetracking OR; the lowest criteria id counted (ALL at 403 did not).
        $this->assertSame('all', $l101->completion_rule);
        $this->assertSame('any', $l103->completion_rule);
        $this->assertSame('all', $l108->completion_rule);
        $this->assertSame(4, $DB->count_records('local_sentientia_programs_levels'));

        foreach ([102, 104, 105, 106, 107] as $id) {
            $row = $this->map('local_program_levels', $id);
            $this->assertSame('skipped', $row->outcome, "level {$id}");
            $this->assertSame('empty_level', $row->reason);
        }
        $this->assertSame('orphan_program', $this->map('local_program_levels', 190)->reason);

        $step = $this->step($report->to_array(), 'program.level');
        $this->assertEquals(['empty_level' => 5, 'orphan_program' => 1], $step['skipped_by_reason']);
    }

    public function test_the_empty_level_choice_is_the_owners(): void {
        global $DB;
        // With 'import' the empty levels stay, flagged, and count in the ranking.
        $decisions = decisions::from_array([
            'program.completed_without_date' => 'completed_flagged', 'program.inactive' => 'archived',
            'program.empty_levels' => 'import', 'program.deleted_users' => 'import',
            'program.pathless' => 'creator_root', 'program.bk_tables' => 'archive_only',
            'tenant.unresolved.program' => 'pathless',
        ]);
        [$result, $report] = $this->apply(['decisions' => $decisions]);
        $this->assertContains($result['exit'], [0, 2], implode('; ', $result['blockers']));
        $this->assertSame(9, $DB->count_records('local_sentientia_programs_levels'), 'all of them but the orphan');
        $this->assertEquals(2, $DB->get_field('local_sentientia_programs_levels', 'sortorder',
            ['id' => $this->target('local_program_levels', 103)]), 'ranked by id including the empty 102');
        $step = $this->step($report->to_array(), 'program.level');
        $this->assertEquals(5, $step['warnings']['empty_level_kept']);
    }

    public function test_courses_collapse_duplicates_and_carry_mandatory_from_the_level_criteria(): void {
        global $DB;
        [$result, $report] = $this->apply();
        $this->assertContains($result['exit'], [0, 2], implode('; ', $result['blockers']));
        [$c1, $c2, $c3, $c4] = [$this->w['c1'], $this->w['c2'], $this->w['c3'], $this->w['c4']];
        $l101 = $this->target('local_program_levels', 101);
        $l103 = $this->target('local_program_levels', 103);

        $row = static fn(int $level, int $course) => $DB->get_record('local_sentientia_programs_courses',
            ['levelid' => $level, 'courseid' => $course]);
        $this->assertEquals(0, $row($l101, $c1)->sortorder);
        $this->assertEquals(1, $row($l101, $c1)->mandatory);
        $this->assertEquals([0, 1], [(int) $row($l103, $c2)->sortorder, (int) $row($l103, $c3)->sortorder], 'rank by id');
        $this->assertEquals(2, $row($l103, $c4)->sortorder);
        $this->assertEquals([1, 1, 0], [(int) $row($l103, $c2)->mandatory, (int) $row($l103, $c3)->mandatory,
            (int) $row($l103, $c4)->mandatory], 'OR with a list: listed courses are mandatory, c4 is not');
        $this->assertEquals(1, $DB->get_field('local_sentientia_programs_courses', 'mandatory',
            ['levelid' => $this->target('local_program_levels', 108), 'courseid' => $c4]), 'no criteria row: mandatory');
        $this->assertSame(6, $DB->count_records('local_sentientia_programs_courses'));

        $dup = $this->map('local_program_level_courses', 202);
        $this->assertSame('merged', $dup->outcome);
        $this->assertSame('dup_level_course', $dup->reason);
        $this->assertEquals($this->target('local_program_level_courses', 201), $dup->targetid, 'points at the winner');
        $this->assertSame('invalid_course', $this->map('local_program_level_courses', 203)->reason, 'the front page');
        $this->assertSame('orphan_course', $this->map('local_program_level_courses', 204)->reason);
        $this->assertSame('invalid_course', $this->map('local_program_level_courses', 208)->reason);
        $this->assertSame('orphan_level', $this->map('local_program_level_courses', 211)->reason);

        $step = $this->step($report->to_array(), 'program.course');
        $this->assertEquals(['dup_level_course' => 1, 'invalid_course' => 2, 'orphan_course' => 1, 'orphan_level' => 1],
            $step['skipped_by_reason']);
    }

    public function test_the_criteria_rows_are_folded_into_the_rows_they_shaped(): void {
        global $DB;
        [$result, $report] = $this->apply();
        $this->assertContains($result['exit'], [0, 2], implode('; ', $result['blockers']));

        // Program criteria: the lowest id wins per program.
        $won = $this->map('local_bc_completion_criteria', 501);
        $this->assertSame('folded', $won->outcome);
        $this->assertSame('local_sentientia_programs', $won->targettable);
        $this->assertEquals(41, $won->targetid);
        $lost = $this->map('local_bc_completion_criteria', 502);
        $this->assertSame('merged', $lost->outcome);
        $this->assertSame('dup_criteria', $lost->reason);
        $this->assertSame('folded', $this->map('local_bc_completion_criteria', 503)->outcome);
        $this->assertSame('orphan_program', $this->map('local_bc_completion_criteria', 504)->reason);

        // Level criteria.
        $l103 = $this->target('local_program_levels', 103);
        $won = $this->map('local_bcl_cmplt_criteria', 402);
        $this->assertSame('folded', $won->outcome);
        $this->assertSame('local_sentientia_programs_levels', $won->targettable);
        $this->assertEquals($l103, $won->targetid);
        $this->assertSame('dup_criteria', $this->map('local_bcl_cmplt_criteria', 403)->reason);
        $this->assertSame('folded', $this->map('local_bcl_cmplt_criteria', 401)->outcome);
        $this->assertSame('orphan_level', $this->map('local_bcl_cmplt_criteria', 404)->reason);
        $this->assertSame('orphan_program', $this->map('local_bcl_cmplt_criteria', 405)->reason);

        $report = $report->to_array();
        $this->assertEquals(['criteria_folded' => 2, 'dup_criteria' => 1, 'orphan_program' => 1],
            $this->step($report, 'program.programcriteria')['skipped_by_reason']);
        $this->assertEquals(['criteria_folded' => 2, 'dup_criteria' => 1, 'orphan_level' => 1, 'orphan_program' => 1],
            $this->step($report, 'program.levelcriteria')['skipped_by_reason']);
    }

    public function test_a_row_under_a_parent_the_import_chose_not_to_keep_is_not_reported_as_an_orphan(): void {
        $this->contract_begin();
        $this->contract_seed();
        [$c1, $t] = [$this->w['c1'], self::T0];
        $crit = fn(int $programid, int $levelid) => [
            'programid' => $programid, 'levelid' => $levelid, 'coursetracking' => 'ALL', 'courseids' => '',
            'usercreated' => $this->w['admin'], 'timecreated' => $t + 4,
        ];

        // Program 44 has no name and is skipped (no_name): its level, that level's course, its criteria rows and an
        // enrolment all have a parent that exists in BizLMS and that the import did not keep.
        $this->add_level(111, 44, 'Level of a nameless program');
        $this->add_level_course(212, 44, 111, $c1);
        $this->legacy('local_bcl_cmplt_criteria', 406, $crit(44, 111));
        $this->legacy('local_bc_completion_criteria', 505, ['programid' => 44, 'leveltracking' => 'ALL',
            'levelids' => '', 'usercreated' => $this->w['admin'], 'timecreated' => $t + 4]);
        $this->add_enrolment(612, 44, $this->w['uA'], 0, 0, '', $t + 11, $t + 14);
        // Level 102 of program 41 was skipped as empty; a criteria row of it is not an orphan either.
        $this->legacy('local_bcl_cmplt_criteria', 407, $crit(41, 102));
        // A criteria row that names program 41 for a level of program 42 shaped nothing: the level step reads its
        // criteria by the level's own program.
        $this->legacy('local_bcl_cmplt_criteria', 408, $crit(41, 110));

        [$result, $report] = $this->contract_run(true);
        $this->assertContains($result['exit'], [0, 2], implode('; ', $result['blockers']));

        $expect = [
            ['local_program_levels', 111, 'parent_skipped', 'no_name'],
            // Two steps down the tree (course -> level -> nameless program): the detail names the root cause.
            ['local_program_level_courses', 212, 'parent_skipped', 'no_name'],
            ['local_bcl_cmplt_criteria', 406, 'parent_skipped', 'no_name'],
            ['local_bc_completion_criteria', 505, 'parent_skipped', 'no_name'],
            ['local_program_users', 612, 'parent_skipped', 'no_name'],
            ['local_bcl_cmplt_criteria', 407, 'parent_skipped', 'empty_level'],
            ['local_bcl_cmplt_criteria', 408, 'criteria_program_mismatch', null],
        ];
        foreach ($expect as [$table, $id, $reason, $detail]) {
            $row = $this->map($table, $id);
            $this->assertSame('skipped', $row->outcome, "{$table} {$id}");
            $this->assertSame($reason, $row->reason, "{$table} {$id}");
            $this->assertSame($detail, $row->detail, "{$table} {$id}");
        }

        // Parents BizLMS really deleted are still orphans.
        $this->assertSame('orphan_program', $this->map('local_program_levels', 190)->reason);
        $this->assertSame('orphan_level', $this->map('local_bcl_cmplt_criteria', 404)->reason);

        $report = $report->to_array();
        $this->assertEquals(['criteria_folded' => 2, 'dup_criteria' => 1, 'orphan_level' => 1, 'orphan_program' => 1,
            'parent_skipped' => 2, 'criteria_program_mismatch' => 1],
            $this->step($report, 'program.levelcriteria')['skipped_by_reason']);
        $this->assertSame(1, $this->step($report, 'program.level')['skipped_by_reason']['parent_skipped']);
        $this->assertSame(1, $this->step($report, 'program.user')['skipped_by_reason']['parent_skipped']);
    }

    public function test_a_program_with_only_a_shortname_is_named_by_it_and_the_report_says_so(): void {
        global $DB;
        $this->contract_begin();
        $this->contract_seed();
        $this->add_program(46, ['name' => '  ', 'shortname' => ' ONLY-SHORT ']);
        [$result, $report] = $this->contract_run(true);
        $this->assertContains($result['exit'], [0, 2], implode('; ', $result['blockers']));

        $this->assertSame('ONLY-SHORT', $DB->get_field('local_sentientia_programs', 'name', ['id' => 46]));
        $this->assertSame(46, $this->target('local_program', 46), 'kept at its legacy id: certificates point at it');
        $this->assertSame(1, $this->step($report->to_array(), 'program.program')['warnings']['name_from_shortname']);
        // No name and no shortname is still the owner's call.
        $this->assertSame('no_name', $this->map('local_program', 44)->reason);
    }

    public function test_enrolments_carry_status_completion_dates_actor_and_source_times(): void {
        global $DB;
        [$result, $report] = $this->apply();
        $this->assertContains($result['exit'], [0, 2], implode('; ', $result['blockers']));
        $t = self::T0;

        $a = $this->enrolment(41, 'uA');
        $this->assertEquals(2, $a->status);
        $this->assertEquals($t + 500, $a->timecompleted, 'the stored completion date');
        $this->assertEquals($this->w['admin'], $a->enrolledby, 'who enrolled the learner');
        $this->assertEquals($t + 5, $a->timecreated);
        $this->assertEquals($t + 510, $a->timemodified);

        $b = $this->enrolment(41, 'uB');
        $this->assertEquals(2, $b->status, 'completed, flagged: the certificate path treated it as completed');
        $this->assertEquals($t + 360, $b->timecompleted, 'the latest level completion, never timemodified (700)');
        $this->assertEquals($t + 700, $b->timemodified);

        $c = $this->enrolment(41, 'uC');
        $this->assertEquals(1, $c->status, 'levelids say they started');
        $this->assertNull($c->timecompleted);
        $this->assertEquals($t + 7, $c->timemodified, 'no modified date: the created date');

        $d = $this->enrolment(41, 'uD');
        $this->assertEquals(0, $d->status, 'nothing done');
        $this->assertEquals($t + 8, $d->timecreated);

        $e = $this->enrolment(41, 'uE');
        $this->assertEquals(2, $e->status, 'the completed row of the duplicate pair wins');
        $this->assertEquals($t + 600, $e->timecompleted);
        $this->assertEquals($t + 10, $e->timecreated, 'the earliest enrolment time');
        $this->assertEquals($t + 610, $e->timemodified);
        $this->assertSame(1, $DB->count_records('local_sentientia_programs_users',
            ['programid' => 41, 'userid' => $this->w['uE']]));
        $dup = $this->map('local_program_users', 605);
        $this->assertSame('merged', $dup->outcome);
        $this->assertSame('dup_user_row', $dup->reason);
        $this->assertEquals($this->target('local_program_users', 606), $dup->targetid);

        $this->assertEquals(0, $this->enrolment(41, 'uF')->status, 'a deleted user is imported for the record');
        $this->assertEquals(1, $this->enrolment(41, 'uD2')->status, 'a course completion on a program course is progress');
        // An archived program keeps its roster, and the signed status rule reads it like any other program's: BizLMS
        // stored uA's P2 enrolment as not completed, but uA completed c1, which is the only course of P2's only level
        // (the same c1 that dates P1's level 101). The rule reads the learner's evidence, not the program's status
        // (mapping doc section 16, Users), so the roster row is In progress, not Enrolled.
        $this->assertEquals(1, $this->enrolment(42, 'uA')->status,
            'an archived program keeps its roster, and a completed program course is progress there too');
        $this->assertSame(8, $DB->count_records('local_sentientia_programs_users'));

        $this->assertSame('orphan_user', $this->map('local_program_users', 608)->reason);
        $this->assertSame('orphan_program', $this->map('local_program_users', 610)->reason);

        $step = $this->step($report->to_array(), 'program.user');
        $this->assertEquals(['completed_without_date' => 1, 'derived_timestamp' => 2], $step['warnings']);
        $this->assertEquals(['dup_user_row' => 1, 'orphan_user' => 1, 'orphan_program' => 1], $step['skipped_by_reason']);
    }

    public function test_level_completions_take_the_real_course_dates_capped_at_the_stored_one(): void {
        global $DB;
        [$result, $report] = $this->apply();
        $this->assertContains($result['exit'], [0, 2], implode('; ', $result['blockers']));
        $t = self::T0;
        $l101 = $this->target('local_program_levels', 101);
        $l103 = $this->target('local_program_levels', 103);
        $row = fn(int $level, string $user) => $DB->get_record('local_sentientia_programs_lvlcomp',
            ['levelid' => $level, 'userid' => $this->w[$user]], '*', MUST_EXIST);

        $a101 = $row($l101, 'uA');
        $this->assertEquals(41, $a101->programid);
        $this->assertEquals(1, $a101->status);
        $this->assertEquals($t + 300, $a101->timecompleted, 'all-course level: the last course (300), below the stored 410');
        $this->assertSame('201', $a101->completedcourseids);
        $this->assertSame('bizlms', $a101->source);
        $this->assertEquals($t + 30, $a101->timecreated, 'the earliest of the duplicate pair');
        $this->assertEquals($t + 410, $a101->timemodified, 'the most recent stored completion won');
        $this->assertEquals($t + 450, $row($l103, 'uA')->timecompleted, 'any-course level: the FIRST course (450), not 900');
        $this->assertEquals($t + 350, $row($l101, 'uB')->timecompleted, 'a course redone after a reset (700) does not pass the stored date');
        $b103 = $row($l103, 'uB');
        $this->assertEquals($t + 360, $b103->timecompleted, 'no course completion on record: the stored date');
        $this->assertNull($b103->completedcourseids);
        $this->assertSame(4, $DB->count_records('local_sentientia_programs_lvlcomp'));

        $this->assertSame('merged', $this->map('local_bc_level_completions', 301)->outcome);
        $this->assertSame('dup_level_completion', $this->map('local_bc_level_completions', 301)->reason);
        $archived = $this->map('local_bc_level_completions', 306);
        $this->assertSame('archived', $archived->outcome, 'a reset leaves a status 0 row: no completion to carry');
        $this->assertSame('level_not_completed', $archived->reason);
        $this->assertSame('no_enrolment', $this->map('local_bc_level_completions', 307)->reason);
        $this->assertSame('orphan_level', $this->map('local_bc_level_completions', 308)->reason);
        $this->assertSame('orphan_program', $this->map('local_bc_level_completions', 309)->reason);

        $step = $this->step($report->to_array(), 'program.levelcompletion');
        $this->assertEquals(['derived_timestamp' => 1], $step['warnings']);
        $this->assertEquals(['dup_level_completion' => 1, 'level_not_completed' => 1, 'no_enrolment' => 1,
            'orphan_level' => 1, 'orphan_program' => 1], $step['skipped_by_reason']);
    }

    public function test_the_current_level_is_worked_out_after_the_completions_have_loaded(): void {
        [$result, $report] = $this->apply();
        $this->assertContains($result['exit'], [0, 2], implode('; ', $result['blockers']));
        $first = $this->target('local_program_levels', 101);
        $last = $this->target('local_program_levels', 108);

        $this->assertEquals($last, $this->enrolment(41, 'uA')->currentlevelid, 'completed: the last level');
        $this->assertEquals($last, $this->enrolment(41, 'uB')->currentlevelid);
        $this->assertEquals($last, $this->enrolment(41, 'uE')->currentlevelid);
        $this->assertEquals($first, $this->enrolment(41, 'uC')->currentlevelid, 'in progress: first level with no stored completion');
        $this->assertEquals($first, $this->enrolment(41, 'uD2')->currentlevelid);
        $this->assertNull($this->enrolment(41, 'uD')->currentlevelid, 'not started: none');
        $this->assertNull($this->enrolment(41, 'uF')->currentlevelid);
        // In progress in the archived program (see the status test): the first level with no stored completion.
        $this->assertEquals($this->target('local_program_levels', 110), $this->enrolment(42, 'uA')->currentlevelid,
            'an archived program has no carve-out: its only level is the current one');
        $this->assertSame('done', $report->to_array()['features']['program']['steps']['program.currentlevel']['status']);
    }

    public function test_trainers_and_their_feedback_are_carried_and_the_backup_tables_are_archived(): void {
        global $DB;
        [$result, $report] = $this->apply();
        $this->assertSame(2, $result['exit'], 'done, but the backup rows and the nameless program need the owner');
        $this->assertEquals(['program:bk_rows_archived=3', 'program:no_name=1'], $result['unproven']);

        $trainer = $DB->get_record('local_sentientia_programs_trainers', ['id' => $this->target('local_program_trainers', 701)]);
        $this->assertEquals(41, $trainer->programid);
        $this->assertEquals($this->w['uH'], $trainer->userid);
        $this->assertEquals(5, $trainer->feedbackid);
        $this->assertSame('4.5', $trainer->feedback_score);
        $this->assertEquals($this->w['admin'], $trainer->assignedby);
        $this->assertEquals(self::T0 + 71, $trainer->timemodified);
        $this->assertSame('orphan_program', $this->map('local_program_trainers', 702)->reason);
        $this->assertSame('orphan_user', $this->map('local_program_trainers', 703)->reason);
        $this->assertSame(1, $DB->count_records('local_sentientia_programs_trainers'));

        $given = $DB->get_record('local_sentientia_programs_trainerfb', ['id' => $this->target('local_program_trainerfb', 801)]);
        $this->assertEquals($trainer->id, $given->programtrainerid, 'pointed at the new trainer row through the map');
        $this->assertEquals($this->w['uC'], $given->userid);
        $anonymous = $DB->get_record('local_sentientia_programs_trainerfb', ['id' => $this->target('local_program_trainerfb', 803)]);
        $this->assertNull($anonymous->userid, 'a giver that no longer exists is left empty, the feedback stays');
        $this->assertSame('orphan_trainer', $this->map('local_program_trainerfb', 802)->reason);
        $this->assertSame(2, $DB->count_records('local_sentientia_programs_trainerfb'));

        foreach ([['local_program_completions_bk', 901], ['local_bc_level_comp_bk', 902], ['local_program_test_score', 903]] as [$table, $id]) {
            $row = $this->map($table, $id);
            $this->assertSame('archived', $row->outcome, $table);
            $this->assertSame('bk_rows_archived', $row->reason);
            $this->assertSame('', $row->targettable, 'nothing is written for an archived row');
        }
        $step = $this->step($report->to_array(), 'program.trainerfeedback');
        $this->assertEquals(['feedback_giver_not_found' => 1], $step['warnings']);
    }

    public function test_an_actor_who_was_hard_deleted_is_left_empty_not_carried_as_a_dangling_id(): void {
        global $DB;
        $this->contract_begin();
        $this->contract_seed();
        // The administrator who enrolled uD and the one who assigned the trainer have since been removed from the site.
        $gone = 987651;
        $this->assertFalse($DB->record_exists('user', ['id' => $gone]));
        $DB->set_field('local_program_users', 'usercreated', $gone, ['id' => 604]);
        $DB->set_field('local_program_trainers', 'usercreated', $gone, ['id' => 701]);
        [$result, $report] = $this->contract_run(true);
        $this->assertContains($result['exit'], [0, 2], implode('; ', $result['blockers']));

        $this->assertEquals(0, $this->enrolment(41, 'uD')->enrolledby);
        $this->assertEquals(0, $DB->get_field('local_sentientia_programs_trainers', 'assignedby',
            ['id' => $this->target('local_program_trainers', 701)]));
        $to = $report->to_array();
        $this->assertSame(1, $this->step($to, 'program.user')['warnings']['enrolledby_not_found']);
        $this->assertSame(1, $this->step($to, 'program.trainer')['warnings']['assignedby_not_found']);
        // The rows themselves are kept, and an actor who still exists is carried as before.
        $this->assertEquals(0, $this->enrolment(41, 'uD')->status);
        $this->assertEquals($this->w['admin'], $this->enrolment(41, 'uA')->enrolledby);
    }

    public function test_the_owner_can_accept_the_needs_owner_reasons(): void {
        $decisions = decisions::from_array([
            'program.completed_without_date' => 'completed_flagged', 'program.inactive' => 'archived',
            'program.empty_levels' => 'skip', 'program.deleted_users' => 'import',
            'program.pathless' => 'creator_root', 'program.bk_tables' => 'archive_only',
            'tenant.unresolved.program' => 'pathless',
            'accepted_reasons' => ['program:bk_rows_archived', 'program:no_name'],
        ]);
        [$result] = $this->apply(['decisions' => $decisions]);
        $this->assertSame(0, $result['exit'], implode('; ', $result['blockers']));
        $this->assertSame([], $result['unproven']);
    }

    public function test_the_owner_can_choose_to_skip_programs_no_tenant_can_be_found_for(): void {
        global $DB;
        $decisions = decisions::from_array([
            'program.completed_without_date' => 'completed_flagged', 'program.inactive' => 'archived',
            'program.empty_levels' => 'skip', 'program.deleted_users' => 'import',
            'program.pathless' => 'cross_tenant_only', 'program.bk_tables' => 'archive_only',
            'tenant.unresolved.program' => 'skip',
        ]);
        [$result] = $this->apply(['decisions' => $decisions]);
        $this->assertSame(2, $result['exit'], implode('; ', $result['blockers']));
        // With no creator fallback, P3 (no path) and P5 ('0') have no tenant and are skipped.
        $this->assertFalse($DB->record_exists('local_sentientia_programs', ['id' => 43]));
        $this->assertFalse($DB->record_exists('local_sentientia_programs', ['id' => 45]));
        $this->assertSame('tenant_unresolved', $this->map('local_program', 43)->reason);
        $this->assertSame(2, $DB->count_records('local_sentientia_programs'));
        // Their children cannot be placed either, and the report says the import chose not to keep the parent: BizLMS
        // did not delete P5, so its criteria row is not an orphan.
        $criteria = $this->map('local_bc_completion_criteria', 503);
        $this->assertSame('parent_skipped', $criteria->reason);
        $this->assertSame('tenant_unresolved', $criteria->detail, "the parent's own reason");
    }

    public function test_a_missing_owner_decision_blocks_the_feature_before_anything_is_written(): void {
        global $DB;
        [$result] = $this->apply(['decisions' => decisions::none()]);
        $this->assertSame(1, $result['exit']);
        $this->assertStringContainsString('missing_decision:program.empty_levels', implode(' ', $result['blockers']));
        $this->assertSame(0, $DB->count_records('local_sentientia_programs'));
        $this->assertSame(0, $DB->count_records(legacymap::TABLE));
    }

    public function test_an_unknown_enum_value_blocks_until_the_owner_maps_it(): void {
        global $DB;
        $this->contract_begin();
        $this->contract_seed();
        $DB->set_field('local_bc_level_completions', 'completion_status', 3, ['id' => 306]);
        [$result] = $this->contract_run(true);
        $this->assertSame(1, $result['exit']);
        $this->assertStringContainsString('unknown_enum:local_bc_level_completions.completion_status=3',
            implode(' ', $result['blockers']));
        $this->assertSame(0, $DB->count_records('local_sentientia_programs'));
    }

    public function test_the_import_writes_no_enrolment_completion_or_event(): void {
        global $DB;
        $this->contract_begin();
        $this->contract_seed();
        $counts = static fn() => [
            'course_completions' => $DB->count_records('course_completions'),
            'user_enrolments' => $DB->count_records('user_enrolments'),
            'role_assignments' => $DB->count_records('role_assignments'),
            'certificates' => $DB->get_manager()->table_exists('tool_certificate_issues')
                ? $DB->count_records('tool_certificate_issues') : 0,
            'legacy_program' => $DB->count_records('local_program'),
            'legacy_completions' => $DB->count_records('local_bc_level_completions'),
            'legacy_users' => $DB->count_records('local_program_users'),
        ];
        $before = $counts();
        $events = $this->redirectEvents();
        [$result] = $this->contract_run(true);
        $this->assertContains($result['exit'], [0, 2], implode('; ', $result['blockers']));
        $this->assertSame($before, $counts(), 'core is untouched and the legacy tables are the archive');
        $this->assertSame(0, $events->count(), 'no program_completed for something that happened years ago');
    }

    public function test_the_logo_is_copied_to_the_area_the_pluginfile_callback_serves_and_twice_copies_nothing(): void {
        $this->apply();
        $fs = get_file_storage();
        $system = \context_system::instance()->id;
        $copy = $fs->get_file($system, 'local_sentientia_programs', 'programlogo', 41, '/', 'logo.png');
        $this->assertNotFalse($copy, 'copied to the system context, item id = the program id');
        $this->assertSame('not really a png', $copy->get_content());
        $this->assertTrue($fs->file_exists($this->w['logocontext'], 'local_program', 'programlogo', 9001, '/', 'logo.png'),
            'the original stays: the legacy archive is never altered');
        $this->assertFalse($fs->file_exists($system, 'local_sentientia_programs', 'programlogo', 42, '/', 'logo.png'));

        $this->contract_run(true);
        $this->assertCount(1, $fs->get_area_files($system, 'local_sentientia_programs', 'programlogo', 41, 'id', false));
    }

    public function test_the_importer_declares_its_logo_copy_through_the_copies_files_marker(): void {
        $importer = new program_importer();
        $this->assertInstanceOf(copies_files::class, $importer);
        $this->assertSame([['local_program', 'programlogo', 'local_sentientia_programs', 'programlogo']],
            $importer->allowed_file_areas());
        $this->assertSame([], $importer->core_writes(), 'a file copy is not a core write, so --purge-feature stays available');
        $this->assertTrue(sideeffect_guard::file_areas_well_formed($importer));
    }

    public function test_the_logo_copy_is_a_declared_side_effect_the_report_counts_and_the_tripwire_accepts(): void {
        // IDN-04: {files} is watched for every importer. Without the marker the copy would trip
        // write_outside_declared_tables:files and the feature would end with no marker.
        [$result, $report] = $this->apply();
        $this->assertContains($result['exit'], [0, 2], implode('; ', $result['blockers']));
        $feature = $report->to_array()['features']['program'];
        $this->assertSame('clean', $feature['tripwire']);
        $this->assertSame(['local_sentientia_programs/programlogo' => 1], $feature['files_copied']);
    }

    public function test_a_native_program_after_the_import_gets_an_id_above_the_legacy_maximum(): void {
        $this->apply();
        $id = program_manager::create((object) ['name' => 'Created after the import']);
        $this->assertGreaterThan(45, $id, 'finalise() reset the sequence: no collision with a preserved id');
    }

    public function test_verify_passes_and_catches_a_broken_current_level(): void {
        global $DB;
        $importer = $this->contract_begin();
        $this->contract_seed();
        $this->contract_run(true);
        $ctx = context::build($importer, false, 0, $this->contract_decisions());
        $this->assertSame([], $importer->verify($ctx), 'a clean import verifies');

        // A current level that belongs to another program.
        $other = $this->target('local_program_levels', 110);
        $DB->set_field('local_sentientia_programs_users', 'currentlevelid', $other, ['id' => $this->enrolment(41, 'uC')->id]);
        $this->assertSame(['current_level_outside_program:1'], $importer->verify($ctx));

        // Once the site is open the structural checks stop (administrators may edit).
        set_config('bizlms_production_open', 1, 'local_sentientia_platform');
        $this->assertSame([], $importer->verify($ctx));
    }

    public function test_the_importer_declares_what_the_mapping_doc_says(): void {
        $importer = new program_importer();
        $this->assertSame('program', $importer->feature());
        $this->assertSame('local_sentientia_programs', $importer->component());
        $this->assertSame(['org'], $importer->depends(), 'tenant paths come from the organisations');
        $this->assertEqualsCanonicalizing([
            'local_program', 'local_program_levels', 'local_program_level_courses', 'local_program_users',
            'local_bc_level_completions', 'local_bcl_cmplt_criteria', 'local_bc_completion_criteria',
            'local_program_trainers', 'local_program_trainerfb', 'local_program_completions_bk',
            'local_bc_level_comp_bk', 'local_program_test_score',
        ], array_keys($importer->sources()), 'all twelve tables of local_program');
        $this->assertSame([], $importer->declined_tables());
        $this->assertEqualsCanonicalizing([
            'local_sentientia_programs', 'local_sentientia_programs_levels', 'local_sentientia_programs_courses',
            'local_sentientia_programs_users', 'local_sentientia_programs_lvlcomp', 'local_sentientia_programs_trainers',
            'local_sentientia_programs_trainerfb',
        ], $importer->target_tables());
        $this->assertSame([], $importer->core_writes(), 'no enrolment, completion or tag is written');
        $this->assertSame(['local_sentientia_programs' => 'open_path'], $importer->tenant_columns());
        $this->assertTrue($importer->atomic());
        $this->assertGreaterThanOrEqual($importer->requires_version(),
            (int) get_config('local_sentientia_programs', 'version'));

        $preserve = [];
        foreach ($importer->steps() as $step) {
            if ($step instanceof \local_sentientia_platform\bizlms\step && $step->idpolicy() === idpolicy::PRESERVE) {
                $preserve[] = $step->targettable();
            }
        }
        $this->assertSame(['local_sentientia_programs'], $preserve, 'only the program id is stored elsewhere');
        $codes = array_map(static fn($reason) => $reason->code, $importer->reasons());
        $needsowner = array_map(static fn($reason) => $reason->code,
            array_filter($importer->reasons(), static fn($reason) => $reason->needsowner));
        $this->assertContains('empty_level', $codes);
        $this->assertContains('parent_skipped', $codes);
        $this->assertContains('criteria_program_mismatch', $codes);
        $this->assertEqualsCanonicalizing(['no_name', 'tenant_unresolved', 'bk_rows_archived'], array_values($needsowner),
            'a child of a skipped program is not a second thing for the owner to decide');
    }

    public function test_every_decision_the_importer_declares_is_accepted_in_the_signed_file(): void {
        $signed = decisions::load(__DIR__ . '/../../sentientia_platform/tests/fixtures/bizlms/bizlms-import-decisions.copy.json');
        foreach ((new program_importer())->decisions() as $decision) {
            $this->assertTrue($signed->has($decision->key), $decision->key . ' is accepted in the signed decisions file');
            if ($decision->allowed !== null) {
                $this->assertContains($signed->get($decision->key), $decision->allowed, $decision->key);
            }
        }
    }

    // What the readers show of the imported data.

    public function test_a_learner_sees_their_own_active_visible_programs_of_their_tenant(): void {
        $this->apply();
        $uA = \core_user::get_user($this->w['uA']);
        $this->setUser($uA);

        $programs = learner_view::programs_for_user((int) $uA->id);
        $this->assertSame([41], array_map('intval', array_keys($programs)),
            'P2 is switched off (archived, hidden): not shown to learners, only to admins');

        $data = learner_view::page_data((int) $uA->id);
        $this->assertTrue($data['has_programs']);
        $p = $data['programs'][0];
        $this->assertSame('Payments Certification', $p['name']);
        $this->assertTrue($p['completed']);
        $this->assertSame(100, $p['overall_pct'], 'the enrolment says completed: the program is');
        $this->assertSame(3, $p['total_levels'], 'the five empty levels were never imported, and none is counted');
        $byname = array_column($p['levels'], null, 'name');
        $this->assertTrue($byname['Foundation']['completed']);
        $this->assertTrue($byname['Foundation']['stored']);
        $this->assertNotSame('', $byname['Foundation']['timecompleted_human']);
        $this->assertTrue($byname['Advanced']['stored']);
        $this->assertFalse($byname['Optional']['completed'], 'live data for a level with no stored completion');
        $this->assertFalse($byname['Optional']['completion_required']);

        // A learner in progress: the first level is open and not done.
        $uC = \core_user::get_user($this->w['uC']);
        $state = program_manager::get_user_program_state(41, (int) $uC->id);
        $this->assertFalse($state['completed']);
        $this->assertSame($this->target('local_program_levels', 101), $state['current_level_id']);

        // A learner who is not enrolled anywhere sees nothing.
        $this->setUser($this->w['uH']);
        $this->assertSame([], learner_view::programs_for_user($this->w['uH']));
    }

    public function test_the_learner_page_is_off_until_its_flag_is_turned_on(): void {
        $this->apply();
        \local_sentientia_platform\feature_flags::invalidate_caches();
        $this->assertFalse(learner_view::enabled(), 'default OFF');
        \local_sentientia_platform\feature_flags::set(learner_view::FLAG_LEARNER, 0, true, null, 'phpunit', 0);
        \local_sentientia_platform\feature_flags::invalidate_caches();
        $this->assertTrue(learner_view::enabled());
    }

    public function test_the_roster_hides_deleted_users_and_offers_no_trash_action_for_imported_rows(): void {
        global $DB;
        $this->apply();
        $this->setAdminUser();

        // The admin roster: six live learners, the deleted one is history that readers filter.
        $this->assertSame(6, program_manager::count_enrolled(41));
        $this->assertSame(6, program_manager::count_enrolled_filtered(41));
        $users = program_manager::get_enrolled_users(41);
        $this->assertNotContains($this->w['uF'], array_map(static fn($r) => (int) $r->userid, $users));

        // A learner enrolled after the import can be removed; an imported one cannot.
        $native = (int) $this->getDataGenerator()->create_user(['open_path' => '/1/5'])->id;
        $this->assertSame(1, program_manager::enrol_users(41, [$native]));
        $this->assertEquals(get_admin()->id,
            $DB->get_field('local_sentientia_programs_users', 'enrolledby', ['programid' => 41, 'userid' => $native]),
            'the acting user is recorded');
        $byuser = array_column($this->roster(41), null, 'userid');
        $this->assertNotSame('', $byuser[$native]['actions'], 'a native enrolment has the trash action');
        $this->assertSame('', $byuser[$this->w['uA']]['actions'], 'an imported one has none');
        $this->assertSame('', $byuser[$this->w['uA']]['completed_at'], 'the Completed on column is behind the history flag');

        \local_sentientia_platform\feature_flags::invalidate_caches();
        \local_sentientia_platform\feature_flags::set(learner_view::FLAG_HISTORY, 0, true, null, 'phpunit', 0);
        \local_sentientia_platform\feature_flags::invalidate_caches();
        $rows = array_column($this->roster(41), null, 'userid');
        $this->assertNotSame('', $rows[$this->w['uA']]['completed_at']);
        $this->assertNotSame('—', $rows[$this->w['uA']]['completed_at']);
        $this->assertSame('—', $rows[$this->w['uC']]['completed_at'], 'not completed');
    }

    public function test_imported_history_cannot_be_deleted_from_the_admin_actions(): void {
        global $DB;
        $this->apply();
        $this->setAdminUser();

        try {
            program_manager::unenrol_user(41, $this->w['uA']);
            $this->fail('an imported enrolment is history');
        } catch (\moodle_exception $e) {
            $this->assertSame('error_history_protected', $e->errorcode);
        }
        try {
            program_manager::delete(41);
            $this->fail('a program with imported history is archived, not deleted');
        } catch (\moodle_exception $e) {
            $this->assertSame('error_history_protected', $e->errorcode);
        }
        try {
            program_manager::delete_level($this->target('local_program_levels', 101));
            $this->fail('somebody has a stored completion for this level');
        } catch (\moodle_exception $e) {
            $this->assertSame('error_history_protected', $e->errorcode);
        }
        $this->assertTrue($DB->record_exists('local_sentientia_programs', ['id' => 41]));
        $this->assertSame(4, $DB->count_records('local_sentientia_programs_lvlcomp'));

        // An imported level is refused too, completion or not: it is the same rule as the program's.
        $optional = $this->target('local_program_levels', 108);
        try {
            program_manager::delete_level($optional);
            $this->fail('an imported level is not deleted');
        } catch (\moodle_exception $e) {
            $this->assertSame('error_history_protected', $e->errorcode);
        }
        $this->assertTrue($DB->record_exists('local_sentientia_programs_levels', ['id' => $optional]));

        // A level a person adds to the imported program goes as before, and archiving always works.
        $added = program_manager::create_level(41, (object) ['name' => 'Added in Sentientia']);
        $this->assertTrue(program_manager::delete_level($added));
        $this->assertSame(program_manager::STATUS_ARCHIVED, program_manager::change_status(41, program_manager::STATUS_ARCHIVED));
        // A program the import did not touch is deleted as before.
        $native = program_manager::create((object) ['name' => 'Native']);
        $this->assertTrue(program_manager::delete($native));
    }

    public function test_an_imported_program_with_nothing_imported_under_it_is_not_deleted(): void {
        global $DB;
        $this->apply();
        $this->setAdminUser();

        // Program 45 kept only its criteria row: no level, no enrolment, no trainer. Its row is the history.
        $this->assertSame(0, $DB->count_records('local_sentientia_programs_levels', ['programid' => 45]));
        $this->assertSame(0, $DB->count_records('local_sentientia_programs_users', ['programid' => 45]));
        $this->assertTrue(program_manager::program_has_imported_history(45));
        try {
            program_manager::delete(45);
            $this->fail('the imported program row is what other tables point at');
        } catch (\moodle_exception $e) {
            $this->assertSame('error_history_protected', $e->errorcode);
        }
        $this->assertTrue($DB->record_exists('local_sentientia_programs', ['id' => 45]));
    }

    public function test_a_tenant_admin_reads_only_their_own_tenants_imported_programs(): void {
        $this->apply();
        $manager = $this->tenant_manager('/77');
        $this->setUser($manager);

        // /77 holds P2 and P3; P1 is /1/5 and P5 has no path (cross-tenant only).
        foreach ([42, 43] as $id) {
            program_manager::assert_program_in_scope(program_manager::get($id));
        }
        foreach ([41, 45] as $id) {
            try {
                program_manager::assert_program_in_scope(program_manager::get($id));
                $this->fail("program {$id} is outside the tenant of a /77 admin");
            } catch (\moodle_exception $e) {
                $this->assertSame('error_outoftenant', $e->errorcode);
            }
        }

        // The roster of the program they can reach lists only their own tenant's learners: uA is in /1/5.
        $this->assertSame(0, program_manager::count_enrolled_filtered(42, '', true));
        $this->assertSame(1, program_manager::count_enrolled_filtered(42), 'unscoped: uA is on it');

        // A site admin still sees every tenant.
        $this->setAdminUser();
        program_manager::assert_program_in_scope(program_manager::get(45));
        $this->assertSame(1, program_manager::count_enrolled_filtered(42, '', true));
    }

    /**
     * The rows the roster web service returns for a program.
     *
     * @param int $programid
     * @return array[]
     */
    private function roster(int $programid): array {
        return external\list_program_users::execute($programid, '', 'lastname', 'asc', 0, 100)['rows'];
    }

    /**
     * A user with the manager role at system level, placed in a tenant.
     *
     * @param string $path
     * @return \stdClass
     */
    private function tenant_manager(string $path): \stdClass {
        global $DB;
        $user = $this->getDataGenerator()->create_user(['open_path' => $path]);
        role_assign((int) $DB->get_field('role', 'id', ['shortname' => 'manager'], MUST_EXIST), $user->id,
            \context_system::instance()->id);
        return $DB->get_record('user', ['id' => $user->id], '*', MUST_EXIST);
    }

    public function test_the_program_logo_is_served_to_the_readers_whose_flag_is_on(): void {
        $this->apply();
        $this->assertNull(program_manager::program_logo_url(42), 'no logo');
        $this->assertNotNull(program_manager::program_logo_url(41));

        // Flags OFF: nobody is allowed, not even an admin.
        \local_sentientia_platform\feature_flags::invalidate_caches();
        $this->setAdminUser();
        $this->assertFalse(program_manager::can_view_program_logo(41));
        $this->setUser($this->w['uA']);
        $this->assertFalse(program_manager::can_view_program_logo(41));

        // The learner flag: an enrolled learner of an active, visible program.
        \local_sentientia_platform\feature_flags::set(learner_view::FLAG_LEARNER, 0, true, null, 'phpunit', 0);
        \local_sentientia_platform\feature_flags::invalidate_caches();
        $this->assertTrue(program_manager::can_view_program_logo(41));
        $this->setUser($this->w['uD']);
        $this->assertTrue(program_manager::can_view_program_logo(41), 'enrolled, not started');
        $this->setUser($this->w['uH']);
        $this->assertFalse(program_manager::can_view_program_logo(41), 'not enrolled');
        $this->setUser($this->w['uG']);
        $this->assertFalse(program_manager::can_view_program_logo(41), 'another tenant');

        // The history flag: an admin of the program's tenant.
        \local_sentientia_platform\feature_flags::set(learner_view::FLAG_HISTORY, 0, true, null, 'phpunit', 0);
        \local_sentientia_platform\feature_flags::invalidate_caches();
        $this->setAdminUser();
        $this->assertTrue(program_manager::can_view_program_logo(41));
        $this->setUser($this->tenant_manager('/77'));
        $this->assertFalse(program_manager::can_view_program_logo(41), 'a /77 admin cannot open a /1/5 program logo');
    }
}
