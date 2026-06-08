<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

defined('MOODLE_INTERNAL') || die();

/**
 * W1-5 (2026-05-15) — scheduled tasks for local_sentientia_evaluation.
 *
 * `process_triggers` drains the trigger queue every 15 minutes.
 */
$tasks = [
    [
        'classname' => '\\local_sentientia_evaluation\\task\\process_triggers',
        'blocking'  => 0,
        'minute'    => '*/15',
        'hour'      => '*',
        'day'       => '*',
        'month'     => '*',
        'dayofweek' => '*',
    ],
    // P1 #42 (2026-05-20) — daily sweep that flips assigned rows
    // with `due_at > 0 AND now > due_at` to 'expired'. Lets the
    // non-respondents page distinguish "actually still waiting" from
    // "the deadline passed, this is dead weight in the report".
    [
        'classname' => '\\local_sentientia_evaluation\\task\\expire_assignments',
        'blocking'  => 0,
        'minute'    => '0',
        'hour'      => '1',
        'day'       => '*',
        'month'     => '*',
        'dayofweek' => '*',
    ],
];
