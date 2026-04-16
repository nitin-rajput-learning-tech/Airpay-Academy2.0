<?php
defined('MOODLE_INTERNAL') || die();
$capabilities = [
    'local/airpay_roles:manage' => [
        'riskbitmask' => RISK_CONFIG, 'captype' => 'write',
        'contextlevel' => CONTEXT_SYSTEM, 'archetypes' => [],
    ],
];
