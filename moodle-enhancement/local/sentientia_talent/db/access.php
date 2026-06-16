<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
defined('MOODLE_INTERNAL') || die();

// Talent mobility is HR-sensitive. Capabilities split read vs write and
// learner-facing vs HR-facing so that an employee can browse + register
// interest in opportunities WITHOUT ever seeing succession plans or other
// employees' nominations. Manager / HR caps are NOT granted to the
// 'student' archetype.
$capabilities = [

    // ── Learner-facing: browse the internal opportunity board + register
    //    interest in opportunities. Granted to all authenticated users. ──
    'local/sentientia_talent:viewopportunities' => [
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

    'local/sentientia_talent:registerinterest' => [
        'captype'      => 'write',
        'contextlevel' => CONTEXT_SYSTEM,
        'archetypes'   => [
            'user'    => CAP_ALLOW,
            'student' => CAP_ALLOW,
            'manager' => CAP_ALLOW,
        ],
    ],

    // ── Learner-facing: view my own career path(s) from my designation. ──
    'local/sentientia_talent:viewcareerpath' => [
        'captype'      => 'read',
        'contextlevel' => CONTEXT_SYSTEM,
        'archetypes'   => [
            'user'    => CAP_ALLOW,
            'student' => CAP_ALLOW,
            'manager' => CAP_ALLOW,
        ],
    ],

    // ── HR / manager: view succession plans (other people's data). High
    //    sensitivity — managers only, never students. ──
    'local/sentientia_talent:viewsuccession' => [
        'riskbitmask'  => RISK_PERSONAL,
        'captype'      => 'read',
        'contextlevel' => CONTEXT_SYSTEM,
        'archetypes'   => ['manager' => CAP_ALLOW],
    ],

    // ── HR / manager: create/edit/delete succession nominations. ──
    'local/sentientia_talent:managesuccession' => [
        'riskbitmask'  => RISK_PERSONAL | RISK_DATALOSS,
        'captype'      => 'write',
        'contextlevel' => CONTEXT_SYSTEM,
        'archetypes'   => ['manager' => CAP_ALLOW],
    ],

    // ── HR / manager: define + edit career paths (reference data). ──
    'local/sentientia_talent:managecareerpaths' => [
        'riskbitmask'  => RISK_CONFIG,
        'captype'      => 'write',
        'contextlevel' => CONTEXT_SYSTEM,
        'archetypes'   => ['manager' => CAP_ALLOW],
    ],

    // ── HR / manager: post + manage internal opportunities, see who
    //    expressed interest. ──
    'local/sentientia_talent:manageopportunities' => [
        'riskbitmask'  => RISK_PERSONAL,
        'captype'      => 'write',
        'contextlevel' => CONTEXT_SYSTEM,
        'archetypes'   => ['manager' => CAP_ALLOW],
    ],

    // ── Compliance: read the talent audit log. Separate from manage caps
    //    so a compliance auditor can review without edit rights. ──
    'local/sentientia_talent:audit' => [
        'riskbitmask'  => RISK_PERSONAL,
        'captype'      => 'read',
        'contextlevel' => CONTEXT_SYSTEM,
        'archetypes'   => ['manager' => CAP_ALLOW],
    ],
];
