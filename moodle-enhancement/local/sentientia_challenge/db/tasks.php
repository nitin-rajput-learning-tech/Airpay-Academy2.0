<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
defined('MOODLE_INTERNAL') || die();

$tasks = [
    [
        // Recompute the leaderboard snapshot for every active challenge.
        // Catches anything the event observer missed (events disabled,
        // events lost during a restore, completions backfilled by cron).
        'classname' => '\local_sentientia_challenge\task\recompute_leaderboard',
        'blocking'  => 0,
        'minute'    => '*/15',
        'hour'      => '*',
        'day'       => '*',
        'month'     => '*',
        'dayofweek' => '*',
        'disabled'  => 0,
    ],
];
