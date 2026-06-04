<?php
defined('MOODLE_INTERNAL') || die();

$functions = [
    'local_sentientia_assistant_ask' => [
        'classname'   => 'local_sentientia_assistant\external',
        'methodname'  => 'ask',
        'description' => 'Ask the AI learning assistant a question',
        'type'        => 'write',
        'ajax'        => true,
        'loginrequired' => true,
    ],
    'local_sentientia_assistant_get_history' => [
        'classname'   => 'local_sentientia_assistant\external',
        'methodname'  => 'get_history',
        'description' => 'Get chat history',
        'type'        => 'read',
        'ajax'        => true,
        'loginrequired' => true,
    ],
];

$services = [
    'Airpay AI Assistant' => [
        'functions' => ['local_sentientia_assistant_ask', 'local_sentientia_assistant_get_history'],
        'restrictedusers' => 0,
        'enabled' => 1,
        'shortname' => 'sentientia_assistant',
    ],
];
