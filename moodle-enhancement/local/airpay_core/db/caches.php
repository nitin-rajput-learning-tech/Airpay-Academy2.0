<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

defined('MOODLE_INTERNAL') || die();

/**
 * Cache definitions for local_airpay_core.
 *
 * `cron_health_banner` — deduplication for the site-notification banner
 *   raised by `publish_cron_health`. Same key for the same set of stuck
 *   tasks; TTL 24 hours so a persistently-stuck task notifies once per
 *   day rather than every 15 minutes.
 */
$definitions = [

    'cron_health_banner' => [
        'mode'        => cache_store::MODE_APPLICATION,
        'ttl'         => 86400,   // 24 hours
        'simplekeys'  => true,
        'staticacceleration' => true,
    ],

    // Phase A0 — feature flag registry cache.
    // The registry is built by walking every plugin's db/feature_flags.php
    // file. That's an O(plugins) filesystem scan we don't want to do on
    // every is_enabled() call. Cached for 60s — short enough that flag
    // toggles propagate within a minute even on multi-PHP-FPM-worker
    // setups, long enough to amortise the scan across thousands of calls.
    'feature_flags_registry' => [
        'mode'        => cache_store::MODE_APPLICATION,
        'ttl'         => 60,
        'simplekeys'  => true,
        'staticacceleration' => true,
    ],

];
