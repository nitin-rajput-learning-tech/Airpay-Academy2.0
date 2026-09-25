<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
defined('MOODLE_INTERNAL') || die();

$capabilities = [
    // Browse active challenges + view leaderboards. Default: all authenticated
    // users (gamification is opt-in by joining, not by viewing).
    'local/sentientia_challenge:view' => [
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

    // Join + leave a challenge. Self-service for any logged-in user.
    'local/sentientia_challenge:participate' => [
        'captype'      => 'write',
        'contextlevel' => CONTEXT_SYSTEM,
        'archetypes'   => [
            'user'           => CAP_ALLOW,
            'student'        => CAP_ALLOW,
            'teacher'        => CAP_ALLOW,
            'editingteacher' => CAP_ALLOW,
            'manager'        => CAP_ALLOW,
        ],
    ],

    // Define + edit + delete challenges. Manager-only.
    'local/sentientia_challenge:manage' => [
        'riskbitmask'  => RISK_CONFIG | RISK_SPAM,
        'captype'      => 'write',
        'contextlevel' => CONTEXT_SYSTEM,
        'archetypes'   => ['manager' => CAP_ALLOW],
    ],

    // View leaderboards across tenants (compliance / HR analytics).
    // Without this cap, the leaderboard auto-scopes to the caller's tenant.
    //
    // No default grant (2026-09-25). Tenant admins hold manager-archetype roles
    // (UAT's "administrator", id 9, is one), so a manager default gave every
    // tenant admin every tenant's leaderboard, names included - the defect
    // local_sentientia_analytics:viewallorgs had until 2026-09-24. Site admins
    // are unscoped anyway; grant this deliberately to a cross-tenant role if one
    // is ever needed. Upgrade step 2026092500 revokes the existing grants.
    //
    // ADR-031 (2026-09-25): holding this no longer unscopes anything on its
    // own. Only tenant::is_cross_tenant() (site admin or
    // local/sentientia_platform:crosstenant) does; the capability is kept
    // declared so existing role definitions and exports stay valid.
    'local/sentientia_challenge:viewall' => [
        'riskbitmask'  => RISK_PERSONAL,
        'captype'      => 'read',
        'contextlevel' => CONTEXT_SYSTEM,
        'archetypes'   => [],
    ],
];
