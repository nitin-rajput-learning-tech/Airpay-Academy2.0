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
    // P1.3 — Agentic Copilot. Both require :useagent (checked in the class).
    'local_sentientia_assistant_agent_turn' => [
        'classname'     => 'local_sentientia_assistant\external_agent',
        'methodname'    => 'agent_turn',
        'description'   => 'Agentic copilot: propose (and run read-only) a turn',
        'type'          => 'write',
        'ajax'          => true,
        'loginrequired' => true,
        'capabilities'  => 'local/sentientia_assistant:useagent',
    ],
    'local_sentientia_assistant_agent_confirm' => [
        'classname'     => 'local_sentientia_assistant\external_agent',
        'methodname'    => 'agent_confirm',
        'description'   => 'Agentic copilot: confirm + execute a proposed write action',
        'type'          => 'write',
        'ajax'          => true,
        'loginrequired' => true,
        'capabilities'  => 'local/sentientia_assistant:useagent',
    ],
];

$services = [
    'Airpay AI Assistant' => [
        'functions' => [
            'local_sentientia_assistant_ask',
            'local_sentientia_assistant_get_history',
            'local_sentientia_assistant_agent_turn',
            'local_sentientia_assistant_agent_confirm',
        ],
        'restrictedusers' => 0,
        'enabled' => 1,
        'shortname' => 'sentientia_assistant',
    ],
];
