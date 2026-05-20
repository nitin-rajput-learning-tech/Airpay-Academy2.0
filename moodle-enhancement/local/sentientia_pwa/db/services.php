<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

defined('MOODLE_INTERNAL') || die();

$functions = [

    'local_sentientia_pwa_save_subscription' => [
        'classname'    => 'local_sentientia_pwa\\external\\save_subscription',
        'methodname'   => 'execute',
        'description'  => 'Save (upsert) the current user\'s push subscription. Called from the subscribe button JS after PushManager.subscribe() succeeds.',
        'type'         => 'write',
        'capabilities' => 'local/sentientia_pwa:subscribe',
        'ajax'         => true,
        'loginrequired' => true,
    ],

    'local_sentientia_pwa_delete_subscription' => [
        'classname'    => 'local_sentientia_pwa\\external\\delete_subscription',
        'methodname'   => 'execute',
        'description'  => 'Delete a push subscription. Called when the user clicks "Unsubscribe" or the browser revokes permission.',
        'type'         => 'write',
        'capabilities' => 'local/sentientia_pwa:subscribe',
        'ajax'         => true,
        'loginrequired' => true,
    ],

    'local_sentientia_pwa_list_my_subscriptions' => [
        'classname'    => 'local_sentientia_pwa\\external\\list_my_subscriptions',
        'methodname'   => 'execute',
        'description'  => 'List the current user\'s push subscriptions for the "my devices" UI. Returns endpoint host + last_seen — never the raw keys.',
        'type'         => 'read',
        'capabilities' => 'local/sentientia_pwa:subscribe',
        'ajax'         => true,
        'loginrequired' => true,
    ],

];
