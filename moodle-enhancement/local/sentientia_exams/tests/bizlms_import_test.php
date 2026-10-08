<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

namespace local_sentientia_exams;

defined('MOODLE_INTERNAL') || die();

use local_sentientia_exams\bizlms\exam_quiz_step;
use local_sentientia_exams\bizlms\exam_step;
use local_sentientia_exams\bizlms\exams_importer;
use local_sentientia_exams\bizlms\reminder_seed_step;
use local_sentientia_exams\tests\bizlms\stub_org_importer;
use local_sentientia_platform\bizlms\decisions;
use local_sentientia_platform\bizlms\importer as framework_importer;
use local_sentientia_platform\bizlms\legacymap;
use local_sentientia_platform\bizlms\registry;
use local_sentientia_platform\bizlms\report;
use local_sentientia_platform\bizlms\runner;
use local_sentientia_platform\phpunit\importer_contract;
use local_sentientia_platform\phpunit\legacy_schema_fixture;

/**
 * The exams feature of the BizLMS import (ADR-032, mapping doc section 9): the quizzes of the online exam courses
 * become local_sentientia_exams rows, and the overdue escalation of their past deadlines is marked as already sent.
 *
 * The importer contract (tests/bizlms of local_sentientia_platform, "Test approach" 4) runs against a seed with every
 * kind of source the feature can meet:
 *
 *   exam courses (open_module online_exams, open_coursetype 1)
 *     basic       /1/5, one quiz: time limit, pass mark 7 of 10, a deadline three days ago, a category; its
 *                 roster is a pending learner, one who finished, a suspended user, a deleted user and a learner
 *                 whose enrolment is suspended
 *     padded      /1/5/9/ (a trailing slash), no time limit, no pass mark
 *     walked      /1/5/4, an organisation that is gone: it walks up to /1/5
 *     multi       /77, two quizzes (one exam per quiz); the second one has a deadline two days ago and a pending learner
 *     nopath      no path at all
 *     badroot     /999/1, a root that is not a tenant
 *     hidden      /177, hidden
 *     registered  /1, an ordinary exam course (the test that pre-registers its quiz is separate)
 *     empty       /1, no quiz
 *   not exam courses
 *     ordinary    open_coursetype 0, holds a quiz
 *     forum       open_module forum, open_coursetype 1, holds a quiz
 *
 * The tests after the contract check the column map, the tenant attribution, the owner's decisions, the reasons,
 * the dedupe rows that keep exam_overdue quiet, that nothing outside the two target tables changes, the two
 * reader fixes shipped with the importer, and that a tenant admin reads only their own tenant's exams.
 *
 * The org feature is registered as a stub (tests/classes/bizlms/stub_org_importer.php): the real org importer is
 * another plugin's, and the registry refuses a tenant importer that does not depend on it.
 *
 * @package    local_sentientia_exams
 * @category   test
 * @covers     \local_sentientia_exams\bizlms\exams_importer
 * @covers     \local_sentientia_exams\bizlms\exam_quiz_step
 * @covers     \local_sentientia_exams\bizlms\exam_step
 * @covers     \local_sentientia_exams\bizlms\reminder_seed_step
 * @covers     \local_sentientia_exams\exam_manager
 *
 * @group local_sentientia_exams
 * @group bizlms_import
 */
final class bizlms_import_test extends \advanced_testcase {
    use legacy_schema_fixture {
        // This class adds its own setUp() (the BizLMS course columns), so the trait's is reached by this name.
        setUp as protected legacy_fixture_setup;
    }
    use importer_contract;
    use \local_sentientia_org\test\bizlms_fixture;

    /** @var int Timestamp base of the seed: a course made on 2020-09-13. */
    private const T0 = 1600000000;

    /** @var int Counter behind unique quiz attempt ids (quiz_attempts.uniqueid is a unique index). */
    private int $attemptseq = 0;

    /** @var int The time the seed was made, the base of the deadlines. */
    private int $now = 0;

    /** @var array<string, int> Course ids by name. */
    private array $course = [];

    /** @var array<string, int> Quiz ids by name. */
    private array $quiz = [];

    /** @var array<string, int> User ids by name. */
    private array $user = [];

    /** @var int The category of the basic course. */
    private int $categoryid = 0;

    protected static function legacy_fixture_definition(): array {
        return ['xml' => __DIR__ . '/fixtures/bizlms/onlineexams.install.xml'];
    }

    /**
     * The trait's setUp() (resets after the test, truncates the legacy tables), then the BizLMS columns the
     * seed writes: user.open_path and course.open_path from the org plugin's fixture, and the two course
     * columns that mark an exam. Idempotent, and DDL on a core table, so it runs after the parent's reset.
     *
     * @return void
     */
    protected function setUp(): void {
        $this->legacy_fixture_setup();
        $this->ensure_bizlms_schema();
        $this->ensure_exam_course_columns();
    }

    /**
     * course.open_module and course.open_coursetype, as BizLMS's local_onlineexams install step adds them
     * (BZ local/onlineexams/db/install.php:29-37). A column another test class added earlier stays as it is.
     *
     * open_coursetype is created NULLABLE, the shape open_path_fixture_trait gives it, not as production's
     * NOT NULL DEFAULT 0: DDL on a core table outlives the test, and the catalog suites write NULL into it.
     * Every course of this seed gets an explicit value, so the difference changes nothing here.
     *
     * @return void
     */
    private function ensure_exam_course_columns(): void {
        global $DB;
        $dbman = $DB->get_manager();
        $table = new \xmldb_table('course');
        $fields = [
            new \xmldb_field('open_module', XMLDB_TYPE_CHAR, '255', null, null, null, null),
            new \xmldb_field('open_coursetype', XMLDB_TYPE_INTEGER, '10', null, null, null, null),
        ];
        foreach ($fields as $field) {
            if (!$dbman->field_exists($table, $field)) {
                $dbman->add_field($table, $field);
            }
        }
    }

