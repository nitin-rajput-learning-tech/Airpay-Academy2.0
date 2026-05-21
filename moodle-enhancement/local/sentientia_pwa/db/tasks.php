<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

/**
 * Scheduled tasks for local_sentientia_pwa.
 *
 * @package local_sentientia_pwa
 */

defined('MOODLE_INTERNAL') || die();

$tasks = [
    [
        'classname' => 'local_sentientia_pwa\\task\\push_log_retention',
        'blocking'  => 0,
        // Run daily at 02:00 — well outside business hours so the purge
        // doesn't compete with peak DB activity.
        'minute'    => '0',
        'hour'      => '2',
        'day'       => '*',
        'month'     => '*',
        'dayofweek' => '*',
        'disabled'  => 0,
    ],
];
