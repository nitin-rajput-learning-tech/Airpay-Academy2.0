<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

defined('MOODLE_INTERNAL') || die();

/**
 * Upgrade script for local_airpay_users.
 *
 * W1-6 (2026-05-16) — first schema for this plugin. Earlier versions used only
 * core mdl_user (no plugin-owned tables). The HRMS bulk import flow needs two
 * audit-log tables: one per upload run, one per failed row.
 *
 * @package   local_airpay_users
 * @copyright 2026 Airpay Payment Services
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
function xmldb_local_airpay_users_upgrade(int $oldversion): bool {
    global $DB;
    $dbman = $DB->get_manager();

    // 2026051600 — W1-6: HRMS sync runs + error log tables.
    if ($oldversion < 2026051600) {

        // ── local_airpay_users_sync_runs ─────────────────────────────────
        $table = new xmldb_table('local_airpay_users_sync_runs');
        if (!$dbman->table_exists($table)) {
            $table->add_field('id',             XMLDB_TYPE_INTEGER, '10', null,
                              XMLDB_NOTNULL, XMLDB_SEQUENCE);
            $table->add_field('filename',       XMLDB_TYPE_CHAR,    '255', null,
                              XMLDB_NOTNULL, null, '');
            $table->add_field('source',         XMLDB_TYPE_CHAR,    '20',  null,
                              XMLDB_NOTNULL, null, 'web');
            $table->add_field('costcenterid',   XMLDB_TYPE_INTEGER, '10',  null,
                              XMLDB_NOTNULL, null, '0');
            $table->add_field('totalrows',      XMLDB_TYPE_INTEGER, '10',  null,
                              XMLDB_NOTNULL, null, '0');
            $table->add_field('insertedcount',  XMLDB_TYPE_INTEGER, '10',  null,
                              XMLDB_NOTNULL, null, '0');
            $table->add_field('updatedcount',   XMLDB_TYPE_INTEGER, '10',  null,
                              XMLDB_NOTNULL, null, '0');
            $table->add_field('skippedcount',   XMLDB_TYPE_INTEGER, '10',  null,
                              XMLDB_NOTNULL, null, '0');
            $table->add_field('errorcount',     XMLDB_TYPE_INTEGER, '10',  null,
                              XMLDB_NOTNULL, null, '0');
            $table->add_field('warningcount',   XMLDB_TYPE_INTEGER, '10',  null,
                              XMLDB_NOTNULL, null, '0');
            $table->add_field('suspendedcount', XMLDB_TYPE_INTEGER, '10',  null,
                              XMLDB_NOTNULL, null, '0');
            $table->add_field('usercreated',    XMLDB_TYPE_INTEGER, '10',  null,
                              XMLDB_NOTNULL, null, '0');
            $table->add_field('status',         XMLDB_TYPE_CHAR,    '20',  null,
                              XMLDB_NOTNULL, null, 'completed');
            $table->add_field('error_summary',  XMLDB_TYPE_TEXT,    null,  null,
                              null);
            $table->add_field('timecreated',    XMLDB_TYPE_INTEGER, '10',  null,
                              XMLDB_NOTNULL, null, '0');
            $table->add_field('timemodified',   XMLDB_TYPE_INTEGER, '10',  null,
                              XMLDB_NOTNULL, null, '0');
            $table->add_key('primary', XMLDB_KEY_PRIMARY, ['id']);
            $table->add_index('idx_costcenter', XMLDB_INDEX_NOTUNIQUE, ['costcenterid']);
            $table->add_index('idx_time',       XMLDB_INDEX_NOTUNIQUE, ['timecreated']);
            $table->add_index('idx_status',     XMLDB_INDEX_NOTUNIQUE, ['status']);
            $dbman->create_table($table);
        }

        // ── local_airpay_users_sync_errors ───────────────────────────────
        $table = new xmldb_table('local_airpay_users_sync_errors');
        if (!$dbman->table_exists($table)) {
            $table->add_field('id',               XMLDB_TYPE_INTEGER, '10', null,
                              XMLDB_NOTNULL, XMLDB_SEQUENCE);
            $table->add_field('runid',            XMLDB_TYPE_INTEGER, '10', null,
                              XMLDB_NOTNULL);
            $table->add_field('csv_line_number',  XMLDB_TYPE_INTEGER, '10', null,
                              XMLDB_NOTNULL, null, '0');
            $table->add_field('email',            XMLDB_TYPE_CHAR,    '254', null,
                              XMLDB_NOTNULL, null, '-');
            $table->add_field('employee_code',    XMLDB_TYPE_CHAR,    '100', null,
                              XMLDB_NOTNULL, null, '-');
            $table->add_field('username',         XMLDB_TYPE_CHAR,    '100', null,
                              XMLDB_NOTNULL, null, '-');
            $table->add_field('firstname',        XMLDB_TYPE_CHAR,    '100', null,
                              XMLDB_NOTNULL, null, '');
            $table->add_field('lastname',         XMLDB_TYPE_CHAR,    '100', null,
                              XMLDB_NOTNULL, null, '');
            $table->add_field('error_message',    XMLDB_TYPE_TEXT,    null,  null,
                              XMLDB_NOTNULL);
            $table->add_field('mandatory_fields', XMLDB_TYPE_TEXT,    null,  null, null);
            $table->add_field('severity',         XMLDB_TYPE_CHAR,    '20',  null,
                              XMLDB_NOTNULL, null, 'error');
            $table->add_field('modified_by',      XMLDB_TYPE_INTEGER, '10', null,
                              XMLDB_NOTNULL, null, '0');
            $table->add_field('timecreated',      XMLDB_TYPE_INTEGER, '10', null,
                              XMLDB_NOTNULL, null, '0');
            $table->add_key('primary', XMLDB_KEY_PRIMARY, ['id']);
            // fk_runid foreign key implicitly indexes the `runid` column;
            // a duplicate idx_runid would collide. (See Moodle XMLDB rules.)
            $table->add_key('fk_runid', XMLDB_KEY_FOREIGN, ['runid'],
                            'local_airpay_users_sync_runs', ['id']);
            $table->add_index('idx_severity', XMLDB_INDEX_NOTUNIQUE, ['severity']);
            $table->add_index('idx_email',    XMLDB_INDEX_NOTUNIQUE, ['email']);
            $dbman->create_table($table);
        }

        upgrade_plugin_savepoint(true, 2026051600, 'local', 'airpay_users');
    }

    return true;
}
