<?php
defined('MOODLE_INTERNAL') || die();

$functions = [
    'local_airpay_org_delete_org' => [
        'classname'    => 'local_airpay_org\external\delete_org',
        'description'  => 'Delete an organisation node (refuses if tenant, has descendants, or has users)',
        'type'         => 'write',
        'ajax'         => true,
        'capabilities' => 'local/airpay_org:manage',
    ],
    'local_airpay_org_toggle_visibility' => [
        'classname'    => 'local_airpay_org\external\toggle_visibility',
        'description'  => 'Toggle an organisation between active and hidden',
        'type'         => 'write',
        'ajax'         => true,
        'capabilities' => 'local/airpay_org:manage',
    ],
];
