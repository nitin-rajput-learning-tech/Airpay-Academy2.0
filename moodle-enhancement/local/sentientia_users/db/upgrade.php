<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

defined('MOODLE_INTERNAL') || die();

/**
 * Upgrade script for local_sentientia_users.
 *
 * W1-6 (2026-05-16) — first schema for this plugin. Earlier versions used only
 * core mdl_user (no plugin-owned tables). The HRMS bulk import flow needs two
 * audit-log tables: one per upload run, one per failed row.
 *
 * 2026100101 (2026-10-01) — ADR-032 BizLMS import, users feature: transcript, login days, position and domain
 * tables (see the step for why each exists).
 *
 * @package   local_sentientia_users
 * @copyright 2026 Airpay Payment Services
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
function xmldb_local_sentientia_users_upgrade(int $oldversion): bool {
    global $DB;
    $dbman = $DB->get_manager();

    // 2026051600 — W1-6: HRMS sync runs + error log tables.
    if ($oldversion < 2026051600) {

        // ── local_sentientia_users_sync_runs ─────────────────────────────────
        $table = new xmldb_table('local_sentientia_users_sync_runs');
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

        // ── local_sentientia_users_sync_errors ───────────────────────────────
        $table = new xmldb_table('local_sentientia_users_sync_errors');
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
                            'local_sentientia_users_sync_runs', ['id']);
            $table->add_index('idx_severity', XMLDB_INDEX_NOTUNIQUE, ['severity']);
            $table->add_index('idx_email',    XMLDB_INDEX_NOTUNIQUE, ['email']);
            $dbman->create_table($table);
        }

        upgrade_plugin_savepoint(true, 2026051600, 'local', 'sentientia_users');
    }

    // 2026100101 — ADR-032 (BizLMS import), users feature: the four tables the imported history lands in.
    //
    //  - local_sentientia_users_transcript: earlier training records (BizLMS local_transcript_history).
    //  - local_sentientia_users_logindays: one row per user per login day (BizLMS local_uniquelogins).
    //  - local_sentientia_users_position and _domain: lookups imported with their ids kept, because
    //    user.open_positionid and user.open_domainid hold those ids (gap G4).
    //
    // Every table is created whether or not a BizLMS database is present: a fresh install has no legacy rows,
    // and the readers must not have to ask whether the table exists. Nothing here reads or writes a BizLMS table.
    // Each table is guarded, so a re-run (or a database where a table was made by hand) changes nothing.
    // No legacykey column: the ADR-032 map (local_sentientia_legacymap) is the idempotence key.
    if ($oldversion < 2026100101) {

        // ── local_sentientia_users_transcript ───────────────────────────────
        $table = new xmldb_table('local_sentientia_users_transcript');
        if (!$dbman->table_exists($table)) {
            $table->add_field('id',                  XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE);
            $table->add_field('userid',              XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
            $table->add_field('employee_id',         XMLDB_TYPE_CHAR,    '255', null, XMLDB_NOTNULL, null, '');
            $table->add_field('learner_name',        XMLDB_TYPE_CHAR,    '255', null, XMLDB_NOTNULL, null, '');
            $table->add_field('title',               XMLDB_TYPE_CHAR,    '255', null, XMLDB_NOTNULL, null, '');
            $table->add_field('training_type',       XMLDB_TYPE_CHAR,    '255', null, XMLDB_NOTNULL, null, '');
            $table->add_field('objectref',           XMLDB_TYPE_CHAR,    '255', null, XMLDB_NOTNULL, null, '');
            $table->add_field('location',            XMLDB_TYPE_CHAR,    '255', null, XMLDB_NOTNULL, null, '');
            $table->add_field('courseid',            XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
            $table->add_field('status',              XMLDB_TYPE_CHAR,    '20',  null, XMLDB_NOTNULL, null, 'unknown');
            $table->add_field('status_raw',          XMLDB_TYPE_CHAR,    '255', null, XMLDB_NOTNULL, null, '');
            $table->add_field('completion_date_raw', XMLDB_TYPE_CHAR,    '255', null, XMLDB_NOTNULL, null, '');
            $table->add_field('score_raw',           XMLDB_TYPE_CHAR,    '255', null, XMLDB_NOTNULL, null, '');
            $table->add_field('hours_raw',           XMLDB_TYPE_CHAR,    '255', null, XMLDB_NOTNULL, null, '');
            $table->add_field('timecompleted',       XMLDB_TYPE_INTEGER, '10', null, null);
            $table->add_field('score',               XMLDB_TYPE_NUMBER,  '10, 2', null, null);
            $table->add_field('hours',               XMLDB_TYPE_NUMBER,  '10, 2', null, null);
            $table->add_field('costcenterid',        XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
            $table->add_field('open_path',           XMLDB_TYPE_CHAR,    '255', null, null);
            $table->add_field('source',              XMLDB_TYPE_CHAR,    '20',  null, XMLDB_NOTNULL, null, 'bizlms');
            $table->add_field('usercreated',         XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
            $table->add_field('usermodified',        XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
            $table->add_field('timecreated',         XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
            $table->add_field('timemodified',        XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
            $table->add_key('primary', XMLDB_KEY_PRIMARY, ['id']);
            $table->add_index('idx_user_completed', XMLDB_INDEX_NOTUNIQUE, ['userid', 'timecompleted']);
            $table->add_index('idx_costcenter',     XMLDB_INDEX_NOTUNIQUE, ['costcenterid']);
            $table->add_index('idx_course',         XMLDB_INDEX_NOTUNIQUE, ['courseid']);
            $dbman->create_table($table);
        }

        // ── local_sentientia_users_logindays ────────────────────────────────
        $table = new xmldb_table('local_sentientia_users_logindays');
        if (!$dbman->table_exists($table)) {
            $table->add_field('id',           XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE);
            $table->add_field('userid',       XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
            $table->add_field('logindate',    XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
            $table->add_field('source',       XMLDB_TYPE_CHAR,    '20', null, XMLDB_NOTNULL, null, 'web');
            $table->add_field('timecreated',  XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
            $table->add_field('timemodified', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
            $table->add_key('primary', XMLDB_KEY_PRIMARY, ['id']);
            $table->add_index('uk_user_day',   XMLDB_INDEX_UNIQUE,    ['userid', 'logindate']);
            $table->add_index('idx_logindate', XMLDB_INDEX_NOTUNIQUE, ['logindate']);
            $dbman->create_table($table);
        }

        // ── local_sentientia_users_position ─────────────────────────────────
        $table = new xmldb_table('local_sentientia_users_position');
        if (!$dbman->table_exists($table)) {
            $table->add_field('id',           XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE);
            $table->add_field('name',         XMLDB_TYPE_CHAR,    '255', null, XMLDB_NOTNULL, null, '');
            $table->add_field('code',         XMLDB_TYPE_CHAR,    '255', null, XMLDB_NOTNULL, null, '');
            $table->add_field('domainid',     XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
            $table->add_field('costcenterid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
            $table->add_field('sortorder',    XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
            $table->add_field('timecreated',  XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
            $table->add_field('timemodified', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
            $table->add_key('primary', XMLDB_KEY_PRIMARY, ['id']);
            $table->add_index('idx_domain',     XMLDB_INDEX_NOTUNIQUE, ['domainid']);
            $table->add_index('idx_costcenter', XMLDB_INDEX_NOTUNIQUE, ['costcenterid']);
            $dbman->create_table($table);
        }

        // ── local_sentientia_users_domain ───────────────────────────────────
        $table = new xmldb_table('local_sentientia_users_domain');
        if (!$dbman->table_exists($table)) {
            $table->add_field('id',           XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE);
            $table->add_field('name',         XMLDB_TYPE_CHAR,    '255', null, XMLDB_NOTNULL, null, '');
            $table->add_field('code',         XMLDB_TYPE_CHAR,    '255', null, XMLDB_NOTNULL, null, '');
            $table->add_field('costcenterid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
            $table->add_field('timecreated',  XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
            $table->add_field('timemodified', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
            $table->add_key('primary', XMLDB_KEY_PRIMARY, ['id']);
            $table->add_index('idx_costcenter', XMLDB_INDEX_NOTUNIQUE, ['costcenterid']);
            $dbman->create_table($table);
        }

        upgrade_plugin_savepoint(true, 2026100101, 'local', 'sentientia_users');
    }

    return true;
}
