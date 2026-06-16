<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

/**
 * Scheduled tasks for local_sentientia_api.
 *
 * @package local_sentientia_api
 */

defined('MOODLE_INTERNAL') || die();

$tasks = [
    [
        'classname' => 'local_sentientia_api\task\cleanup',
        'blocking'  => 0,
        'minute'    => '17',
        'hour'      => '3',
        'day'       => '*',
        'month'     => '*',
        'dayofweek' => '*',
    ],
];