    protected function contract_importer(): framework_importer {
        return new exams_importer();
    }

    /**
     * The importer depends on org, which is not this plugin's, so the org feature is registered as a stub next to it.
     * A class method overrides the trait's, and every contract test calls it through $this.
     *
     * @return framework_importer
     */
    protected function contract_begin(): framework_importer {
        $this->resetAfterTest();
        $importer = $this->contract_importer();
        registry::set_testing_importers([new stub_org_importer(), $importer]);
        return $importer;
    }

    /**
     * The owner's two choices, as the signed file records them (status accepted).
     *
     * @param array<string, mixed> $override Replaces a value, for the tests of a different choice.
     * @return decisions
     */
    protected function contract_decisions(array $override = []): decisions {
        return decisions::from_array($override + [
            'tenant.unresolved.exams' => 'pathless',
            'exams.multi_quiz' => 'per_quiz',
        ]);
    }

    protected function contract_user_columns(): array {
        return ['local_sentientia_exams_remind_sent' => ['userid']];
    }

    // Seeding.

    /**
     * Organisations with explicit ids, because an organisation's path ends in its own id: /1, /1/5, /1/5/9, /77, /177.
     *
     * @return void
     */
    private function seed_orgs(): void {
        global $DB;
        foreach ([[1, 0, '/1'], [5, 1, '/1/5'], [9, 5, '/1/5/9'], [77, 0, '/77'], [177, 0, '/177']] as [$id, $parent, $path]) {
            $DB->import_record('local_sentientia_org', (object) [
                'id' => $id, 'fullname' => 'Org ' . $id, 'shortname' => 'org' . $id, 'parentid' => $parent,
                'depth' => substr_count($path, '/'), 'visible' => 1, 'sortorder' => 0, 'path' => $path,
                'timecreated' => self::T0, 'timemodified' => self::T0,
            ]);
        }
    }

    /**
     * One course, with the BizLMS columns and the timestamps the test expects to find on the exam.
     *
     * @param string $fullname
     * @param string|null $path course.open_path
     * @param string|null $module course.open_module
     * @param int $type course.open_coursetype
     * @param int $visible
     * @param int|null $category
     * @return int Course id.
     */
    private function make_course(string $fullname, ?string $path, ?string $module, int $type, int $visible = 1,
                                 ?int $category = null): int {
        global $DB;
        static $n = 0;
        $record = ['fullname' => $fullname, 'shortname' => 'exim' . (++$n) . '_' . random_int(1000, 9999),
            'visible' => $visible];
        if ($category !== null) {
            $record['category'] = $category;
        }
        $course = $this->getDataGenerator()->create_course($record);
        $DB->update_record('course', (object) [
            'id' => $course->id, 'open_path' => $path, 'open_module' => $module, 'open_coursetype' => $type,
            'timecreated' => self::T0 + 10, 'timemodified' => self::T0 + 20,
        ]);
        return (int) $course->id;
    }

    /**
     * A quiz with a grade item out of 10.
     *
     * @param int $courseid
     * @param string $name
     * @param array<string, mixed> $extra timelimit, timeclose.
     * @return int Quiz id.
     */
    private function make_quiz(int $courseid, string $name, array $extra = []): int {
        $quiz = $this->getDataGenerator()->create_module('quiz', $extra + [
            'course' => $courseid, 'name' => $name, 'grade' => 10,
        ]);
        return (int) $quiz->id;
    }

    /**
     * Give a quiz a pass mark of $pass out of 10 (Moodle keeps it in the quiz's grade item).
     *
     * @param int $quizid
     * @param float $pass
     * @return void
     */
    private function set_pass_mark(int $quizid, float $pass): void {
        global $DB;
        $where = ['itemtype' => 'mod', 'itemmodule' => 'quiz', 'iteminstance' => $quizid, 'itemnumber' => 0];
        $this->assertTrue($DB->record_exists('grade_items', $where), 'the quiz has a grade item');
        $DB->set_field('grade_items', 'grademax', 10, $where);
        $DB->set_field('grade_items', 'gradepass', $pass, $where);
    }

    /**
     * A quiz attempt, written directly (every NOT NULL column given).
     *
     * @param int $quizid
     * @param int $userid
     * @param string $state
     * @param float|null $sumgrades
     * @return void
     */
    private function add_attempt(int $quizid, int $userid, string $state, ?float $sumgrades): void {
        global $DB;
        $DB->insert_record('quiz_attempts', (object) [
            'quiz' => $quizid, 'userid' => $userid, 'attempt' => 1, 'uniqueid' => 700000 + (++$this->attemptseq),
            'layout' => '', 'currentpage' => 0, 'preview' => 0, 'state' => $state,
            'timestart' => self::T0 + 100, 'timefinish' => $state === 'finished' ? self::T0 + 200 : 0,
            'timemodified' => self::T0 + 200, 'timemodifiedoffline' => 0, 'timecheckstate' => null,
            'sumgrades' => $sumgrades, 'gradednotificationsenttime' => null,
        ]);
    }

