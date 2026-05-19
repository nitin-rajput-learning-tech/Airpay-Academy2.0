<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

defined('MOODLE_INTERNAL') || die();

$plugin->component = 'local_airpay_recompletion';
// P1 #20 (2026-05-16) — `completion_reset` event class so observers
// (notifications, analytics, SIEM via logstore_standard_log) can
// listen for resets. Closes audit item #19 from
// parity-audit-2026-05-15/airpay_recompletion.md.
$plugin->version   = 2026051901;
$plugin->requires  = 2024042200;
$plugin->maturity  = MATURITY_STABLE;
$plugin->release   = '1.1.0';     // +P1 #20 completion_reset event
$plugin->dependencies = [
    'local_airpay_org' => 2026040100,
];
