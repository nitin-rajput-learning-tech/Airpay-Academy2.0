<?php
/**
 * Scheduled tasks for Airpay Integrations.
 */
defined('MOODLE_INTERNAL') || die();

$tasks = [
    [
        'classname' => 'local_airpay_integrations\task\hrms_sync',
        'blocking'  => 0,
        'minute'    => '0',
        'hour'      => '*/4',   // Every 4 hours
        'day'       => '*',
        'month'     => '*',
        'dayofweek' => '*',
        'disabled'  => 1,       // DISABLED by default — enable in Site Admin → Scheduled tasks
    ],
];