    /**
     * Eleven quizzes in eleven courses (nine of them exam courses, one of those with no quiz), and the learners the
     * dedupe seed needs (see the class comment).
     *
     * @return void
     */
    protected function contract_seed(): void {
        global $DB;
        $this->now = time();
        $this->seed_orgs();
        $gen = $this->getDataGenerator();
        $this->categoryid = (int) $gen->create_category(['name' => 'Compliance exams'])->id;

        $m = exam_quiz_step::MODULE_EXAMS;
        $this->course = [
            'basic' => $this->make_course('Safety Exam', '/1/5', $m, 1, 1, $this->categoryid),
            'padded' => $this->make_course('Padded Path Exam', '/1/5/9/', $m, 1),
            'walked' => $this->make_course('Walked Up Exam', '/1/5/4', $m, 1),
            'multi' => $this->make_course('Compliance', '/77', $m, 1),
            'nopath' => $this->make_course('No Path Exam', null, $m, 1),
            'badroot' => $this->make_course('Foreign Root Exam', '/999/1', $m, 1),
            'hidden' => $this->make_course('Hidden Exam', '/177', $m, 1, 0),
            'registered' => $this->make_course('Registered Exam', '/1', $m, 1),
            'empty' => $this->make_course('Empty Exam Course', '/1', $m, 1),
            'ordinary' => $this->make_course('Ordinary Course', '/1', null, 0),
            'forum' => $this->make_course('Forum Course', '/1', 'forum', 1),
        ];
        $c = $this->course;

        $this->quiz = [
            'basic' => $this->make_quiz($c['basic'], 'Safety quiz', ['timelimit' => 1800, 'timeclose' => $this->now - 3 * DAYSECS]),
            'padded' => $this->make_quiz($c['padded'], 'Padded quiz'),
            'walked' => $this->make_quiz($c['walked'], 'Walked quiz'),
            'multia' => $this->make_quiz($c['multi'], 'Part A', ['timelimit' => 600]),
            'multib' => $this->make_quiz($c['multi'], 'Part B', ['timeclose' => $this->now - 2 * DAYSECS]),
            'nopath' => $this->make_quiz($c['nopath'], 'No path quiz'),
            'badroot' => $this->make_quiz($c['badroot'], 'Foreign root quiz'),
            'hidden' => $this->make_quiz($c['hidden'], 'Hidden quiz'),
            'registered' => $this->make_quiz($c['registered'], 'Registered quiz'),
            'ordinary' => $this->make_quiz($c['ordinary'], 'Ordinary quiz'),
            'forum' => $this->make_quiz($c['forum'], 'Forum quiz'),
        ];
        $this->set_pass_mark($this->quiz['basic'], 7);
        $this->set_pass_mark($this->quiz['hidden'], 0);

        // The rosters.
        $this->user = [
            'pending' => (int) $gen->create_user()->id,
            'done' => (int) $gen->create_user()->id,
            'suspended' => (int) $gen->create_user(['suspended' => 1])->id,
            'gone' => (int) $gen->create_user()->id,
            'inactive' => (int) $gen->create_user()->id,
            'multi' => (int) $gen->create_user()->id,
        ];
        foreach (['pending', 'done', 'suspended', 'gone'] as $name) {
            $gen->enrol_user($this->user[$name], $c['basic']);
        }
        $gen->enrol_user($this->user['inactive'], $c['basic'], null, 'manual', 0, 0, ENROL_USER_SUSPENDED);
        $gen->enrol_user($this->user['multi'], $c['multi']);
        $DB->set_field('user', 'deleted', 1, ['id' => $this->user['gone']]);
        $this->add_attempt($this->quiz['basic'], $this->user['done'], 'finished', 6.0);
    }

    protected function contract_mutate_source(): void {
        // One more quiz in an exam course: the source's row count and highest id change.
        $this->make_quiz($this->course['basic'], 'Added after the run started');
    }

    // Helpers for the assertions.

    /**
     * The exam row of a quiz.
     *
     * @param string $quizname Key of $this->quiz.
     * @return \stdClass
     */
    private function exam_of(string $quizname): \stdClass {
        global $DB;
        return $DB->get_record('local_sentientia_exams', ['quizid' => $this->quiz[$quizname]], '*', MUST_EXIST);
    }

    /**
     * One section of a step in a report.
     *
     * @param report $report
     * @param string $step Step key.
     * @param string $section warnings, skipped_by_reason, tenant_methods or counters.
     * @return array
     */
    private function step_section(report $report, string $step, string $section): array {
        return $report->to_array()['features']['exams']['steps'][$step][$section] ?? [];
    }

    /**
     * Seed, apply with the owner's decisions, and fail the test with the run's own words when it does not finish.
     *
     * @param array<string, mixed> $decisions Override of the signed values.
     * @return report
     */
    private function seed_and_apply(array $decisions = []): report {
        $this->contract_begin();
        $this->contract_seed();
        [$result, $report] = $this->contract_run(true, ['decisions' => $this->contract_decisions($decisions)]);
        $this->assertSame(0, $result['exit'], implode('; ', array_merge($result['blockers'], $result['unproven'])));
        return $report;
    }

    /**
     * A tenant admin: the stock manager-archetype role at system context.
     *
     * @param string $path
     * @return \stdClass
     */
    private function tenant_admin(string $path): \stdClass {
        global $DB;
        $user = $this->getDataGenerator()->create_user();
        $DB->set_field('user', 'open_path', $path, ['id' => $user->id]);
        $managerid = (int) $DB->get_field('role', 'id', ['shortname' => 'manager'], MUST_EXIST);
        role_assign($managerid, $user->id, \context_system::instance()->id);
        accesslib_clear_all_caches_for_unit_testing();
        return $DB->get_record('user', ['id' => $user->id], '*', MUST_EXIST);
    }

