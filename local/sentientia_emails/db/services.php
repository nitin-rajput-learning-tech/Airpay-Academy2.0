<?php
defined('MOODLE_INTERNAL') || die();

$functions = [
    'local_sentientia_emails_get_template' => [
        'classname'   => 'local_sentientia_emails\external\template_api',
        'methodname'  => 'get_template',
        'description' => 'Get template content (DB override or file default)',
        'type'        => 'read',
        'ajax'        => true,
        'capabilities' => 'local/sentientia_emails:manage_templates',
    ],
    'local_sentientia_emails_save_template' => [
        'classname'   => 'local_sentientia_emails\external\template_api',
        'methodname'  => 'save_template',
        'description' => 'Save a template override to DB',
        'type'        => 'write',
        'ajax'        => true,
        'capabilities' => 'local/sentientia_emails:manage_templates',
    ],
    'local_sentientia_emails_revert_template' => [
        'classname'   => 'local_sentientia_emails\external\template_api',
        'methodname'  => 'revert_template',
        'description' => 'Delete DB override, revert to file template',
        'type'        => 'write',
        'ajax'        => true,
        'capabilities' => 'local/sentientia_emails:manage_templates',
    ],
    'local_sentientia_emails_preview_template' => [
        'classname'   => 'local_sentientia_emails\external\template_api',
        'methodname'  => 'preview_template',
        'description' => 'Render a template preview with sample data',
        'type'        => 'read',
        'ajax'        => true,
        'capabilities' => 'local/sentientia_emails:preview',
    ],
    'local_sentientia_emails_toggle_rule' => [
        'classname'   => 'local_sentientia_emails\external\rule_api',
        'methodname'  => 'toggle_rule',
        'description' => 'Toggle a notification rule enabled/disabled',
        'type'        => 'write',
        'ajax'        => true,
        'capabilities' => 'local/sentientia_emails:manage_rules',
    ],
];
