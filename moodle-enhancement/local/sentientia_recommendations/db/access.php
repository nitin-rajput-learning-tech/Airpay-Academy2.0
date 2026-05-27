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
 *                Manager only — cost-sensitive.
 *   manage_all — view + manage recommendation history across all
 *                learners (e.g. for cost analytics + auditing).
 *                Manager only.
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

    'local/sentientia_recommendations:manage_all' => [
        'captype'      => 'write',
        'contextlevel' => CONTEXT_SYSTEM,
        'archetypes'   => [
            'manager' => CAP_ALLOW,
        ],
    ],
];
