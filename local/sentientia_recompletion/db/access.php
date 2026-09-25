<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

defined('MOODLE_INTERNAL') || die();

// ADR-031 (2026-09-25): :view and :manage are tenant-scoped in code
// (classes/rule_access.php) - they say WHAT, never WHERE. :reset has no
// consumer yet; its install-time grant is revoked by upgrade step 2026092500.
$capabilities = [
    'local/sentientia_recompletion:view' => [
        'captype'      => 'read',
        'contextlevel' => CONTEXT_SYSTEM,
        'archetypes'   => ['manager' => CAP_ALLOW],
    ],
    'local/sentientia_recompletion:manage' => [
        'riskbitmask'  => RISK_CONFIG | RISK_DATALOSS,
        'captype'      => 'write',
        'contextlevel' => CONTEXT_SYSTEM,
        'archetypes'   => [],
    ],
    'local/sentientia_recompletion:reset' => [
        'riskbitmask'  => RISK_DATALOSS,
        'captype'      => 'write',
        'contextlevel' => CONTEXT_SYSTEM,
        'archetypes'   => [],
    ],
];
