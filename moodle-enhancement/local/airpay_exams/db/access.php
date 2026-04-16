<?php
defined('MOODLE_INTERNAL') || die();

$capabilities = [
    'local/airpay_exams:manage' => [
        'riskbitmask'  => RISK_CONFIG,
        'captype'      => 'write',
        'contextlevel' => CONTEXT_SYSTEM,
        'archetypes'   => ['manager' => CAP_ALLOW],
    ],
    'local/airpay_exams:view' => [
        'captype'      => 'read',
        'contextlevel' => CONTEXT_SYSTEM,
        'archetypes'   => ['manager' => CAP_ALLOW, 'editingteacher' => CAP_ALLOW],
    ],
];
