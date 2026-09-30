<?php
defined('MOODLE_INTERNAL') || die();

$capabilities = [
    'local/sentientia_classroom:manage' => [
        'riskbitmask'  => RISK_CONFIG,
        'captype'      => 'write',
        'contextlevel' => CONTEXT_SYSTEM,
        'archetypes'   => ['manager' => CAP_ALLOW],
    ],
    // The Sentientia/BizLMS `trainer` role is archetype `teacher`, not `editingteacher`: the
    // people who run a classroom session must be able to open it and take attendance (T-01
    // persona-caps class). Least privilege: only :view and :attendance, none of :manage,
    // :create, :update, :delete. ADR-031 still limits them to classrooms in their own tenant.
    // Existing roles get the grant from upgrade step 2026093001 (db/upgradelib.php).
    'local/sentientia_classroom:view' => [
        'captype'      => 'read',
        'contextlevel' => CONTEXT_SYSTEM,
        'archetypes'   => ['manager' => CAP_ALLOW, 'editingteacher' => CAP_ALLOW, 'teacher' => CAP_ALLOW],
    ],
    'local/sentientia_classroom:attendance' => [
        'captype'      => 'write',
        'contextlevel' => CONTEXT_SYSTEM,
        'archetypes'   => ['manager' => CAP_ALLOW, 'editingteacher' => CAP_ALLOW, 'teacher' => CAP_ALLOW],
    ],
    'local/sentientia_classroom:create' => [
        'captype'      => 'write',
        'contextlevel' => CONTEXT_SYSTEM,
        'archetypes'   => ['manager' => CAP_ALLOW],
    ],
    'local/sentientia_classroom:update' => [
        'captype'      => 'write',
        'contextlevel' => CONTEXT_SYSTEM,
        'archetypes'   => ['manager' => CAP_ALLOW],
    ],
    'local/sentientia_classroom:delete' => [
        'riskbitmask'  => RISK_DATALOSS,
        'captype'      => 'write',
        'contextlevel' => CONTEXT_SYSTEM,
        'archetypes'   => [],  // Siteadmin only.
    ],
];
