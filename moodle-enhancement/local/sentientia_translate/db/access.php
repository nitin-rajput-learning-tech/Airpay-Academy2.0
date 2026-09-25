<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

/**
 * Capabilities for Sentientia LMS AI Content Translation (Phase T.0 MVP).
 *
 *   translate     — paste source + call Claude to produce a translation,
 *                   review the diff, and save. Manager only — cost-sensitive.
 *   manage_brands — add / edit / remove per-customer brand-name overrides.
 *                   Manager only.
 *   manage_all    — see + manage translation history across all owners.
 *                   NO default grant (ADR-031, 2026-09-25): it reaches other
 *                   tenants only for a caller who is also cross-tenant
 *                   (tenant::is_cross_tenant()); a deliberate grant to anyone
 *                   else stays inside their own tenant.
 *
 * @package local_sentientia_translate
 */

defined('MOODLE_INTERNAL') || die();

$capabilities = [

    'local/sentientia_translate:translate' => [
        'captype'      => 'write',
        'contextlevel' => CONTEXT_SYSTEM,
        'archetypes'   => [
            'manager' => CAP_ALLOW,
        ],
    ],

    'local/sentientia_translate:manage_brands' => [
        'captype'      => 'write',
        'contextlevel' => CONTEXT_SYSTEM,
        'archetypes'   => [
            'manager' => CAP_ALLOW,
        ],
    ],

    // No default grant (ADR-031, 2026-09-25). Tenant admins hold
    // manager-archetype roles at system context (UAT's "administrator", id 9),
    // and the manager default let every one of them open any tenant's
    // translation (source and translated course / compliance text) by its
    // sequential rowid, and save or discard it. Site admins pass anyway.
    // translate_engine now honours it only together with
    // tenant::is_cross_tenant(). Upgrade step 2026092500 revokes the existing
    // grants (archetype changes never revoke).
    'local/sentientia_translate:manage_all' => [
        'riskbitmask'  => RISK_PERSONAL,
        'captype'      => 'write',
        'contextlevel' => CONTEXT_SYSTEM,
        'archetypes'   => [],
    ],
];