    // The contract, where this feature differs from a feature with legacy tables.

    /**
     * The contract's version drops every table the importer claims. This importer claims quiz, a core table, and
     * dropping it would break the test database for every class after this one, so this class says its own thing:
     * with no exam course at all the feature is applicable (quiz is there), finds nothing, writes nothing and still
     * completes. A BizLMS database that never had an exam has exactly that.
     *
     * @return void
     */
    public function test_contract_not_applicable_without_tables(): void {
        global $DB;
        $importer = $this->contract_begin();
        $this->seed_orgs();
        $quizzes = $DB->count_records('quiz');
        [$result, $report] = $this->contract_run(true, ['decisions' => $this->contract_decisions()]);
        $this->assertSame(0, $result['exit'], implode('; ', $result['blockers']));
        $this->assertSame('complete', $result['features'][$importer->feature()]);
        $this->assertSame(0, $DB->count_records(legacymap::TABLE), 'no exam course, no map row');
        $this->assertSame(0, $DB->count_records('local_sentientia_exams'));
        $this->assertSame($quizzes, $DB->count_records('quiz'), 'the quiz table is the source and is never touched');
        $counts = $report->to_array()['features']['exams']['preflight']['counts'];
        $this->assertSame(0, $counts['exam_courses_with_a_quiz']);
    }

    // The column map.

    public function test_exam_columns_are_mapped_and_the_source_times_are_kept(): void {
        global $DB;
        $this->seed_and_apply();
        $this->assertSame(9, $DB->count_records('local_sentientia_exams'), 'one exam per quiz of an exam course');

        $basic = $this->exam_of('basic');
        $this->assertSame('Safety Exam', $basic->name);
        $this->assertSame(5, (int) $basic->costcenterid, 'the organisation at /1/5');
        $this->assertSame(5, (int) $basic->departmentid, 'the second segment of the path');
        $this->assertSame($this->categoryid, (int) $basic->categoryid);
        $this->assertSame('/1/5', $basic->open_path);
        $this->assertSame(1800, (int) $basic->duration, 'seconds, from quiz.timelimit');
        $this->assertEqualsWithDelta(70.0, (float) $basic->passinggrade, 0.001, 'a pass mark of 7 out of 10 is 70 percent');
        $this->assertSame(1, (int) $basic->status);
        $this->assertSame(1, (int) $basic->visible);
        $this->assertSame(self::T0 + 10, (int) $basic->timecreated, 'the course\'s own creation time, not the import time');
        $this->assertSame(self::T0 + 20, (int) $basic->timemodified);

        $padded = $this->exam_of('padded');
        $this->assertNull($padded->duration, 'a time limit of 0 is no time limit');
        $this->assertNull($padded->passinggrade, 'no pass mark: view.php then reads 50');
        $this->assertSame((int) $DB->get_field('course', 'category', ['id' => $this->course['padded']]),
            (int) $padded->categoryid, 'the course\'s own category (the default one here), not the basic course\'s');
        $this->assertNotSame($this->categoryid, (int) $padded->categoryid);

        $hidden = $this->exam_of('hidden');
        $this->assertSame(0, (int) $hidden->status, 'a hidden course is an inactive, hidden exam');
        $this->assertSame(0, (int) $hidden->visible);
        $this->assertNull($hidden->passinggrade, 'a pass mark of 0 is none');
        $this->assertSame(177, (int) $hidden->costcenterid);
        $this->assertNull($hidden->departmentid, 'a tenant root has no second segment');
    }

    public function test_a_course_with_two_quizzes_gives_one_exam_per_quiz(): void {
        global $DB;
        $report = $this->seed_and_apply();
        $first = $this->exam_of('multia');
        $second = $this->exam_of('multib');
        $this->assertSame('Compliance — Part A', $first->name);
        $this->assertSame('Compliance — Part B', $second->name);
        $this->assertSame(600, (int) $first->duration);
        $this->assertSame('/77', $first->open_path);
        $this->assertSame('/77', $second->open_path);

        // The map key is the COURSE id: '' for the lowest quiz, 'quiz:<id>' for each further one.
        $course = $this->course['multi'];
        $primary = $DB->get_record(legacymap::TABLE,
            ['sourcetable' => exam_step::SOURCE, 'sourceid' => $course, 'subkey' => ''], '*', MUST_EXIST);
        $this->assertSame((int) $first->id, (int) $primary->targetid);
        $this->assertSame('imported', $primary->outcome);
        $sub = $DB->get_record(legacymap::TABLE, [
            'sourcetable' => exam_step::SOURCE, 'sourceid' => $course, 'subkey' => 'quiz:' . $this->quiz['multib'],
        ], '*', MUST_EXIST);
        $this->assertSame((int) $second->id, (int) $sub->targetid);
        $this->assertSame(1, (int) ($this->step_section($report, 'exams.exam', 'warnings')['multi_quiz'] ?? 0),
            'a course with several quizzes is flagged once');
    }

