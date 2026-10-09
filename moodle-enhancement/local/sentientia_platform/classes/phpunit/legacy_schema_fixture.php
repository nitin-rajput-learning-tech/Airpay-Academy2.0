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

    /**
     * @var array{xml: string, extrafields: array<string, \xmldb_field[]>, content: string} What create_legacy_tables()
     *      was given, to recreate a dropped table. content is the fixture file's text: a fixture a test GENERATES under
     *      the dataroot's temp directory (the classroom test joins two BizLMS files there) is deleted by the per-test
     *      dataroot reset, so the loader writes it back before reading it again.
     */
    private static array $legacyfixturedefinition = ['xml' => '', 'extrafields' => [], 'content' => ''];

    /**
     * @var array<string, bool> Fixture tables that are ALSO tables of an installed component (the cart fixture's
     *      paygw_airpay, paygw_airpay_errorlog, paygw_course_enrolmentlog are the real Airpay gateway tables). They are
     *      borrowed: emptied, never dropped or altered.
     */
    private static array $legacyborrowedtables = [];

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
        self::$legacyborrowedtables = [];
        self::$legacyfixturedefinition = ['xml' => $fixturexml, 'extrafields' => $extrafields,
            'content' => (string) file_get_contents($fixturexml)];
        $installed = self::installed_table_names();
        foreach ($tables as $table) {
            if (isset($installed[$table])) {
                // A real table of an installed component. Dropping it (as the branch below does for a leftover
                // legacy table) deleted the Airpay gateway's own table: the reset after the next test then failed,
                // core marked the PHPUnit environment stale ('phpunittest' = 'na') and every later suite refused to
                // start (2026-10-07 and 10-08 local runs, right after the cart suite). Borrow it: empty, never drop.
                if (!$dbman->table_exists($table)) {
                    self::install_fixture_table($fixturexml, $table);
                }
                $DB->delete_records($table);
                self::$legacyborrowedtables[$table] = true;
                self::$legacyfixturetables[] = $table;
                continue;
            }
            // A killed run may have left the table behind.
            if ($dbman->table_exists($table)) {
                $dbman->drop_table(new \xmldb_table($table));
            }
            self::install_fixture_table($fixturexml, $table);
            self::$legacyfixturetables[] = $table;
        }

        foreach ($extrafields as $table => $fields) {
            if (isset(self::$legacyborrowedtables[$table])) {
                throw new \coding_exception('legacy fixture may not add columns to the installed table ' . $table);
            }
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
     * Install one table of a fixture file.
     *
     * Several fixtures are byte-identical copies of a BizLMS install.xml and keep its PATH attribute
     * (local/ratings/db, local/classroom/db, local/location/db, local/recompletion/db, local/skillrepository/db).
     * Core refuses a file whose PATH is not the directory it sits in (xmldb_structure::arr2xmldb_structure, which
     * then skips every table), so such a file is loaded from a temporary copy next to it with PATH set to that
     * directory, removed straight after. The fixture itself stays identical to the BizLMS original.
     *
     * Core resolves realpath($CFG->dirroot . '/' . PATH . '/' . basename) and compares it with the file, so a fixture a
     * test copied OUTSIDE the code tree (the classroom test writes one under the dataroot's temp directory) gets a
     * PATH with '..' segments. Only a fixture on another drive than the code cannot be expressed; that is refused.
     *
     * @param string $fixturexml Absolute path of the fixture install.xml.
     * @param string $table
     * @return void
     */
    private static function install_fixture_table(string $fixturexml, string $table): void {
        global $CFG, $DB;
        $dbman = $DB->get_manager();
        if (!is_file($fixturexml)) {
            // A generated fixture under the dataroot's temp directory does not survive the per-test dataroot reset.
            $content = self::$legacyfixturedefinition['content'] ?? '';
            if ($content === '' || $fixturexml !== self::$legacyfixturedefinition['xml']) {
                throw new \coding_exception('legacy fixture file is missing: ' . $fixturexml);
            }
            if (!is_dir(dirname($fixturexml))) {
                mkdir(dirname($fixturexml), $CFG->directorypermissions ?? 0777, true);
            }
            file_put_contents($fixturexml, $content);
        }
        $dir = dirname((string) realpath($fixturexml));
        $root = (string) realpath($CFG->dirroot);
        $relative = self::relative_dir($root, $dir);
        if ($relative === null) {
            throw new \coding_exception('legacy fixture is on another drive than the Moodle code, so no XMLDB PATH can '
                . 'reach it: ' . $fixturexml);
        }
        $xml = (string) file_get_contents($fixturexml);
        if (!preg_match('/<XMLDB\b[^>]*\bPATH="([^"]*)"/', $xml, $m) || $m[1] === $relative) {
            $dbman->install_one_table_from_xmldb_file($fixturexml, $table);
            return;
        }
        // A name of its own per call: on Windows a copy an earlier call could not delete (a scanner holding it) would
        // otherwise be reused, and the 2026-10-09 run read one back empty. The write is checked for the same reason.
        $copy = $dir . DIRECTORY_SEPARATOR . 'tmp-' . getmypid() . '-' . uniqid() . '-' . basename($fixturexml);
        $copied = (string) preg_replace('/(<XMLDB\b[^>]*\bPATH=")[^"]*(")/', '${1}' . $relative . '${2}', $xml, 1);
        if ($copied === '' || file_put_contents($copy, $copied) !== strlen($copied)) {
            @unlink($copy);
            throw new \coding_exception('could not write the PATH-corrected copy of the legacy fixture: ' . $copy);
        }
        try {
            $dbman->install_one_table_from_xmldb_file($copy, $table);
        } finally {
            @unlink($copy);
        }
    }

    /**
     * The path of $to relative to $from, with '..' segments where it lies outside, '/' separated.
     *
     * @param string $from An absolute directory (the code root).
     * @param string $to An absolute directory.
     * @return string|null '' for the same directory; null when the two are on different Windows drives.
     */
    private static function relative_dir(string $from, string $to): ?string {
        $split = static fn(string $p): array => array_values(array_filter(explode('/', str_replace('\\', '/', $p)),
            static fn(string $s): bool => $s !== ''));
        $a = $split($from);
        $b = $split($to);
        $windows = DIRECTORY_SEPARATOR === '\\';
        $same = static fn(string $x, string $y): bool => $windows ? strcasecmp($x, $y) === 0 : $x === $y;
        $isdrive = static fn(array $s): bool => isset($s[0]) && preg_match('/^[A-Za-z]:$/', $s[0]) === 1;
        if (($isdrive($a) || $isdrive($b)) && !(isset($a[0], $b[0]) && $same($a[0], $b[0]))) {
            return null;
        }
        $i = 0;
        while ($i < count($a) && $i < count($b) && $same($a[$i], $b[$i])) {
            $i++;
        }
        return implode('/', array_merge(array_fill(0, count($a) - $i, '..'), array_slice($b, $i)));
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
        self::install_fixture_table(self::$legacyfixturedefinition['xml'], $table);
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
                if (!$dbman->table_exists($table)) {
                    continue;
                }
                if (isset(self::$legacyborrowedtables[$table])) {
                    $DB->delete_records($table);
                    continue;
                }
                $dbman->drop_table(new \xmldb_table($table));
            }
        } finally {
            self::$legacyfixturetables = [];
            self::$legacyborrowedtables = [];
        }
    }

    /**
     * Names of every table the installed components declare (their install.xml), cached for the process.
     *
     * @return array<string, bool>
     */
    private static function installed_table_names(): array {
        global $DB;
        static $names = null;
        if ($names === null) {
            $names = [];
            foreach ($DB->get_manager()->get_install_xml_schema()->getTables() as $xmltable) {
                $names[$xmltable->getName()] = true;
            }
        }
        return $names;
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
        if (isset(self::$legacyborrowedtables[$table])) {
            throw new \coding_exception('legacy fixture refuses to drop the installed table ' . $table);
        }
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
