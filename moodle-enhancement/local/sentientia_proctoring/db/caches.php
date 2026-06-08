<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

/**
 * Cache definitions for local_sentientia_proctoring.
 *
 * @package local_sentientia_proctoring
 */

defined('MOODLE_INTERNAL') || die();

$definitions = [
    // Phase 8.1 B7 fix — per-user-per-hour rate limit on identity submits.
    // Key: "u:{userid}:h:{hour_bucket}"  → counter int.
    // TTL ~3600s (one hour). Small footprint; high read+write rate is
    // confined to identity-verification kickoff, not every page.
    'identity_rate' => [
        'mode'       => cache_store::MODE_APPLICATION,
        'ttl'        => 3600,
        'simplekeys' => true,
        'staticacceleration' => true,
        'staticaccelerationsize' => 50,
    ],
];
