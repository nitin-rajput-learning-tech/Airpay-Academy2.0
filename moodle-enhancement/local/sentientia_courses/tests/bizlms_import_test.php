<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

namespace local_sentientia_courses;

defined('MOODLE_INTERNAL') || die();

use local_sentientia_courses\bizlms\course_lookups_importer;
use local_sentientia_courses\tests\bizlms\org_stub_importer;
use local_sentientia_platform\bizlms\context;
use local_sentientia_platform\bizlms\decisions;
use local_sentientia_platform\bizlms\importer;
use local_sentientia_platform\bizlms\legacymap;
use local_sentientia_platform\bizlms\registry;
use local_sentientia_platform\phpunit\importer_contract;
use local_sentientia_platform\phpunit\legacy_schema_fixture;

/**
 * The ADR-032 course_lookups importer: the importer contract plus the feature's own cases
 * (mapping doc, section 6, "Fixture").
 *
 * The seed (contract_seed()), with the numbers a test may rely on. Courses c1 to c5 are created in that order, so
 * their ids ascend. c1 open_path /1; c2 /77 with open_cost 250 and open_identifiedas '2' already set by an
 * administrator; c3 no open_path; c4 /999 (a root that is not a registered tenant); c5 /1 and actively shared to /77.
 *
 *  local_costcenter       ids 1 (/1), 5 (/5, a root that is not a registered tenant), 50 (/1/50), 77 (/77), 177 (/177).
 *  local_course_types     ids 1 to 7. orgid 0 for 1, 2 and 5 (no tenant), 1 for 3, 77 for 4, 50 for 6 (/1/50), 5 for 7
 *                         (unresolved). Type 5 is disabled. Ids 1 to 5 are protected.
 *  local_custom_category  ids 10 to 17: 11 is a child of 10 (cost centre 50, so the root /1); 13 has no cost centre;
 *                         14 has a parent that exists nowhere; 15 is a child of 16, a HIGHER id; 17 names cost centre 5
 *                         (unresolved).
 *  local_dashboardcourses 7 rows: 1 = c1,c2,999999; 2 = c2,c3; 3 = c2; 4 = ' , ,abc'; 5 = 999998; 6 = c4; 7 = c5.
 *                         Featured rows: c1 (list 1), c2 (list 77), c3 (global), c5 (global, shared): four rows.
 *  local_coursedetails    8 rows: 1 and 2 are c1 (the first fills five columns, the second is merged into it); 3 is c2
 *                         (every column already set: nothing to fill); 4 names a missing course; 5 and 6 are c3 (a missing
 *                         user, an invalid list: nothing to fill); 7 is c4 (requestcourseid fills, credits '3.5' does
 *                         not); 8 is c5 (only the two candidate columns have a value).
 *
 * @package    local_sentientia_courses
 * @category   test
 * @copyright  2026 Airpay Payment Services
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \local_sentientia_courses\bizlms\course_lookups_importer
 * @covers     \local_sentientia_courses\bizlms\course_types_step
 * @covers     \local_sentientia_courses\bizlms\course_categories_step
 * @covers     \local_sentientia_courses\bizlms\featured_courses_step
 * @covers     \local_sentientia_courses\bizlms\course_details_step
 * @covers     \local_sentientia_courses\bizlms\course_details_fill_step
 * @covers     \local_sentientia_courses\bizlms\course_details_plan
 *
 * @group local_sentientia_courses
 * @group bizlms_import
 * @group tenant_isolation
 */
final class bizlms_import_test extends \advanced_testcase {
    use legacy_schema_fixture {
        setUp as protected legacy_fixture_setup;
        tearDownAfterClass as protected legacy_fixture_teardown;
    }
    use importer_contract {
        contract_clear_import as protected contract_clear_framework_and_targets;
    }

    /** @var string[] Fixture files whose tables the class creates itself (the trait loads one file per class). */
    private const EXTRA_FIXTURES = ['local_courses', 'local_custom_category'];

    /** @var int Timestamp base of the seed. */
    private static int $t0 = 1700000000;

    /** @var \stdClass The user the seed's course creator column names. */
    private \stdClass $user;

    /** @var \stdClass[] Courses c1 to c5 of the seed, keyed 1 to 5. */
    private array $course = [];

    /** @var array<string, mixed> Decision values a test overrides. */
    private array $decisionvalues = [];

    // Fixture plumbing. The legacy_schema_fixture trait loads ONE fixture file per class; this feature claims tables of
    // three BizLMS plugins (local_courses, local_custom_category, local_costcenter), so the trait loads the
    // local_costcenter file and the class creates the other two files' tables itself, with the same safety rule.

    protected static function legacy_fixture_definition(): array {
        return ['xml' => __DIR__ . '/fixtures/bizlms/local_costcenter.install.xml'];
    }

    protected function setUp(): void {
        $this->legacy_fixture_setup();
        self::create_extra_legacy_tables();
        self::provision_course_columns();
    }

