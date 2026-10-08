<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

defined('MOODLE_INTERNAL') || die();

$plugin->component = 'local_sentientia_cart';
// P1 #57 (2026-05-20) — Hindi pack: 117 strings covering cart UI, checkout,
// order history, admin orders, pricing, settings (gateway/tax/email/IP),
// notifications, errors, privacy metadata.
$plugin->version   = 2026100701;  // owner decisions 2026-10-07: finance keys declared by the importer, withheld-line refund amounts, order notifications built in the recipient's own language (en + hi strings); no schema/cap change
// 2026100101: ADR-032: BizLMS cart importer (orders, ledger, invoices, credits), history.legacy_source, local_sentientia_cart_credit_txn, imported-history readers behind two default-OFF flags
// 2026093002: D1 review round 1: add_item prices via cart_manager (no lib.php dependency); back-fill also covers $CFG->defaultuserroleid
// 2026093001: D1 (persona pass 2026-09-30): upgrade back-fills :purchase onto the authenticated-user role
// 2026092500: ADR-031: daily-sums CSV, invoices, pricing scoped to the caller's tenant; no-tenant callers fail closed
// 2026092202: privacy provider now declares every user table it owns  // smoke CLI path filter /-bounded
// 2026052001:
$plugin->requires  = 2024042200;  // Moodle 4.5+
$plugin->maturity  = MATURITY_STABLE;
$plugin->release   = '1.1.1';     // 1.1.1: cart.withheld_line_refund (per-line refund amounts for review), notifier in the recipient's language, importer declares the two finance keys (was 1.1.0)
// 1.1.0: ADR-032 BizLMS cart importer, frozen admin-only order/ledger/invoice/credit history, legacy rows refused by mark_paid/mark_failed/refund (was 1.0.6)
// 1.0.6: D1 review round 1 - add_item no longer needs lib.php; back-fill also covers the default user role
// 1.0.5: D1 - :purchase back-filled onto the user role (buying still gated by enabled_tenants + ADR-031)
// 1.0.4: ADR-031 cross-tenant authority
// 1.0.3: +P1 #57 Hindi pack
$plugin->dependencies = [
    'local_sentientia_org'    => 2026040100,  // Tenant scoping engine
    'local_sentientia_emails' => 2026040100,  // Email templates for receipts
    'local_sentientia_platform'   => 2026093001,  // tenant::is_cross_tenant() (ADR-031); the ADR-032 import framework (bizlms\importer)
];
