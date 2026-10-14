<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

namespace local_sentientia_platform;

defined('MOODLE_INTERNAL') || die();

use local_sentientia_platform\bizlms\decisions;
use local_sentientia_platform\bizlms\fingerprint;
use local_sentientia_platform\bizlms\legacy_tables;
use local_sentientia_platform\bizlms\parity;
use local_sentientia_platform\bizlms\parity_gate;
use local_sentientia_platform\parity\core as parity_core;
use local_sentientia_platform\parity\legacy as parity_legacy;
use local_sentientia_platform\parity\metrics as parity_metrics;
use local_sentientia_platform\parity\moodle_db;
use local_sentientia_platform\phpunit\legacy_schema_fixture;
use local_sentientia_platform\tests\bizlms\toy_seed;
use local_sentientia_platform\tests\parity\hiding_database;

/**
 * The parity library and gate on a real database (ADR-032 "Parity hooks", Stage B gate 4): the same SCORM numbers on both
 * layouts, the fingerprint the standalone tool takes equal to the framework's, and the explanation of the import's core
 * writes from its own records.
 *
 * @package    local_sentientia_platform
 * @category   test
 * @covers     \local_sentientia_platform\bizlms\parity_gate
 *
 * @group local_sentientia_platform
 * @group bizlms_import
 */
final class bizlms_parity_gate_test extends \advanced_testcase {
    use legacy_schema_fixture {
        setUpBeforeClass as protected legacy_fixture_set_up_before_class;
    }
    use toy_seed;

    protected static function legacy_fixture_definition(): array {
        return ['xml' => __DIR__ . '/../fixtures/bizlms/toy.install.xml'];
    }

    public static function setUpBeforeClass(): void {
        // The trait's setUpBeforeClass() creates the toy legacy tables, and a class method of the same name replaces it:
        // parent::setUpBeforeClass() reaches advanced_testcase and skips them, so the two tests that seed the toy data
        // died on insert_record_raw() (Moodle 5.3 PHPUnit run, 2026-10-09). Call the trait's by its alias.
        self::legacy_fixture_set_up_before_class();
        parity_gate::load_library();
    }

    /**
     * Skip the test when a plugin's table is not on this site.
     *
     * @param string $table
     * @return void
     */
    private function require_table(string $table): void {
        global $DB;
        if (!$DB->get_manager()->table_exists($table)) {
            $this->markTestSkipped("{$table} is not on this site (its plugin is not installed)");
        }
    }

    /**
     * One map row, the way the runner writes it.
     *
     * @param string $table Target table.
     * @param int $targetid
     * @param int $sourceid
     * @param string $subkey
     * @param string $outcome
     * @param int $runid
     * @return void
     */
    private function map_row(string $table, int $targetid, int $sourceid, string $subkey = '', string $outcome = 'imported',
                             int $runid = 7): void {
        global $DB;
        $DB->insert_record('local_sentientia_legacymap', (object) [
            'feature' => 'enrolments', 'sourcetable' => '#' . $table . '.id', 'sourceid' => $sourceid, 'subkey' => $subkey,
            'targettable' => $table, 'targetid' => $targetid, 'outcome' => $outcome, 'reason' => null, 'detail' => null,
            'runid' => $runid, 'timecreated' => time(),
        ]);
    }

    /**
     * A row of core enrol the way a BizLMS instance sits there (the enrol plugin itself need not exist: the gate reads the row).
     *
     * @param int $courseid
     * @param string $method The enrol column.
     * @param int $status
     * @return int The new instance id.
     */
    private function enrol_instance(int $courseid, string $method = 'classroom', int $status = 0): int {
        global $DB;
        return (int) $DB->insert_record('enrol', (object) ['enrol' => $method, 'status' => $status, 'courseid' => $courseid,
            'sortorder' => 50, 'timecreated' => 1, 'timemodified' => 2]);
    }

