<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

defined('MOODLE_INTERNAL') || die();

/**
 * W1-5 (2026-05-15) — scheduled tasks for local_airpay_evaluation.
 *
 * `process_triggers` drains the trigger queue every 15 minutes.
 */
$tasks = [
    [
        'classname' => '\\local_airpay_evaluation\\task\\process_triggers',
        'blocking'  => 0,
        'minute'    => '*/15',
        'hour'      => '*',
        'day'       => '*',
        'month'     => '*',
        'dayofweek' => '*',
    ],
];