    public function test_only_exam_courses_are_read(): void {
        global $DB;
        $this->seed_and_apply();
        $this->assertFalse($DB->record_exists('local_sentientia_exams', ['quizid' => $this->quiz['ordinary']]),
            'an ordinary course\'s quiz is not an exam');
        $this->assertFalse($DB->record_exists('local_sentientia_exams', ['quizid' => $this->quiz['forum']]),
            'a forum pseudo-course shares open_coursetype 1 but is not an exam');
        $this->assertFalse($DB->record_exists(legacymap::TABLE,
            ['sourcetable' => exam_step::SOURCE, 'sourceid' => $this->course['empty']]), 'an exam course with no quiz has nothing to wrap');
    }

    // Tenant.

    public function test_the_tenant_path_is_normalised_walked_up_and_never_global(): void {
        $report = $this->seed_and_apply();

        $padded = $this->exam_of('padded');
        $this->assertSame('/1/5/9', $padded->open_path, 'a trailing slash is not part of the path');
        $this->assertSame(9, (int) $padded->costcenterid);
        $this->assertSame(5, (int) $padded->departmentid);

        $walked = $this->exam_of('walked');
        $this->assertSame('/1/5', $walked->open_path, 'the organisation /1/5/4 is gone: the exam is homed at its parent');
        $this->assertSame(5, (int) $walked->costcenterid);

        foreach (['nopath', 'badroot'] as $name) {
            $exam = $this->exam_of($name);
            $this->assertNull($exam->open_path, $name . ': no tenant path, so cross-tenant callers only');
            $this->assertSame(0, (int) $exam->costcenterid, $name);
            $this->assertNull($exam->departmentid, $name);
        }

        $methods = $this->step_section($report, 'exams.exam', 'tenant_methods');
        $this->assertSame(2, (int) ($methods['unresolved'] ?? 0), 'the two pathless exams are always reported');
        $this->assertSame(1, (int) ($methods['normalised'] ?? 0));
        $this->assertSame(1, (int) ($methods['walked_up'] ?? 0));
    }

    public function test_deciding_skip_leaves_the_pathless_courses_out_and_reports_them(): void {
        global $DB;
        // A skipped row is not imported, so its reason needs the owner's written acceptance or the run exits 2.
        $report = $this->seed_and_apply([
            'tenant.unresolved.exams' => 'skip',
            'accepted_reasons' => ['exams:tenant_unresolved'],
        ]);
        $this->assertSame(7, $DB->count_records('local_sentientia_exams'), 'nopath and badroot are not imported');
        foreach (['nopath', 'badroot'] as $name) {
            $map = $DB->get_record(legacymap::TABLE,
                ['sourcetable' => exam_step::SOURCE, 'sourceid' => $this->course[$name], 'subkey' => ''], '*', MUST_EXIST);
            $this->assertSame('skipped', $map->outcome);
            $this->assertSame(exams_importer::REASON_TENANT_UNRESOLVED, $map->reason);
            $this->assertNull($map->targetid);
        }
        $this->assertSame(2, (int) ($this->step_section($report, 'exams.exam', 'skipped_by_reason')['tenant_unresolved'] ?? 0));
    }

    /**
     * @group tenant_isolation
     */
    public function test_a_tenant_admin_reads_only_their_own_tenants_imported_exams(): void {
        $this->seed_and_apply();

        $this->setUser($this->tenant_admin('/1'));
        $this->assertSame(4, exam_manager::count_scoped(),
            '/1: basic /1/5, padded /1/5/9, walked /1/5 and registered /1; never /77, /177 or a pathless exam');

        $this->setUser($this->tenant_admin('/77'));
        $this->assertSame(2, exam_manager::count_scoped(), '/77: the two quizzes of the multi-quiz course');

        $this->setUser($this->tenant_admin('/177'));
        $this->assertSame(1, exam_manager::count_scoped(), '/177: the hidden exam');

        $this->setAdminUser();
        $this->assertSame(9, exam_manager::count_scoped(), 'a cross-tenant caller sees every exam, pathless ones included');
    }

    // A quiz that already has an exam.

    public function test_a_quiz_that_already_has_an_exam_is_never_wrapped_twice(): void {
        global $DB;
        $this->contract_begin();
        $this->contract_seed();
        $mine = $DB->insert_record('local_sentientia_exams', (object) [
            'name' => 'Made by hand', 'quizid' => $this->quiz['registered'], 'costcenterid' => 1, 'departmentid' => null,
            'categoryid' => 0, 'open_path' => '/1', 'duration' => null, 'passinggrade' => 55, 'status' => 1, 'visible' => 1,
            'timecreated' => self::T0, 'timemodified' => self::T0,
        ]);
        [$result, $report] = $this->contract_run(true, ['decisions' => $this->contract_decisions()]);
        $this->assertSame(0, $result['exit'], implode('; ', array_merge($result['blockers'], $result['unproven'])));

        $this->assertSame(1, $DB->count_records('local_sentientia_exams', ['quizid' => $this->quiz['registered']]),
            'idx_quizid is not unique: the importer checks the quiz id itself');
        $existing = $DB->get_record('local_sentientia_exams', ['id' => $mine], '*', MUST_EXIST);
        $this->assertSame('Made by hand', $existing->name, 'the existing exam is untouched');
        $this->assertEqualsWithDelta(55.0, (float) $existing->passinggrade, 0.001);

        $map = $DB->get_record(legacymap::TABLE,
            ['sourcetable' => exam_step::SOURCE, 'sourceid' => $this->course['registered'], 'subkey' => ''], '*', MUST_EXIST);
        $this->assertSame('folded', $map->outcome);
        $this->assertSame(exams_importer::REASON_ALREADY_REGISTERED, $map->reason);
        $this->assertSame($mine, (int) $map->targetid);
        $this->assertSame(1, (int) ($this->step_section($report, 'exams.exam', 'skipped_by_reason')['already_registered'] ?? 0));
        $this->assertSame(9, $DB->count_records('local_sentientia_exams'),
            'the hand-made exam plus the other eight quizzes, which are imported');
    }