    public static function tearDownAfterClass(): void {
        try {
            self::drop_extra_legacy_tables();
        } finally {
            self::legacy_fixture_teardown();
        }
    }

    /**
     * Create (or empty) the tables of the other two fixture files.
     *
     * @return void
     */
    private static function create_extra_legacy_tables(): void {
        global $DB;
        self::assert_extra_fixture_is_safe();
        $dbman = $DB->get_manager();
        foreach (self::EXTRA_FIXTURES as $name) {
            $xml = __DIR__ . '/fixtures/bizlms/' . $name . '.install.xml';
            preg_match_all('/<TABLE\s+NAME="([^"]+)"/', (string) file_get_contents($xml), $matches);
            foreach ($matches[1] as $table) {
                if ($dbman->table_exists($table)) {
                    $DB->delete_records($table);
                } else {
                    $dbman->install_one_table_from_xmldb_file($xml, $table);
                }
            }
        }
    }

    /**
     * Drop the tables of the other two fixture files.
     *
     * @return void
     */
    private static function drop_extra_legacy_tables(): void {
        global $DB;
        self::assert_extra_fixture_is_safe();
        $dbman = $DB->get_manager();
        foreach (self::EXTRA_FIXTURES as $name) {
            preg_match_all('/<TABLE\s+NAME="([^"]+)"/',
                (string) file_get_contents(__DIR__ . '/fixtures/bizlms/' . $name . '.install.xml'), $matches);
            foreach (array_reverse($matches[1]) as $table) {
                if ($dbman->table_exists($table)) {
                    $dbman->drop_table(new \xmldb_table($table));
                }
            }
        }
    }

    /**
     * The same rule as the trait's: never create or drop a table outside PHPUnit's own database.
     *
     * @return void
     */
    private static function assert_extra_fixture_is_safe(): void {
        global $CFG;
        if (!defined('PHPUNIT_TEST') || !PHPUNIT_TEST) {
            throw new \coding_exception('the legacy fixture tables may only be created under PHPUnit');
        }
        if (empty($CFG->phpunit_prefix) || $CFG->prefix !== $CFG->phpunit_prefix) {
            throw new \coding_exception('the legacy fixture tables refuse: $CFG->prefix is not the phpunit prefix');
        }
    }

    /**
     * The BizLMS columns of {course} the importer reads and writes. A vanilla PHPUnit database has none of them, and
     * the schema is put back after every test, so every test adds them.
     *
     * @return void
     */
    private static function provision_course_columns(): void {
        global $DB;
        $dbman = $DB->get_manager();
        $table = new \xmldb_table('course');
        $have = $DB->get_columns('course');
        $columns = [
            'open_path' => [XMLDB_TYPE_CHAR, '255'],
            'open_identifiedas' => [XMLDB_TYPE_CHAR, '255'],
            'open_categoryid' => [XMLDB_TYPE_INTEGER, '18'],
            'open_cost' => [XMLDB_TYPE_INTEGER, '18'],
            'open_coursecompletiondays' => [XMLDB_TYPE_INTEGER, '18'],
            'open_coursecreator' => [XMLDB_TYPE_INTEGER, '18'],
            'open_requestcourseid' => [XMLDB_TYPE_INTEGER, '18'],
            'open_skill' => [XMLDB_TYPE_INTEGER, '18'],
            'open_level' => [XMLDB_TYPE_INTEGER, '18'],
            'open_points' => [XMLDB_TYPE_INTEGER, '18'],
        ];
        foreach ($columns as $name => [$type, $length]) {
            if (!isset($have[$name])) {
                $dbman->add_field($table, new \xmldb_field($name, $type, $length, null, null, null, null));
            }
        }
    }

    // Contract hooks.

    protected function contract_importer(): importer {
        return new course_lookups_importer();
    }

    /**
     * Register the org stand-in beside the importer: course_lookups depends on org, and the registry refuses a
     * dependency that is not registered.
     *
     * @return importer
     */
    protected function contract_begin(): importer {
        $this->resetAfterTest();
        $importer = $this->contract_importer();
        registry::set_testing_importers([new org_stub_importer(), $importer]);
        return $importer;
    }

    /**
     * The contract puts the framework tables and the importer's target tables back to empty between two runs of one
     * test. This importer also fills core course columns, which that clearing does not know: a second run would find
     * them filled and import nothing, and the contract's "same rows as a clean run" would fail for the wrong reason.
     * So the course columns go back to what the seed gave them.
     *
     * @param importer $importer
     * @return void
     */
    protected function contract_clear_import(importer $importer): void {
        global $DB;
        $this->contract_clear_framework_and_targets($importer);
        foreach ($this->course as $course) {
            foreach (['open_cost', 'open_coursecompletiondays', 'open_coursecreator', 'open_requestcourseid', 'open_skill',
                    'open_level', 'open_points', 'open_identifiedas'] as $column) {
                $DB->set_field('course', $column, null, ['id' => $course->id]);
            }
        }
        // c2 carries what an administrator set before the import.
        $DB->set_field('course', 'open_cost', 250, ['id' => $this->course[2]->id]);
        $DB->set_field('course', 'open_identifiedas', '2', ['id' => $this->course[2]->id]);
    }

