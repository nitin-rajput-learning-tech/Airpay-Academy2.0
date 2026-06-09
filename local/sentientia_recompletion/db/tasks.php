<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

defined('MOODLE_INTERNAL') || die();

$tasks = [
    [
        'classname' => 'local_sentientia_recompletion\task\run_rules',
        'blocking'  => 0,
        'minute'    => '15',
        'hour'      => '3',  // 03:15 daily
        'day'       => '*',
        'dayofweek' => '*',
        'month'     => '*',
    ],
];
