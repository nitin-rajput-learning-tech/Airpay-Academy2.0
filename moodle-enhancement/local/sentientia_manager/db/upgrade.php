<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
defined('MOODLE_INTERNAL') || die();

function xmldb_local_sentientia_manager_upgrade(int $oldversion): bool {
    global $DB;
    $dbman = $DB->get_manager();

    // 2026050800 — first schema; closes Phase-B of the 2026-05-08 stretch.
    if ($oldversion < 2026050800) {
        // Requests table.
        $tbl = new xmldb_table('local_sentientia_mgr_requests');
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
        $tbl = new xmldb_table('local_sentientia_mgr_allocations');
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

        upgrade_plugin_savepoint(true, 2026050800, 'local', 'sentientia_manager');
    }

    // 2026051500 — W1-10: multi-type allocation. Add item_type + itemid
    // columns to support allocating courses, classrooms, programs, and
    // learning paths from a single table. The legacy `courseid` column is
    // kept for backward compat and we backfill (item_type, itemid) from
    // (course, courseid) for every existing row.
    if ($oldversion < 2026051500) {
        $table = new xmldb_table('local_sentientia_mgr_allocations');

        $field = new xmldb_field('item_type', XMLDB_TYPE_CHAR, '20',
            null, XMLDB_NOTNULL, null, 'course', 'userid');
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        $field = new xmldb_field('itemid', XMLDB_TYPE_INTEGER, '10',
            null, XMLDB_NOTNULL, null, '0', 'item_type');
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        // Backfill existing rows: every legacy allocation is a course allocation.
        $DB->execute(
            "UPDATE {local_sentientia_mgr_allocations}
                SET itemid = courseid, item_type = 'course'
              WHERE item_type = 'course' AND itemid = 0 AND courseid > 0"
        );

        // Unique index on (userid, item_type, itemid) so we cannot
        // double-allocate the same item-type to the same user.
        $index = new xmldb_index('idx_user_item', XMLDB_INDEX_UNIQUE,
            ['userid', 'item_type', 'itemid']);
        if (!$dbman->index_exists($table, $index)) {
            $dbman->add_index($table, $index);
        }

        $index = new xmldb_index('idx_item_type', XMLDB_INDEX_NOTUNIQUE, ['item_type']);
        if (!$dbman->index_exists($table, $index)) {
            $dbman->add_index($table, $index);
        }

        upgrade_plugin_savepoint(true, 2026051500, 'local', 'sentientia_manager');
    }

    return true;
}
