<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

namespace local_sentientia_platform\phpunit;

defined('MOODLE_INTERNAL') || die();

/**
 * PHPUnit fixture trait: create BizLMS legacy tables for an import test
 * (ADR-032, "Test approach" 1).
 *
 * The phpunit database is built from install.xml files, and the BizLMS plugins
 * are not installed in it, so a test that imports from them must create their
 * tables itself. This trait loads a checked-in XMLDB copy (see "Fixture copies"
 * in the ADR: tests/fixtures/bizlms/<plugin>.install.xml) and creates the tables
 * one at a time, dropping a leftover of a killed run first.
 *
 * Lifecycle, chosen for speed on the slow local MariaDB: create ONCE in
 * setUpBeforeClass(), truncate in setUp(), drop in tearDownAfterClass() inside
 * try/finally so the parent teardown always runs. DDL is never run inside a
 * transaction: setUp() calls preventResetByRollback(), because on PostgreSQL
 * Moodle wraps a whole test in a transaction unless told not to.
 *
 * What "create once" really does. After every test that called resetAfterTest(), which this
 * trait's setUp() does, testing_util::reset_database() drops every table that is not in the
 * install snapshot. The fixture tables are not, so they are gone before the next test and
 * truncate_legacy_tables() recreates them through its "table is missing" branch
 * (recreate_legacy_table()). The lifecycle is therefore correct but not fast: each test pays
 * a CREATE TABLE per fixture table, and the local MariaDB (default XAMPP settings) makes that
 * the dominant cost of a bizlms_import run. Budget for it, and do not "optimise" the trait by
 * dropping resetAfterTest(): without it Moodle flags every test as an unexpected database
 * modification.
 *
 * Safety: it refuses to run unless PHPUNIT_TEST is set and $CFG->prefix equals
 * $CFG->phpunit_prefix, so it can never create or drop a table in a real database.
 *
 * The using class must define legacy_fixture_definition() and must not define
 * setUpBeforeClass(), tearDownAfterClass() or setUp() itself.
 *
 * @package    local_sentientia_platform
 * @copyright  2026 Airpay Payment Services
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
trait legacy_schema_fixture {

    /** @var string[] Tables this class created, in creation order. */
    private static array $legacyfixturetables = [];

    /** @var array{xml: string, extrafields: array<string, \xmldb_field[]>} What create_legacy_tables() was given, to recreate a dropped table. */
    private static array $legacyfixturedefinition = ['xml' => '', 'extrafields' => []];

    /**
     * What to create.
     *
     * @return array{xml: string, only?: string[], extrafields?: array<string, \xmldb_field[]>}
     *         xml is the absolute path of the fixture install.xml; only limits the tables
     *         created (default all); extrafields adds production-only columns per table.
     */
    abstract protected static function legacy_fixture_definition(): array;

    /**
     * Create the legacy tables once for the class.
     *
     * @return void
     */
    public static function setUpBeforeClass(): void {
        parent::setUpBeforeClass();
        $definition = static::legacy_fixture_definition();
        self::create_legacy_tables($definition['xml'], $definition['only'] ?? [], $definition['extrafields'] ?? []);
    }

    /**
     * Drop the legacy tables; the parent teardown runs whatever happens.
     *
     * @return void
     */
    public static function tearDownAfterClass(): void {
        try {
            self::drop_legacy_tables();
        } finally {
            parent::tearDownAfterClass();
        }
    }

    /**
     * Truncate the legacy tables before every test.
     *
     * @return void
     */
    protected function setUp(): void {
        parent::setUp();
        // Truncating is a database write, so every test of a class that uses this trait is a writing test:
        // without this Moodle flags the ones that only read as an unexpected database modification.
        $this->resetAfterTest();
        $this->preventResetByRollback();
        self::truncate_legacy_tables();
    }

    /**
     * Create the tables of a fixture file.
     *
     * @param string $fixturexml Absolute path of the fixture install.xml.
     * @param string[] $only Only these tables; empty means every table of the file.
     * @param array<string, \xmldb_field[]> $extrafields table => production-only columns to add.
     * @return string[] The tables created.
     */
    protected static function create_legacy_tables(string $fixturexml, array $only = [], array $extrafields = []): array {
        global $DB;
        self::assert_legacy_fixture_is_safe();
        if (!is_readable($fixturexml)) {
            throw new \coding_exception('legacy fixture file is not readable: ' . $fixturexml);
        }
        $dbman = $DB->get_manager();

        preg_match_all('/<TABLE\s+NAME="([^"]+)"/', (string) file_get_contents($fixturexml), $matches);
        $tables = $only ? array_values(array_intersect($matches[1], $only)) : $matches[1];

        self::$legacyfixturetables = [];
        self::$legacyfixturedefinition = ['xml' => $fixturexml, 'extrafields' => $extrafields];
        foreach ($tables as $table) {
            // A killed run may have left the table behind.
            if ($dbman->table_exists($table)) {
                $dbman->drop_table(new \xmldb_table($table));
            }
            $dbman->install_one_table_from_xmldb_file($fixturexml, $table);
            self::$legacyfixturetables[] = $table;
        }

        foreach ($extrafields as $table => $fields) {
            $xmltable = new \xmldb_table($table);
            foreach ($fields as $field) {
                if (!$dbman->field_exists($xmltable, $field)) {
                    $dbman->add_field($xmltable, $field);
                }
            }
        }
        return self::$legacyfixturetables;
    }

    /**
     * Remove every row of the tables this class created.
     *
     * @return void
     */
    protected static function truncate_legacy_tables(): void {
        global $DB;
        self::assert_legacy_fixture_is_safe();
        $dbman = $DB->get_manager();
        foreach (self::$legacyfixturetables as $table) {
            if (!$dbman->table_exists($table)) {
                // A test dropped it (drop_legacy_table); put it back for the next one.
                self::recreate_legacy_table($table);
                continue;
            }
            $DB->delete_records($table);
        }
    }

    /**
     * Recreate one table of the fixture, with its production-only columns.
     *
     * @param string $table
     * @return void
     */
    private static function recreate_legacy_table(string $table): void {
        global $DB;
        $dbman = $DB->get_manager();
        $dbman->install_one_table_from_xmldb_file(self::$legacyfixturedefinition['xml'], $table);
        foreach (self::$legacyfixturedefinition['extrafields'][$table] ?? [] as $field) {
            $dbman->add_field(new \xmldb_table($table), $field);
        }
    }

    /**
     * Drop the tables this class created.
     *
     * @return void
     */
    protected static function drop_legacy_tables(): void {
        global $DB;
        self::assert_legacy_fixture_is_safe();
        $dbman = $DB->get_manager();
        try {
            foreach (array_reverse(self::$legacyfixturetables) as $table) {
                if ($dbman->table_exists($table)) {
                    $dbman->drop_table(new \xmldb_table($table));
                }
            }
        } finally {
            self::$legacyfixturetables = [];
        }
    }

    /**
     * Drop one legacy table mid-test, for the "feature not applicable without
     * its tables" case. The class teardown tolerates a table that is already gone.
     *
     * @param string $table
     * @return void
     */
    protected static function drop_legacy_table(string $table): void {
        global $DB;
        self::assert_legacy_fixture_is_safe();
        $dbman = $DB->get_manager();
        if ($dbman->table_exists($table)) {
            $dbman->drop_table(new \xmldb_table($table));
        }
    }

    /**
     * Refuse outside PHPUnit or on a database that is not the phpunit one.
     *
     * @return void
     */
    private static function assert_legacy_fixture_is_safe(): void {
        global $CFG;
        if (!defined('PHPUNIT_TEST') || !PHPUNIT_TEST) {
            throw new \coding_exception('legacy_schema_fixture may only run under PHPUnit');
        }
        if (empty($CFG->phpunit_prefix) || $CFG->prefix !== $CFG->phpunit_prefix) {
            throw new \coding_exception('legacy_schema_fixture refuses: $CFG->prefix is not the phpunit prefix');
        }
    }
}
