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
];
