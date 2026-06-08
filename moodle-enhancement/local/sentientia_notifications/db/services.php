<?php
defined('MOODLE_INTERNAL') || die();

$functions = [
    'local_sentientia_notifications_list_rules' => [
        'classname'    => 'local_sentientia_notifications\external\list_rules',
        'description'  => 'List notification rules for shared datatable',
        'type'         => 'read',
        'ajax'         => true,
        'capabilities' => 'local/sentientia_notifications:manage',
    ],
    'local_sentientia_notifications_toggle_rule' => [
        'classname'    => 'local_sentientia_notifications\external\toggle_rule',
        'description'  => 'Enable or disable a notification rule',
        'type'         => 'write',
        'ajax'         => true,
        'capabilities' => 'local/sentientia_notifications:manage',
    ],
    'local_sentientia_notifications_delete_rule' => [
        'classname'    => 'local_sentientia_notifications\external\delete_rule',
        'description'  => 'Delete a notification rule',
        'type'         => 'write',
        'ajax'         => true,
        'capabilities' => 'local/sentientia_notifications:manage',
    ],

    // Phase C.2 (2026-05-08) — per-user prefs + preview / test-send.
    'local_sentientia_notifications_save_prefs' => [
        'classname'    => 'local_sentientia_notifications\external\save_prefs',
        'description'  => 'Save current user notification preferences',
        'type'         => 'write',
        'ajax'         => true,
        'loginrequired' => true,
    ],
    'local_sentientia_notifications_preview_rule' => [
        'classname'    => 'local_sentientia_notifications\external\preview_rule',
        'description'  => 'Render a rule message body without sending',
        'type'         => 'read',
        'ajax'         => true,
        'capabilities' => 'local/sentientia_notifications:manage',
    ],
    'local_sentientia_notifications_test_send' => [
        'classname'    => 'local_sentientia_notifications\external\test_send',
        'description'  => 'Send a one-off test notification using a rule template',
        'type'         => 'write',
        'ajax'         => true,
        'capabilities' => 'local/sentientia_notifications:manage',
    ],
];
