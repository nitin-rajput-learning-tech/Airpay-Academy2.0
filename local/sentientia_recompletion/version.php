<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

defined('MOODLE_INTERNAL') || die();

$plugin->component = 'local_sentientia_recompletion';
// P1 #20 (2026-05-16) — `completion_reset` event class so observers
// (notifications, analytics, SIEM via logstore_standard_log) can
// listen for resets. Closes audit item #19 from
// parity-audit-2026-05-15/sentientia_recompletion.md.
// P1 #53 (2026-05-20) — Hindi pack: 40 strings covering navigation,
// capabilities, settings, rule form, messages, event labels, privacy.
// ADR-031 (2026-09-25) — rules, courses and history tenant-scoped
// (classes/rule_access.php); a tenant admin's rule is stamped with their
// tenant instead of 0 = every tenant; :reset grant revoked (step 2026092500).
$plugin->version   = 2026092500;  // ADR-031: tenant-scoped rules/history; :reset grant revoked
$plugin->requires  = 2024042200;
$plugin->maturity  = MATURITY_STABLE;
$plugin->release   = '1.1.2';     // ADR-031 tenant scope (1.1.1: +P1 #53 Hindi pack)
$plugin->dependencies = [
    'local_sentientia_org'      => 2026040100,
    'local_sentientia_platform' => 2026092500,  // tenant::is_cross_tenant / scope_path
];