    protected function contract_decisions(): decisions {
        return decisions::from_array($this->decisionvalues + [
            'tenant.unresolved.course_lookups' => 'pathless',
            'course_lookups.featured_scope' => 'rehome',
            'course_lookups.coursedetails_unhomed_columns' => 'leave_in_legacy_table',
            'course_lookups.declined_config_tables' => 'stay_in_place',
        ]);
    }

    protected function contract_mutate_source(): void {
        global $DB;
        // A new row changes the count and the max id on every engine, so detection does not depend on the CRC.
        $DB->import_record('local_dashboardcourses', (object) ['id' => 50, 'courseids' => '1']);
    }

    protected function contract_collision(): ?array {
        return ['table' => course_lookups_importer::TYPE_TABLE, 'row' => (object) [
            'id' => 1, 'name' => 'Somebody else', 'shortname' => 'else', 'tenant_path' => null, 'active' => 1,
            'protected' => 0, 'usercreated' => 0, 'usermodified' => 0, 'timecreated' => 5, 'timemodified' => 5]];
    }

    protected function contract_adoptable(): ?array {
        // What a header copy looks like: the same id, name and creation time as the source row, nothing else right.
        return ['table' => course_lookups_importer::TYPE_TABLE, 'sourcetable' => 'local_course_types', 'row' => (object) [
            'id' => 2, 'name' => 'Classroom', 'shortname' => 'copy', 'tenant_path' => null, 'active' => 0,
            'protected' => 0, 'usercreated' => 0, 'usermodified' => 0, 'timecreated' => self::$t0 + 2, 'timemodified' => 9]];
    }

    protected function contract_user_columns(): array {
        return [
            course_lookups_importer::TYPE_TABLE => ['usercreated', 'usermodified'],
            course_lookups_importer::CATEGORY_TABLE => ['usercreated', 'usermodified'],
        ];
    }

