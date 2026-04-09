<?php
defined('MOODLE_INTERNAL') || die();

$tasks = [
    [
        'classname' => '\local_airpay_notifications\task\process_rules',
        'blocking'  => 0,
        'minute'    => '0',
        'hour'      => '*',    // Every hour.
        'day'       => '*',
        'month'     => '*',
        'dayofweek' => '*',
    ],
];
