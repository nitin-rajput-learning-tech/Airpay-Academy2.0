<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

/**
 * PHPUnit coverage for the db/upgrade.php migration path.
 *
 * The Phase B.12 hotfix (commit 114fed155) added a defensive
 * `$dbman->create_table()` call inside the `< 2026051300` savepoint
 * because that savepoint inserts into `quizaccess_airpay_proctor`
 * but on production (v2026051120) the table did not exist yet —
 * install.xml created it only on FRESH installs, not on upgrade.
 *
 * This test simulates the production upgrade path:
 *   1. Drop the quizaccess_airpay_proctor table (mirrors prod state at v2026051120).
 *   2. Seed a `mdl_config_plugins` row keyed `quizaccess_airpay_proctoring`
 *      / `quiz_42_enabled` = 1 (the legacy config-row-per-quiz pattern).
 *   3. Call xmldb_quizaccess_airpay_proctoring_upgrade($oldversion=2026051200).
 *   4. Verify the table now exists.
 *   5. Verify the legacy config row was migrated into a relational row.
 *   6. Verify the legacy config row was deleted.
 *
 * @package    quizaccess_airpay_proctoring
 * @category   test
 * @copyright  2026 Airpay Payment Services
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace quizaccess_airpay_proctoring;

defined('MOODLE_INTERNAL') || die();

/**
 * Tests for db/upgrade.php migration behavior.
 *
 * Note: db/upgrade.php is not autoloaded by PHPUnit — Moodle only
 * includes it during the plugin upgrade flow. The tests include the
 * file manually before invoking the upgrade function.
 *
 * @covers ::xmldb_quizaccess_airpay_proctoring_upgrade
 */
final class upgrade_test extends \advanced_testcase {

    /**
     * Include the upgrade function once per test class.
     */
    public static function setUpBeforeClass(): void {
        global $CFG;
        require_once($CFG->dirroot . '/mod/quiz/accessrule/airpay_proctoring/db/upgrade.php');
    }

    /**
     * The B.12 hotfix scenario: production sits at v2026051120 with NO
     * table, then upgrades. The upgrade must create the table AND
     * migrate config rows.
     */
    public function test_upgrade_creates_table_when_missing_and_migrates_legacy_config(): void {
        global $DB;
        $this->resetAfterTest();
        $dbman = $DB->get_manager();

        // 1. Drop the table to mirror production v2026051120 state.
        $table = new \xmldb_table('quizaccess_airpay_proctor');
        if ($dbman->table_exists($table)) {
            $dbman->drop_table($table);
        }
        $this->assertFalse($dbman->table_exists($table),
            'Setup failure — table still exists after drop');

        // 2. Seed legacy config_plugins rows (the production pattern).
        $DB->insert_record('config_plugins', (object) [
            'plugin' => 'quizaccess_airpay_proctoring',
            'name'   => 'quiz_42_enabled',
            'value'  => '1',
        ]);
        $DB->insert_record('config_plugins', (object) [
            'plugin' => 'quizaccess_airpay_proctoring',
            'name'   => 'quiz_43_enabled',
            'value'  => '0',
        ]);

        // 3. Run the upgrade from a pre-2026051300 version.
        $result = xmldb_quizaccess_airpay_proctoring_upgrade(2026051200);

        // 4. Upgrade returned true.
        $this->assertTrue($result);

        // 5. Table now exists.
        $this->assertTrue($dbman->table_exists($table),
            'Defensive create_table() in upgrade.php did not create the table');

        // 6. Legacy config rows were migrated to relational rows.
        $row42 = $DB->get_record('quizaccess_airpay_proctor', ['quizid' => 42]);
        $this->assertNotFalse($row42, 'quiz 42 should have been migrated');
        $this->assertEquals(1, (int) $row42->enabled);

        $row43 = $DB->get_record('quizaccess_airpay_proctor', ['quizid' => 43]);
        $this->assertNotFalse($row43, 'quiz 43 should have been migrated');
        $this->assertEquals(0, (int) $row43->enabled);

        // 7. Legacy config rows were deleted by the migration.
        $this->assertFalse($DB->record_exists('config_plugins', [
            'plugin' => 'quizaccess_airpay_proctoring',
            'name'   => 'quiz_42_enabled',
        ]));
        $this->assertFalse($DB->record_exists('config_plugins', [
            'plugin' => 'quizaccess_airpay_proctoring',
            'name'   => 'quiz_43_enabled',
        ]));
    }