    protected function contract_seed(): void {
        global $DB;
        $gen = $this->getDataGenerator();
        $t0 = self::$t0;
        $this->user = $gen->create_user();

        // Courses.
        foreach ([1 => '/1', 2 => '/77', 3 => null, 4 => '/999', 5 => '/1'] as $n => $path) {
            $course = $gen->create_course(['fullname' => 'Lookup course ' . $n, 'shortname' => 'lkp' . $n]);
            $DB->set_field('course', 'open_path', $path, ['id' => $course->id]);
            $this->course[$n] = $course;
        }
        $DB->set_field('course', 'open_cost', 250, ['id' => $this->course[2]->id]);
        $DB->set_field('course', 'open_identifiedas', '2', ['id' => $this->course[2]->id]);
        $DB->insert_record('local_sentientia_courses_tenant_share', (object) [
            'courseid' => $this->course[5]->id, 'tenant_id' => 77, 'shared_by' => $this->user->id, 'status' => 'active',
            'timeshared' => $t0, 'timemodified' => $t0]);

        // Organisations.
        foreach ([[1, 'Airpay', '/1'], [5, 'Unknown', '/5'], [50, 'Department', '/1/50'], [77, 'Public', '/77'],
                [177, 'ZEEA', '/177']] as [$id, $name, $path]) {
            $DB->import_record('local_costcenter', (object) ['id' => $id, 'fullname' => $name, 'shortname' => strtolower($name),
                'parentid' => 0, 'path' => $path, 'depth' => 1]);
        }
        $DB->import_record('local_moduleconfig', (object) ['id' => 1, 'moduleid' => 3, 'costcenters' => '1']);
        $DB->import_record('local_moduleconfig', (object) ['id' => 2, 'moduleid' => 4, 'costcenters' => '1,77']);
        $DB->import_record('local_filters', (object) ['id' => 1, 'plugins' => 'a', 'filters' => 'b', 'plugins_to' => 'c',
            'timecreated' => $t0, 'timemodified' => $t0, 'usermodified' => 0]);

        // Course types.
        foreach ([[1, 'E-Learning', 0, 1], [2, 'Classroom', 0, 1], [3, 'Learning Path', 1, 1], [4, 'Program', 77, 1],
                [5, 'Exam', 0, 0], [6, 'Compliance', 50, 1], [7, 'Orphan', 5, 1]] as [$id, $name, $orgid, $active]) {
            $DB->import_record('local_course_types', (object) ['id' => $id, 'name' => $name,
                'shortname' => strtolower(str_replace(' ', '', $name)), 'orgid' => $orgid, 'active' => $active,
                'timecreated' => $t0 + $id, 'timemodified' => $t0 + 100 + $id,
                'usercreated' => $this->user->id, 'usermodified' => $this->user->id]);
        }

        // Custom categories.
        foreach ([
            [10, 'Compliance', 0, 1, '/10', 1],
            [11, 'Security', 10, 50, '/10/11', 2],
            [12, 'Public skills', 0, 77, '/12', 1],
            [13, 'Global', null, null, null, null],
            [14, 'Orphan parent', 99, 1, '/99/14', 2],
            [15, 'Child before parent', 16, 177, '/16/15', 2],
            [16, 'Late parent', 0, 177, '/16', 1],
            [17, 'Unresolved org', 0, 5, '/17', 1],
        ] as [$id, $name, $parent, $costcenter, $path, $depth]) {
            $DB->import_record('local_custom_category', (object) ['id' => $id, 'fullname' => $name,
                'shortname' => strtolower(str_replace(' ', '', $name)), 'parentid' => $parent, 'costcenterid' => $costcenter,
                'timecreated' => $t0 + $id, 'timemodified' => $t0 + 100 + $id, 'usercreated' => $this->user->id,
                'usermodified' => $this->user->id, 'path' => $path, 'depth' => $depth]);
        }

        // Dashboard courses.
        $c = array_map(static fn($course) => (int) $course->id, $this->course);
        foreach ([
            1 => "{$c[1]},{$c[2]},999999",
            2 => "{$c[2]},{$c[3]}",
            3 => (string) $c[2],
            4 => ' , ,abc',
            5 => '999998',
            6 => (string) $c[4],
            7 => (string) $c[5],
        ] as $id => $list) {
            $DB->import_record('local_dashboardcourses', (object) ['id' => $id, 'courseids' => $list]);
        }

        // Course details. The columns that never matter for a row are given the neutral value BizLMS stored.
        $blank = ['costcenterid' => null, 'credits' => null, 'cost' => 0, 'enrollstartdate' => null, 'enrollenddate' => null,
            'coursecompletiondays' => 0, 'coursecreator' => 0, 'duration' => '0', 'identifiedas' => '0', 'requestcourseid' => 0,
            'timecreated' => $t0, 'timemodified' => $t0 + 1, 'usercreated' => 0, 'usermodified' => 0, 'proficiencylevel' => 0,
            'skill' => 0, 'prerequisite_courses' => null];
        $details = [
            1 => ['courseid' => $c[1], 'cost' => 500, 'coursecompletiondays' => 30, 'coursecreator' => $this->user->id,
                'identifiedas' => '1,3', 'skill' => 4, 'proficiencylevel' => 2, 'credits' => '5',
                'enrollstartdate' => $t0, 'prerequisite_courses' => '3,4'],
            2 => ['courseid' => $c[1], 'cost' => 999, 'identifiedas' => '2'],
            3 => ['courseid' => $c[2], 'cost' => 100, 'identifiedas' => '1'],
            4 => ['courseid' => 999999, 'cost' => 100],
            5 => ['courseid' => $c[3], 'coursecreator' => 987654],
            6 => ['courseid' => $c[3], 'identifiedas' => 'x,y'],
            7 => ['courseid' => $c[4], 'requestcourseid' => 77, 'credits' => '3.5'],
            8 => ['courseid' => $c[5], 'proficiencylevel' => 3, 'credits' => '12'],
        ];
        foreach ($details as $id => $row) {
            $DB->import_record('local_coursedetails', (object) (['id' => $id] + $row + $blank));
        }
    }

    // Helpers.

    /**
     * Begin, seed, run, and expect a finished run (exit 0, or 2 for the needs-owner skips the seed contains).
     *
     * @param array<string, mixed> $decisions Decision values that replace the defaults.
     * @return array{0: array, 1: \local_sentientia_platform\bizlms\report, 2: importer}
     */
    private function import(array $decisions = []): array {
        $importer = $this->contract_begin();
        $this->contract_seed();
        $this->decisionvalues = $decisions;
        [$result, $report] = $this->contract_run(true);
        $this->assertContains($result['exit'], [0, 2], implode('; ', $result['blockers']));
        $this->assertSame('complete', $result['features'][course_lookups_importer::FEATURE]);
        return [$result, $report, $importer];
    }

    /**
     * One step's section of the report.
     *
     * @param \local_sentientia_platform\bizlms\report $report
     * @param string $suffix Step key after the feature prefix.
     * @return array
     */
    private function step_report(\local_sentientia_platform\bizlms\report $report, string $suffix): array {
        return $report->to_array()['features'][course_lookups_importer::FEATURE]['steps'][course_lookups_importer::FEATURE . '.' . $suffix];
    }

    /**
     * The primary map row of a source row.
     *
     * @param string $table
     * @param int $sourceid
     * @param string $subkey
     * @return \stdClass
     */
    private function map(string $table, int $sourceid, string $subkey = ''): \stdClass {
        global $DB;
        return $DB->get_record(legacymap::TABLE, ['sourcetable' => $table, 'sourceid' => $sourceid, 'subkey' => $subkey],
            '*', MUST_EXIST);
    }

