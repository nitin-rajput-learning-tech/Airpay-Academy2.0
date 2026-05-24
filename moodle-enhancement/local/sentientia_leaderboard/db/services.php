<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
defined('MOODLE_INTERNAL') || die();

$functions = [

    'local_sentientia_leaderboard_get_board' => [
        'classname'    => 'local_sentientia_leaderboard\external\get_board',
        'methodname'   => 'execute',
        'description'  => 'Get the top-N entries for a leaderboard, tenant-scoped, opt-outs filtered.',
        'type'         => 'read',
        'capabilities' => 'local/sentientia_leaderboard:view',
        'ajax'         => true,
        'loginrequired' => true,
    ],

    'local_sentientia_leaderboard_list_boards' => [
        'classname'    => 'local_sentientia_leaderboard\external\list_boards',
        'methodname'   => 'execute',
        'description'  => 'List active boards visible to the caller (tenant-scoped unless :viewall).',
        'type'         => 'read',
        'capabilities' => 'local/sentientia_leaderboard:view',
        'ajax'         => true,
        'loginrequired' => true,
    ],

    'local_sentientia_leaderboard_set_optout' => [
        'classname'    => 'local_sentientia_leaderboard\external\set_optout',
        'methodname'   => 'execute',
        'description'  => 'Set the caller\'s opt-out preference. Privacy-mandated reversible toggle.',
        'type'         => 'write',
        'ajax'         => true,
        'loginrequired' => true,
    ],
];
