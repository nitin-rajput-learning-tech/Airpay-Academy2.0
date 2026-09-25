<?php
defined('MOODLE_INTERNAL') || die();

$capabilities = [
    'local/sentientia_emails:preview' => [
        'captype'      => 'read',
        'contextlevel' => CONTEXT_SYSTEM,
        'archetypes'   => [
            'manager' => CAP_ALLOW,
        ],
    ],
    'local/sentientia_emails:manage' => [
        'captype'      => 'write',
        'contextlevel' => CONTEXT_SYSTEM,
        'archetypes'   => [
            'manager' => CAP_ALLOW,
        ],
    ],
    // ADR-031 follow-up (sweep hit 23): the holder writes the HTML body that
    // is emailed to learners - a link/markup injection and spam vector - so
    // the role UI must flag it. The tenant half (own tenant only) is enforced
    // in tenant_scope::require_can_write_tenant().
    'local/sentientia_emails:manage_templates' => [
        'riskbitmask'  => RISK_XSS | RISK_SPAM,
        'captype'      => 'write',
        'contextlevel' => CONTEXT_SYSTEM,
        'archetypes'   => [
            'manager' => CAP_ALLOW,
        ],
    ],
    'local/sentientia_emails:manage_rules' => [
        'captype'      => 'write',
        'contextlevel' => CONTEXT_SYSTEM,
        'archetypes'   => [
            'manager' => CAP_ALLOW,
        ],
    ],
    'local/sentientia_emails:view_logs' => [
        'captype'      => 'read',
        'contextlevel' => CONTEXT_SYSTEM,
        'archetypes'   => [
            'manager' => CAP_ALLOW,
        ],
    ],
    'local/sentientia_emails:manage_settings' => [
        'captype'      => 'write',
        'contextlevel' => CONTEXT_SYSTEM,
        'archetypes'   => [],
    ],
];
