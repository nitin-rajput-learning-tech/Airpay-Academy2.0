<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

defined('MOODLE_INTERNAL') || die();

/**
 * Upgrade routine for local_airpay_whatsapp.
 *
 * @param int $oldversion previously installed version
 * @return bool always true on success
 */
function xmldb_local_airpay_whatsapp_upgrade(int $oldversion): bool {
    global $DB;
    $dbman = $DB->get_manager();

    // 0.2.0 — Phase A1 iters 2 + 3 tables.
    // Idempotent: only creates tables if they don't already exist.
    if ($oldversion < 2026051501) {

        // ── local_airpay_dlt_templates (iter 2) ──────────────────────
        $table = new xmldb_table('local_airpay_dlt_templates');

        if (!$dbman->table_exists($table)) {
            $table->add_field('id',              XMLDB_TYPE_INTEGER, '10', null,
                XMLDB_NOTNULL, XMLDB_SEQUENCE, null);
            $table->add_field('template_key',    XMLDB_TYPE_CHAR,    '64', null,
                XMLDB_NOTNULL, null, null);
            $table->add_field('channel',         XMLDB_TYPE_CHAR,    '16', null,
                XMLDB_NOTNULL, null, null);
            $table->add_field('category',        XMLDB_TYPE_CHAR,    '32', null,
                XMLDB_NOTNULL, null, 'transactional');
            $table->add_field('dlt_id',          XMLDB_TYPE_CHAR,    '64', null,
                null, null, null);
            $table->add_field('status',          XMLDB_TYPE_CHAR,    '16', null,
                XMLDB_NOTNULL, null, 'pending');
            $table->add_field('body',            XMLDB_TYPE_TEXT,    null, null,
                XMLDB_NOTNULL, null, null);
            $table->add_field('variables_json',  XMLDB_TYPE_TEXT,    null, null,
                null, null, null);
            $table->add_field('language',        XMLDB_TYPE_CHAR,    '8',  null,
                XMLDB_NOTNULL, null, 'en');
            $table->add_field('rejection_reason', XMLDB_TYPE_TEXT,   null, null,
                null, null, null);
            $table->add_field('submitted_at',    XMLDB_TYPE_INTEGER, '10', null,
                null, null, null);
            $table->add_field('approved_at',     XMLDB_TYPE_INTEGER, '10', null,
                null, null, null);
            $table->add_field('last_synced_at',  XMLDB_TYPE_INTEGER, '10', null,
                null, null, null);
            $table->add_field('timecreated',     XMLDB_TYPE_INTEGER, '10', null,
                XMLDB_NOTNULL, null, '0');
            $table->add_field('timemodified',    XMLDB_TYPE_INTEGER, '10', null,
                XMLDB_NOTNULL, null, '0');

            $table->add_key('primary', XMLDB_KEY_PRIMARY, ['id']);
            $table->add_key('uk_key_channel_lang', XMLDB_KEY_UNIQUE,
                ['template_key', 'channel', 'language']);

            $table->add_index('idx_status',  XMLDB_INDEX_NOTUNIQUE, ['status']);
            $table->add_index('idx_channel', XMLDB_INDEX_NOTUNIQUE, ['channel']);

            $dbman->create_table($table);
        }

        // ── local_airpay_send_log (iter 3+) ──────────────────────────
        $table2 = new xmldb_table('local_airpay_send_log');

        if (!$dbman->table_exists($table2)) {
            $table2->add_field('id',           XMLDB_TYPE_INTEGER, '10', null,
                XMLDB_NOTNULL, XMLDB_SEQUENCE, null);
            $table2->add_field('userid',       XMLDB_TYPE_INTEGER, '10', null,
                XMLDB_NOTNULL, null, '0');
            $table2->add_field('channel',      XMLDB_TYPE_CHAR,    '16', null,
                XMLDB_NOTNULL, null, null);
            $table2->add_field('template_key', XMLDB_TYPE_CHAR,    '64', null,
                XMLDB_NOTNULL, null, null);
            $table2->add_field('status',       XMLDB_TYPE_CHAR,    '16', null,
                XMLDB_NOTNULL, null, 'queued');
            $table2->add_field('provider_id',  XMLDB_TYPE_CHAR,    '128', null,
                null, null, null);
            $table2->add_field('recipient',    XMLDB_TYPE_CHAR,    '64', null,
                null, null, null);
            $table2->add_field('failure_reason', XMLDB_TYPE_TEXT,  null, null,
                null, null, null);
            $table2->add_field('attempts',     XMLDB_TYPE_INTEGER, '3', null,
                XMLDB_NOTNULL, null, '1');
            $table2->add_field('cost_paise',   XMLDB_TYPE_INTEGER, '10', null,
                null, null, null);
            $table2->add_field('mock_mode',    XMLDB_TYPE_INTEGER, '1', null,
                XMLDB_NOTNULL, null, '0');
            $table2->add_field('timecreated',  XMLDB_TYPE_INTEGER, '10', null,
                XMLDB_NOTNULL, null, '0');
            $table2->add_field('timeupdated',  XMLDB_TYPE_INTEGER, '10', null,
                null, null, null);

            $table2->add_key('primary', XMLDB_KEY_PRIMARY, ['id']);
            $table2->add_key('fk_user', XMLDB_KEY_FOREIGN, ['userid'], 'user', ['id']);

            $table2->add_index('idx_user_time',    XMLDB_INDEX_NOTUNIQUE,
                ['userid', 'timecreated']);
            $table2->add_index('idx_status_time',  XMLDB_INDEX_NOTUNIQUE,
                ['status', 'timecreated']);
            $table2->add_index('idx_channel_time', XMLDB_INDEX_NOTUNIQUE,
                ['channel', 'timecreated']);
            $table2->add_index('idx_provider_id',  XMLDB_INDEX_NOTUNIQUE,
                ['provider_id']);

            $dbman->create_table($table2);
        }

        upgrade_plugin_savepoint(true, 2026051501, 'local', 'airpay_whatsapp');
    }

    // ── 2026052101 — Stream C / Phase C.1 (no schema change) ──
    // Adds classes/notification_bridge.php — the helper that cron tasks
    // in other airpay_* plugins call to fan out their email send into
    // an additional WhatsApp/SMS message. No DB changes. Just a savepoint
    // so the upgrade pointer advances.
    if ($oldversion < 2026052101) {
        upgrade_plugin_savepoint(true, 2026052101, 'local', 'airpay_whatsapp');
    }

    // ── 2026052501 — Stream F / Wave E2 P4 (no schema change) ──
    // Adds 4 content-event triggers on classes/notification_bridge.php
    // + classes/observer.php + db/events.php + db/feature_flags.php
    // + 4 new DLT templates. Seeds the templates idempotently here
    // because install.php only runs on fresh installs.
    if ($oldversion < 2026052501) {
        require_once(__DIR__ . '/install.php');
        if (function_exists('seed_starter_templates')) {
            seed_starter_templates();
        }
        upgrade_plugin_savepoint(true, 2026052501, 'local', 'airpay_whatsapp');
    }

    return true;
}
