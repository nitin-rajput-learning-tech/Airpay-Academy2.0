<?php
defined('MOODLE_INTERNAL') || die();

$capabilities = [
    'local/airpay_skills:view' => [
        'captype'      => 'read',
        'contextlevel' => CONTEXT_SYSTEM,
        'archetypes'   => [
            'manager'        => CAP_ALLOW,
            'editingteacher' => CAP_ALLOW,
            'student'        => CAP_ALLOW,
        ],
    ],
    'local/airpay_skills:manage' => [
        'riskbitmask'  => RISK_CONFIG,
        'captype'      => 'write',
        'contextlevel' => CONTEXT_SYSTEM,
        'archetypes'   => ['manager' => CAP_ALLOW],
    ],
    // P1 #25 (2026-05-20) — learner self-attestation of skill level.
    // Closes audit item #26 from
    // parity-audit-2026-05-15/airpay_skills.md. Granted to learners
    // (student archetype) by default so the "I'm already an expert"
    // workflow works out of the box. Admins can revoke per-tenant.
    'local/airpay_skills:self_rate' => [
        'captype'      => 'write',
        'contextlevel' => CONTEXT_SYSTEM,
        'archetypes'   => [
            'user'           => CAP_ALLOW,  // any logged-in user
            'student'        => CAP_ALLOW,
            'editingteacher' => CAP_ALLOW,
            'manager'        => CAP_ALLOW,
        ],
    ],
];
