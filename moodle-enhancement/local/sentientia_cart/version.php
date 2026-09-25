<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

defined('MOODLE_INTERNAL') || die();

$plugin->component = 'local_sentientia_cart';
// P1 #57 (2026-05-20) — Hindi pack: 117 strings covering cart UI, checkout,
// order history, admin orders, pricing, settings (gateway/tax/email/IP),
// notifications, errors, privacy metadata.
$plugin->version   = 2026092500;  // ADR-031: daily-sums CSV, invoices, pricing scoped to the caller's tenant; no-tenant callers fail closed
// 2026092202: privacy provider now declares every user table it owns  // smoke CLI path filter /-bounded
// 2026052001:
$plugin->requires  = 2024042200;  // Moodle 4.5+
$plugin->maturity  = MATURITY_STABLE;
$plugin->release   = '1.0.4';     // ADR-031 cross-tenant authority
// 1.0.3: +P1 #57 Hindi pack
$plugin->dependencies = [
    'local_sentientia_org'    => 2026040100,  // Tenant scoping engine
    'local_sentientia_emails' => 2026040100,  // Email templates for receipts
    'local_sentientia_platform'   => 2026092500,  // tenant::is_cross_tenant() (ADR-031)
];
