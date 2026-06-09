<?php
defined('MOODLE_INTERNAL') || die();
$capabilities = [
    'block/sentientia_trainer:addinstance' => [
        'captype' => 'write', 'contextlevel' => CONTEXT_BLOCK,
        'archetypes' => ['editingteacher' => CAP_ALLOW, 'manager' => CAP_ALLOW],
    ],
    'block/sentientia_trainer:myaddinstance' => [
        'captype' => 'write', 'contextlevel' => CONTEXT_SYSTEM,
        'archetypes' => ['editingteacher' => CAP_ALLOW, 'manager' => CAP_ALLOW],
    ],
    'block/sentientia_trainer:viewtrainerslist' => [
        'captype' => 'read', 'contextlevel' => CONTEXT_SYSTEM,
        'archetypes' => ['editingteacher' => CAP_ALLOW, 'manager' => CAP_ALLOW],
    ],
];
