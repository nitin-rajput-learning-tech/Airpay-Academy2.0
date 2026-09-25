<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

/**
 * Capabilities for Sentientia LMS Skills Intelligence (Gap P0.1 MVP).
 *
 *   extract    — paste source text + call Claude to propose candidate
 *                skills. Editingteacher+, manager. Cost-sensitive — never
 *                a plain user.
 *   review     — open the candidate review UI, approve/edit/reject
 *                candidates, and promote approved candidates into the
 *                canonical taxonomy. This is the mandatory human gate.
 *   manage_taxonomy — edit/retire taxonomy nodes + author skill->impact
 *                mappings. Manager-level curation of the canonical model.
 *   viewgaps   — view skills-gap feeds. Managers + reporting roles.
 *   manage_all — see + review every job/candidate across all owners and
 *                tenants. No default (ADR-031): only a cross-tenant caller
 *                is unscoped by it.
 *
 * @package local_sentientia_skillsai
 */

defined('MOODLE_INTERNAL') || die();

$capabilities = [

    'local/sentientia_skillsai:extract' => [
        'captype'      => 'write',
        'contextlevel' => CONTEXT_SYSTEM,
        'archetypes'   => [
            'editingteacher' => CAP_ALLOW,
            'teacher'        => CAP_ALLOW,
            'manager'        => CAP_ALLOW,
        ],
    ],

    'local/sentientia_skillsai:review' => [
        'captype'      => 'write',
        'contextlevel' => CONTEXT_SYSTEM,
        'archetypes'   => [
            'editingteacher' => CAP_ALLOW,
            'teacher'        => CAP_ALLOW,
            'manager'        => CAP_ALLOW,
        ],
    ],

    'local/sentientia_skillsai:manage_taxonomy' => [
        'captype'      => 'write',
        'contextlevel' => CONTEXT_SYSTEM,
        'archetypes'   => [
            'manager' => CAP_ALLOW,
        ],
    ],

    'local/sentientia_skillsai:viewgaps' => [
        'captype'      => 'read',
        'contextlevel' => CONTEXT_SYSTEM,
        'archetypes'   => [
            'manager' => CAP_ALLOW,
        ],
    ],

    // ADR-031 (2026-09-25): no archetype default. :manage_all existed only
    // to reach across owners AND tenants, and tenant admins hold
    // manager-archetype roles, so the old manager default gave every one of
    // them every tenant's extraction jobs, review queue, taxonomy and
    // per-user gap feeds. It now unscopes only a cross-tenant caller
    // (taxonomy_manager::can_manage_all()); site admins keep it. Existing
    // grants are revoked by db/upgrade.php step 2026092500, because
    // changing archetypes never revokes.
    'local/sentientia_skillsai:manage_all' => [
        'riskbitmask'  => RISK_PERSONAL | RISK_DATALOSS,
        'captype'      => 'write',
        'contextlevel' => CONTEXT_SYSTEM,
        'archetypes'   => [],
    ],
];
