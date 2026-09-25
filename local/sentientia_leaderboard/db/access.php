<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

defined('MOODLE_INTERNAL') || die();

/**
 * Capabilities for local_sentientia_leaderboard (Phase L.0).
 *
 *   view         — view any leaderboard inside the caller's tenant
 *   manageboard  — create / edit / delete boards inside the caller's tenant
 *   promoteboard — create a customer-wide board (tenantid=0). Risk: PERSONAL
 *                  because it makes a learner's ranking visible across tenants.
 *                  No default grant; creating a tenantid=0 board requires a
 *                  cross-tenant actor (ADR-031).
 *   viewall      — view boards across tenants (HR analytics). No default
 *                  grant, and it no longer unscopes or bypasses opt-outs on
 *                  its own (ADR-031, 2026-09-25).
 *
 * @package local_sentientia_leaderboard
 */

$capabilities = [

    'local/sentientia_leaderboard:view' => [
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

    'local/sentientia_leaderboard:manageboard' => [
        'riskbitmask'  => RISK_CONFIG,
        'captype'      => 'write',
        'contextlevel' => CONTEXT_SYSTEM,
        'archetypes'   => [
            'editingteacher' => CAP_ALLOW,
            // T-01 back-fill (2026-08-04): the BizLMS `trainer` role is
            // archetype=teacher — trainers run cohort boards (:view already
            // includes teacher; manageboard missed it). Existing installs
            // are back-filled in db/upgrade.php.
            'teacher'        => CAP_ALLOW,
            'manager'        => CAP_ALLOW,
        ],
    ],

    // ADR-031 (2026-09-25): :promoteboard and :viewall have NO default grant.
    // Both exist only to cross tenants - a customer-wide board ranks and shows
    // learners from every tenant, and :viewall listed and opened every
    // tenant's boards (and bypassed opt-outs). Tenant admins hold
    // manager-archetype roles at system context (UAT's "administrator", id 9),
    // so the manager default handed every tenant admin every tenant's ranked
    // learner names. Neither capability unscopes anything on its own any more:
    // board_manager::list_for_viewer() / viewer_can_see() / create() use
    // tenant::is_cross_tenant(), and the opt-out bypass is site-admin only.
    // Both stay declared so existing role definitions remain valid. Upgrade
    // step 2026092500 revokes the existing grants (archetype changes never
    // revoke).
    'local/sentientia_leaderboard:promoteboard' => [
        'riskbitmask'  => RISK_CONFIG | RISK_PERSONAL,
        'captype'      => 'write',
        'contextlevel' => CONTEXT_SYSTEM,
        'archetypes'   => [],
    ],

    'local/sentientia_leaderboard:viewall' => [
        'riskbitmask'  => RISK_PERSONAL,
        'captype'      => 'read',
        'contextlevel' => CONTEXT_SYSTEM,
        'archetypes'   => [],
    ],
];
