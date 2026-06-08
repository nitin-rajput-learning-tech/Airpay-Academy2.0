<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

/**
 * Upgrade hooks for local_sentientia_platform.
 *
 * @package local_sentientia_platform
 */

defined('MOODLE_INTERNAL') || die();

function xmldb_local_sentientia_platform_upgrade(int $oldversion): bool {
    global $DB;
    $dbman = $DB->get_manager();

    // ── Phase A0 (2026-05-14) — Feature flags + audit infrastructure
    // First DB tables this plugin has ever owned. They power the
    // Switchboard admin UI and the runtime feature_flags::is_enabled()
    // resolution path.
    if ($oldversion < 2026051401) {

        // local_sentientia_feature_flags — override storage
        $table = new xmldb_table('local_sentientia_feature_flags');
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

        // local_sentientia_feature_flag_audit — change history
        $table = new xmldb_table('local_sentientia_feature_flag_audit');
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

        upgrade_plugin_savepoint(true, 2026051401, 'local', 'sentientia_platform');
    }

    // ── Session 2 / ADR-002 (2026-05-20) — customer-level feature flags
    // Adds customer_id column to both feature-flag tables, restructures the
    // unique key + index to include the new column, and adds a per-customer
    // audit-feed index. Backwards-compatible: existing rows get
    // customer_id=0 (the "all customers" sentinel) and resolve identically
    // to before. See docs/adr/ADR-002-customer-level-feature-flags.md.
    if ($oldversion < 2026052101) {

        // ── local_sentientia_feature_flags ─────────────────────────────────
        $table = new xmldb_table('local_sentientia_feature_flags');

        // 1. Add customer_id column. Default 0 preserves every existing row
        //    as "applies to all customers" — the legacy resolution result.
        $field = new xmldb_field('customer_id', XMLDB_TYPE_INTEGER, '10', null,
            XMLDB_NOTNULL, null, '0', 'flag_key');
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        // 2. Drop the old uk_key_tenant unique key — it constrained
        //    (flag_key, tenant_id) which is no longer enough now that
        //    (flag_key, customer_id, tenant_id) is the row identity.
        $old_uk = new xmldb_key('uk_key_tenant', XMLDB_KEY_UNIQUE,
            ['flag_key', 'tenant_id']);
        if ($dbman->find_key_name($table, $old_uk)) {
            $dbman->drop_key($table, $old_uk);
        }

        // 3. Add the new composite unique key.
        $new_uk = new xmldb_key('uk_key_cust_tenant', XMLDB_KEY_UNIQUE,
            ['flag_key', 'customer_id', 'tenant_id']);
        if (!$dbman->find_key_name($table, $new_uk)) {
            $dbman->add_key($table, $new_uk);
        }

        // 4. Drop the old idx_tenant_key — replaced by idx_cust_tenant_key.
        $old_idx = new xmldb_index('idx_tenant_key', XMLDB_INDEX_NOTUNIQUE,
            ['tenant_id', 'flag_key']);
        if ($dbman->index_exists($table, $old_idx)) {
            $dbman->drop_index($table, $old_idx);
        }

        // 5. Add the new composite index covering the resolver's WHERE clause.
        $new_idx = new xmldb_index('idx_cust_tenant_key', XMLDB_INDEX_NOTUNIQUE,
            ['customer_id', 'tenant_id', 'flag_key']);
        if (!$dbman->index_exists($table, $new_idx)) {
            $dbman->add_index($table, $new_idx);
        }

        // ── local_sentientia_feature_flag_audit ────────────────────────────
        $audit_table = new xmldb_table('local_sentientia_feature_flag_audit');

        // 1. Add customer_id column. Default 0 — every historical audit row
        //    is treated as "all customers" scope which is correct for the
        //    pre-Session-2 single-customer world.
        $field = new xmldb_field('customer_id', XMLDB_TYPE_INTEGER, '10', null,
            XMLDB_NOTNULL, null, '0', 'flag_key');
        if (!$dbman->field_exists($audit_table, $field)) {
            $dbman->add_field($audit_table, $field);
        }

        // 2. Add the per-customer audit-feed index.
        $audit_idx = new xmldb_index('idx_customer_time', XMLDB_INDEX_NOTUNIQUE,
            ['customer_id', 'timecreated']);
        if (!$dbman->index_exists($audit_table, $audit_idx)) {
            $dbman->add_index($audit_table, $audit_idx);
        }

        upgrade_plugin_savepoint(true, 2026052101, 'local', 'sentientia_platform');
    }

    // ── ADR-008 (2026-05-22) — local_sentientia_customer_brand table ─────
    // Per-customer branding bundle. Replaces the hard-wired switch in
    // \local_sentientia_platform\customer::branding() with a cached DB lookup.
    // Back-compatible: the resolver falls back to the hard-coded Airpay
    // bundle when the table is empty.
    if ($oldversion < 2026052201) {
        $brand_table = new xmldb_table('local_sentientia_customer_brand');

        if (!$dbman->table_exists($brand_table)) {
            $brand_table->add_field('id', XMLDB_TYPE_INTEGER, '10', null,
                XMLDB_NOTNULL, XMLDB_SEQUENCE);
            $brand_table->add_field('customerid', XMLDB_TYPE_INTEGER, '10', null,
                XMLDB_NOTNULL);
            $brand_table->add_field('name', XMLDB_TYPE_CHAR, '120', null,
                XMLDB_NOTNULL);
            $brand_table->add_field('short_name', XMLDB_TYPE_CHAR, '40', null,
                XMLDB_NOTNULL);
            $brand_table->add_field('theme_color', XMLDB_TYPE_CHAR, '7', null,
                XMLDB_NOTNULL);
            $brand_table->add_field('bg_color', XMLDB_TYPE_CHAR, '7', null,
                XMLDB_NOTNULL);
            $brand_table->add_field('icon_192_url', XMLDB_TYPE_CHAR, '500', null,
                XMLDB_NOTNULL);
            $brand_table->add_field('icon_512_url', XMLDB_TYPE_CHAR, '500', null,
                XMLDB_NOTNULL);
            $brand_table->add_field('start_url', XMLDB_TYPE_CHAR, '500', null,
                XMLDB_NOTNULL);
            $brand_table->add_field('lang', XMLDB_TYPE_CHAR, '10', null,
                XMLDB_NOTNULL, null, 'en');
            $brand_table->add_field('status_bar_style', XMLDB_TYPE_CHAR, '20',
                null, null);
            $brand_table->add_field('categories', XMLDB_TYPE_CHAR, '200', null,
                null);
            $brand_table->add_field('timecreated', XMLDB_TYPE_INTEGER, '10', null,
                XMLDB_NOTNULL, null, '0');
            $brand_table->add_field('timemodified', XMLDB_TYPE_INTEGER, '10', null,
                XMLDB_NOTNULL, null, '0');

            $brand_table->add_key('primary', XMLDB_KEY_PRIMARY, ['id']);
            $brand_table->add_key('uk_customer', XMLDB_KEY_UNIQUE, ['customerid']);

            $dbman->create_table($brand_table);
        }

        // Backfill the Airpay customer-zero row from the bundle that
        // \local_sentientia_platform\customer::branding() returned pre-Phase-2.
        // Idempotent — the unique key on customerid blocks duplicates,
        // so re-running this savepoint is a no-op.
        if (!$DB->record_exists('local_sentientia_customer_brand', ['customerid' => 1])) {
            $now = time();
            $DB->insert_record('local_sentientia_customer_brand', (object) [
                'customerid'       => 1,
                'name'             => 'Airpay Academy',
                'short_name'       => 'Academy',
                'theme_color'      => '#0066A7',
                'bg_color'         => '#F2F4FB',
                'icon_192_url'     => '/local/sentientia_platform/pix/customer/1/icon-192.png',
                'icon_512_url'     => '/local/sentientia_platform/pix/customer/1/icon-512.png',
                'start_url'        => '/my/dashboard.php?utm_source=pwa_install',
                'lang'             => 'en',
                'status_bar_style' => 'default',
                'categories'       => 'education,productivity',
                'timecreated'      => $now,
                'timemodified'     => $now,
            ]);
        }

        upgrade_plugin_savepoint(true, 2026052201, 'local', 'sentientia_platform');
    }

    // ── ADR-017 / Phase 0 (2026-05-28) — Polymorphic user-types
    // Schema-only. No behaviour change at this savepoint; classification
    // CLI (Phase 1) backfills rows, providers (Phase 2-5) read them.
    //
    // 4 v1 types: employee | consumer | partner_employee | operator.
    // Tables are append-only (per Q1 immutability ruling); promotion
    // creates a new mdl_user account, not a row update.
    if ($oldversion < 2026052801) {

        // ── local_airpay_user_type — the classification row, 1:1 with mdl_user
        $table = new xmldb_table('local_airpay_user_type');
        if (!$dbman->table_exists($table)) {
            $table->add_field('id', XMLDB_TYPE_INTEGER, '10', null,
                XMLDB_NOTNULL, XMLDB_SEQUENCE);
            $table->add_field('userid', XMLDB_TYPE_INTEGER, '10', null,
                XMLDB_NOTNULL);
            $table->add_field('user_type', XMLDB_TYPE_CHAR, '20', null,
                XMLDB_NOTNULL);
            $table->add_field('provisioning_source', XMLDB_TYPE_CHAR, '40', null,
                XMLDB_NOTNULL, null, 'unknown');
            $table->add_field('provisioned_at', XMLDB_TYPE_INTEGER, '10', null,
                XMLDB_NOTNULL, null, '0');
            $table->add_field('timecreated', XMLDB_TYPE_INTEGER, '10', null,
                XMLDB_NOTNULL, null, '0');
            $table->add_field('timemodified', XMLDB_TYPE_INTEGER, '10', null,
                XMLDB_NOTNULL, null, '0');

            $table->add_key('primary', XMLDB_KEY_PRIMARY, ['id']);
            $table->add_key('uk_userid', XMLDB_KEY_UNIQUE, ['userid']);
            $table->add_key('fk_userid', XMLDB_KEY_FOREIGN, ['userid'],
                'user', ['id']);
            $table->add_index('idx_type', XMLDB_INDEX_NOTUNIQUE, ['user_type']);

            $dbman->create_table($table);
        }

        // ── local_airpay_employee_profile — Airpay-customer staff
        $table = new xmldb_table('local_airpay_employee_profile');
        if (!$dbman->table_exists($table)) {
            $table->add_field('id', XMLDB_TYPE_INTEGER, '10', null,
                XMLDB_NOTNULL, XMLDB_SEQUENCE);
            $table->add_field('userid', XMLDB_TYPE_INTEGER, '10', null,
                XMLDB_NOTNULL);
            $table->add_field('employee_id', XMLDB_TYPE_CHAR, '40', null,
                null, null, null);
            $table->add_field('department', XMLDB_TYPE_CHAR, '80', null,
                null, null, null);
            $table->add_field('job_title', XMLDB_TYPE_CHAR, '80', null,
                null, null, null);
            $table->add_field('manager_userid', XMLDB_TYPE_INTEGER, '10', null,
                null, null, null);
            $table->add_field('hire_date', XMLDB_TYPE_INTEGER, '10', null,
                null, null, null);
            $table->add_field('cost_center_path', XMLDB_TYPE_CHAR, '255', null,
                null, null, null);
            $table->add_field('timecreated', XMLDB_TYPE_INTEGER, '10', null,
                XMLDB_NOTNULL, null, '0');
            $table->add_field('timemodified', XMLDB_TYPE_INTEGER, '10', null,
                XMLDB_NOTNULL, null, '0');

            $table->add_key('primary', XMLDB_KEY_PRIMARY, ['id']);
            $table->add_key('uk_userid', XMLDB_KEY_UNIQUE, ['userid']);
            $table->add_key('fk_userid', XMLDB_KEY_FOREIGN, ['userid'],
                'user', ['id']);
            $table->add_key('fk_manager', XMLDB_KEY_FOREIGN,
                ['manager_userid'], 'user', ['id']);
            // FK on manager_userid already creates the underlying index;
            // no need for a separate XMLDB index entry.
            $table->add_index('idx_dept', XMLDB_INDEX_NOTUNIQUE, ['department']);

            $dbman->create_table($table);
        }

        // ── local_airpay_consumer_profile — public-signup learners
        $table = new xmldb_table('local_airpay_consumer_profile');
        if (!$dbman->table_exists($table)) {
            $table->add_field('id', XMLDB_TYPE_INTEGER, '10', null,
                XMLDB_NOTNULL, XMLDB_SEQUENCE);
            $table->add_field('userid', XMLDB_TYPE_INTEGER, '10', null,
                XMLDB_NOTNULL);
            $table->add_field('interests_json', XMLDB_TYPE_TEXT, null, null,
                null, null, null);
            $table->add_field('weekly_goal', XMLDB_TYPE_INTEGER, '2', null,
                null, null, null);
            $table->add_field('referral_source', XMLDB_TYPE_CHAR, '40', null,
                null, null, null);
            $table->add_field('consent_marketing', XMLDB_TYPE_INTEGER, '1', null,
                XMLDB_NOTNULL, null, '0');
            $table->add_field('consent_leaderboard', XMLDB_TYPE_INTEGER, '1', null,
                XMLDB_NOTNULL, null, '0');
            $table->add_field('payment_history_url', XMLDB_TYPE_CHAR, '255', null,
                null, null, null);
            $table->add_field('timecreated', XMLDB_TYPE_INTEGER, '10', null,
                XMLDB_NOTNULL, null, '0');
            $table->add_field('timemodified', XMLDB_TYPE_INTEGER, '10', null,
                XMLDB_NOTNULL, null, '0');

            $table->add_key('primary', XMLDB_KEY_PRIMARY, ['id']);
            $table->add_key('uk_userid', XMLDB_KEY_UNIQUE, ['userid']);
            $table->add_key('fk_userid', XMLDB_KEY_FOREIGN, ['userid'],
                'user', ['id']);

            $dbman->create_table($table);
        }

        // ── local_airpay_partner_employee_profile — B2B partner-org staff
        $table = new xmldb_table('local_airpay_partner_employee_profile');
        if (!$dbman->table_exists($table)) {
            $table->add_field('id', XMLDB_TYPE_INTEGER, '10', null,
                XMLDB_NOTNULL, XMLDB_SEQUENCE);
            $table->add_field('userid', XMLDB_TYPE_INTEGER, '10', null,
                XMLDB_NOTNULL);
            $table->add_field('customer_id', XMLDB_TYPE_INTEGER, '10', null,
                XMLDB_NOTNULL);
            $table->add_field('partner_employee_id', XMLDB_TYPE_CHAR, '40', null,
                null, null, null);
            $table->add_field('partner_department', XMLDB_TYPE_CHAR, '80', null,
                null, null, null);
            $table->add_field('partner_job_title', XMLDB_TYPE_CHAR, '80', null,
                null, null, null);
            $table->add_field('partner_manager_userid', XMLDB_TYPE_INTEGER, '10',
                null, null, null, null);
            $table->add_field('partner_hire_date', XMLDB_TYPE_INTEGER, '10', null,
                null, null, null);
            $table->add_field('cost_center_path', XMLDB_TYPE_CHAR, '255', null,
                null, null, null);
            $table->add_field('timecreated', XMLDB_TYPE_INTEGER, '10', null,
                XMLDB_NOTNULL, null, '0');
            $table->add_field('timemodified', XMLDB_TYPE_INTEGER, '10', null,
                XMLDB_NOTNULL, null, '0');

            $table->add_key('primary', XMLDB_KEY_PRIMARY, ['id']);
            $table->add_key('uk_userid', XMLDB_KEY_UNIQUE, ['userid']);
            $table->add_key('fk_userid', XMLDB_KEY_FOREIGN, ['userid'],
                'user', ['id']);
            $table->add_key('fk_manager', XMLDB_KEY_FOREIGN,
                ['partner_manager_userid'], 'user', ['id']);
            // FK on partner_manager_userid creates the underlying index.
            $table->add_index('idx_customer', XMLDB_INDEX_NOTUNIQUE,
                ['customer_id']);

            $dbman->create_table($table);
        }

        // ── local_airpay_operator_profile — platform operators / Site Admins
        $table = new xmldb_table('local_airpay_operator_profile');
        if (!$dbman->table_exists($table)) {
            $table->add_field('id', XMLDB_TYPE_INTEGER, '10', null,
                XMLDB_NOTNULL, XMLDB_SEQUENCE);
            $table->add_field('userid', XMLDB_TYPE_INTEGER, '10', null,
                XMLDB_NOTNULL);
            $table->add_field('operator_role', XMLDB_TYPE_CHAR, '40', null,
                null, null, null);
            $table->add_field('contact_phone', XMLDB_TYPE_CHAR, '40', null,
                null, null, null);
            $table->add_field('oncall_for_customer_id', XMLDB_TYPE_INTEGER, '10',
                null, null, null, null);
            $table->add_field('timecreated', XMLDB_TYPE_INTEGER, '10', null,
                XMLDB_NOTNULL, null, '0');
            $table->add_field('timemodified', XMLDB_TYPE_INTEGER, '10', null,
                XMLDB_NOTNULL, null, '0');

            $table->add_key('primary', XMLDB_KEY_PRIMARY, ['id']);
            $table->add_key('uk_userid', XMLDB_KEY_UNIQUE, ['userid']);
            $table->add_key('fk_userid', XMLDB_KEY_FOREIGN, ['userid'],
                'user', ['id']);

            $dbman->create_table($table);
        }

        upgrade_plugin_savepoint(true, 2026052801, 'local', 'sentientia_platform');
    }

    return true;
}