    /**
     * The fresh-install scenario: install.xml has already created the
     * table. Calling the upgrade from $oldversion < 2026051300 with no
     * legacy config rows must be a clean no-op (table still there, no
     * rows added).
     */
    public function test_upgrade_is_idempotent_on_fresh_install_with_no_legacy_rows(): void {
        global $DB;
        $this->resetAfterTest();
        $dbman = $DB->get_manager();

        $table = new \xmldb_table('quizaccess_airpay_proctor');
        $this->assertTrue($dbman->table_exists($table),
            'install.xml should have created the table in PHPUnit reset');

        // No legacy config rows seeded.
        $before = $DB->count_records('quizaccess_airpay_proctor');

        $result = xmldb_quizaccess_airpay_proctoring_upgrade(2026051200);

        $this->assertTrue($result);
        $this->assertTrue($dbman->table_exists($table));
        $this->assertEquals($before,
            $DB->count_records('quizaccess_airpay_proctor'),
            'No new rows should appear when there are no legacy config rows');
    }

    /**
     * Upgrade from $oldversion >= 2026051300 should be a no-op for the
     * migration block (Moodle's savepoint-comparison machinery would
     * normally prevent the block from running at all, but the function
     * itself must remain idempotent if called manually).
     */
    public function test_upgrade_skips_migration_for_already_migrated_version(): void {
        global $DB;
        $this->resetAfterTest();

        // Seed a config row that the migration WOULD pick up if it ran.
        $DB->insert_record('config_plugins', (object) [
            'plugin' => 'quizaccess_airpay_proctoring',
            'name'   => 'quiz_99_enabled',
            'value'  => '1',
        ]);

        $result = xmldb_quizaccess_airpay_proctoring_upgrade(2026051300);
        $this->assertTrue($result);

        // The config row should still exist — migration did NOT run.
        $this->assertTrue($DB->record_exists('config_plugins', [
            'plugin' => 'quizaccess_airpay_proctoring',
            'name'   => 'quiz_99_enabled',
        ]));
        $this->assertFalse($DB->record_exists('quizaccess_airpay_proctor', ['quizid' => 99]));
    }

    /**
     * Migration must skip config rows that don't match the
     * `quiz_<N>_enabled` pattern (defensive against unrelated
     * keys that share the plugin prefix).
     */
    public function test_upgrade_ignores_unrelated_config_keys(): void {
        global $DB;
        $this->resetAfterTest();
        $dbman = $DB->get_manager();

        // Drop the table to force the create_table branch.
        $table = new \xmldb_table('quizaccess_airpay_proctor');
        if ($dbman->table_exists($table)) {
            $dbman->drop_table($table);
        }

        // Seed an unrelated config key that the regex must reject.
        $DB->insert_record('config_plugins', (object) [
            'plugin' => 'quizaccess_airpay_proctoring',
            'name'   => 'quiz_foobar_enabled',  // not numeric
            'value'  => '1',
        ]);
        // And a matching one to confirm only the right pattern fires.
        $DB->insert_record('config_plugins', (object) [
            'plugin' => 'quizaccess_airpay_proctoring',
            'name'   => 'quiz_77_enabled',
            'value'  => '1',
        ]);

        $result = xmldb_quizaccess_airpay_proctoring_upgrade(2026051200);

        $this->assertTrue($result);
        // 77 was migrated.
        $this->assertTrue($DB->record_exists('quizaccess_airpay_proctor', ['quizid' => 77]));
        // foobar row is still in config_plugins, unmigrated.
        $this->assertTrue($DB->record_exists('config_plugins', [
            'plugin' => 'quizaccess_airpay_proctoring',
            'name'   => 'quiz_foobar_enabled',
        ]));
    }
}
