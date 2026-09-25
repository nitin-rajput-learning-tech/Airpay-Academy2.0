<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

/**
 * Capabilities for the Sentientia AI Gateway.
 *
 *   viewledger — open the spend-ledger admin page (index.php): per-call
 *                rows + today/month aggregates for EVERY tenant and
 *                customer. RISK_PERSONAL because rows are user-attributed.
 *                A platform-operator view: NO default grant (ADR-031,
 *                2026-09-25) - tenant admins are manager-archetype, so the
 *                manager default gave every one of them every tenant's
 *                ledger - and the page also requires
 *                tenant::is_cross_tenant() (ledger::can_view()). Site admins
 *                pass by the admin bypass.
 *   manage     — reserved for future runtime controls (quota overrides,
 *                model routing). Those are global controls, so it has NO
 *                default grant either (ADR-031). Settings themselves stay
 *                behind $hassiteconfig as usual.
 *
 * @package local_sentientia_ai
 */

defined('MOODLE_INTERNAL') || die();

$capabilities = [

    // No default grant (ADR-031). Upgrade step 2026092500 revokes the
    // existing grants (archetype changes never revoke).
    'local/sentientia_ai:viewledger' => [
        'riskbitmask'  => RISK_PERSONAL,
        'captype'      => 'read',
        'contextlevel' => CONTEXT_SYSTEM,
        'archetypes'   => [],
    ],

    // No default grant (ADR-031): never checked yet, but its purpose is
    // global, so a manager default would leak the day code checks it.
    'local/sentientia_ai:manage' => [
        'riskbitmask'  => RISK_CONFIG,
        'captype'      => 'write',
        'contextlevel' => CONTEXT_SYSTEM,
        'archetypes'   => [],
    ],
];
