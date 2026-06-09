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

    // ADR-020 Wave 3.2a — Sentientia org-hierarchy model (additive, default-legacy,
    // dormant until 3.2b dual-write + 3.3 backfill seed it).
    if ($oldversion < 2026060101) {

        // 1. local_sentientia_org_unit — define BEFORE the member table so the
        //    fk_unit reference resolves.
        $unit = new xmldb_table('local_sentientia_org_unit');
        $unit->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE);
        $unit->add_field('parentid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        $unit->add_field('tenantrootid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        $unit->add_field('name', XMLDB_TYPE_CHAR, '255', null, XMLDB_NOTNULL);
        $unit->add_field('idnumber', XMLDB_TYPE_CHAR, '255', null, null);
        $unit->add_field('path', XMLDB_TYPE_CHAR, '255', null, null);
        $unit->add_field('status', XMLDB_TYPE_CHAR, '20', null, XMLDB_NOTNULL, null, 'active');
        $unit->add_field('timecreated', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        $unit->add_field('timemodified', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        $unit->add_key('primary', XMLDB_KEY_PRIMARY, ['id']);
        $unit->add_index('idx_parentid', XMLDB_INDEX_NOTUNIQUE, ['parentid']);
        $unit->add_index('idx_tenantrootid', XMLDB_INDEX_NOTUNIQUE, ['tenantrootid']);
        $unit->add_index('idx_idnumber', XMLDB_INDEX_NOTUNIQUE, ['idnumber']);
        $unit->add_index('idx_status', XMLDB_INDEX_NOTUNIQUE, ['status']);
        if (!$dbman->table_exists($unit)) {
            $dbman->create_table($unit);
        }

        // 2. local_sentientia_org_member.
        $member = new xmldb_table('local_sentientia_org_member');
        $member->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE);
        $member->add_field('userid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        $member->add_field('unitid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        $member->add_field('role', XMLDB_TYPE_CHAR, '20', null, XMLDB_NOTNULL, null, 'member');
        $member->add_field('timecreated', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        $member->add_field('timemodified', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        $member->add_key('primary', XMLDB_KEY_PRIMARY, ['id']);
        $member->add_key('fk_unit', XMLDB_KEY_FOREIGN, ['unitid'],
            'local_sentientia_org_unit', ['id']);
        $member->add_index('uq_user_unit', XMLDB_INDEX_UNIQUE, ['userid', 'unitid']);
        $member->add_index('idx_userid', XMLDB_INDEX_NOTUNIQUE, ['userid']);
        $member->add_index('idx_unitid', XMLDB_INDEX_NOTUNIQUE, ['unitid']);
        $member->add_index('idx_role', XMLDB_INDEX_NOTUNIQUE, ['role']);
        if (!$dbman->table_exists($member)) {
            $dbman->create_table($member);
        }

        upgrade_plugin_savepoint(true, 2026060101, 'local', 'sentientia_core');
    }

    // ADR-020 2026-06-01 modelling decision — the manager relationship is the
    // direct edge (org_member.managerid, mirroring open_supervisorid), not the
    // unit role. Additive column on the still-empty table.
    if ($oldversion < 2026060102) {
        $member = new xmldb_table('local_sentientia_org_member');
        $field = new xmldb_field('managerid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0', 'role');
        if (!$dbman->field_exists($member, $field)) {
            $dbman->add_field($member, $field);
        }
        $index = new xmldb_index('idx_managerid', XMLDB_INDEX_NOTUNIQUE, ['managerid']);
        if (!$dbman->index_exists($member, $index)) {
            $dbman->add_index($member, $index);
        }
        upgrade_plugin_savepoint(true, 2026060102, 'local', 'sentientia_core');
    }

    // ADR-024 Wave 2 — own the BizLMS-compatible open_* substrate first-party.
    // Idempotent + additive: adds the open_* columns to {user}/{course} if
    // missing, so a from-scratch Sentientia install has a working multi-tenant
    // substrate WITHOUT the external eAbyas plugins. No-op where they already
    // exist (e.g. an Airpay production DB carried from the eAbyas distribution).
    // See classes/substrate.php + docs/core-mods/2026-06-04-open-substrate-ownership.md + ADR-024.
    if ($oldversion < 2026060400) {
        \local_sentientia_core\substrate::ensure_all(false);
        upgrade_plugin_savepoint(true, 2026060400, 'local', 'sentientia_core');
    }

    return true;
}
