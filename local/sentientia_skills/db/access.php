<?php
defined('MOODLE_INTERNAL') || die();

$capabilities = [
    'local/sentientia_skills:view' => [
        'captype'      => 'read',
        'contextlevel' => CONTEXT_SYSTEM,
        'archetypes'   => [
            'manager'        => CAP_ALLOW,
            'editingteacher' => CAP_ALLOW,
            'student'        => CAP_ALLOW,
        ],
    ],
    // ADR-031 (2026-09-25): no archetype default. The skills catalogue
    // (categories, skills, levels, designation matrix, course mappings) has
    // no tenant column - it is one catalogue shared by every tenant - so
    // managing it is a platform act, and deleting a skill deletes every
    // tenant's learners' levels for it. Tenant admins hold manager-archetype
    // roles, so the old manager default let each of them rewrite every
    // tenant's framework. Site admins keep it; grant it deliberately (with
    // local/sentientia_platform:crosstenant) to a named platform role. The
    // existing grants are revoked by db/upgrade.php step 2026092500, because
    // changing archetypes never revokes.
    'local/sentientia_skills:manage' => [
        'riskbitmask'  => RISK_CONFIG | RISK_DATALOSS,
        'captype'      => 'write',
        'contextlevel' => CONTEXT_SYSTEM,
        'archetypes'   => [],
    ],
    // P1 #25 (2026-05-20) — learner self-attestation of skill level.
    // Closes audit item #26 from
    // parity-audit-2026-05-15/sentientia_skills.md. Granted to learners
    // (student archetype) by default so the "I'm already an expert"
    // workflow works out of the box. Admins can revoke per-tenant.
    'local/sentientia_skills:self_rate' => [
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