    // Feature cases.

    public function test_the_importer_is_declared_and_depends_on_org(): void {
        $importer = new course_lookups_importer();
        $this->assertSame('course_lookups', $importer->feature());
        $this->assertSame(['org'], $importer->depends());
        $this->assertSame('local_sentientia_courses', $importer->component());
        $this->assertSame(['course' => $importer->core_writes()['course']], $importer->core_writes());
        $this->assertSame(['local_moduleconfig', 'local_filters'], array_keys($importer->declined_tables()));
        $this->assertEqualsCanonicalizing(['local_course_types', 'local_custom_category', 'local_dashboardcourses',
            'local_coursedetails'], array_keys($importer->sources()));
        $registered = registry::CORE_WRITES_ALLOWED['course']['operations'];
        $this->assertSame(['update'], $registered, 'the course write is an UPDATE and nothing else');
    }

    public function test_course_types_keep_their_ids_tenant_and_protection(): void {
        global $DB;
        [, $report] = $this->import();

        $types = $DB->get_records(course_lookups_importer::TYPE_TABLE, null, 'id ASC');
        $this->assertSame([1, 2, 3, 4, 5, 6, 7], array_map('intval', array_keys($types)));
        $this->assertSame('E-Learning', $types[1]->name);
        $this->assertSame('classroom', $types[2]->shortname);

        // orgid 0 means every tenant in BizLMS: no tenant path. A real org gives its own path; an unresolvable one none.
        $this->assertNull($types[1]->tenant_path);
        $this->assertSame('/1', $types[3]->tenant_path);
        $this->assertSame('/77', $types[4]->tenant_path);
        $this->assertSame('/1/50', $types[6]->tenant_path, 'the path of the org, not its root');
        $this->assertNull($types[7]->tenant_path, 'an org that is not a registered tenant is pathless, never global');

        $this->assertSame([1, 1, 1, 1, 1, 0, 0], array_values(array_map(static fn($t) => (int) $t->protected, $types)));
        $this->assertSame(0, (int) $types[5]->active);
        $this->assertSame(1, (int) $types[6]->active);
        $this->assertSame((int) $this->user->id, (int) $types[3]->usercreated);
        $this->assertSame(self::$t0 + 3, (int) $types[3]->timecreated, 'the source timestamp, not the import time');
        $this->assertSame(self::$t0 + 103, (int) $types[3]->timemodified);

        $methods = $this->step_report($report, 'types')['tenant_methods'];
        $this->assertSame(1, $methods['unresolved'] ?? 0);
    }

    public function test_the_native_insert_after_the_import_gets_an_id_above_the_legacy_maximum(): void {
        global $DB;
        $this->import();
        $id = $DB->insert_record(course_lookups_importer::TYPE_TABLE, (object) ['name' => 'Native', 'shortname' => 'native',
            'tenant_path' => null, 'active' => 1, 'protected' => 0, 'usercreated' => 0, 'usermodified' => 0,
            'timecreated' => time(), 'timemodified' => time()]);
        $this->assertGreaterThan(7, (int) $id, 'the runner resets the sequence of a PRESERVE target after the commit');
    }

    public function test_categories_keep_their_ids_parents_and_resolve_the_tenant_root(): void {
        global $DB;
        [, $report] = $this->import();

        $categories = $DB->get_records(course_lookups_importer::CATEGORY_TABLE, null, 'id ASC');
        $this->assertSame([10, 11, 12, 13, 14, 15, 16, 17], array_map('intval', array_keys($categories)));
        $this->assertSame(0, (int) $categories[10]->parentid);
        $this->assertSame(10, (int) $categories[11]->parentid);
        $this->assertSame(16, (int) $categories[15]->parentid, 'a parent with a higher id is kept: PRESERVE keeps ids');
        $this->assertSame(99, (int) $categories[14]->parentid, 'an orphan keeps the parent it had');

        // The root of the cost centre's path, not the path: cost centre 50 is /1/50.
        $this->assertSame('/1', $categories[10]->tenant_path);
        $this->assertSame('/1', $categories[11]->tenant_path);
        $this->assertSame('/77', $categories[12]->tenant_path);
        $this->assertNull($categories[13]->tenant_path, 'a category with no cost centre has no tenant');
        $this->assertSame('/177', $categories[15]->tenant_path);
        $this->assertNull($categories[17]->tenant_path, 'an unresolvable cost centre is pathless, never global');

        // The category-tree path and depth are copied; a missing one becomes empty and 0.
        $this->assertSame('/10/11', $categories[11]->path);
        $this->assertSame(2, (int) $categories[11]->depth);
        $this->assertSame('', $categories[13]->path);
        $this->assertSame(0, (int) $categories[13]->depth);
        $this->assertSame(self::$t0 + 11, (int) $categories[11]->timecreated);

        $step = $this->step_report($report, 'categories');
        $this->assertSame(1, $step['warnings']['orphan_parent'] ?? 0);
        $this->assertSame(1, $step['tenant_methods']['unresolved'] ?? 0);
    }

