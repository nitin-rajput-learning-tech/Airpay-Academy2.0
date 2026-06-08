<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

defined('MOODLE_INTERNAL') || die();

$plugin->component = 'local_sentientia_cart';
// P1 #57 (2026-05-20) — Hindi pack: 117 strings covering cart UI, checkout,
// order history, admin orders, pricing, settings (gateway/tax/email/IP),
// notifications, errors, privacy metadata.
$plugin->version   = 2026052001;
$plugin->requires  = 2024042200;  // Moodle 4.5+
$plugin->maturity  = MATURITY_STABLE;
$plugin->release   = '1.0.2';     // +P1 #57 Hindi pack
$plugin->dependencies = [
    'local_airpay_org'    => 2026040100,  // Tenant scoping engine
    'local_sentientia_emails' => 2026040100,  // Email templates for receipts
    'local_airpay_core'   => 2026051200,  // Shared tenant helper
];
