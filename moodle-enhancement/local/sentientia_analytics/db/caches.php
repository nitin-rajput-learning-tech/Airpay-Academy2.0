<?php
// Cache definitions for local_sentientia_analytics.
//
// Each definition wraps an expensive aggregate query. TTL is 5 min — long
// enough that repeated dashboard hits stay instant, short enough that data
// is at most slightly stale during a busy session. Bust manually after a
// large data load: `cache_helper::purge_by_definition('local_sentientia_analytics', '<name>')`.
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
    // TTL = 600s (10 min); also pre-warmed hourly by the scheduled task
    // so dashboard hits always serve warm data.
    // simpledata=false: each entry is a deeply nested array.
    'predictive_atrisk' => [
        'mode'       => cache_store::MODE_APPLICATION,
        'ttl'        => 600,
        'simplekeys' => true,
        'simpledata' => false,
    ],
    'predictive_skillgap' => [
        'mode'       => cache_store::MODE_APPLICATION,
        'ttl'        => 600,
        'simplekeys' => true,
        'simpledata' => false,
    ],

    // ── P1.2 Training ROI cache ──────────────────────────────────────
    // ROI involves multiple aggregate queries; 10 min TTL; pre-warmed
    // by the same hourly scheduled task.
    'roi' => [
        'mode'       => cache_store::MODE_APPLICATION,
        'ttl'        => 600,
        'simplekeys' => true,
        'simpledata' => false,
    ],
];
