<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

/**
 * Capabilities for Sentientia LMS AI Quiz Generation (Phase G.0 MVP).
 *
 *   generate   — paste source text + call Claude to produce a draft.
 *                Teacher archetypes + manager. Cost-sensitive — never user.
 *                T-01 back-fill (2026-08-04): 'teacher' added because the
 *                BizLMS `trainer` role is archetype=teacher, not
 *                editingteacher — real trainers were locked out. The
 *                custom `sentientiaauthor` role (no archetype) is granted
 *                via db/upgrade.php.
 *   review     — open the review UI, approve/edit/reject questions,
 *                push approved questions to mod_quiz. Same archetypes
 *                as :generate; in practice the generator IS the reviewer
 *                for MVP. Two-person review can be enforced later via
 *                a per-customer flag.
 *   manage_all — see + review every draft across all owners. NO default
 *                grant (ADR-031, 2026-09-25): it unscopes only a caller who is
 *                also cross-tenant (tenant::is_cross_tenant()); a deliberate
 *                grant to anyone else stays inside their own tenant.
 *
 * @package local_sentientia_aiquiz
 */

defined('MOODLE_INTERNAL') || die();

$capabilities = [

    'local/sentientia_aiquiz:generate' => [
        'captype'      => 'write',
        'contextlevel' => CONTEXT_SYSTEM,
        'archetypes'   => [
            'editingteacher' => CAP_ALLOW,
            'teacher'        => CAP_ALLOW,
            'manager'        => CAP_ALLOW,
        ],
    ],

    'local/sentientia_aiquiz:review' => [
        'captype'      => 'write',
        'contextlevel' => CONTEXT_SYSTEM,
        'archetypes'   => [
            'editingteacher' => CAP_ALLOW,
            'teacher'        => CAP_ALLOW,
            'manager'        => CAP_ALLOW,
        ],
    ],

    // No default grant (ADR-031, 2026-09-25). Tenant admins hold
    // manager-archetype roles at system context (UAT's "administrator", id 9),
    // and the manager default let every one of them list, open, approve, edit
    // and finalise any tenant's AI quiz drafts (generated questions, answers,
    // explanations, owner names) by sequential id. Site admins pass anyway.
    // draft_manager now honours it only together with
    // tenant::is_cross_tenant(). Upgrade step 2026092500 revokes the existing
    // grants (archetype changes never revoke).
    'local/sentientia_aiquiz:manage_all' => [
        'riskbitmask'  => RISK_PERSONAL | RISK_DATALOSS,
        'captype'      => 'write',
        'contextlevel' => CONTEXT_SYSTEM,
        'archetypes'   => [],
    ],
];
