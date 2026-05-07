<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
defined('MOODLE_INTERNAL') || die();

function xmldb_local_airpay_integrations_upgrade(int $oldversion): bool {
    global $DB;
    $dbman = $DB->get_manager();

    // 2026050700 — first install.xml shipment. Closes the runtime bug
    // where webhook.php inserts into local_airpay_integration_log but the
    // table never existed (INTEGRATIONS-AUDIT.md §4.1).
    if ($oldversion < 2026050700) {
        $tbl = new xmldb_table('local_airpay_integration_log');
        if (!$dbman->table_exists($tbl)) {
            $tbl->add_field('id',          XMLDB_TYPE_INTEGER, '10',  null, XMLDB_NOTNULL, XMLDB_SEQUENCE);
            $tbl->add_field('source',      XMLDB_TYPE_CHAR,    '50',  null, XMLDB_NOTNULL);
            $tbl->add_field('event_type',  XMLDB_TYPE_CHAR,    '100');
            $tbl->add_field('payload',     XMLDB_TYPE_TEXT);
            $tbl->add_field('status',      XMLDB_TYPE_CHAR,    '20',  null, XMLDB_NOTNULL, null, 'received');
            $tbl->add_field('errormsg',    XMLDB_TYPE_TEXT);
            $tbl->add_field('timecreated', XMLDB_TYPE_INTEGER, '10',  null, XMLDB_NOTNULL, null, '0');
            $tbl->add_key('primary', XMLDB_KEY_PRIMARY, ['id']);
            $tbl->add_index('idx_source_status', XMLDB_INDEX_NOTUNIQUE, ['source', 'status']);
            $tbl->add_index('idx_timecreated',   XMLDB_INDEX_NOTUNIQUE, ['timecreated']);
            $dbman->create_table($tbl);
        }
        upgrade_plugin_savepoint(true, 2026050700, 'local', 'airpay_integrations');
    }

    return true;
}
