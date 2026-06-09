<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

namespace local_sentientia_platform\phpunit;

defined('MOODLE_INTERNAL') || die();

/**
 * PHPUnit fixture trait — ensure mdl_user.open_path and mdl_course.open_path
 * exist for the duration of a test run.
 *
 * Background
 * ----------
 * Airpay's tenant model is BizLMS-derived: each user has an `open_path`
 * column on `mdl_user` (e.g. '/1/183/45' for an Airpay employee in
 * department 45). The bizlms plugin adds this column at install time in
 * production. The vanilla Moodle PHPUnit fixture does NOT include the
 * bizlms plugin, so `mdl_user.open_path` is absent in the test DB.
 *
 * Before this trait shipped (Day-3, 2026-05-14), 14 PHPUnit tests across
 * `tenant_test`, `sharing_manager_test`, and `request_manager_test`
 * detected the missing column and called `$this->markTestSkipped(...)`,
 * which meant those tests ONLY ran on staging where bizlms IS installed.
 *
 * This trait adds the column at `setUpBeforeClass` time using Moodle's
 * `database_manager::add_field()` API. The column is added if absent and
 * left alone if present — idempotent across re-runs. Adding a nullable
 * column is non-destructive and PHPUnit's transactional test isolation
 * still rolls back any data inserted into it between tests.
 *
 * Usage
 * -----
 *     class my_tenant_test extends \advanced_testcase {
 *         use \local_sentientia_platform\tests\fixtures\open_path_fixture_trait;
 *
 *         public function test_something_with_tenants(): void {
 *             $this->resetAfterTest(true);
 *             // open_path is now available; no skip needed.
 *             $u = $this->getDataGenerator()->create_user();
 *             $DB->set_field('user', 'open_path', '/77', ['id' => $u->id]);
 *             ...
 *         }
 *     }
 *
 * Why a trait and not a base class
 * --------------------------------
 * Moodle's PHPUnit tests must extend `\advanced_testcase`. Single PHP
 * inheritance would force every test class to choose between
 * advanced_testcase and a tenant-aware base class — that breaks the
 * established testing pattern. Traits are the standard way to
 * compose shared setup in this codebase (see
 * `\local_sentientia_platform\tests\fixtures\open_path_fixture_trait` callers).
 *
 * @package local_sentientia_platform
 */
trait open_path_fixture_trait {

    /**
     * Ensure both mdl_user.open_path and mdl_course.open_path exist on
     * the test schema. Idempotent — does nothing when the columns are
     * already present (e.g. on staging or when bizlms is loaded).
     *
     * Why per-test setUp (not setUpBeforeClass)
     * -----------------------------------------
     * Moodle's `advanced_testcase::setUp()` runs `phpunit_util::reset_
     * all_data()` which restores the schema snapshot taken at test-env
     * init. That snapshot doesn't have our `open_path` column, so each
     * test resets to "column absent" before its body runs. We add the
     * column inside setUp() AFTER the parent's reset_all_data, so the
     * column exists for the actual test body.
     *
     * We also pre-call `resetAfterTest(true)` so Moodle's "did the
     * test modify the DB?" assertion at tearDown is appeased — every
     * test using this trait is automatically a write-test in the
     * framework's eyes.
     */
    public function setUp(): void {
        parent::setUp();
        // Mark the test as one that may modify DB state. Idempotent
        // and safe to re-call from the test body.
        $this->resetAfterTest(true);
        self::ensure_open_path_column('user');
        self::ensure_open_path_column('course');
        // Day-3 catalog tests also touch open_level / open_skill /
        // open_coursetype which the catalog_manager query selects.
        // Adding them in the trait so any future catalog/admin test
        // that uses BizLMS course fields gets them for free.
        self::ensure_bizlms_course_columns();
    }

    /**
     * Add `open_path` to the given table if it isn't there already.
     * The column is varchar(254) nullable — same shape bizlms uses on
     * production. We deliberately don't index it (the column is
     * indexed in production by a bizlms upgrade step that we don't
     * need to replicate for unit testing).
     *
     * @param string $table Moodle table name ('user' or 'course')
     */
    private static function ensure_open_path_column(string $table): void {
        global $DB;
        $columns = $DB->get_columns($table);
        if (isset($columns['open_path'])) {
            return;  // already there (staging, bizlms loaded, or earlier run)
        }
        $dbman = $DB->get_manager();
        $xmldb_table = new \xmldb_table($table);
        $field = new \xmldb_field('open_path', XMLDB_TYPE_CHAR, '254',
            null, null, null, null);
        // Skip when somehow the table itself doesn't exist (paranoia —
        // shouldn't happen for {user}/{course}).
        if (!$dbman->table_exists($xmldb_table)) {
            return;
        }
        $dbman->add_field($xmldb_table, $field);
    }

    /**
     * Add the rest of the BizLMS-extension course fields the catalog
     * SELECTs need: open_level, open_skill, open_coursetype. All are
     * nullable ints in BizLMS production. The catalog_manager query
     * lists them in its SELECT clause; without them, every catalog
     * test fails with "Unknown column 'c.open_level' in 'SELECT'".
     */
    private static function ensure_bizlms_course_columns(): void {
        global $DB;
        $dbman = $DB->get_manager();
        $columns = $DB->get_columns('course');
        $xmldb_table = new \xmldb_table('course');
        if (!$dbman->table_exists($xmldb_table)) {
            return;
        }
        $needed = [
            'open_level'      => XMLDB_TYPE_INTEGER,
            'open_skill'      => XMLDB_TYPE_INTEGER,
            'open_coursetype' => XMLDB_TYPE_INTEGER,
        ];
        foreach ($needed as $name => $type) {
            if (isset($columns[$name])) {
                continue;
            }
            $field = new \xmldb_field($name, $type, '10', null, null, null, null);
            $dbman->add_field($xmldb_table, $field);
        }
    }
}
