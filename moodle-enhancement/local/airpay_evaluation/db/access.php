<?php
defined('MOODLE_INTERNAL') || die();
$capabilities = [
    'local/airpay_evaluation:manage' => [
        'captype' => 'write', 'contextlevel' => CONTEXT_SYSTEM,
        'archetypes' => ['manager' => CAP_ALLOW],
    ],
    'local/airpay_evaluation:respond' => [
        'captype' => 'write', 'contextlevel' => CONTEXT_SYSTEM,
        'archetypes' => ['student' => CAP_ALLOW, 'manager' => CAP_ALLOW],
    ],
];
