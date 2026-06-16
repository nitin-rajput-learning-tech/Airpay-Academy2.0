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
 *   manage_all      — see + review every draft + template across all owners
 *                     and tenants. Manager only.
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
            'manager'        => CAP_ALLOW,
        ],
    ],

    'local/sentientia_authoring:review' => [
        'captype'      => 'write',
        'contextlevel' => CONTEXT_SYSTEM,
        'archetypes'   => [
            'editingteacher' => CAP_ALLOW,
            'manager'        => CAP_ALLOW,
        ],
    ],

    'local/sentientia_authoring:managetemplates' => [
        'captype'      => 'write',
        'contextlevel' => CONTEXT_SYSTEM,
        'archetypes'   => [
            'editingteacher' => CAP_ALLOW,
            'manager'        => CAP_ALLOW,
        ],
    ],

    'local/sentientia_authoring:manage_all' => [
        'captype'      => 'write',
        'contextlevel' => CONTEXT_SYSTEM,
        'archetypes'   => [
            'manager' => CAP_ALLOW,
        ],
    ],
];
