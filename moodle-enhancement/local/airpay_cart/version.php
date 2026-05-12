<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

defined('MOODLE_INTERNAL') || die();

$plugin->component = 'local_airpay_cart';
$plugin->version   = 2026051100;
$plugin->requires  = 2024042200;  // Moodle 4.5+
$plugin->maturity  = MATURITY_STABLE;
$plugin->release   = '1.0.0';
$plugin->dependencies = [
    'local_airpay_org'    => 2026040100,  // Tenant scoping engine
    'local_airpay_emails' => 2026040100,  // Email templates for receipts
];
