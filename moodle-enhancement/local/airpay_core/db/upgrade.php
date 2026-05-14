<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

/**
 * Upgrade hooks for local_airpay_core.
 *
 * @package local_airpay_core
 */

defined('MOODLE_INTERNAL') || die();

function xmldb_local_airpay_core_upgrade(int $oldversion): bool {
    global $DB;
    $dbman = $DB->get_manager();

    // ── Phase A0 (2026-05-14) — Feature flags + audit infrastructure
    // First DB tables this plugin has ever owned. They power the
    // Switchboard admin UI and the runtime feature_flags::is_enabled()
    // resolution path.
    if ($oldversion < 2026051401) {

        // local_airpay_feature_flags — override storage
        $table = new xmldb_table('local_airpay_feature_flags');
        if (!$dbman->table_exists($table)) {
            $table->add_field('id', XMLDB_TYPE_INTEGER, '10', null,
                XMLDB_NOTNULL, XMLDB_SEQUENCE);
            $table->add_field('flag_key', XMLDB_TYPE_CHAR, '128', null,
                XMLDB_NOTNULL);
            $table->add_field('tenant_id', XMLDB_TYPE_INTEGER, '10', null,
                XMLDB_NOTNULL, null, '0');
            $table->add_field('is_enabled', XMLDB_TYPE_INTEGER, '1', null,
                XMLDB_NOTNULL);
            $table->add_field('modified_by', XMLDB_TYPE_INTEGER, '10', null,
                XMLDB_NOTNULL, null, '0');
            $table->add_field('timecreated', XMLDB_TYPE_INTEGER, '10', null,
                XMLDB_NOTNULL, null, '0');
            $table->add_field('timemodified', XMLDB_TYPE_INTEGER, '10', null,
                XMLDB_NOTNULL, null, '0');

            $table->add_key('primary', XMLDB_KEY_PRIMARY, ['id']);
            $table->add_key('uk_key_tenant', XMLDB_KEY_UNIQUE,
                ['flag_key', 'tenant_id']);
            $table->add_key('fk_modified_by', XMLDB_KEY_FOREIGN,
                ['modified_by'], 'user', ['id']);
            $table->add_index('idx_tenant_key', XMLDB_INDEX_NOTUNIQUE,
                ['tenant_id', 'flag_key']);

            $dbman->create_table($table);
        }

        // local_airpay_feature_flag_audit — change history
        $table = new xmldb_table('local_airpay_feature_flag_audit');
        if (!$dbman->table_exists($table)) {
            $table->add_field('id', XMLDB_TYPE_INTEGER, '10', null,
                XMLDB_NOTNULL, XMLDB_SEQUENCE);
            $table->add_field('flag_key', XMLDB_TYPE_CHAR, '128', null,
                XMLDB_NOTNULL);
            $table->add_field('tenant_id', XMLDB_TYPE_INTEGER, '10', null,
                XMLDB_NOTNULL, null, '0');
            $table->add_field('old_value', XMLDB_TYPE_INTEGER, '1', null,
                null, null, null);
            $table->add_field('new_value', XMLDB_TYPE_INTEGER, '1', null,
                null, null, null);
            $table->add_field('changed_by', XMLDB_TYPE_INTEGER, '10', null,
                XMLDB_NOTNULL);
            $table->add_field('reason', XMLDB_TYPE_CHAR, '255', null,
                null, null, null);
            $table->add_field('timecreated', XMLDB_TYPE_INTEGER, '10', null,
                XMLDB_NOTNULL, null, '0');

            $table->add_key('primary', XMLDB_KEY_PRIMARY, ['id']);
            $table->add_key('fk_changed_by', XMLDB_KEY_FOREIGN,
                ['changed_by'], 'user', ['id']);
            $table->add_index('idx_key_time', XMLDB_INDEX_NOTUNIQUE,
                ['flag_key', 'timecreated']);
            $table->add_index('idx_tenant_time', XMLDB_INDEX_NOTUNIQUE,
                ['tenant_id', 'timecreated']);

            $dbman->create_table($table);
        }

        upgrade_plugin_savepoint(true, 2026051401, 'local', 'airpay_core');
    }

    return true;
}
