<?php
// Cache definitions for local_sentientia_analytics.
//
// Each definition wraps an expensive aggregate query. Bust manually after a
// large data load: `cache_helper::purge_by_definition('local_sentientia_analytics', '<name>')`.
//
// Two lifetimes (post-demo plan 2.4, 2026-11-19):
//  - kpis, funnel, course_effectiveness, compliance_heatmap: 300 s. Nothing
//    pre-warms them. The first three read live tables. The heatmap reads the
//    compliance snapshot, which its own task rebuilds row by row from the top
//    of each hour for several minutes; a 5-minute TTL picks up the finished
//    snapshot soon after, where an hour-long entry could hold a half-rebuilt
//    one until the next hour.
//  - predictive_atrisk, predictive_skillgap, roi: 3900 s. The hourly task
//    refresh_predictive_cache (db/tasks.php, 5 past each hour) purges and
//    rebuilds them. With the old 10-minute TTL the rebuilt entries expired
//    at 15 past and the first viewer of the remaining 50 minutes paid for a
//    cold computation, so the pre-warm was wasted.
//
// @package    local_sentientia_analytics
// @copyright  2026 Airpay Payment Services
// @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

defined('MOODLE_INTERNAL') || die();

$definitions = [
    'kpis' => [
        'mode'               => cache_store::MODE_APPLICATION,
        'ttl'                => 300,
        'simplekeys'         => true,
        'simpledata'         => true,
        'staticacceleration' => true,
    ],
    'funnel' => [
        'mode'               => cache_store::MODE_APPLICATION,
        'ttl'                => 300,
        'simplekeys'         => true,
        'simpledata'         => true,
        'staticacceleration' => true,
    ],
    'compliance_heatmap' => [
        'mode'               => cache_store::MODE_APPLICATION,
        'ttl'                => 300,
        'simplekeys'         => true,
        'simpledata'         => true,
        'staticacceleration' => true,
    ],
    'course_effectiveness' => [
        'mode'               => cache_store::MODE_APPLICATION,
        'ttl'                => 300,
        'simplekeys'         => true,
        'simpledata'         => true,
        'staticacceleration' => true,
    ],

    // ── P1.2 Predictive Analytics caches ────────────────────────────
    // at-risk + skill-gap are expensive (3 queries × 3,500+ users).
    // TTL = one hour + 5 min (3900 s): the hourly scheduled task purges and
    // rebuilds them, so a dashboard hit serves warm data all hour. The 5
    // minutes of slack keep an entry alive when cron starts the next run a
    // little late; if cron stops, an entry is at most 65 minutes old before
    // the next viewer recomputes it. Was 600 s until 2026-11-19.
    // simpledata=false: each entry is a deeply nested array.
    'predictive_atrisk' => [
        'mode'       => cache_store::MODE_APPLICATION,
        'ttl'        => 3900,
        'simplekeys' => true,
        'simpledata' => false,
    ],
    'predictive_skillgap' => [
        'mode'       => cache_store::MODE_APPLICATION,
        'ttl'        => 3900,
        'simplekeys' => true,
        'simpledata' => false,
    ],

    // ── P1.2 Training ROI cache ──────────────────────────────────────
    // ROI involves multiple aggregate queries; pre-warmed by the same hourly
    // scheduled task, so the same 3900 s TTL (was 600 s until 2026-11-19).
    // A changed ROI setting still shows at once: the settings are part of
    // the key (roi_calculator::compute()).
    'roi' => [
        'mode'       => cache_store::MODE_APPLICATION,
        'ttl'        => 3900,
        'simplekeys' => true,
        'simpledata' => false,
    ],
];
