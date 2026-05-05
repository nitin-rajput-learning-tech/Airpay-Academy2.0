<?php
defined('MOODLE_INTERNAL') || die();

$functions = [
    'local_airpay_notifications_list_rules' => [
        'classname'    => 'local_airpay_notifications\external\list_rules',
        'description'  => 'List notification rules for shared datatable',
        'type'         => 'read',
        'ajax'         => true,
        'capabilities' => 'local/airpay_notifications:manage',
    ],
    'local_airpay_notifications_toggle_rule' => [
        'classname'    => 'local_airpay_notifications\external\toggle_rule',
        'description'  => 'Enable or disable a notification rule',
        'type'         => 'write',
        'ajax'         => true,
        'capabilities' => 'local/airpay_notifications:manage',
    ],
    'local_airpay_notifications_delete_rule' => [
        'classname'    => 'local_airpay_notifications\external\delete_rule',
        'description'  => 'Delete a notification rule',
        'type'         => 'write',
        'ajax'         => true,
        'capabilities' => 'local/airpay_notifications:manage',
    ],
];
