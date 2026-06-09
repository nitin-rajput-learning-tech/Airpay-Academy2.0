<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

defined('MOODLE_INTERNAL') || die();

/**
 * Observer registration — Phase L.1 (2026-05-24).
 *
 * Routes the in-plugin `rankings_updated` event to the L.1 notification
 * observer. The observer is gated on the
 * `sentientia.leaderboards.notifications.enabled` feature flag (default
 * OFF), so dropping the file in place is a pure no-op for every existing
 * tenant until the flag is flipped.
 *
 * `internal => true` keeps the dispatch synchronous within the recompute
 * transaction's commit boundary — the throttle log row writes immediately
 * after `message_send()` returns, which matches the test contract
 * (`test_throttle_blocks_duplicate_within_24h`).
 */
$observers = [
    [
        'eventname' => '\\local_sentientia_leaderboard\\event\\rankings_updated',
        'callback'  => '\\local_sentientia_leaderboard\\observer::on_rankings_updated',
        'internal'  => true,
        'priority'  => 9000,
    ],
];
