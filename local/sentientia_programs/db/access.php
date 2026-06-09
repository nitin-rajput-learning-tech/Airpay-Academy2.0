<?php
defined('MOODLE_INTERNAL') || die();
$capabilities = [
    'local/sentientia_programs:view' => [
        'captype' => 'read', 'contextlevel' => CONTEXT_SYSTEM,
        'archetypes' => ['manager' => CAP_ALLOW, 'editingteacher' => CAP_ALLOW],
    ],
    'local/sentientia_programs:manage' => [
        'riskbitmask' => RISK_CONFIG, 'captype' => 'write',
        'contextlevel' => CONTEXT_SYSTEM, 'archetypes' => ['manager' => CAP_ALLOW],
    ],
    'local/sentientia_programs:enrol' => [
        'captype' => 'write', 'contextlevel' => CONTEXT_SYSTEM,
        'archetypes' => ['manager' => CAP_ALLOW],
    ],
    'local/sentientia_programs:create' => [
        'captype' => 'write', 'contextlevel' => CONTEXT_SYSTEM,
        'archetypes' => ['manager' => CAP_ALLOW],
    ],
    'local/sentientia_programs:update' => [
        'captype' => 'write', 'contextlevel' => CONTEXT_SYSTEM,
        'archetypes' => ['manager' => CAP_ALLOW],
    ],
    'local/sentientia_programs:delete' => [
        'riskbitmask' => RISK_DATALOSS,
        'captype' => 'write', 'contextlevel' => CONTEXT_SYSTEM,
        'archetypes' => [],
    ],
];
