<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

/**
 * Capabilities for Sentientia LMS AI Course Recommendations (Phase H.0 MVP).
 *
 *   view       — see one's own recommendations on the dashboard.
 *                All authenticated users (the dashboard block enforces
 *                login + tenant scope).
 *   generate   — trigger a generation batch via the admin UI / cron.
 *                Manager only — cost-sensitive. A legitimate tenant-admin
 *                function, so the manager default stays; since ADR-031
 *                (2026-09-25) the target learner must be in the caller's
 *                tenant and the candidate courses come from the learner's.
 *   manage_all — view + manage recommendation history across all
 *                learners (e.g. for cost analytics + auditing). Declared but
 *                not yet checked anywhere. NO default grant (ADR-031): a
 *                manager default under a cross-learner name would hand
 *                every tenant admin every tenant's history the day code
 *                starts checking it.
 *
 * @package local_sentientia_recommendations
 */

defined('MOODLE_INTERNAL') || die();

$capabilities = [

    'local/sentientia_recommendations:view' => [
        'captype'      => 'read',
        'contextlevel' => CONTEXT_SYSTEM,
        'archetypes'   => [
            'user'           => CAP_ALLOW,
            'student'        => CAP_ALLOW,
            'teacher'        => CAP_ALLOW,
            'editingteacher' => CAP_ALLOW,
            'manager'        => CAP_ALLOW,
        ],
    ],

    'local/sentientia_recommendations:generate' => [
        'captype'      => 'write',
        'contextlevel' => CONTEXT_SYSTEM,
        'archetypes'   => [
            'manager' => CAP_ALLOW,
        ],
    ],

    // No default grant (ADR-031, 2026-09-25). Upgrade step 2026092500
    // revokes the existing grants (archetype changes never revoke).
    'local/sentientia_recommendations:manage_all' => [
        'riskbitmask'  => RISK_PERSONAL,
        'captype'      => 'write',
        'contextlevel' => CONTEXT_SYSTEM,
        'archetypes'   => [],
    ],
];