    public function test_featured_courses_are_the_deduplicated_union_rehomed_by_course_path(): void {
        global $DB;
        [, $report] = $this->import();
        $c = array_map(static fn($course) => (int) $course->id, $this->course);

        $rows = $DB->get_records('local_sentientia_featured_courses', null, 'courseid ASC');
        $bycourse = [];
        foreach ($rows as $row) {
            $bycourse[(int) $row->courseid] = $row;
        }
        $this->assertCount(4, $rows);
        $this->assertEqualsCanonicalizing([$c[1], $c[2], $c[3], $c[5]], array_keys($bycourse));

        // Home: the course's tenant; no open_path is global; an actively shared course stays global.
        $this->assertSame(1, (int) $bycourse[$c[1]]->costcenterid);
        $this->assertSame(77, (int) $bycourse[$c[2]]->costcenterid);
        $this->assertSame(0, (int) $bycourse[$c[3]]->costcenterid);
        $this->assertSame(0, (int) $bycourse[$c[5]]->costcenterid);

        // Order: by course id, newest first, times ten, over every existing course of the union (c4 ranks 20 too).
        $this->assertSame(50, (int) $bycourse[$c[1]]->sort_order);
        $this->assertSame(40, (int) $bycourse[$c[2]]->sort_order);
        $this->assertSame(30, (int) $bycourse[$c[3]]->sort_order);
        $this->assertSame(10, (int) $bycourse[$c[5]]->sort_order);
        $this->assertNull($bycourse[$c[1]]->label);
        $this->assertGreaterThanOrEqual(self::$t0, (int) $bycourse[$c[1]]->timecreated);

        // Every source row has exactly one primary outcome, with its reason.
        $expect = [
            1 => ['imported', null], 2 => ['imported', null], 3 => ['merged', 'duplicate_course'],
            4 => ['archived', 'empty_course_list'], 5 => ['skipped', 'course_missing'],
            6 => ['skipped', 'tenant_unresolved'], 7 => ['imported', null],
        ];
        foreach ($expect as $id => [$outcome, $reason]) {
            $map = $this->map('local_dashboardcourses', $id);
            $this->assertSame($outcome, $map->outcome, "row {$id}");
            $this->assertSame($reason, $map->reason === null ? null : (string) $map->reason, "row {$id}");
        }
        $this->assertSame((int) $this->map('local_dashboardcourses', 1)->targetid,
            (int) $this->map('local_dashboardcourses', 3)->targetid, 'a merged list points at the row that won');

        // The second course of the first list is a sub-row with its own key.
        $sub = $this->map('local_dashboardcourses', 1, 'course:' . $c[2]);
        $this->assertSame('imported', $sub->outcome);
        $this->assertSame((int) $bycourse[$c[2]]->id, (int) $sub->targetid);

        $step = $this->step_report($report, 'featured');
        $this->assertSame(2, $step['warnings']['featured_course_missing'] ?? 0);
        $this->assertSame(2, $step['warnings']['featured_course_duplicate'] ?? 0);
        $this->assertSame(1, $step['warnings']['invalid_course_id'] ?? 0);
        $this->assertSame(1, $step['warnings']['featured_tenant_unresolved'] ?? 0);
        $this->assertSame(1, $step['tenant_methods']['fallback:no_open_path'] ?? 0);
        $this->assertSame(1, $step['tenant_methods']['fallback:shared_course'] ?? 0);
    }

    public function test_featured_scope_global_puts_every_course_on_the_global_list(): void {
        global $DB;
        $this->import(['course_lookups.featured_scope' => 'global']);
        $rows = $DB->get_records('local_sentientia_featured_courses');
        $this->assertNotEmpty($rows);
        foreach ($rows as $row) {
            $this->assertSame(0, (int) $row->costcenterid);
        }
        // With no tenant to resolve, c4 is no longer unresolved: it is featured too.
        $this->assertCount(5, $rows);
    }

    public function test_the_skip_decision_leaves_unresolved_lookup_rows_out(): void {
        global $DB;
        [$result] = $this->import(['tenant.unresolved.course_lookups' => 'skip']);
        $this->assertSame(2, $result['exit'], 'a needs-owner skip is unproven until the owner accepts it');
        $this->assertFalse($DB->record_exists(course_lookups_importer::TYPE_TABLE, ['id' => 7]));
        $this->assertFalse($DB->record_exists(course_lookups_importer::CATEGORY_TABLE, ['id' => 17]));
        $type = $this->map('local_course_types', 7);
        $this->assertSame('skipped', $type->outcome);
        $this->assertSame('tenant_unresolved', $type->reason);
        $this->assertSame('skipped', $this->map('local_custom_category', 17)->outcome);
        $this->assertSame(6, $DB->count_records(course_lookups_importer::TYPE_TABLE));
    }

