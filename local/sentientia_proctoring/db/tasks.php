<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

defined('MOODLE_INTERNAL') || die();

$tasks = [
    [
        'classname' => 'local_sentientia_proctoring\task\purge_old_recordings',
        'blocking'  => 0,
        'minute'    => '23',
        'hour'      => '2',  // 02:23 daily
        'day'       => '*',
        'dayofweek' => '*',
        'month'     => '*',
    ],
];
