<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

/**
 * Schema upgrades for local_sentientia_cart.
 *
 * @package local_sentientia_cart
 */

defined('MOODLE_INTERNAL') || die();

function xmldb_local_sentientia_cart_upgrade(int $oldversion): bool {
    global $DB;

    // ── 2026051201 — Phase 8.1 security remediation ──────────────────
    //
    // Phase 8.1 / B9 changed `local/sentientia_cart:manageprices` from
    // contextlevel CONTEXT_SYSTEM to CONTEXT_COURSE in db/access.php.
    //
    // Moodle re-registers capability metadata automatically when the
    // plugin version bumps (`update_capabilities()` called on upgrade).
    // The archetype defaults `manager => CAP_ALLOW` re-apply at the
    // new context level. So out-of-the-box archetype roles continue
    // to work after upgrade.
    //
    // **Correction (ADR-031, 2026-09-25):** this used to say that a
    // `:manageprices` grant at CONTEXT_SYSTEM "silently no-ops" once the cap
    // is checked at CONTEXT_COURSE. It does not: a system-level grant is
    // inherited by EVERY course context on the site, so a tenant admin (a
    // manager-archetype role assigned at system context) could price any
    // tenant's course. Moving the check to course context scoped nothing.
    // The tenant bound is now enforced in code instead
    // (cart_manager::require_course_in_tenant() in set_course_price, and
    // tenant::path_filter() on set_price.php). Per re-audit finding N4, ops
    // may still prefer to grant custom roles at the tenant root
    // CONTEXT_COURSECAT rather than at system context.
    //
    // The "Set capability cleanup checklist" entry in
    // PHASE-8-DEPLOYMENT-RUNBOOK.md §0 enforces the manual step.
    if ($oldversion < 2026051201) {
        upgrade_plugin_savepoint(true, 2026051201, 'local', 'sentientia_cart');
    }

    // ── 2026093001 — D1 (persona pass 2026-09-30): purchase for the user role ──
    //
    // db/access.php has always listed the `user` archetype for :purchase (since
    // the plugin was written), but the row was missing on the persona-pass site -
    // most likely because the capability was first registered outside
    // update_capabilities() (a CLI patch, the rename --migrate-caps path), which
    // is the only thing that applies archetype defaults. So a real public (/77) or
    // ZEEA (/177) learner - who holds no system role but Authenticated user - could
    // open the cart and was refused at add-to-cart and checkout. Fill the gap;
    // buying is still gated by enabled_tenants and the ADR-031 catalogue purchase gate.
    // Never overrides a PREVENT / PROHIBIT; idempotent. See db/upgradelib.php.
    if ($oldversion < 2026093001) {
        require_once(__DIR__ . '/upgradelib.php');
        local_sentientia_cart_backfill_user_purchase();
        upgrade_plugin_savepoint(true, 2026093001, 'local', 'sentientia_cart');
    }

    // ── 2026093002 — D1 review round 1: back-fill the default user role too ──
    //
    // Step 2026093001 found the authenticated-user role by archetype and shortname
    // only. The helper now also picks the role $CFG->defaultuserroleid points at
    // (how Moodle itself identifies it), which covers a restored BizLMS database
    // that renamed the role and cleared its archetype. Run the same helper again
    // for any site that already took 2026093001. Idempotent: it fills a missing
    // row only and never touches an existing ALLOW, PREVENT or PROHIBIT.
    if ($oldversion < 2026093002) {
        require_once(__DIR__ . '/upgradelib.php');
        local_sentientia_cart_backfill_user_purchase();
        upgrade_plugin_savepoint(true, 2026093002, 'local', 'sentientia_cart');
    }

    // ── 2026100101 — ADR-032: BizLMS cart import schema ─────────────────────
    //
    // The cart importer (classes/bizlms/) copies the BizLMS orders, ledger,
    // invoices and credit journal into this plugin's tables as frozen,
    // admin-only history. It needs two additions, both idempotent:
    //   - local_sentientia_cart_history.legacy_source: the in-row marker that
    //     mark_paid(), mark_failed() and refund() branch on to refuse an
    //     imported order, and that every reader uses to hide it from its owner;
    //   - local_sentientia_cart_credit_txn: the credit journal (the balance a
    //     user held lives on in local_sentientia_cart_credits).
    // The new status values (abandoned, part_cancelled) and event types
    // (legacy_*) fit the existing columns, so they need no change.
    if ($oldversion < 2026100101) {
        $dbman = $DB->get_manager();

        $table = new xmldb_table('local_sentientia_cart_history');
        $field = new xmldb_field('legacy_source', XMLDB_TYPE_CHAR, '20', null, null, null, null, 'timemodified');
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        $table = new xmldb_table('local_sentientia_cart_credit_txn');
        if (!$dbman->table_exists($table)) {
            $table->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE, null);
            $table->add_field('userid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
            $table->add_field('costcenterid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
            $table->add_field('event_type', XMLDB_TYPE_CHAR, '30', null, XMLDB_NOTNULL, null, null);
            $table->add_field('amount', XMLDB_TYPE_NUMBER, '14, 2', null, XMLDB_NOTNULL, null, '0');
            $table->add_field('balance_after', XMLDB_TYPE_NUMBER, '14, 2', null, XMLDB_NOTNULL, null, '0');
            $table->add_field('currency', XMLDB_TYPE_CHAR, '3', null, XMLDB_NOTNULL, null, 'INR');
            $table->add_field('historyid', XMLDB_TYPE_INTEGER, '10', null, null, null, null);
            $table->add_field('orderid', XMLDB_TYPE_INTEGER, '10', null, null, null, null);
            $table->add_field('ledgerid', XMLDB_TYPE_INTEGER, '10', null, null, null, null);
            $table->add_field('initiatedby', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
            $table->add_field('reason', XMLDB_TYPE_TEXT, null, null, null, null, null);
            $table->add_field('timecreated', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
            $table->add_field('timemodified', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
            $table->add_key('primary', XMLDB_KEY_PRIMARY, ['id']);
            $table->add_key('fk_user', XMLDB_KEY_FOREIGN, ['userid'], 'user', ['id']);
            $table->add_index('idx_user_time', XMLDB_INDEX_NOTUNIQUE, ['userid', 'timecreated']);
            $table->add_index('idx_costcenter', XMLDB_INDEX_NOTUNIQUE, ['costcenterid']);
            $dbman->create_table($table);
        }

        upgrade_plugin_savepoint(true, 2026100101, 'local', 'sentientia_cart');
    }

    // ── 2026101400 - GST tax invoices, credit notes and purchase receipts (1.2.0) ──
    //
    // Spec: docs/integrations/GST-TAX-INVOICE-AND-RECEIPT-SPEC-2026-10-14.md (sections 4 to 8, 11).
    // Everything here is additive, each part guarded so a re-run, or a site that took a part by hand, is safe, and
    // db/install.xml defines exactly the same schema (a fresh install and an upgraded site must end identically):
    //   - history: billing_state, gst_rate, invoice_due (+ idx_invoice_due);
    //   - invoices: billing_state, place_of_supply, sac_code, gst_rate, irn, ack_no, ack_date, signed_qr, supplier_json
    //     (the supplier as printed at issue: an issued invoice must not change when the company settings do) (+ idx_status);
    //   - NEW local_sentientia_cart_creditnotes;
    //   - NEW local_sentientia_cart_docseq (the gap-free number counters).
    // No row is touched and no flag is switched on: both new flags (db/feature_flags.php) default OFF, so until
    // Finance has confirmed section 10 of the spec the plugin issues no invoice and no credit note.
    if ($oldversion < 2026101400) {
        $dbman = $DB->get_manager();

        $table = new xmldb_table('local_sentientia_cart_history');
        $field = new xmldb_field('billing_state', XMLDB_TYPE_CHAR, '2', null, null, null, null, 'legacy_source');
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }
        $field = new xmldb_field('gst_rate', XMLDB_TYPE_NUMBER, '5, 2', null, null, null, null, 'billing_state');
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }
        $field = new xmldb_field('invoice_due', XMLDB_TYPE_INTEGER, '1', null, XMLDB_NOTNULL, null, '0', 'gst_rate');
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }
        $index = new xmldb_index('idx_invoice_due', XMLDB_INDEX_NOTUNIQUE, ['invoice_due']);
        if (!$dbman->index_exists($table, $index)) {
            $dbman->add_index($table, $index);
        }

        $table = new xmldb_table('local_sentientia_cart_invoices');
        $newcolumns = [
            new xmldb_field('billing_state', XMLDB_TYPE_CHAR, '2', null, null, null, null, 'timecreated'),
            new xmldb_field('place_of_supply', XMLDB_TYPE_CHAR, '2', null, null, null, null, 'billing_state'),
            new xmldb_field('sac_code', XMLDB_TYPE_CHAR, '20', null, null, null, null, 'place_of_supply'),
            new xmldb_field('gst_rate', XMLDB_TYPE_NUMBER, '5, 2', null, null, null, null, 'sac_code'),
            new xmldb_field('irn', XMLDB_TYPE_CHAR, '64', null, null, null, null, 'gst_rate'),
            new xmldb_field('ack_no', XMLDB_TYPE_CHAR, '40', null, null, null, null, 'irn'),
            new xmldb_field('ack_date', XMLDB_TYPE_INTEGER, '10', null, null, null, null, 'ack_no'),
            new xmldb_field('signed_qr', XMLDB_TYPE_TEXT, null, null, null, null, null, 'ack_date'),
            new xmldb_field('supplier_json', XMLDB_TYPE_TEXT, null, null, null, null, null, 'signed_qr'),
        ];
        foreach ($newcolumns as $field) {
            if (!$dbman->field_exists($table, $field)) {
                $dbman->add_field($table, $field);
            }
        }
        $index = new xmldb_index('idx_status', XMLDB_INDEX_NOTUNIQUE, ['status']);
        if (!$dbman->index_exists($table, $index)) {
            $dbman->add_index($table, $index);
        }

        $table = new xmldb_table('local_sentientia_cart_creditnotes');
        if (!$dbman->table_exists($table)) {
            $table->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE, null);
            $table->add_field('invoiceid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
            $table->add_field('historyid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
            $table->add_field('ledgerid', XMLDB_TYPE_INTEGER, '10', null, null, null, null);
            $table->add_field('cn_number', XMLDB_TYPE_CHAR, '50', null, null, null, null);
            $table->add_field('userid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
            $table->add_field('costcenterid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
            $table->add_field('billing_name', XMLDB_TYPE_CHAR, '200', null, XMLDB_NOTNULL, null, null);
            $table->add_field('billing_email', XMLDB_TYPE_CHAR, '200', null, null, null, null);
            $table->add_field('billing_phone', XMLDB_TYPE_CHAR, '40', null, null, null, null);
            $table->add_field('billing_address', XMLDB_TYPE_TEXT, null, null, null, null, null);
            $table->add_field('billing_gstn', XMLDB_TYPE_CHAR, '20', null, null, null, null);
            $table->add_field('billing_state', XMLDB_TYPE_CHAR, '2', null, null, null, null);
            $table->add_field('place_of_supply', XMLDB_TYPE_CHAR, '2', null, null, null, null);
            $table->add_field('sac_code', XMLDB_TYPE_CHAR, '20', null, null, null, null);
            $table->add_field('gst_rate', XMLDB_TYPE_NUMBER, '5, 2', null, null, null, null);
            $table->add_field('taxable', XMLDB_TYPE_NUMBER, '14, 2', null, XMLDB_NOTNULL, null, '0');
            $table->add_field('cgst', XMLDB_TYPE_NUMBER, '14, 2', null, XMLDB_NOTNULL, null, '0');
            $table->add_field('sgst', XMLDB_TYPE_NUMBER, '14, 2', null, XMLDB_NOTNULL, null, '0');
            $table->add_field('igst', XMLDB_TYPE_NUMBER, '14, 2', null, XMLDB_NOTNULL, null, '0');
            $table->add_field('total', XMLDB_TYPE_NUMBER, '14, 2', null, XMLDB_NOTNULL, null, '0');
            $table->add_field('currency', XMLDB_TYPE_CHAR, '3', null, XMLDB_NOTNULL, null, 'INR');
            $table->add_field('reason', XMLDB_TYPE_TEXT, null, null, null, null, null);
            $table->add_field('status', XMLDB_TYPE_CHAR, '20', null, XMLDB_NOTNULL, null, 'due');
            $table->add_field('irn', XMLDB_TYPE_CHAR, '64', null, null, null, null);
            $table->add_field('ack_no', XMLDB_TYPE_CHAR, '40', null, null, null, null);
            $table->add_field('ack_date', XMLDB_TYPE_INTEGER, '10', null, null, null, null);
            $table->add_field('signed_qr', XMLDB_TYPE_TEXT, null, null, null, null, null);
            $table->add_field('supplier_json', XMLDB_TYPE_TEXT, null, null, null, null, null);
            $table->add_field('timecreated', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
            $table->add_key('primary', XMLDB_KEY_PRIMARY, ['id']);
            $table->add_key('unique_cn_number', XMLDB_KEY_UNIQUE, ['cn_number']);
            $table->add_key('fk_invoice', XMLDB_KEY_FOREIGN, ['invoiceid'], 'local_sentientia_cart_invoices', ['id']);
            $table->add_key('fk_history', XMLDB_KEY_FOREIGN, ['historyid'], 'local_sentientia_cart_history', ['id']);
            $table->add_index('idx_ledger', XMLDB_INDEX_NOTUNIQUE, ['ledgerid']);
            $table->add_index('idx_status', XMLDB_INDEX_NOTUNIQUE, ['status']);
            $table->add_index('idx_user_time', XMLDB_INDEX_NOTUNIQUE, ['userid', 'timecreated']);
            $dbman->create_table($table);
        }

        $table = new xmldb_table('local_sentientia_cart_docseq');
        if (!$dbman->table_exists($table)) {
            $table->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE, null);
            $table->add_field('series', XMLDB_TYPE_CHAR, '40', null, XMLDB_NOTNULL, null, null);
            $table->add_field('lastno', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
            $table->add_field('timemodified', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
            $table->add_key('primary', XMLDB_KEY_PRIMARY, ['id']);
            $table->add_key('unique_series', XMLDB_KEY_UNIQUE, ['series']);
            $dbman->create_table($table);
        }

        upgrade_plugin_savepoint(true, 2026101400, 'local', 'sentientia_cart');
    }

    return true;
}