    public function test_coursedetails_fill_only_empty_course_columns_and_fire_no_event(): void {
        global $DB;
        $this->contract_begin();
        $this->contract_seed();
        $c = array_map(static fn($course) => (int) $course->id, $this->course);
        $modified = (int) $DB->get_field('course', 'timemodified', ['id' => $c[1]]);
        $criteria = $DB->count_records('course_completion_criteria');
        $events = $this->redirectEvents();

        [$result, $report] = $this->contract_run(true);
        $this->assertContains($result['exit'], [0, 2], implode('; ', $result['blockers']));

        // c1: five empty columns filled from the first row; the unmapped ones are left alone.
        $c1 = $DB->get_record('course', ['id' => $c[1]]);
        $this->assertSame(500, (int) $c1->open_cost);
        $this->assertSame(30, (int) $c1->open_coursecompletiondays);
        $this->assertSame((int) $this->user->id, (int) $c1->open_coursecreator);
        $this->assertSame('1,3', $c1->open_identifiedas);
        $this->assertSame(4, (int) $c1->open_skill);
        $this->assertEmpty($c1->open_requestcourseid);
        $this->assertEmpty($c1->open_level, 'the candidate columns are counted, not written, until the owner says fill');
        $this->assertEmpty($c1->open_points);

        // c2: an administrator's values stay.
        $c2 = $DB->get_record('course', ['id' => $c[2]]);
        $this->assertSame(250, (int) $c2->open_cost);
        $this->assertSame('2', $c2->open_identifiedas);
        // c3: a missing user and an invalid list fill nothing.
        $c3 = $DB->get_record('course', ['id' => $c[3]]);
        $this->assertEmpty($c3->open_coursecreator);
        $this->assertEmpty($c3->open_identifiedas);
        // c4: requestcourseid fills, credits '3.5' is not a whole number.
        $c4 = $DB->get_record('course', ['id' => $c[4]]);
        $this->assertSame(77, (int) $c4->open_requestcourseid);
        $this->assertEmpty($c4->open_points);
        // c5: only candidate columns had a value.
        $c5 = $DB->get_record('course', ['id' => $c[5]]);
        $this->assertEmpty($c5->open_level);
        $this->assertEmpty($c5->open_points);

        // The trail has the two courses that were filled, and which columns.
        $trail = $DB->get_records(course_lookups_importer::LEDGER, null, 'courseid ASC');
        $this->assertCount(2, $trail);
        $byc = [];
        foreach ($trail as $row) {
            $byc[(int) $row->courseid] = $row;
        }
        $this->assertSame(1, (int) $byc[$c[1]]->detailid);
        $this->assertSame('open_cost,open_coursecompletiondays,open_coursecreator,open_identifiedas,open_skill',
            $byc[$c[1]]->filledcols);
        $this->assertSame(7, (int) $byc[$c[4]]->detailid);
        $this->assertSame('open_requestcourseid', $byc[$c[4]]->filledcols);
        $this->assertSame(self::$t0, (int) $byc[$c[1]]->timecreated, 'the source timestamps');
        $this->assertSame(self::$t0 + 1, (int) $byc[$c[1]]->timemodified);

        // Every source row has exactly one primary outcome.
        $expect = [
            1 => ['imported', null], 2 => ['merged', 'duplicate_course_row'], 3 => ['archived', 'nothing_to_fill'],
            4 => ['skipped', 'course_missing'], 5 => ['archived', 'nothing_to_fill'], 6 => ['archived', 'nothing_to_fill'],
            7 => ['imported', null], 8 => ['archived', 'nothing_to_fill'],
        ];
        foreach ($expect as $id => [$outcome, $reason]) {
            $map = $this->map('local_coursedetails', $id);
            $this->assertSame($outcome, $map->outcome, "row {$id}");
            $this->assertSame($reason, $map->reason === null ? null : (string) $map->reason, "row {$id}");
        }

        // It is a field write, not update_course(): no event, the course keeps its timemodified, and
        // prerequisite_courses ('3,4' on the first row) never became a completion criterion.
        $this->assertSame($modified, (int) $DB->get_field('course', 'timemodified', ['id' => $c[1]]));
        $this->assertSame($criteria, $DB->count_records('course_completion_criteria'));
        $this->assertCount(0, $events->get_events());
        $this->assertSame(1, $this->step_report($report, 'details')['skipped_by_reason']['course_missing'] ?? 0);
    }

