<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

/**
 * Capabilities for Sentientia LMS GenAI Authoring Studio (P0.3 MVP).
 *
 *   generate        — open the studio, paste source + call Claude (or mock)
 *                     to produce a full course draft. Cost-sensitive —
 *                     editingteacher+, manager. Never plain user.
 *   review          — open the review queue, approve/edit/reject cards +
 *                     questions, request voiceover. Same archetypes as
 *                     :generate; the generator is usually the reviewer for
 *                     MVP. Two-person review can be enforced later via a flag.
 *   managetemplates — create / edit / delete instructional-design templates.
 *                     editingteacher+, manager.
 *   manage_all      — see + review every draft + template across all owners.
 *                     NO default grant (ADR-031, 2026-09-25): it reaches
 *                     other tenants only for a caller who is also
 *                     cross-tenant (tenant::is_cross_tenant()); a deliberate
 *                     grant to anyone else stays inside their own tenant.
 *
 * @package local_sentientia_authoring
 */

defined('MOODLE_INTERNAL') || die();

$capabilities = [

    'local/sentientia_authoring:generate' => [
        'captype'      => 'write',
        'contextlevel' => CONTEXT_SYSTEM,
        'archetypes'   => [
            'editingteacher' => CAP_ALLOW,
            'teacher'        => CAP_ALLOW,
            'manager'        => CAP_ALLOW,
        ],
    ],

    'local/sentientia_authoring:review' => [
        'captype'      => 'write',
        'contextlevel' => CONTEXT_SYSTEM,
        'archetypes'   => [
            'editingteacher' => CAP_ALLOW,
            'teacher'        => CAP_ALLOW,
            'manager'        => CAP_ALLOW,
        ],
    ],

    'local/sentientia_authoring:managetemplates' => [
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
    // and the manager default let every one of them list, review, edit,
    // finalise and publish any tenant's course drafts, trigger voice-overs on
    // them, and rewrite or archive any tenant's private templates. Site admins
    // pass anyway. draft_manager / template_manager now honour it only
    // together with tenant::is_cross_tenant(). Upgrade step 2026092500 revokes
    // the existing grants (archetype changes never revoke).
    'local/sentientia_authoring:manage_all' => [
        'riskbitmask'  => RISK_PERSONAL | RISK_DATALOSS,
        'captype'      => 'write',
        'contextlevel' => CONTEXT_SYSTEM,
        'archetypes'   => [],
    ],
];
