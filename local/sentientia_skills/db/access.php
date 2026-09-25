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
    // Follow-up (2026-09-25): course mappings moved to :mapcourses (below),
    // and every catalogue write now also requires tenant::is_cross_tenant()
    // in code (skills_manager::require_catalogue_write()), so a :manage grant
    // to a tenant-admin role can no longer rewrite the shared catalogue.
    'local/sentientia_skills:manage' => [
        'riskbitmask'  => RISK_CONFIG | RISK_DATALOSS,
        'captype'      => 'write',
        'contextlevel' => CONTEXT_SYSTEM,
        'archetypes'   => [],
    ],
    // ADR-031 follow-up (2026-09-25): mapping skills onto courses is a
    // TENANT function, split out of :manage. Every mapping write is held to
    // the caller's own tenant's courses (skills_manager::require_course_write_scope()),
    // so it keeps the manager default :manage had before ADR-031, and tenant
    // admins keep mapping their own courses without :manage. The catalogue
    // itself stays :manage + cross-tenant only
    // (skills_manager::require_catalogue_write()). New capability: Moodle
    // grants the archetype default on install / upgrade.
    'local/sentientia_skills:mapcourses' => [
        'riskbitmask'  => RISK_CONFIG,
        'captype'      => 'write',
        'contextlevel' => CONTEXT_SYSTEM,
        'archetypes'   => [
            'manager' => CAP_ALLOW,
        ],
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
