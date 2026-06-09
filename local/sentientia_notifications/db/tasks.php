<?php
defined('MOODLE_INTERNAL') || die();

$tasks = [
    [
        'classname' => '\local_sentientia_notifications\task\process_rules',
        'blocking'  => 0,
        'minute'    => '0',
        'hour'      => '*',    // Every hour.
        'day'       => '*',
        'month'     => '*',
        'dayofweek' => '*',
    ],
    [
        'classname' => '\local_sentientia_notifications\task\daily_digest',
        'blocking'  => 0,
        'minute'    => '30',
        'hour'      => '8',   // 8:30 AM daily.
        'day'       => '*',
        'month'     => '*',
        'dayofweek' => '1-5', // Weekdays only.
    ],
];
