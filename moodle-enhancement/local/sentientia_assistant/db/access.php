<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

/**
 * Capabilities for the Sentientia Agentic Copilot (P1.3).
 *
 * The agent loop is SECURITY-CRITICAL: the LLM only ever PROPOSES a tool
 * call; platform code authorises and executes it. Authorisation = a
 * capability check at CONTEXT_SYSTEM PLUS a tenant-scope check via
 * \local_sentientia_platform\tenant. Every tool maps to exactly one of
 * the capabilities below so the LLM cannot invent an action it has no
 * right to run.
 *
 *   useagent   — open the agentic copilot surface and drive the loop.
 *                Self-service learner action — granted to all learners
 *                (user archetype) so an employee can ask the copilot to
 *                act on their OWN learning. Every downstream tool still
 *                re-checks its own capability + tenant scope.
 *   enrol      — let the copilot enrol the CURRENT user into a
 *                self-enrollable course. Learner-safe self-service.
 *   bookilt    — let the copilot book the CURRENT user into an ILT /
 *                classroom session. Learner-safe self-service.
 *   recommend  — surface gap-closing content (read-only suggestion).
 *                Learner-safe.
 *   manageall  — inspect the full agent audit log across all users in
 *                the viewer's tenant. Manager only.
 *
 * NOTE: enrol/bookilt are deliberately scoped to "act on the current
 * user" — the copilot never enrols or books OTHER users. A manager-level
 * "act on behalf of a report" tool is intentionally out of scope for
 * P1.3 to keep the blast radius minimal.
 *
 * @package local_sentientia_assistant
 */

defined('MOODLE_INTERNAL') || die();

$capabilities = [

    'local/sentientia_assistant:useagent' => [
        'captype'      => 'read',
        'contextlevel' => CONTEXT_SYSTEM,
        'archetypes'   => [
            'user'           => CAP_ALLOW,
            'student'        => CAP_ALLOW,
            'editingteacher' => CAP_ALLOW,
            'manager'        => CAP_ALLOW,
        ],
    ],

    'local/sentientia_assistant:enrol' => [
        'captype'      => 'write',
        'contextlevel' => CONTEXT_SYSTEM,
        'archetypes'   => [
            'user'           => CAP_ALLOW,
            'student'        => CAP_ALLOW,
            'editingteacher' => CAP_ALLOW,
            'manager'        => CAP_ALLOW,
        ],
    ],

    'local/sentientia_assistant:bookilt' => [
        'captype'      => 'write',
        'contextlevel' => CONTEXT_SYSTEM,
        'archetypes'   => [
            'user'           => CAP_ALLOW,
            'student'        => CAP_ALLOW,
            'editingteacher' => CAP_ALLOW,
            'manager'        => CAP_ALLOW,
        ],
    ],

    'local/sentientia_assistant:recommend' => [
        'captype'      => 'read',
        'contextlevel' => CONTEXT_SYSTEM,
        'archetypes'   => [
            'user'           => CAP_ALLOW,
            'student'        => CAP_ALLOW,
            'editingteacher' => CAP_ALLOW,
            'manager'        => CAP_ALLOW,
        ],
    ],

    'local/sentientia_assistant:manageall' => [
        'captype'      => 'read',
        'contextlevel' => CONTEXT_SYSTEM,
        'archetypes'   => [
            'manager' => CAP_ALLOW,
        ],
    ],
];
