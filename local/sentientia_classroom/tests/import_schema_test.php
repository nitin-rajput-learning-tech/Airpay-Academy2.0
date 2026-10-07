<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

/**
 * The schema the BizLMS classroom importer writes to is the same on a fresh install and on an upgraded site
 * (ADR-032, mapping doc section 15, "Schema additions").
 *
 * install.xml declares it for a fresh install; upgrade step 2026093002 (db/upgradelib.php
 * local_sentientia_classroom_ensure_import_schema) brings an existing site to it without touching a row. The two
 * must stay identical, so the new tables are built both ways and compared.
 *
 * @package    local_sentientia_classroom
 * @category   test
 * @copyright  2026 Airpay Payment Services
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_sentientia_classroom;

defined('MOODLE_INTERNAL') || die();

/**
 * @covers ::local_sentientia_classroom_ensure_import_schema
 * @covers ::xmldb_local_sentientia_classroom_upgrade
 * @group local_sentientia_classroom
 * @group bizlms_import
 */
final class import_schema_test extends \advanced_testcase {

    /** @var array<string, string[]> Columns the import adds to tables that already existed. */
    private const ADDED_COLUMNS = [
        'local_sentientia_classroom' => ['shortname', 'trainingstart', 'trainingend', 'timecompleted', 'createdby'],
        'local_sentientia_classroom_users' => ['completion_status', 'timecompleted', 'hours'],
        'local_sentientia_locations' => ['parentid', 'venue_type', 'building'],
    ];

    /** @var string[] The tables the import adds. */
    private const NEW_TABLES = ['local_sentientia_classroom_trainers', 'local_sentientia_classroom_courses'];

    protected function setUp(): void {
        global $CFG;
        parent::setUp();
        $this->resetAfterTest();
        $this->preventResetByRollback();
        require_once($CFG->dirroot . '/local/sentientia_classroom/db/upgradelib.php');
    }

    protected function tearDown(): void {
        global $DB;
        // A failed assertion must not leave the shared test DB without the schema the other tests expect.
        local_sentientia_classroom_ensure_import_schema($DB->get_manager());
        parent::tearDown();
    }

    /**
     * Assert the schema install.xml declares.
     *
     * @return void
     */
    private function assert_import_schema(): void {
        global $DB;
        $dbman = $DB->get_manager();
        foreach (self::ADDED_COLUMNS as $table => $columns) {
            $have = $DB->get_columns($table, false);
            foreach ($columns as $column) {
                $this->assertArrayHasKey($column, $have, "{$table}.{$column} must exist");
            }
        }
        $this->assertTrue((bool) $DB->get_columns('local_sentientia_classroom_users', false)['completion_status']->not_null);
        $this->assertSame('0', (string) $DB->get_columns('local_sentientia_classroom_users', false)['completion_status']->default_value);
        foreach (self::NEW_TABLES as $table) {
            $this->assertTrue($dbman->table_exists($table), "{$table} must exist");
        }
        $trainers = new \xmldb_table('local_sentientia_classroom_trainers');
        $this->assertTrue($dbman->index_exists($trainers,
            new \xmldb_index('idx_classroom_trainer', XMLDB_INDEX_UNIQUE, ['classroomid', 'trainerid'])));
        $courses = new \xmldb_table('local_sentientia_classroom_courses');
        $this->assertTrue($dbman->index_exists($courses,
            new \xmldb_index('idx_classroom_course', XMLDB_INDEX_UNIQUE, ['classroomid', 'courseid'])));
    }

    /**
     * Take the import schema away, as a site installed before it looks.
     *
     * @return void
     */
    private function drop_import_schema(): void {
        global $DB;
        $dbman = $DB->get_manager();
        foreach (self::NEW_TABLES as $table) {
            if ($dbman->table_exists($table)) {
                $dbman->drop_table(new \xmldb_table($table));
            }
        }
        foreach (self::ADDED_COLUMNS as $table => $columns) {
            foreach ($columns as $column) {
                $xmltable = new \xmldb_table($table);
                $field = new \xmldb_field($column);
                if ($dbman->field_exists($xmltable, $field)) {
                    $dbman->drop_field($xmltable, $field);
                }
            }
        }
    }

    public function test_a_fresh_install_already_has_the_import_schema(): void {
        global $DB;
        $this->assert_import_schema();
        $this->assertSame([], local_sentientia_classroom_ensure_import_schema($DB->get_manager()),
            'A fresh install needs nothing: it is already what install.xml declares.');
    }

