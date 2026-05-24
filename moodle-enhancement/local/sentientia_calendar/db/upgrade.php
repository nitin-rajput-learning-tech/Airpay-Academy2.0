<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

/**
 * Upgrade steps for local_sentientia_calendar.
 *
 * @package local_sentientia_calendar
 */

defined('MOODLE_INTERNAL') || die();

function xmldb_local_sentientia_calendar_upgrade(int $oldversion): bool {
    global $DB;
    $dbman = $DB->get_manager();

    // ─────────────────────────────────────────────────────────────────
    // 2026052401 — Tier 2.6 Phase 2 OAuth scaffolding.
    //
    // Adds an additive table {local_sentientia_calendar_oauth} for
    // encrypted-at-rest Microsoft 365 + Google Calendar OAuth tokens.
    // The Phase 1 token table is untouched.
    //
    // Nothing else changes on this upgrade — no surfaces yet write
    // rows into this table (scaffolding only); Phase 2.1 wires up the
    // live token exchange that populates real rows.
    // ─────────────────────────────────────────────────────────────────
    if ($oldversion < 2026052401) {
        $table = new xmldb_table('local_sentientia_calendar_oauth');

        $table->add_field('id', XMLDB_TYPE_INTEGER, '10', null,
            XMLDB_NOTNULL, XMLDB_SEQUENCE, null);
        $table->add_field('userid', XMLDB_TYPE_INTEGER, '10', null,
            XMLDB_NOTNULL, null, null);
        $table->add_field('customerid', XMLDB_TYPE_INTEGER, '10', null,
            XMLDB_NOTNULL, null, '1');
        $table->add_field('provider', XMLDB_TYPE_CHAR, '20', null,
            XMLDB_NOTNULL, null, null);
        $table->add_field('access_token_enc', XMLDB_TYPE_TEXT, null, null,
            XMLDB_NOTNULL, null, null);
        $table->add_field('refresh_token_enc', XMLDB_TYPE_TEXT, null, null,
            null, null, null);
        $table->add_field('expires', XMLDB_TYPE_INTEGER, '10', null,
            XMLDB_NOTNULL, null, '0');
        $table->add_field('scopes', XMLDB_TYPE_TEXT, null, null,
            null, null, null);
        $table->add_field('timecreated', XMLDB_TYPE_INTEGER, '10', null,
            XMLDB_NOTNULL, null, '0');
        $table->add_field('timemodified', XMLDB_TYPE_INTEGER, '10', null,
            XMLDB_NOTNULL, null, '0');

        $table->add_key('primary', XMLDB_KEY_PRIMARY, ['id']);
        $table->add_key('uk_user_provider', XMLDB_KEY_UNIQUE, ['userid', 'provider']);
        $table->add_key('fk_user_oauth', XMLDB_KEY_FOREIGN, ['userid'], 'user', ['id']);

        $table->add_index('idx_provider_expires', XMLDB_INDEX_NOTUNIQUE,
            ['provider', 'expires']);
        $table->add_index('idx_customer_provider', XMLDB_INDEX_NOTUNIQUE,
            ['customerid', 'provider']);

        if (!$dbman->table_exists($table)) {
            $dbman->create_table($table);
        }

        upgrade_plugin_savepoint(true, 2026052401, 'local', 'sentientia_calendar');
    }

    return true;
}
