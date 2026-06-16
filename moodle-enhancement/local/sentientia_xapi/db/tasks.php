<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

/**
 * Scheduled tasks for local_sentientia_xapi.
 *
 * @package    local_sentientia_xapi
 * @copyright  2026 Airpay Payment Services
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$tasks = [
    [
        'classname' => '\local_sentientia_xapi\task\purge_old_statements',
        'blocking'  => 0,
        'minute'    => '30',
        'hour'      => '2',
        'day'       => '*',
        'month'     => '*',
        'dayofweek' => '*',
    ],
];
