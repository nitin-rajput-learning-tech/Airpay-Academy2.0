<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

defined('MOODLE_INTERNAL') || die();

/**
 * Capabilities for local_sentientia_core.
 *
 * ADR-021 Wave 4: managetenants gates the tenant-registry admin UI. v1 is
 * site-admin-only — no archetype grants, so only site admins (who bypass all
 * capability checks) and roles an admin explicitly assigns this to can manage
 * the registry. Per-customer operator delegation is a later capability.
 */
$capabilities = [

    'local/sentientia_core:managetenants' => [
        'captype'      => 'write',
        'contextlevel' => CONTEXT_SYSTEM,
        'riskbitmask'  => RISK_CONFIG | RISK_DATALOSS,
        'archetypes'   => [],
    ],

    // ADR-032 legacy_logs: read the imported BizLMS admin log (and bulk upload errors).
    // No archetype grant: BizLMS had no screen for these tables, so a default grant would
    // make the history more visible than it ever was (owner rule 3), and the descriptions
    // name the actor by first name (RISK_PERSONAL). A holder who is not cross-tenant
    // (tenant::is_cross_tenant()) sees only the rows whose actor sat inside their own
    // tenant; a row with no resolvable tenant is cross-tenant only. The report page is
    // also behind the default-OFF flag sentientia.legacy_logs.report.enabled.
    'local/sentientia_core:viewadminlog' => [
        'captype'      => 'read',
        'contextlevel' => CONTEXT_SYSTEM,
        'riskbitmask'  => RISK_PERSONAL,
        'archetypes'   => [],
    ],
];
