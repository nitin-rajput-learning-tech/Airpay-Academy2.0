<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

defined('MOODLE_INTERNAL') || die();

/**
 * Phase 9 (N7 follow-up) upgrade: migrate get_config/set_config based
 * per-quiz settings into a proper relational table.
 *
 * Phase B.12 hotfix (2026-05-24): the `< 2026051300` savepoint
 * originally assumed `quizaccess_sentientia_proctor` would already exist
 * by the time upgrade.php ran. That's true for fresh installs (where
 * install.xml creates the table) but FALSE for upgrades from any
 * pre-2026051300 version on production, because production never had
 * the table — it had the old key-value config rows in
 * mdl_config_plugins instead. The defensive `table_exists()` +
 * `create_table()` block at the top of the savepoint is what makes
 * this upgrade safe on production.
 *
 * @param int $oldversion
 * @return bool
 */
function xmldb_quizaccess_sentientia_proctoring_upgrade(int $oldversion): bool {
    global $DB;
    $dbman = $DB->get_manager();

    if ($oldversion < 2026051300) {
        // Phase B.12 hotfix — ensure the table exists before the
        // migration tries to write to it. On a fresh install this is
        // a no-op (install.xml already created it). On a production
        // upgrade from v2026051120 this is the line that prevents a
        // "table does not exist" fatal halfway through the upgrade.
        $table = new xmldb_table('quizaccess_sentientia_proctor');
        $table->add_field('id', XMLDB_TYPE_INTEGER, '10', null,
            XMLDB_NOTNULL, XMLDB_SEQUENCE, null);
        $table->add_field('quizid', XMLDB_TYPE_INTEGER, '10', null,
            XMLDB_NOTNULL, null, null);
        $table->add_field('enabled', XMLDB_TYPE_INTEGER, '1', null,
            XMLDB_NOTNULL, null, '0');
        $table->add_field('min_match_score', XMLDB_TYPE_NUMBER, '3,2',
            null, null, null, '0.85');
        $table->add_field('retention_days_override', XMLDB_TYPE_INTEGER,
            '6', null, null, null, null);
        $table->add_field('timecreated', XMLDB_TYPE_INTEGER, '10', null,
            XMLDB_NOTNULL, null, '0');
        $table->add_field('timemodified', XMLDB_TYPE_INTEGER, '10', null,
            XMLDB_NOTNULL, null, '0');
        $table->add_key('primary', XMLDB_KEY_PRIMARY, ['id']);
        // FK on quizid auto-creates an index, so we only need to declare
        // the unique constraint via the key (XMLDB_KEY_UNIQUE) instead of
        // a separate add_index — the earlier `add_index('uniq_quizid', ...)`
        // collided with the FK's implicit index and aborted upgrade with
        // "Key fk_quiz collides with indexuniq_quizid". Fixed 2026-05-24.
        $table->add_key('uniq_quizid', XMLDB_KEY_UNIQUE, ['quizid']);
        $table->add_key('fk_quiz', XMLDB_KEY_FOREIGN, ['quizid'],
            'quiz', ['id']);

        if (!$dbman->table_exists($table)) {
            $dbman->create_table($table);
        }

        // Migrate every existing `quizaccess_sentientia_proctoring`
        // `quiz_<id>_enabled` config entry into the new table.
        $rows = $DB->get_records_select('config_plugins',
            "plugin = :p AND " . $DB->sql_like('name', ':n'),
            ['p' => 'quizaccess_sentientia_proctoring', 'n' => 'quiz_%_enabled']);
        $now = time();
        foreach ($rows as $row) {
            // Parse: 'quiz_42_enabled' → 42
            if (!preg_match('/^quiz_(\d+)_enabled$/', $row->name, $m)) {
                continue;
            }
            $quizid = (int) $m[1];
            $enabled = (int) (string) $row->value;
            if ($DB->record_exists('quizaccess_sentientia_proctor', ['quizid' => $quizid])) {
                continue;  // already migrated
            }
            $DB->insert_record('quizaccess_sentientia_proctor', (object) [
                'quizid'       => $quizid,
                'enabled'      => $enabled,
                'timecreated'  => $now,
                'timemodified' => $now,
            ]);
            // Drop the now-redundant config entry.
            $DB->delete_records('config_plugins', ['id' => $row->id]);
        }
        upgrade_plugin_savepoint(true, 2026051300, 'quizaccess', 'sentientia_proctoring');
    }

    if ($oldversion < 2026052401) {
        // Phase B.12 hotfix marker savepoint — no functional change.
        // Records that this DB has the table-exists-guarded upgrade
        // shipped on 2026-05-24. Useful for support diagnostics.
        upgrade_plugin_savepoint(true, 2026052401, 'quizaccess', 'sentientia_proctoring');
    }

    return true;
}