    /**
     * A row of the enrolments importer's trail of switched-off instances, and (with a run) the map row that says that run wrote it.
     *
     * @param int $enrolid
     * @param int $courseid
     * @param string $method What the trail recorded as the instance's enrol method.
     * @param int $prior The status the instance had.
     * @param int $runid 0 for no map row.
     * @return void
     */
    private function trail_row(int $enrolid, int $courseid, string $method = 'classroom', int $prior = 0, int $runid = 0): void {
        global $DB;
        $id = (int) $DB->insert_record('local_sentientia_courses_enroloff', (object) ['enrolid' => $enrolid,
            'courseid' => $courseid, 'method' => $method, 'priorstatus' => $prior, 'timecreated' => 1, 'timemodified' => 2]);
        if ($runid > 0) {
            $this->map_row('local_sentientia_courses_enroloff', $id, $enrolid, '', 'imported', $runid);
        }
    }

    // The SCORM numbers.

    public function test_scorm_numbers_are_the_same_whichever_layout_holds_them(): void {
        global $DB;
        $this->resetAfterTest();
        $dbman = $DB->get_manager();

        // The data, once as Moodle 4.3 and later keep it...
        $elements = [];
        foreach (['cmi.core.lesson_status', 'cmi.core.score.raw', 'x.start.time'] as $element) {
            $elements[$element] = $DB->insert_record('scorm_element', (object) ['element' => $element]);
        }
        $logical = [
            // user, scorm, attempt, sco, element, value, time.
            [5, 1, 1, 10, 'cmi.core.lesson_status', 'completed', 1700000001],
            [5, 1, 1, 10, 'cmi.core.score.raw', '85', 1700000002],
            [5, 1, 2, 10, 'cmi.core.lesson_status', 'incomplete', 1700000003],
            [6, 2, 1, 20, 'cmi.core.lesson_status', 'passed', 1700000004],
            [6, 2, 1, 20, 'x.start.time', '2026-01-01', 1700000005],
        ];
        $attempts = [];
        foreach ($logical as [$user, $scorm, $attempt, $sco, $element, $value, $time]) {
            $key = "{$user}/{$scorm}/{$attempt}";
            $attempts[$key] ??= $DB->insert_record('scorm_attempt', (object) ['userid' => $user, 'scormid' => $scorm,
                'attempt' => $attempt]);
            $DB->insert_record('scorm_scoes_value', (object) ['scoid' => $sco, 'attemptid' => $attempts[$key],
                'elementid' => $elements[$element], 'value' => $value, 'timemodified' => $time]);
        }

        // ... and once as Moodle 4.1 kept it, in a table of the 4.1 shape beside them (hidden from the other view).
        $table = new \xmldb_table('scorm_scoes_track');
        $table->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE);
        $table->add_field('userid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        $table->add_field('scormid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        $table->add_field('scoid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        $table->add_field('attempt', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '1');
        $table->add_field('element', XMLDB_TYPE_CHAR, '255', null, XMLDB_NOTNULL, null, '');
        $table->add_field('value', XMLDB_TYPE_TEXT, null, null, XMLDB_NOTNULL);
        $table->add_field('timemodified', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        $table->add_key('primary', XMLDB_KEY_PRIMARY, ['id']);
        if ($dbman->table_exists($table)) {
            $dbman->drop_table($table);
        }
        $dbman->create_table($table);
        try {
            foreach ($logical as [$user, $scorm, $attempt, $sco, $element, $value, $time]) {
                $DB->insert_record('scorm_scoes_track', (object) ['userid' => $user, 'scormid' => $scorm, 'attempt' => $attempt,
                    'scoid' => $sco, 'element' => $element, 'value' => $value, 'timemodified' => $time]);
            }

            $db = new moodle_db($DB);
            $new = parity_metrics::collect(new hiding_database($db, ['scorm_scoes_track']));
            $old = parity_metrics::collect(new hiding_database($db, ['scorm_attempt', 'scorm_scoes_value', 'scorm_element']));
            $both = parity_metrics::collect($db);
        } finally {
            $dbman->drop_table($table);
        }

        $this->assertSame('value', $new['layout']['scorm']);
        $this->assertSame('track', $old['layout']['scorm']);
        $this->assertSame(3, $old['counts']['scorm_attempts'], 'distinct (user, scorm, attempt)');
        $this->assertSame(5, $old['counts']['scorm_tracks']);
        $this->assertSame($old['counts']['scorm_attempts'], $new['counts']['scorm_attempts']);
        $this->assertSame($old['counts']['scorm_tracks'], $new['counts']['scorm_tracks']);
        $this->assertSame($old['checksums']['scorm_tracks'], $new['checksums']['scorm_tracks'],
            'the same stored elements hash the same on either layout (rows, columns and, on MySQL and MariaDB, the CRC)');
        $this->assertSame('both', $both['layout']['scorm'], 'an unfinished upgrade is not read as either');
    }

    // The fingerprint of the BizLMS tables.

    public function test_the_fingerprint_the_standalone_tool_takes_is_the_one_the_framework_takes(): void {
        global $DB;
        $this->resetAfterTest();
        $this->seed_toy_data();
        legacy_tables::reset();
        $db = new moodle_db($DB);
        foreach (['local_toy_org', 'local_toy_item', 'local_toy_dup', 'local_toy_event', 'local_toy_fan', 'local_toy_unused'] as $table) {
            $this->assertSame(fingerprint::table($table), parity_legacy::fingerprint($db, $table), $table);
        }
    }

    public function test_the_legacy_comparison_finds_a_changed_and_a_missing_table(): void {
        global $DB;
        $this->resetAfterTest();
        $this->seed_toy_data();
        legacy_tables::reset();
        $db = new moodle_db($DB);

        $baseline = parity_legacy::fingerprints($db, legacy_tables::detect());
        $this->assertArrayHasKey('local_toy_org', $baseline);

        $same = parity_gate::legacy_comparison($db, $baseline, []);
        $this->assertSame([], parity::comparison_problems($same['comparison'])['hard']);
        $this->assertSame([], $same['other']);

        $DB->import_record('local_toy_org', (object) ['id' => 70, 'name' => 'Extra', 'parentid' => 0, 'path' => null,
            'status' => 1, 'timecreated' => 1, 'timemodified' => 1]);
        self::drop_legacy_table('local_toy_unused');
        $problems = parity::comparison_problems(parity_gate::legacy_comparison($db, $baseline, [])['comparison']);
        $this->assertNotEmpty(preg_grep('/^legacy_table_changed:local_toy_org rows 5->6/', $problems['hard']));
        $this->assertContains('legacy_table_missing:local_toy_unused', $problems['hard']);
    }

    // The explanation of the import's core writes.

    public function test_expected_is_what_the_map_says_the_import_inserted(): void {
        $this->resetAfterTest();
        $this->map_row('user_enrolments', 101, 1, '', 'imported', 7);
        $this->map_row('user_enrolments', 102, 2, '', 'imported', 8);
        $this->map_row('user_enrolments', 101, 3, '', 'folded', 7);
        $this->map_row('role_assignments', 55, 1, '', 'imported', 7);
        $this->map_row('role_assignments', 56, 1, 'pos:2', 'imported', 7);
        $this->map_row('enrol', 9, 4, '', 'imported', 7);

        $all = parity_gate::expected(null);
        $this->assertEqualsCanonicalizing([101, 102], $all['user_enrolments']['inserted'], 'a fold inserted nothing');
        $this->assertEqualsCanonicalizing([55, 56], $all['role_assignments']['inserted'], 'a fan-out sub-row counts');
        $this->assertSame([9], $all['enrol']['inserted']);
        $this->assertSame(['enrolments' => 2, 'enrol_instances' => 1, 'role_assignments' => 2],
            parity_gate::explained_counts($all));

        $this->assertSame([101], parity_gate::expected(7)['user_enrolments']['inserted'] ?? [], 'run 7 only');
        $this->assertSame([102], parity_gate::expected(8)['user_enrolments']['inserted']);
        $this->assertSame([], parity_gate::expected(9)['user_enrolments']['inserted']);
    }

    public function test_the_ledgers_name_the_rows_and_columns_the_import_updated(): void {
        global $DB;
        $this->resetAfterTest();
        $this->require_table('local_sentientia_courses_detailfill');
        $this->require_table('local_sentientia_courses_tagmove');
        $DB->insert_record('local_sentientia_courses_detailfill', (object) ['courseid' => 9, 'detailid' => 1,
            'filledcols' => 'open_skill,open_cost', 'timecreated' => 1, 'timemodified' => 1]);
        $DB->insert_record('local_sentientia_courses_detailfill', (object) ['courseid' => 10, 'detailid' => 2,
            'filledcols' => '', 'timecreated' => 1, 'timemodified' => 1]);
        $DB->insert_record('local_sentientia_courses_tagmove', (object) ['taginstanceid' => 31, 'timecreated' => 1,
            'timemodified' => 1]);

        $expected = parity_gate::expected(null);
        $this->assertSame([9 => ['open_cost', 'open_skill']], $expected['course']['changed'],
            'sorted, and a trail row that filled nothing names no change');
        $this->assertSame([31 => ['component', 'itemtype']], $expected['tag_instance']['changed']);
    }

    public function test_the_core_gate_explains_exactly_the_enrolments_the_import_inserted(): void {
        global $DB;
        $this->resetAfterTest();
        $gen = $this->getDataGenerator();
        $course = $gen->create_course();
        [$u1, $u2, $u3, $u4] = [$gen->create_user(), $gen->create_user(), $gen->create_user(), $gen->create_user()];
        $gen->enrol_user($u1->id, $course->id);
        $gen->enrol_user($u2->id, $course->id);
        $mysql = $DB->get_dbfamily() === 'mysql';

        $db = new moodle_db($DB);
        $base = parity_core::baseline($db);
        $this->assertSame('insert', $base['user_enrolments']['mode']);
        $this->assertSame(2, $base['user_enrolments']['count']);
        $oldid = (int) $DB->get_field('user_enrolments', 'MIN(id)', []);

        // The import's conversion of one learner: a new row and its map row.
        $enrol = $DB->get_record('enrol', ['courseid' => $course->id, 'enrol' => 'manual'], '*', MUST_EXIST);
        $row = (object) ['status' => 0, 'enrolid' => $enrol->id, 'userid' => $u3->id, 'timestart' => 0, 'timeend' => 0,
            'modifierid' => 0, 'timecreated' => 5, 'timemodified' => 5];
        $newid = (int) $DB->insert_record('user_enrolments', $row);
        $this->map_row('user_enrolments', $newid, 1);

        $verdict = parity_core::evaluate($base, parity_core::evidence($db, $base), parity_gate::expected(null));
        $this->assertSame([], $verdict['hard']);
        if ($mysql) {
            $this->assertSame([], $verdict['unproven']);
        }

        // A row nobody recorded.
        $row->userid = $u4->id;
        $stray = (int) $DB->insert_record('user_enrolments', $row);
        $hard = implode(' | ', parity_core::evaluate($base, parity_core::evidence($db, $base), parity_gate::expected(null))['hard']);
        $this->assertStringContainsString("core_rows_added_not_in_the_import:user_enrolments: 1 (ids {$stray})", $hard);
        $this->map_row('user_enrolments', $stray, 2);

        // A recorded row that is gone.
        $DB->delete_records('user_enrolments', ['id' => $newid]);
        $hard = implode(' | ', parity_core::evaluate($base, parity_core::evidence($db, $base), parity_gate::expected(null))['hard']);
        $this->assertStringContainsString("core_rows_in_the_import_not_found:user_enrolments: 1 (ids {$newid})", $hard);

        // The row is back: explained again. Then an old row the import must never touch.
        $DB->import_record('user_enrolments', (object) ['id' => $newid, 'status' => 0, 'enrolid' => $enrol->id,
            'userid' => $u3->id, 'timestart' => 0, 'timeend' => 0, 'modifierid' => 0, 'timecreated' => 5, 'timemodified' => 5]);
        $this->assertSame([], parity_core::evaluate($base, parity_core::evidence($db, $base), parity_gate::expected(null))['hard']);
        if ($mysql) {
            $DB->set_field('user_enrolments', 'status', 1, ['id' => $oldid]);
            $hard = implode(' | ', parity_core::evaluate($base, parity_core::evidence($db, $base), parity_gate::expected(null))['hard']);
            $this->assertStringContainsString('core_rows_changed:user_enrolments: crc of the old rows', $hard);
        }
    }

    public function test_the_core_gate_explains_a_tag_instance_only_when_the_ledger_names_it(): void {
        global $DB;
        $this->resetAfterTest();
        $this->require_table('local_sentientia_courses_tagmove');
        if ($DB->get_dbfamily() !== 'mysql') {
            $this->markTestSkipped('per-row hashes need CRC32 (MySQL or MariaDB)');
        }
        $make = fn(int $item): int => (int) $DB->insert_record('tag_instance', (object) ['tagid' => 1,
            'component' => 'local_courses', 'itemtype' => 'courses', 'itemid' => $item, 'contextid' => 1, 'tiuserid' => 0,
            'ordering' => 0, 'timecreated' => 1, 'timemodified' => 1]);
        $moved = $make(5);
        $other = $make(6);
        $db = new moodle_db($DB);
        $base = parity_core::baseline($db);
        $this->assertSame('update', $base['tag_instance']['mode']);
        $this->assertCount(2, $base['tag_instance']['rows']);

        // The import moves one and records it.
        $DB->update_record('tag_instance', (object) ['id' => $moved, 'component' => 'core', 'itemtype' => 'course']);
        $DB->insert_record('local_sentientia_courses_tagmove', (object) ['taginstanceid' => $moved, 'timecreated' => 1,
            'timemodified' => 1]);
        $verdict = parity_core::evaluate($base, parity_core::evidence($db, $base), parity_gate::expected(null));
        $this->assertSame([], $verdict['hard']);

        // Something else moves another one: nothing in the import's records says so.
        $DB->set_field('tag_instance', 'component', 'core', ['id' => $other]);
        $hard = implode(' | ', parity_core::evaluate($base, parity_core::evidence($db, $base), parity_gate::expected(null))['hard']);
        $this->assertStringContainsString("core_row_changed_not_in_the_import:tag_instance:id={$other} columns component", $hard);

        // And a column the import never writes changes on the moved one.
        $DB->set_field('tag_instance', 'component', 'local_courses', ['id' => $other]);
        $DB->set_field('tag_instance', 'ordering', 3, ['id' => $moved]);
        $hard = implode(' | ', parity_core::evaluate($base, parity_core::evidence($db, $base), parity_gate::expected(null))['hard']);
        $this->assertStringContainsString("core_fixed_column_changed:tag_instance:id={$moved}", $hard);
    }

    public function test_expected_names_the_switched_off_instances_and_keeps_the_instances_the_import_inserted(): void {
        global $DB;
        $this->resetAfterTest();
        $this->require_table('local_sentientia_courses_enroloff');
        $course = $this->getDataGenerator()->create_course();
        $off = $this->enrol_instance($course->id, 'classroom');
        $left = $this->enrol_instance($course->id, 'program');
        $other = $this->enrol_instance($course->id, 'manual');
        $new = $this->enrol_instance($course->id, 'manual');

        // The importer decided to switch off $off and $left; the step switched off $off only (the site was open for the other, or an
        // administrator switched it back on). A trail row that names a manual instance is a lie the gate must not take on trust.
        $this->trail_row($off, $course->id, 'classroom', 0, 7);
        $this->trail_row($left, $course->id, 'program', 0, 7);
        $this->trail_row($other, $course->id, 'manual', 0, 8);
        $DB->update_record('enrol', (object) ['id' => $off, 'status' => 1, 'timemodified' => 99]);
        $DB->update_record('enrol', (object) ['id' => $other, 'status' => 1, 'timemodified' => 99]);
        $this->map_row('enrol', $new, 5, '', 'imported', 7);

        $all = parity_gate::expected(null)['enrol'];
        $this->assertSame([$off => ['status', 'timemodified']], $all['changed'],
            'only a BizLMS instance that really differs from the status the trail kept is named');
        $this->assertSame([$new], $all['inserted'], 'the ledger adds to the inserted ids, it does not replace them');

        $this->assertSame([$off => ['status', 'timemodified']], parity_gate::expected(7)['enrol']['changed'], 'run 7 wrote the trail row');
        $this->assertSame([], parity_gate::expected(8)['enrol']['changed'], 'run 8 only wrote a trail row for a manual instance');
        $this->assertSame([$new], parity_gate::expected(7)['enrol']['inserted']);
        $this->assertSame([], parity_gate::expected(8)['enrol']['inserted']);
        $this->assertSame(['enrolments' => 0, 'enrol_instances' => 1, 'role_assignments' => 0],
            parity_gate::explained_counts(parity_gate::expected(null)));
    }

    public function test_the_core_gate_explains_a_switched_off_bizlms_instance_only_when_the_trail_names_it(): void {
        global $DB;
        $this->resetAfterTest();
        $this->require_table('local_sentientia_courses_enroloff');
        if ($DB->get_dbfamily() !== 'mysql') {
            $this->markTestSkipped('per-row hashes need CRC32 (MySQL or MariaDB)');
        }
        $course = $this->getDataGenerator()->create_course();
        $other = $this->getDataGenerator()->create_course();
        $a = $this->enrol_instance($course->id, 'classroom');
        $b = $this->enrol_instance($course->id, 'learningplan');
        $c = $this->enrol_instance($course->id, 'manual');
        $d = $this->enrol_instance($course->id, 'program');
        $db = new moodle_db($DB);
        $base = parity_core::baseline($db);
        $this->assertSame('update', $base['enrol']['mode']);
        $this->assertSame(['status', 'timemodified'], $base['enrol']['writable']);
        $this->assertNotContains('status', $base['enrol']['fixed']);
        $verdict = fn() => parity_core::evaluate($base, parity_core::evidence($db, $base), parity_gate::expected(null));
        $hard = fn() => implode(' | ', $verdict()['hard']);
        $this->assertSame([], $verdict()['hard'], 'nothing happened yet');

        // The import switches $a off and records it, and inserts a manual instance and records that. The old rows of enrol still
        // hash to the baseline: status and timemodified are not in what is held fixed.
        $DB->update_record('enrol', (object) ['id' => $a, 'status' => 1, 'timemodified' => 99]);
        $this->trail_row($a, $course->id, 'classroom');
        $new = $this->enrol_instance($other->id, 'manual');
        $this->map_row('enrol', $new, 1);
        $result = $verdict();
        $this->assertSame([], $result['hard'], 'a recorded switch-off and a recorded insert are explained');
        $this->assertSame([], $result['unproven']);

        // An instance nobody recorded.
        $DB->set_field('enrol', 'status', 1, ['id' => $b]);
        $this->assertStringContainsString("core_row_changed_not_in_the_import:enrol:id={$b} columns status", $hard());
        $DB->set_field('enrol', 'status', 0, ['id' => $b]);

        // An instance that is not a BizLMS one, even with a trail row naming it (the trail is the importer's claim, not proof).
        $DB->update_record('enrol', (object) ['id' => $c, 'status' => 1, 'timemodified' => 99]);
        $this->trail_row($c, $course->id, 'manual');
        $this->assertStringContainsString("core_row_changed_not_in_the_import:enrol:id={$c} columns status,timemodified", $hard());
        $DB->update_record('enrol', (object) ['id' => $c, 'status' => 0, 'timemodified' => 2]);
        $this->assertSame([], $verdict()['hard']);

        // A trail row for an instance the step left enabled (the site was open): it names nothing, so it cannot fail.
        $this->trail_row($d, $course->id, 'program');
        $this->assertSame([], $verdict()['hard']);

        // A column the import never writes changes on the switched-off instance: the trail excuses status and timemodified only.
        $DB->set_field('enrol', 'name', 'renamed', ['id' => $a]);
        $this->assertStringContainsString("core_fixed_column_changed:enrol:id={$a}", $hard());
        $DB->set_field('enrol', 'name', null, ['id' => $a]);
        $this->assertSame([], $verdict()['hard']);

        // An old row that is gone, and an added row nobody recorded.
        $stray = $this->enrol_instance($other->id, 'manual');
        $this->assertStringContainsString("core_rows_added_not_in_the_import:enrol: 1 (ids {$stray})", $hard());
        $DB->delete_records('enrol', ['id' => $stray]);
        $DB->delete_records('enrol', ['id' => $d]);
        $this->assertStringContainsString("core_row_removed:enrol:id={$d}", $hard());
    }

    // Runs and reports.

    public function test_the_comparison_is_refused_when_there_is_no_import_to_explain_it_with(): void {
        global $DB;
        $this->resetAfterTest();
        $this->assertSame(['no_complete_apply_run_in_the_database'], parity_gate::refusals(null));
        $this->assertSame(['run_not_found:99'], parity_gate::refusals(99));

        $run = (object) ['runmode' => 'apply', 'status' => 'complete', 'features' => '[]', 'decisionshash' => 'abc',
            'codehash' => 'def', 'fingerprint' => fingerprint::install(), 'host' => 'h', 'pid' => 1, 'timestarted' => 1,
            'heartbeat' => 1, 'timefinished' => 2];
        $good = (int) $DB->insert_record('local_sentientia_legacyrun', $run);
        $run->status = 'failed';
        $failed = (int) $DB->insert_record('local_sentientia_legacyrun', $run);
        $run->status = 'complete';
        $run->fingerprint = 'aaaaaaaaaaaa';
        $foreign = (int) $DB->insert_record('local_sentientia_legacyrun', $run);

        $this->assertSame([], parity_gate::refusals(null));
        $this->assertSame([], parity_gate::refusals($good));
        $this->assertSame(["run_did_not_complete:{$failed}:failed"], parity_gate::refusals($failed));
        $this->assertSame(["run_is_from_another_install:{$foreign}"], parity_gate::refusals($foreign));
    }

    public function test_a_report_is_checked_against_the_database_the_install_and_the_decisions(): void {
        global $DB;
        $this->resetAfterTest();
        $decisions = decisions::none();
        $runid = (int) $DB->insert_record('local_sentientia_legacyrun', (object) ['runmode' => 'apply', 'status' => 'complete',
            'features' => '[]', 'decisionshash' => $decisions->hash(), 'codehash' => 'x', 'fingerprint' => fingerprint::install(),
            'host' => 'h', 'pid' => 1, 'timestarted' => 1, 'heartbeat' => 1, 'timefinished' => 2]);
        $DB->insert_record('local_sentientia_legacystep', (object) ['runid' => $runid, 'feature' => 'enrolments',
            'stepkey' => 'enrolments.enrolments', 'sourcetable' => '#user_enrolments.id', 'status' => 'done', 'watermark' => 9,
            'srccount' => 5, 'srcmaxid' => 9, 'srccrc' => null, 'processed' => 5, 'imported' => 3, 'adopted' => 0, 'merged' => 0,
            'folded' => 1, 'archived' => 0, 'skipped' => 1, 'updated' => 0, 'error' => null, 'timestarted' => 1,
            'timemodified' => 2, 'timefinished' => 2]);
        $report = [
            'meta' => ['runid' => $runid, 'mode' => 'apply', 'dryrun' => false, 'fingerprint' => fingerprint::install(),
                'decisions_hash' => $decisions->hash()],
            'features' => ['enrolments' => ['steps' => ['enrolments.enrolments' => ['counters' => [
                'processed' => 5, 'imported' => 3, 'adopted' => 0, 'merged' => 0, 'folded' => 1, 'archived' => 0, 'skipped' => 1,
                'updated' => 0]]]]],
        ];
        $this->assertSame([], parity_gate::report_problems($report, $runid, $decisions));
        $this->assertSame([], parity_gate::report_problems($report, null, null));

        $bad = $report;
        $bad['features']['enrolments']['steps']['enrolments.enrolments']['counters']['imported'] = 4;
        $this->assertSame(['report_step_counter_differs:enrolments:enrolments.enrolments:imported report=4 database=3'],
            parity_gate::report_problems($bad, $runid, $decisions));

        $bad = $report;
        $bad['meta']['dryrun'] = true;
        $bad['meta']['mode'] = 'dry-run';
        $bad['meta']['fingerprint'] = 'bbbbbbbbbbbb';
        $bad['meta']['decisions_hash'] = 'other';
        $problems = parity_gate::report_problems($bad, $runid + 1, $decisions);
        $this->assertContains('report_is_of_a_dry_run', $problems);
        $this->assertContains('report_mode_is_not_apply:dry-run', $problems);
        $this->assertContains('report_is_from_another_install', $problems);
        $this->assertContains('report_was_made_with_other_decisions', $problems);
        $this->assertContains('report_is_of_run_' . $runid . '_not_run_' . ($runid + 1), $problems);

        $this->assertSame(['report_has_no_run_id'], parity_gate::report_problems(['meta' => []], null, null));
        $this->assertSame(['report_run_is_not_in_the_database:424242'],
            array_values(array_filter(parity_gate::report_problems(['meta' => ['runid' => 424242, 'mode' => 'apply']], null, null),
                fn(string $p): bool => str_starts_with($p, 'report_run'))));
    }
}
