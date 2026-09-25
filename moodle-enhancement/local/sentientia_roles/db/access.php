<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
defined('MOODLE_INTERNAL') || die();

$capabilities = [
    // View the role-management UI (read-only listing + per-role detail).
    // ADR-031 (2026-09-25): kept for tenant admins - role definitions are not
    // tenant data, and role_manager bounds the holder lists and counts to the
    // caller's own tenant. RISK_PERSONAL: the holder list shows names + email.
    'local/sentientia_roles:view' => [
        'riskbitmask'  => RISK_PERSONAL,
        'captype'      => 'read',
        'contextlevel' => CONTEXT_SYSTEM,
        'archetypes'   => ['manager' => CAP_ALLOW],
    ],

    // Change capability permissions on roles. Higher risk than view because
    // a wrong cap change can lock admins out or grant users excessive access.
    //
    // ADR-031 (2026-09-25): no default grant. A role definition is shared by
    // every tenant, so editing one is cross-tenant by nature; tenant admins
    // hold manager-archetype roles (UAT's "administrator", id 9), and with this
    // default any of them could give their own role any capability - including
    // the cross-tenant ones revoked elsewhere - or strip other tenants' roles.
    // role_manager::update_capability() now also requires tenant::is_cross_tenant().
    // Grant it deliberately to the platform (cross-tenant) role only. Upgrade
    // step 2026092500 revokes the existing grants.
    'local/sentientia_roles:manage' => [
        'riskbitmask'  => RISK_CONFIG | RISK_PERSONAL | RISK_MANAGETRUST,
        'captype'      => 'write',
        'contextlevel' => CONTEXT_SYSTEM,
        'archetypes'   => [],
    ],

    // Assign / unassign users to roles in system context. Separate from
    // :manage so an L&D admin can grant role memberships without being able
    // to redefine the capabilities those roles carry.
    //
    // ADR-031 (2026-09-25): no default grant. A system-context assignment is
    // platform-wide, and with the manager default every tenant admin could make
    // anyone in any tenant a manager or strip another tenant's admins. A holder
    // who is not cross-tenant is also bounded in role_manager: only a role they
    // hold themselves and may assign (role_allow_assign), only to somebody else
    // in their own tenant. Upgrade step 2026092500 revokes the existing grants.
    'local/sentientia_roles:assign' => [
        'riskbitmask'  => RISK_PERSONAL | RISK_MANAGETRUST,
        'captype'      => 'write',
        'contextlevel' => CONTEXT_SYSTEM,
        'archetypes'   => [],
    ],

    // Read the audit log of who changed which cap when. Read-only but
    // separate cap so we can grant compliance auditors visibility without
    // editing rights. ADR-031: kept; list_audit() shows a scoped caller only
    // the entries made by, or made to, someone in their own tenant.
    'local/sentientia_roles:audit' => [
        'riskbitmask'  => RISK_PERSONAL,
        'captype'      => 'read',
        'contextlevel' => CONTEXT_SYSTEM,
        'archetypes'   => ['manager' => CAP_ALLOW],
    ],

    // Export role + capability data + audit log to CSV for compliance review.
    // ADR-031: kept; the audit export goes through the same tenant-scoped
    // list_audit(), and role definitions are not tenant data.
    'local/sentientia_roles:export' => [
        'riskbitmask'  => RISK_PERSONAL,
        'captype'      => 'read',
        'contextlevel' => CONTEXT_SYSTEM,
        'archetypes'   => ['manager' => CAP_ALLOW],
    ],
];
