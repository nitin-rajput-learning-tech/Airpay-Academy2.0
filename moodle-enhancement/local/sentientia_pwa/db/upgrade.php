<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

defined('MOODLE_INTERNAL') || die();

/**
 * Upgrade steps for local_sentientia_pwa.
 *
 * Each savepoint corresponds to a $plugin->version bump. The Moodle
 * upgrade runner calls this function with the OLD version; we add the
 * new artifact and call upgrade_plugin_savepoint() to advance.
 *
 * @package local_sentientia_pwa
 */
function xmldb_local_sentientia_pwa_upgrade(int $oldversion): bool {
    global $DB;
    $dbman = $DB->get_manager();

    // ── 2026052003 — Phase B.2 schema: push subscriptions table ──
    // The table is also declared in db/install.xml so fresh installs
    // get it without running this upgrade step. This branch handles
    // existing installs that already have the plugin from Phase B.1
    // (version 2026052001) or the brief 2026052002 release that shipped
    // the manager classes WITHOUT the db/upgrade.php step (caught on
    // smoke test — no row was created in mdl_config_plugins for the
    // new table).
    if ($oldversion < 2026052003) {
        $table = new xmldb_table('local_sentientia_push_subs');

        if (!$dbman->table_exists($table)) {
            $table->add_field('id',          XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE);
            $table->add_field('userid',      XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL);
            $table->add_field('customerid',  XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '1');
            $table->add_field('tenantid',    XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
            $table->add_field('endpoint',    XMLDB_TYPE_CHAR,   '1024', null, XMLDB_NOTNULL);
            $table->add_field('endpoint_hash', XMLDB_TYPE_CHAR,   '40', null, XMLDB_NOTNULL);
            $table->add_field('p256dh',      XMLDB_TYPE_CHAR,    '128', null, XMLDB_NOTNULL);
            $table->add_field('auth_secret', XMLDB_TYPE_CHAR,     '32', null, XMLDB_NOTNULL);
            $table->add_field('user_agent',  XMLDB_TYPE_CHAR,    '255', null);
            $table->add_field('last_seen',   XMLDB_TYPE_INTEGER, '10', null);
            $table->add_field('fail_count',  XMLDB_TYPE_INTEGER,  '3', null, XMLDB_NOTNULL, null, '0');
            $table->add_field('timecreated', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
            $table->add_field('timemodified', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');

            $table->add_key('primary',          XMLDB_KEY_PRIMARY, ['id']);
            $table->add_key('uk_user_endpoint', XMLDB_KEY_UNIQUE,  ['userid', 'endpoint_hash']);
            $table->add_key('fk_userid',        XMLDB_KEY_FOREIGN, ['userid'], 'user', ['id']);

            $table->add_index('idx_customer_tenant', XMLDB_INDEX_NOTUNIQUE,
                ['customerid', 'tenantid']);
            $table->add_index('idx_last_seen',       XMLDB_INDEX_NOTUNIQUE,
                ['last_seen']);

            $dbman->create_table($table);
        }

        upgrade_plugin_savepoint(true, 2026052003, 'local', 'sentientia_pwa');
    }

    // ── 2026052101 — Phase B.2.b: subscribe UI (no schema changes) ──
    // This step ONLY advances the version pointer. The actual additions
    // are PHP / JS / Mustache files that ship with the plugin — no DB
    // mutation required. We still need the savepoint so Moodle stops
    // re-running this upgrade.
    if ($oldversion < 2026052101) {
        upgrade_plugin_savepoint(true, 2026052101, 'local', 'sentientia_pwa');
    }

    // ── 2026052102 — Phase B.2.5: real push delivery (no schema changes) ──
    // jwt_signer + payload_encrypter + push_sender rewrite. CLI test_crypto.php
    // validates the math via self-consistent roundtrip. Maturity dropped to
    // MATURITY_ALPHA because the crypto is hand-rolled — needs security
    // review before flipping sentientia.pwa.push.enabled ON in production.
    if ($oldversion < 2026052102) {
        upgrade_plugin_savepoint(true, 2026052102, 'local', 'sentientia_pwa');
    }

    return true;
}