    public function test_a_category_that_is_gone_becomes_uncategorised(): void {
        global $DB;
        $this->contract_begin();
        $this->contract_seed();
        $DB->set_field('course', 'category', 987654, ['id' => $this->course['walked']]);
        [$result, $report] = $this->contract_run(true, ['decisions' => $this->contract_decisions()]);
        $this->assertSame(0, $result['exit'], implode('; ', array_merge($result['blockers'], $result['unproven'])));
        $this->assertSame(0, (int) $this->exam_of('walked')->categoryid);
        $this->assertSame(1, (int) ($this->step_section($report, 'exams.exam', 'warnings')['category_missing'] ?? 0));
    }

    // The owner's decisions.

    public function test_a_value_the_importer_does_not_implement_blocks_the_feature(): void {
        global $DB;
        $this->contract_begin();
        $this->contract_seed();
        [$result] = $this->contract_run(true, ['decisions' => $this->contract_decisions(['exams.multi_quiz' => 'final_only'])]);
        $this->assertSame(1, $result['exit']);
        $this->assertStringContainsString('decision_value_not_allowed:exams.multi_quiz', implode(' ', $result['blockers']));
        $this->assertSame(0, $DB->count_records('local_sentientia_exams'), 'a blocked run writes nothing');
    }

    public function test_a_missing_decision_blocks_the_feature(): void {
        $this->contract_begin();
        $this->contract_seed();
        [$result] = $this->contract_run(true, ['decisions' => decisions::none()]);
        $this->assertSame(1, $result['exit']);
        $blockers = implode(' ', $result['blockers']);
        $this->assertStringContainsString('missing_decision:tenant.unresolved.exams', $blockers);
        $this->assertStringContainsString('missing_decision:exams.multi_quiz', $blockers);
    }

    public function test_the_importer_declares_only_the_decisions_it_reads(): void {
        $keys = array_map(fn($d) => $d->key, (new exams_importer())->decisions());
        sort($keys);
        $this->assertSame(['exams.multi_quiz', 'tenant.unresolved.exams'], $keys,
            'exams.forum_pseudocourses is the catalog\'s to implement: declaring it here would gate the import on it for nothing');
    }

    // Preflight.

    public function test_local_onlinetests_with_rows_blocks_the_import(): void {
        global $DB;
        $this->contract_begin();
        $this->contract_seed();
        $DB->insert_record('local_onlinetests', (object) [
            'name' => 'Somebody\'s exam', 'quizid' => $this->quiz['basic'], 'costcenterid' => 1, 'departmentid' => 5,
            'timecreated' => self::T0, 'timemodified' => self::T0,
        ]);
        [$result] = $this->contract_run(true, ['decisions' => $this->contract_decisions()]);
        $this->assertSame(1, $result['exit']);
        $this->assertStringContainsString('local_onlinetests_has_rows', implode(' ', $result['blockers']));
        $this->assertSame(0, $DB->count_records(legacymap::TABLE), 'a blocked run writes no map row');
    }

    public function test_an_empty_local_onlinetests_does_not_block(): void {
        $this->contract_begin();
        $this->contract_seed();
        [$result] = $this->contract_run(true, ['decisions' => $this->contract_decisions()]);
        $this->assertSame(0, $result['exit'], implode('; ', $result['blockers']));
    }

    public function test_preflight_counts_what_will_and_will_not_be_imported(): void {
        $this->contract_begin();
        $this->contract_seed();
        [, $report] = $this->contract_run(false, ['decisions' => $this->contract_decisions()]);
        $counts = $report->to_array()['features']['exams']['preflight']['counts'];
        $this->assertSame(8, $counts['exam_courses_with_a_quiz']);
        $this->assertSame(9, $counts['exam_quizzes']);
        $this->assertSame(1, $counts['multi_quiz_extra_quizzes']);
        $this->assertSame(1, $counts['exam_courses_without_a_quiz']);
        $this->assertSame(1, $counts['exam_courses_without_a_path'], 'the course with no open_path; the foreign root has one');
        $this->assertSame(1, $counts['forum_pseudo_courses']);
        $this->assertSame(0, $counts['quizzes_already_registered']);
    }

    // The dedupe rows.

