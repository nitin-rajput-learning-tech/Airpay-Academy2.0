<?php
defined('MOODLE_INTERNAL') || die();
$capabilities = [
    'local/airpay_programs:view' => [
        'captype' => 'read', 'contextlevel' => CONTEXT_SYSTEM,
        'archetypes' => ['manager' => CAP_ALLOW, 'editingteacher' => CAP_ALLOW],
    ],
    'local/airpay_programs:manage' => [
        'riskbitmask' => RISK_CONFIG, 'captype' => 'write',
        'contextlevel' => CONTEXT_SYSTEM, 'archetypes' => ['manager' => CAP_ALLOW],
    ],
    'local/airpay_programs:enrol' => [
        'captype' => 'write', 'contextlevel' => CONTEXT_SYSTEM,
        'archetypes' => ['manager' => CAP_ALLOW],
    ],
    'local/airpay_programs:create' => [
        'captype' => 'write', 'contextlevel' => CONTEXT_SYSTEM,
        'archetypes' => ['manager' => CAP_ALLOW],
    ],
    'local/airpay_programs:update' => [
        'captype' => 'write', 'contextlevel' => CONTEXT_SYSTEM,
        'archetypes' => ['manager' => CAP_ALLOW],
    ],
    'local/airpay_programs:delete' => [
        'riskbitmask' => RISK_DATALOSS,
        'captype' => 'write', 'contextlevel' => CONTEXT_SYSTEM,
        'archetypes' => [],
    ],
];
