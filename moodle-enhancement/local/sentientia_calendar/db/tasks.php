<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

/**
 * Scheduled tasks for local_sentientia_calendar.
 *
 * @package local_sentientia_calendar
 */

defined('MOODLE_INTERNAL') || die();

$tasks = [
    [
        // Daily cleanup of revoked tokens older than 90 days.
        'classname' => 'local_sentientia_calendar\\task\\purge_old_tokens',
        'blocking'  => 0,
        'minute'    => '17',
        'hour'      => '3',
        'day'       => '*',
        'dayofweek' => '*',
        'month'     => '*',
    ],
];
