<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
defined('MOODLE_INTERNAL') || die();

function xmldb_local_sentientia_roles_upgrade(int $oldversion): bool {
    global $DB;
    $dbman = $DB->get_manager();

    if ($oldversion < 2026050700) {
        // Initial table — audit log.
        $table = new xmldb_table('local_sentientia_roles_auditlog');
        if (!$dbman->table_exists($table)) {
            $table->add_field('id',            XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE);
            $table->add_field('roleid',        XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
            $table->add_field('roleshortname', XMLDB_TYPE_CHAR,    '100', null, XMLDB_NOTNULL);
            $table->add_field('action',        XMLDB_TYPE_CHAR,    '40', null, XMLDB_NOTNULL);
            $table->add_field('capability',    XMLDB_TYPE_CHAR,    '255');
            $table->add_field('oldpermission', XMLDB_TYPE_INTEGER, '6');
            $table->add_field('newpermission', XMLDB_TYPE_INTEGER, '6');
            $table->add_field('contextid',     XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
            $table->add_field('targetuserid',  XMLDB_TYPE_INTEGER, '10');
            $table->add_field('changedby',     XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
            $table->add_field('reason',        XMLDB_TYPE_TEXT);
            $table->add_field('open_path',     XMLDB_TYPE_CHAR,    '255');
            $table->add_field('timecreated',   XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
            $table->add_key('primary', XMLDB_KEY_PRIMARY, ['id']);
            $table->add_key('fk_changedby', XMLDB_KEY_FOREIGN, ['changedby'], 'user', ['id']);
            $table->add_index('idx_roleid',      XMLDB_INDEX_NOTUNIQUE, ['roleid']);
            $table->add_index('idx_action',      XMLDB_INDEX_NOTUNIQUE, ['action']);
            $table->add_index('idx_timecreated', XMLDB_INDEX_NOTUNIQUE, ['timecreated']);
            $table->add_index('idx_capability',  XMLDB_INDEX_NOTUNIQUE, ['capability']);
            $dbman->create_table($table);
        }

        upgrade_plugin_savepoint(true, 2026050700, 'local', 'sentientia_roles');
    }

    return true;
}