    public function test_the_candidate_columns_are_written_only_when_the_owner_says_fill(): void {
        global $DB;
        $this->import(['course_lookups.coursedetails_candidate_columns' => 'fill']);
        $c = array_map(static fn($course) => (int) $course->id, $this->course);

        $c1 = $DB->get_record('course', ['id' => $c[1]]);
        $this->assertSame(2, (int) $c1->open_level);
        $this->assertSame(5, (int) $c1->open_points);
        $c5 = $DB->get_record('course', ['id' => $c[5]]);
        $this->assertSame(3, (int) $c5->open_level);
        $this->assertSame(12, (int) $c5->open_points);
        $c4 = $DB->get_record('course', ['id' => $c[4]]);
        $this->assertEmpty($c4->open_points, 'credits 3.5 is not a whole number whatever the owner says');

        $this->assertSame(3, $DB->count_records(course_lookups_importer::LEDGER), 'c5 now has something to fill');
        $trail = $DB->get_record(course_lookups_importer::LEDGER, ['courseid' => $c[1]]);
        $this->assertStringContainsString('open_level', $trail->filledcols);
        $this->assertStringContainsString('open_points', $trail->filledcols);
    }

    public function test_a_second_apply_changes_no_course_row_and_keeps_a_value_an_administrator_set(): void {
        global $DB;
        $this->import();
        $c = array_map(static fn($course) => (int) $course->id, $this->course);
        // An administrator changes a filled column after the import, then the import is applied again.
        $DB->set_field('course', 'open_cost', 777, ['id' => $c[1]]);
        $before = $DB->get_records('course', null, 'id ASC');
        $trail = $DB->get_records(course_lookups_importer::LEDGER, null, 'id ASC');

        [$result] = $this->contract_run(true);
        $this->assertContains($result['exit'], [0, 2]);

        $this->assertEquals($before, $DB->get_records('course', null, 'id ASC'), 'a repeat apply fills nothing it filled before');
        $this->assertEquals($trail, $DB->get_records(course_lookups_importer::LEDGER, null, 'id ASC'));
        $this->assertSame(777, (int) $DB->get_field('course', 'open_cost', ['id' => $c[1]]));
    }

    public function test_verify_names_a_column_the_trail_says_it_filled_and_a_featured_row_off_every_list(): void {
        global $DB;
        [, , $importer] = $this->import();
        $c = array_map(static fn($course) => (int) $course->id, $this->course);
        $ctx = context::build($importer, false, 0, $this->contract_decisions());
        $this->assertSame([], $importer->verify($ctx), 'a clean import verifies');

        $DB->set_field('course', 'open_cost', 0, ['id' => $c[1]]);
        $this->assertContains('trail_column_not_filled:' . $c[1] . '.open_cost', $importer->verify($ctx));
        $DB->set_field('course', 'open_cost', 500, ['id' => $c[1]]);

        $featured = $DB->get_record('local_sentientia_featured_courses', ['courseid' => $c[1]], '*', MUST_EXIST);
        $DB->set_field('local_sentientia_featured_courses', 'costcenterid', 55, ['id' => $featured->id]);
        $this->assertContains('featured_costcenterid_is_not_a_registered_tenant:55 rows=1', $importer->verify($ctx));
    }

    public function test_preflight_counts_what_the_import_will_do_and_what_it_leaves(): void {
        $importer = $this->contract_begin();
        $this->contract_seed();
        $pf = $importer->preflight(context::build($importer, true, 0, $this->contract_decisions()))->to_array();
        $counts = $pf['counts'];

        $this->assertSame(7, $counts['featured_distinct_courses']);
        $this->assertSame(2, $counts['featured_missing_courses']);
        $this->assertSame(1, $counts['featured_ignored_tokens']);
        $this->assertSame(2, $counts['declined_rows:local_moduleconfig']);
        $this->assertSame(1, $counts['declined_rows:local_filters']);
        $this->assertSame(3, $counts['coursedetails_rows_that_can_fill_a_course']);
        $this->assertSame(2, $counts['coursedetails_candidate_open_level_not_written']);
        $this->assertSame(2, $counts['coursedetails_candidate_open_points_not_written']);
        $this->assertSame(1, $counts['coursedetails_rows_with_columns_left_in_the_legacy_table']);
        $this->assertSame(3, $counts['rows_without_an_organisation:local_course_types']);
        $this->assertSame(1, $counts['rows_with_an_unresolved_organisation:local_course_types']);
        $this->assertSame(1, $counts['rows_without_an_organisation:local_custom_category']);
        $this->assertSame(1, $counts['rows_with_an_unresolved_organisation:local_custom_category']);
        $this->assertContains('unresolved_organisation:local_course_types:1', $pf['warnings']);
        $this->assertSame([], $pf['blockers']);
    }

    public function test_every_decision_the_importer_declares_has_a_value_the_owner_can_sign(): void {
        $importer = new course_lookups_importer();
        $keys = array_map(static fn($decision) => $decision->key, $importer->decisions());
        $this->assertEqualsCanonicalizing([
            'tenant.unresolved.course_lookups', 'course_lookups.featured_scope',
            'course_lookups.coursedetails_unhomed_columns', 'course_lookups.declined_config_tables',
            'course_lookups.coursedetails_candidate_columns',
        ], $keys);
        foreach ($importer->decisions() as $decision) {
            if ($decision->required) {
                $this->assertNull($decision->default, $decision->key . ': an owner choice is data, not a default');
            }
        }
    }
}
