<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
defined('MOODLE_INTERNAL') || die();
$plugin->component = 'local_sentientia_roles';
// P1 #56 (2026-05-20) — Hindi pack: 94 strings covering capabilities,
// filters, table columns, view tabs, capability edit modal, audit log,
// errors, privacy metadata.
// Goal A audit Bug #10 (2026-05-22) — align list_audit WS with the shared
// theme_sentientia/datatable contract (accept `search`, aliased to the
// existing capability filter).
// ADR-031 (2026-09-25) - role definitions are cross-tenant only; :manage and
// :assign lose their manager default (+ revoke step); a scoped :assign holder
// may only assign a role they hold, to somebody else in their tenant; holder
// lists, counts and the audit log are tenant-bounded and fail closed.
$plugin->version   = 2026092500;  // ADR-031: cross-tenant role authority
// 2026052201: Goal A Bug #10 WS-contract alignment.
$plugin->requires  = 2024100700;
$plugin->maturity  = MATURITY_BETA;
$plugin->release   = '1.2.0-beta'; // ADR-031 tenant scope + escalation closed
// 1.1.3-beta: +Goal A Bug #10 WS-contract alignment
// role_manager calls local_sentientia_platform\tenant (ADR-031).
$plugin->dependencies = [
    'local_sentientia_platform' => ANY_VERSION,
];
