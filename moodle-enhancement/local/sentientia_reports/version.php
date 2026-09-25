<?php
defined('MOODLE_INTERNAL') || die();
$plugin->component = 'local_sentientia_reports';
// P1 #52 (2026-05-20) — Hindi pack: 34 strings (CRUD form, capabilities,
// errors, status, confirms, toasts, privacy metadata).
$plugin->version   = 2026092500;  // ADR-031: run/export/edit/list confined to the caller's tenant; "All organisations" reports cross-tenant only
$plugin->requires  = 2024100700;
$plugin->maturity  = MATURITY_STABLE;
$plugin->release   = '1.2.0'; // +ADR-031 tenant scope (was 1.1.1 +P1 #52 Hindi pack)
$plugin->dependencies = [
    'local_sentientia_org'      => 2026041600,
    'local_sentientia_platform' => 2026092500,  // tenant::is_cross_tenant() (ADR-031)
];
