<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

/**
 * Upgrade steps for local_sentientia_xapi.
 *
 * @package    local_sentientia_xapi
 * @copyright  2026 Airpay Payment Services
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

function xmldb_local_sentientia_xapi_upgrade(int $oldversion): bool {
    global $DB;
    $dbman = $DB->get_manager();

    // Version 2026061600 — initial schema (created via install.xml).

    if ($oldversion < 2026090300) {
        // 2026-09-03 (UAT Stage A finding): 'stored' is a RESERVED word in MySQL 8
        // (generated columns: ... AS (expr) STORED). MariaDB accepted it unquoted, so
        // local installs worked; the fresh install on UAT MySQL 8.4 failed with a
        // syntax error. Renamed to 'timestored' (install.xml + all code paths).
        $table = new xmldb_table('local_sentientia_xapi_stmts');
        $old = new xmldb_field('stored', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0', 'timestamp');
        if ($dbman->field_exists($table, $old)) {
            $oldindex = new xmldb_index('idx_stored', XMLDB_INDEX_NOTUNIQUE, ['stored']);
            if ($dbman->index_exists($table, $oldindex)) {
                $dbman->drop_index($table, $oldindex);
            }
            $dbman->rename_field($table, $old, 'timestored');
        }
        $newindex = new xmldb_index('idx_timestored', XMLDB_INDEX_NOTUNIQUE, ['timestored']);
        if (!$dbman->index_exists($table, $newindex)) {
            $dbman->add_index($table, $newindex);
        }
        upgrade_plugin_savepoint(true, 2026090300, 'local', 'sentientia_xapi');
    }

    if ($oldversion < 2026090302) {
        // H3 fix (UAT-SECURITY-POSTURE-2026-09-03): the /lrs/statements
        // endpoint had no rate limiting, unlike the SCIM endpoint in the
        // same plugin family. New table backs a per-client fixed-window
        // limiter (see classes/lrs/rate_limiter.php).
        $table = new xmldb_table('local_sentientia_xapi_lrs_rate');
        if (!$dbman->table_exists($table)) {
            $table->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE);
            $table->add_field('clientid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
            $table->add_field('windowstart', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
            $table->add_field('hits', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
            $table->add_field('timemodified', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
            $table->add_key('primary', XMLDB_KEY_PRIMARY, ['id']);
            $table->add_index('uniq_client_window', XMLDB_INDEX_UNIQUE, ['clientid', 'windowstart']);
            $dbman->create_table($table);
        }
        upgrade_plugin_savepoint(true, 2026090302, 'local', 'sentientia_xapi');
    }

    return true;
}
