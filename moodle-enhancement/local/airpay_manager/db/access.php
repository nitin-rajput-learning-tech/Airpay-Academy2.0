<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
defined('MOODLE_INTERNAL') || die();

$capabilities = [
    'local/airpay_manager:view' => [
        'captype'      => 'read',
        'contextlevel' => CONTEXT_SYSTEM,
        'archetypes'   => ['manager' => CAP_ALLOW, 'editingteacher' => CAP_ALLOW],
    ],

    // Approve / reject enrolment requests from direct reports.
    'local/airpay_manager:approve' => [
        'riskbitmask'  => RISK_PERSONAL,
        'captype'      => 'write',
        'contextlevel' => CONTEXT_SYSTEM,
        'archetypes'   => ['manager' => CAP_ALLOW, 'editingteacher' => CAP_ALLOW],
    ],

    // Allocate (assign) courses to direct reports.
    'local/airpay_manager:allocate' => [
        'riskbitmask'  => RISK_PERSONAL,
        'captype'      => 'write',
        'contextlevel' => CONTEXT_SYSTEM,
        'archetypes'   => ['manager' => CAP_ALLOW, 'editingteacher' => CAP_ALLOW],
    ],
];