    public function test_every_overdue_escalation_of_a_past_deadline_is_marked_as_sent(): void {
        global $DB;
        $report = $this->seed_and_apply();

        $basic = $this->exam_of('basic');
        $rows = $DB->get_records('local_sentientia_exams_remind_sent', ['examid' => $basic->id]);
        $this->assertCount(3, $rows, 'one pending learner, three overdue buckets (1, 7 and 14 days)');
        $buckets = [];
        foreach ($rows as $row) {
            $buckets[] = (int) $row->days_before_deadline;
            $this->assertSame($this->user['pending'], (int) $row->userid,
                'not the learner who finished, the suspended or deleted user, or the one with a suspended enrolment');
            $this->assertSame($this->now - 3 * DAYSECS, (int) $row->deadline_ts, 'the quiz\'s own deadline');
            $this->assertGreaterThan(0, (int) $row->timesent);
        }
        sort($buckets);
        $this->assertSame([-14, -7, -1], $buckets, 'overdue buckets are stored as negative days');

        // The second quiz of a multi-quiz course is seeded against ITS exam.
        $second = $this->exam_of('multib');
        $this->assertSame(3, $DB->count_records('local_sentientia_exams_remind_sent', ['examid' => $second->id]));
        $this->assertSame(0, $DB->count_records('local_sentientia_exams_remind_sent', ['examid' => $this->exam_of('multia')->id]),
            'the first quiz has no deadline');
        $this->assertSame(6, $DB->count_records('local_sentientia_exams_remind_sent'), 'nothing else is seeded');

        // The courses with nothing to seed are archived with a reason, and the seeded ones are not.
        $this->assertSame(6, (int) ($this->step_section($report, 'exams.reminder_seed', 'skipped_by_reason')['nothing_to_seed'] ?? 0));
        $primary = $DB->get_record(legacymap::TABLE,
            ['sourcetable' => reminder_seed_step::SOURCE, 'sourceid' => $this->course['basic'], 'subkey' => ''], '*', MUST_EXIST);
        $this->assertSame('imported', $primary->outcome);
        $this->assertSame('local_sentientia_exams_remind_sent', $primary->targettable);
        $this->assertSame('archived', $DB->get_field(legacymap::TABLE, 'outcome',
            ['sourcetable' => reminder_seed_step::SOURCE, 'sourceid' => $this->course['padded'], 'subkey' => '']));
    }

    public function test_the_overdue_and_reminder_tasks_stay_quiet_after_the_import(): void {
        global $DB;
        $this->seed_and_apply();

        // Every learner has a supervisor, and both tasks are switched on: the state of a site that has just
        // enabled them. The learner on each deadline is pending, so without the seed they would be escalated.
        $supervisor = (int) $this->getDataGenerator()->create_user()->id;
        $DB->set_field_select('user', 'open_supervisorid', $supervisor, 'id <> :s', ['s' => $supervisor]);
        set_config('overdue_enabled', 1, 'local_sentientia_exams');
        set_config('reminder_enabled', 1, 'local_sentientia_exams');
        $sink = $this->redirectMessages();

        $this->run_task(new task\exam_overdue());
        $this->assertSame(0, (int) get_config('local_sentientia_exams', 'overdue_last_sent'),
            'no supervisor is told about a deadline that passed before the import');
        $this->run_task(new task\exam_reminder());
        $this->assertSame(0, (int) get_config('local_sentientia_exams', 'reminder_last_sent'));
        $this->assertSame(0, $sink->count());

        // The control: without the seeded rows the same task does escalate, so the quiet above is the seed's doing.
        $DB->delete_records('local_sentientia_exams_remind_sent');
        $this->run_task(new task\exam_overdue());
        $this->assertSame(2, (int) get_config('local_sentientia_exams', 'overdue_last_sent'),
            'the pending learner of each of the two past deadlines');
        $sink->close();
    }

    /**
     * Run a scheduled task, swallowing its mtrace output.
     *
     * @param \core\task\scheduled_task $task
     * @return void
     */
    private function run_task(\core\task\scheduled_task $task): void {
        ob_start();
        try {
            $task->execute();
        } finally {
            ob_end_clean();
        }
    }

    public function test_buckets_follow_the_task_configuration(): void {
        global $DB;
        set_config('overdue_days_after', '2, 30 xx 400 0', 'local_sentientia_exams');
        $this->assertSame([2, 30], reminder_seed_step::overdue_buckets(), 'whole days from 1 to 365, as the task parses them');
        $this->seed_and_apply();
        $buckets = [];
        foreach ($DB->get_records('local_sentientia_exams_remind_sent', ['examid' => $this->exam_of('basic')->id]) as $row) {
            $buckets[] = (int) $row->days_before_deadline;
        }
        sort($buckets);
        $this->assertSame([-30, -2], $buckets);
    }

    // Nothing else changes.

    public function test_the_import_changes_nothing_but_its_two_tables(): void {
        global $DB;
        $this->contract_begin();
        $this->contract_seed();
        $snapshot = function () use ($DB): array {
            return [
                'course' => $DB->get_records('course', null, 'id', 'id, fullname, category, visible, open_path, open_module, timemodified, cacherev'),
                'quiz' => $DB->get_records('quiz', null, 'id', 'id, course, name, timelimit, timeclose, timemodified'),
                'attempts' => $DB->get_records('quiz_attempts', null, 'id', 'id, quiz, userid, state, sumgrades'),
                'enrolments' => $DB->get_records('user_enrolments', null, 'id', 'id, enrolid, userid, status'),
                'grade_items' => $DB->count_records('grade_items'),
                'onlinetests' => $DB->count_records('local_onlinetests'),
            ];
        };
        $before = $snapshot();
        $events = $this->redirectEvents();
        $messages = $this->redirectMessages();
        [$result] = $this->contract_run(true, ['decisions' => $this->contract_decisions()]);
        $this->assertSame(0, $result['exit'], 'the run finished: ' . implode('; ', $result['blockers']));
        $this->assertEquals($before, $snapshot(), 'courses, quizzes, attempts and enrolments are what they were');
        $this->assertSame(0, $events->count());
        $this->assertSame(0, $messages->count());
    }

    // Verify.

