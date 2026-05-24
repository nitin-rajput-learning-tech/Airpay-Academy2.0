<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
defined('MOODLE_INTERNAL') || die();

$tasks = [
    [
        // Recompute every board whose last_recomputed is older than its
        // configured recompute_seconds. Cron tick is */2 (every 2 min)
        // so a board with recompute_seconds=60 still gets recomputed at
        // the next available tick (~2 min worst-case latency).
        'classname' => '\local_sentientia_leaderboard\task\recompute_due_boards',
        'blocking'  => 0,
        'minute'    => '*/2',
        'hour'      => '*',
        'day'       => '*',
        'month'     => '*',
        'dayofweek' => '*',
        'disabled'  => 0,
    ],
    [
        // Purge old events daily at 03:00 to keep the events table from
        // unbounded growth.
        'classname' => '\local_sentientia_leaderboard\task\purge_old_events',
        'blocking'  => 0,
        'minute'    => '0',
        'hour'      => '3',
        'day'       => '*',
        'month'     => '*',
        'dayofweek' => '*',
        'disabled'  => 0,
    ],
];
