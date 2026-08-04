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
 *   viewall      — view boards across tenants (HR analytics). Risk: PERSONAL
 *                  because it bypasses the opt-out filter.
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

    'local/sentientia_leaderboard:promoteboard' => [
        'riskbitmask'  => RISK_CONFIG | RISK_PERSONAL,
        'captype'      => 'write',
        'contextlevel' => CONTEXT_SYSTEM,
        'archetypes'   => [
            'manager' => CAP_ALLOW,
        ],
    ],

    'local/sentientia_leaderboard:viewall' => [
        'riskbitmask'  => RISK_PERSONAL,
        'captype'      => 'read',
        'contextlevel' => CONTEXT_SYSTEM,
        'archetypes'   => [
            'manager' => CAP_ALLOW,
        ],
    ],
];
