<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
defined('MOODLE_INTERNAL') || die();

$capabilities = [
    // View the role-management UI (read-only listing + per-role detail).
    'local/sentientia_roles:view' => [
        'captype'      => 'read',
        'contextlevel' => CONTEXT_SYSTEM,
        'archetypes'   => ['manager' => CAP_ALLOW],
    ],

    // Change capability permissions on roles. Higher risk than view because
    // a wrong cap change can lock admins out or grant users excessive access.
    'local/sentientia_roles:manage' => [
        'riskbitmask'  => RISK_CONFIG | RISK_PERSONAL,
        'captype'      => 'write',
        'contextlevel' => CONTEXT_SYSTEM,
        'archetypes'   => ['manager' => CAP_ALLOW],
    ],

    // Assign / unassign users to roles in system context. Separate from
    // :manage so an L&D admin can grant role memberships without being able
    // to redefine the capabilities those roles carry.
    'local/sentientia_roles:assign' => [
        'riskbitmask'  => RISK_PERSONAL,
        'captype'      => 'write',
        'contextlevel' => CONTEXT_SYSTEM,
        'archetypes'   => ['manager' => CAP_ALLOW],
    ],

    // Read the audit log of who changed which cap when. Read-only but
    // separate cap so we can grant compliance auditors visibility without
    // editing rights.
    'local/sentientia_roles:audit' => [
        'captype'      => 'read',
        'contextlevel' => CONTEXT_SYSTEM,
        'archetypes'   => ['manager' => CAP_ALLOW],
    ],

    // Export role + capability data + audit log to CSV for compliance review.
    'local/sentientia_roles:export' => [
        'captype'      => 'read',
        'contextlevel' => CONTEXT_SYSTEM,
        'archetypes'   => ['manager' => CAP_ALLOW],
    ],
];
