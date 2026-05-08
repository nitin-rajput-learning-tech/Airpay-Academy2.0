<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
defined('MOODLE_INTERNAL') || die();

function xmldb_local_airpay_manager_upgrade(int $oldversion): bool {
    global $DB;
    $dbman = $DB->get_manager();

    // 2026050800 — first schema; closes Phase-B of the 2026-05-08 stretch.
    if ($oldversion < 2026050800) {
        // Requests table.
        $tbl = new xmldb_table('local_airpay_mgr_requests');
        if (!$dbman->table_exists($tbl)) {
            $tbl->add_field('id',              XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE);
            $tbl->add_field('userid',          XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
            $tbl->add_field('courseid',        XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
            $tbl->add_field('managerid',       XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
            $tbl->add_field('status',          XMLDB_TYPE_CHAR,    '20', null, XMLDB_NOTNULL, null, 'pending');
            $tbl->add_field('reason',          XMLDB_TYPE_TEXT);
            $tbl->add_field('decision_reason', XMLDB_TYPE_TEXT);
            $tbl->add_field('decided_by',      XMLDB_TYPE_INTEGER, '10');
            $tbl->add_field('decided_at',      XMLDB_TYPE_INTEGER, '10');
            $tbl->add_field('timecreated',     XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
            $tbl->add_field('timemodified',    XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
            $tbl->add_key('primary',   XMLDB_KEY_PRIMARY, ['id']);
            $tbl->add_key('fk_user',   XMLDB_KEY_FOREIGN, ['userid'],   'user',   ['id']);
            $tbl->add_key('fk_course', XMLDB_KEY_FOREIGN, ['courseid'], 'course', ['id']);
            $tbl->add_index('idx_managerid_status', XMLDB_INDEX_NOTUNIQUE, ['managerid', 'status']);
            $tbl->add_index('idx_user_course',      XMLDB_INDEX_NOTUNIQUE, ['userid', 'courseid']);
            $tbl->add_index('idx_status',           XMLDB_INDEX_NOTUNIQUE, ['status']);
            $dbman->create_table($tbl);
        }

        // Allocations table.
        $tbl = new xmldb_table('local_airpay_mgr_allocations');
        if (!$dbman->table_exists($tbl)) {
            $tbl->add_field('id',           XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE);
            $tbl->add_field('managerid',    XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
            $tbl->add_field('userid',       XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
            $tbl->add_field('courseid',     XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
            $tbl->add_field('due_date',     XMLDB_TYPE_INTEGER, '10');
            $tbl->add_field('status',       XMLDB_TYPE_CHAR,    '20', null, XMLDB_NOTNULL, null, 'assigned');
            $tbl->add_field('note',         XMLDB_TYPE_TEXT);
            $tbl->add_field('timecreated',  XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
            $tbl->add_field('timemodified', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
            $tbl->add_key('primary',   XMLDB_KEY_PRIMARY, ['id']);
            $tbl->add_key('fk_user',   XMLDB_KEY_FOREIGN, ['userid'],   'user',   ['id']);
            $tbl->add_key('fk_course', XMLDB_KEY_FOREIGN, ['courseid'], 'course', ['id']);
            $tbl->add_index('idx_managerid',   XMLDB_INDEX_NOTUNIQUE, ['managerid']);
            $tbl->add_index('idx_user_course', XMLDB_INDEX_UNIQUE,    ['userid', 'courseid']);
            $tbl->add_index('idx_status',      XMLDB_INDEX_NOTUNIQUE, ['status']);
            $dbman->create_table($tbl);
        }

        upgrade_plugin_savepoint(true, 2026050800, 'local', 'airpay_manager');
    }

    return true;
}
