<?php
defined('MOODLE_INTERNAL') || die();

$tasks = [
    [
        'classname' => 'local_airpay_lifecycle\task\compliance_check',
        'blocking'  => 0,
        'minute'    => '0',
        'hour'      => '7',    // Run at 7 AM daily
        'day'       => '*',
        'month'     => '*',
        'dayofweek' => '1-5',  // Weekdays only
        'disabled'  => 0,      // Enabled by default
    ],
];