    public function test_verify_proves_the_import_until_the_site_opens(): void {
        global $DB;
        $this->seed_and_apply();
        $verify = fn(): array => (new runner(['decisions' => $this->contract_decisions()]))->verify(['exams']);

        $clean = $verify();
        $this->assertSame([], $clean['failures']['exams'], 'a clean import verifies');

        // What happens on a live site: an exam course appears (the source is live core data) and an admin deletes
        // an exam, leaving its dedupe rows behind.
        $late = $this->make_course('Late exam course', '/1', exam_quiz_step::MODULE_EXAMS, 1);
        $this->make_quiz($late, 'Late quiz');
        $DB->delete_records('local_sentientia_exams', ['id' => $this->exam_of('basic')->id]);

        $changed = $verify();
        $this->assertSame(1, $changed['exit']);
        $lines = implode(' ', $changed['failures']['exams']);
        $this->assertStringContainsString('accounting:' . exam_step::SOURCE, $lines, 'the new exam course has no map row');
        $this->assertStringContainsString('seeded_rows_without_an_exam', $lines);

        // The runbook sets this at go-live; from then on neither is a failed import.
        set_config('bizlms_production_open', 1, 'local_sentientia_platform');
        $open = $verify();
        $this->assertSame([], $open['failures']['exams']);
        $this->assertSame(0, $open['exit']);
    }

    // The reader fixes shipped with the importer.

    public function test_the_exam_reader_no_longer_falls_back_to_the_legacy_table(): void {
        global $DB;
        $this->contract_begin();
        $this->contract_seed();
        $DB->insert_record('local_onlinetests', (object) [
            'name' => 'Legacy exam', 'quizid' => $this->quiz['basic'], 'costcenterid' => 7, 'departmentid' => 8,
            'timecreated' => self::T0, 'timemodified' => self::T0,
        ]);
        $this->assertSame(0, exam_manager::count_exams(), 'the new table is empty and the legacy row is not counted');

        $cmid = (int) get_coursemodule_from_instance('quiz', $this->quiz['basic'])->id;
        $this->assertFalse(exam_manager::get_by_course_module($cmid), 'the legacy table is not read');
        $this->add_attempt($this->quiz['basic'], $this->user['pending'], 'inprogress', null);
        $attemptid = (int) $DB->get_field('quiz_attempts', 'id', ['quiz' => $this->quiz['basic'], 'userid' => $this->user['pending']]);
        $this->assertFalse(exam_manager::get_by_attempt($attemptid), 'the legacy table is not read');

        // And with the exam row there, both find it. The legacy row goes first: preflight blocks an import while
        // local_onlinetests holds rows (test_local_onlinetests_with_rows_blocks_the_import covers that), so with it the run
        // imported nothing and exam_of('basic') found no row.
        $DB->delete_records('local_onlinetests');
        [$result] = $this->contract_run(true, ['decisions' => $this->contract_decisions()]);
        $this->assertSame(0, $result['exit'], implode('; ', $result['blockers']));
        $exam = $this->exam_of('basic');
        $this->assertSame((int) $exam->id, (int) exam_manager::get_by_course_module($cmid)->id);
        $this->assertSame((int) $exam->id, (int) exam_manager::get_by_attempt($attemptid)->id);
        $this->assertSame(9, exam_manager::count_exams());
    }

    public function test_the_pass_count_divides_by_the_quiz_marks_not_by_every_learners_grades(): void {
        global $DB;
        $this->contract_begin();
        $this->contract_seed();
        $quizid = $this->quiz['basic'];
        $DB->set_field('quiz', 'sumgrades', 10, ['id' => $quizid]);
        $a = (int) $this->getDataGenerator()->create_user()->id;
        $b = (int) $this->getDataGenerator()->create_user()->id;
        $c = (int) $this->getDataGenerator()->create_user()->id;
        $d = (int) $this->getDataGenerator()->create_user()->id;
        $this->add_attempt($quizid, $a, 'finished', 6.0);      // 60 percent.
        $this->add_attempt($quizid, $b, 'finished', 9.0);      // 90 percent.
        $this->add_attempt($quizid, $c, 'finished', 4.0);      // 40 percent.
        $this->add_attempt($quizid, $d, 'inprogress', 10.0);   // Not finished: never counts.
        // The old denominator was SUM(quiz_grades.grade) over everybody: 24 here, which made 6 of 10 look like 25 percent.
        foreach ([$a, $b, $c] as $userid) {
            $DB->insert_record('quiz_grades', (object) ['quiz' => $quizid, 'userid' => $userid, 'grade' => 8, 'timemodified' => self::T0]);
        }
        // The seed's own finished attempt (user 'done', 6 of 10) counts too.
        $this->assertSame(3, exam_manager::count_passed_learners($quizid, 50.0), '60, 60 and 90 percent');
        $this->assertSame(1, exam_manager::count_passed_learners($quizid, 70.0), 'only 90 percent');
        $this->assertSame(0, exam_manager::count_passed_learners($quizid, 95.0));
        $this->assertSame(0, exam_manager::count_passed_learners($quizid, 50.0, '1 = 0'), 'the tenant condition applies');
    }

    public function test_the_pass_mark_helper_reads_the_quizs_grade_item(): void {
        $this->contract_begin();
        $this->contract_seed();
        $marks = exam_manager::quiz_pass_percentages(
            [$this->quiz['basic'], $this->quiz['hidden'], $this->quiz['padded'], 0, -4]);
        $this->assertSame([$this->quiz['basic']], array_keys($marks), 'a pass mark of 0 and a quiz with none have no entry');
        $this->assertEqualsWithDelta(70.0, $marks[$this->quiz['basic']], 0.001);
        $this->assertSame([], exam_manager::quiz_pass_percentages([]));
    }
}
