<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

/**
 * Capabilities for the Sentientia AI Gateway.
 *
 *   viewledger — open the spend-ledger admin page (index.php): per-call
 *                rows + today/month aggregates. RISK_PERSONAL because
 *                rows are user-attributed. Manager archetype (the
 *                BizLMS `administrator` role shares it).
 *   manage     — reserved for future runtime controls (quota overrides,
 *                model routing). Manager archetype. Settings themselves
 *                stay behind $hassiteconfig as usual.
 *
 * @package local_sentientia_ai
 */

defined('MOODLE_INTERNAL') || die();

$capabilities = [

    'local/sentientia_ai:viewledger' => [
        'riskbitmask'  => RISK_PERSONAL,
        'captype'      => 'read',
        'contextlevel' => CONTEXT_SYSTEM,
        'archetypes'   => [
            'manager' => CAP_ALLOW,
        ],
    ],

    'local/sentientia_ai:manage' => [
        'riskbitmask'  => RISK_CONFIG,
        'captype'      => 'write',
        'contextlevel' => CONTEXT_SYSTEM,
        'archetypes'   => [
            'manager' => CAP_ALLOW,
        ],
    ],
];
