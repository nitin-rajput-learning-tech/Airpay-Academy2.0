<?php
defined('MOODLE_INTERNAL') || die();
$plugin->component = 'local_sentientia_reports';
// P1 #52 (2026-05-20) — Hindi pack: 34 strings (CRUD form, capabilities,
// errors, status, confirms, toasts, privacy metadata).
// 2026-10-01 — ADR-032 BizLMS import, users feature: the Training Transcript report type (flag
// sentientia.reports.training_transcript) and the imported-login-days column of User Activity (flag
// sentientia.reports.login_days), both default OFF, both reading local_sentientia_users_* tables that exist
// only after local_sentientia_users 2026100101 (guarded by table_exists).
$plugin->version   = 2026100101;  // ADR-032 users: flagged Training Transcript report + imported login days column
// 2026092500: ADR-031: run/export/edit/list confined to the caller's tenant; "All organisations" reports cross-tenant only
$plugin->requires  = 2024100700;
$plugin->maturity  = MATURITY_STABLE;
$plugin->release   = '1.3.0'; // +ADR-032 users reports (1.2.0: +ADR-031 tenant scope; 1.1.1: +P1 #52 Hindi pack)
$plugin->dependencies = [
    'local_sentientia_org'      => 2026041600,
    'local_sentientia_platform' => 2026092500,  // tenant::is_cross_tenant() (ADR-031)
];