    public function test_a_site_installed_before_the_import_gets_the_tables_and_columns(): void {
        global $DB;
        $this->drop_import_schema();

        $changed = local_sentientia_classroom_ensure_import_schema($DB->get_manager());

        $this->assertContains('created local_sentientia_classroom_trainers', $changed);
        $this->assertContains('created local_sentientia_classroom_courses', $changed);
        $this->assertContains('added local_sentientia_classroom.shortname', $changed);
        $this->assertContains('added local_sentientia_classroom_users.completion_status', $changed);
        $this->assertContains('added local_sentientia_locations.parentid', $changed);
        $this->assert_import_schema();
        $this->assertSame([], local_sentientia_classroom_ensure_import_schema($DB->get_manager()),
            'the step is idempotent');
    }

    public function test_the_step_touches_no_row(): void {
        global $DB;
        $now = time();
        $classroomid = (int) $DB->insert_record('local_sentientia_classroom', (object) [
            'name' => 'Before the import schema', 'timecreated' => $now, 'timemodified' => $now]);
        $user = $this->getDataGenerator()->create_user();
        $DB->insert_record('local_sentientia_classroom_users', (object) [
            'classroomid' => $classroomid, 'userid' => $user->id, 'timecreated' => $now, 'timemodified' => $now]);
        $this->drop_import_schema();

        local_sentientia_classroom_ensure_import_schema($DB->get_manager());

        $this->assertSame('Before the import schema', $DB->get_field('local_sentientia_classroom', 'name', ['id' => $classroomid]));
        $row = $DB->get_record('local_sentientia_classroom_users', ['classroomid' => $classroomid], '*', MUST_EXIST);
        $this->assertSame(0, (int) $row->completion_status, 'a new NOT NULL column takes its default on existing rows');
        $this->assertNull($row->timecompleted);
        $this->assertNull($row->hours);
    }

    public function test_upgrade_step_2026093002_brings_an_upgraded_site_to_the_schema(): void {
        global $CFG, $DB;
        require_once($CFG->libdir . '/upgradelib.php');
        require_once($CFG->dirroot . '/local/sentientia_classroom/db/upgrade.php');
        $this->drop_import_schema();

        set_config('version', 2026093001, 'local_sentientia_classroom');
        ob_start();
        $ok = xmldb_local_sentientia_classroom_upgrade(2026093001);
        $out = (string) ob_get_clean();

        $this->assertTrue($ok);
        $this->assertStringContainsString('created local_sentientia_classroom_trainers', $out);
        $this->assert_import_schema();
        $this->assertEquals(2026093002, get_config('local_sentientia_classroom', 'version'));
    }

    public function test_install_xml_and_the_upgrade_helper_build_the_same_new_tables(): void {
        global $CFG, $DB;
        $dbman = $DB->get_manager();
        $signature = function (string $table) use ($DB): array {
            $out = [];
            foreach ($DB->get_columns($table, false) as $name => $column) {
                $out[$name] = [$column->meta_type, (bool) $column->not_null, (string) $column->default_value,
                    (int) $column->max_length];
            }
            return $out;
        };
        foreach (self::NEW_TABLES as $table) {
            $frominstall = $signature($table);
            $dbman->drop_table(new \xmldb_table($table));
            local_sentientia_classroom_ensure_import_schema($dbman);
            $this->assertEquals($frominstall, $signature($table), "{$table}: upgradelib.php and install.xml disagree");
        }
        // The location columns too: a table created by the helper must match install.xml's.
        $fromxml = $signature('local_sentientia_locations');
        $dbman->drop_table(new \xmldb_table('local_sentientia_locations'));
        local_sentientia_classroom_ensure_import_schema($dbman);
        $this->assertEquals($fromxml, $signature('local_sentientia_locations'));
    }

    public function test_install_xml_has_no_raw_less_than_sign_in_a_comment(): void {
        global $CFG;
        // A raw '<' in a COMMENT attribute silently breaks the whole file (XMLDB trap, 2026-08-29).
        $raw = (string) file_get_contents($CFG->dirroot . '/local/sentientia_classroom/db/install.xml');
        preg_match_all('/COMMENT="([^"]*)"/', $raw, $matches);
        foreach ($matches[1] as $comment) {
            $this->assertStringNotContainsString('<', $comment);
        }
        $this->assertNotFalse(simplexml_load_string($raw), 'install.xml is well-formed');
    }
}
