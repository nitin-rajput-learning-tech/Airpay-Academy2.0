<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

defined('MOODLE_INTERNAL') || die();

$plugin->component = 'local_airpay_proctoring';
$plugin->version   = 2026051201;  // Phase 8.1 security remediation
$plugin->requires  = 2024042200;
$plugin->maturity  = MATURITY_STABLE;
$plugin->release   = '1.0.1';     // Phase 8.1: B2+B3+B7 fixes
$plugin->dependencies = [
    'local_airpay_org'     => 2026040100,
    'local_airpay_privacy' => 2026040100,  // DSR pipeline integration
    'local_airpay_core'    => 2026051200,  // Shared tenant helper
];
