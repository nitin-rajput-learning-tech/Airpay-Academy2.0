<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

defined('MOODLE_INTERNAL') || die();

/**
 * local_sentientia_core upgrade steps.
 *
 * @param int $oldversion
 * @return bool
 */
function xmldb_local_sentientia_core_upgrade(int $oldversion): bool {
    global $DB;
    $dbman = $DB->get_manager();

    // ADR-021 Wave 4 — tenant registry tables (additive, default-legacy).
    if ($oldversion < 2026060100) {

        // 1. local_sentientia_customer — define BEFORE the tenant table so the
        //    fk_customer reference resolves.
        $customer = new xmldb_table('local_sentientia_customer');
        $customer->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE);
        $customer->add_field('name', XMLDB_TYPE_CHAR, '255', null, XMLDB_NOTNULL);
        $customer->add_field('shortname', XMLDB_TYPE_CHAR, '100', null, XMLDB_NOTNULL);
        $customer->add_field('status', XMLDB_TYPE_CHAR, '20', null, XMLDB_NOTNULL, null, 'active');
        $customer->add_field('timecreated', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        $customer->add_field('timemodified', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        $customer->add_key('primary', XMLDB_KEY_PRIMARY, ['id']);
        $customer->add_index('uq_shortname', XMLDB_INDEX_UNIQUE, ['shortname']);
        $customer->add_index('idx_status', XMLDB_INDEX_NOTUNIQUE, ['status']);
        if (!$dbman->table_exists($customer)) {
            $dbman->create_table($customer);
        }

        // 2. local_sentientia_tenant.
        $tenant = new xmldb_table('local_sentientia_tenant');
        $tenant->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE);
        $tenant->add_field('rootid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        $tenant->add_field('customerid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        $tenant->add_field('name', XMLDB_TYPE_CHAR, '255', null, XMLDB_NOTNULL);
        $tenant->add_field('idnumber', XMLDB_TYPE_CHAR, '255', null, null);
        $tenant->add_field('status', XMLDB_TYPE_CHAR, '20', null, XMLDB_NOTNULL, null, 'active');
        $tenant->add_field('timecreated', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        $tenant->add_field('timemodified', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        $tenant->add_key('primary', XMLDB_KEY_PRIMARY, ['id']);
        $tenant->add_key('fk_customer', XMLDB_KEY_FOREIGN, ['customerid'],
            'local_sentientia_customer', ['id']);
        $tenant->add_index('uq_rootid', XMLDB_INDEX_UNIQUE, ['rootid']);
        $tenant->add_index('idx_customerid', XMLDB_INDEX_NOTUNIQUE, ['customerid']);
        $tenant->add_index('idx_status', XMLDB_INDEX_NOTUNIQUE, ['status']);
        if (!$dbman->table_exists($tenant)) {
            $dbman->create_table($tenant);
        }

        upgrade_plugin_savepoint(true, 2026060100, 'local', 'sentientia_core');
    }

    return true;
}
