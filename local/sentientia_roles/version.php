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
// ADR-032 (2026-09-30) - the org_roles BizLMS importer (db/bizlms_import.php, classes/bizlms/) and the
// default-OFF flag sentientia.roles.org_assignments for the org-level role-holder list. No schema change.
// ADR-032 review round (2026-09-30) - the importer never grants a role across tenants or at a category the role may
// not be assigned at (both skipped as owner reasons), audit rows of an out-of-tenant actor carry no path, finalise()
// marks assigned users dirty. No schema change.
// ADR-032 owner decision IDN-01 (2026-10-07) - fail closed: a user with no tenant path is left out of an org-role row
// (warning user_without_tenant) and a row with nobody left is skipped with the owner reason user_without_tenant. No
// schema change; importer::requires_version() names this version.
$plugin->version   = 2026100701;  // ADR-032 IDN-01: org_roles fails closed for a user with no tenant path (on top of 2026093002)
// 2026052201: Goal A Bug #10 WS-contract alignment.
$plugin->requires  = 2024100700;
$plugin->maturity  = MATURITY_BETA;
$plugin->release   = '1.3.2-beta'; // ADR-032 IDN-01 fail-closed for a pathless user (1.3.1-beta: org_roles importer, reviewed; ADR-031 tenant scope + escalation closed)
// 1.1.3-beta: +Goal A Bug #10 WS-contract alignment
// role_manager calls local_sentientia_platform\tenant (ADR-031).
$plugin->dependencies = [
    'local_sentientia_platform' => ANY_VERSION,
];
