<?php
// Cache definitions for local_airpay_analytics.
//
// Each definition wraps an expensive aggregate query. TTL is 5 min — long
// enough that repeated dashboard hits stay instant, short enough that data
// is at most slightly stale during a busy session. Bust manually after a
// large data load: `cache_helper::purge_by_definition('local_airpay_analytics', '<name>')`.
//
// @package    local_airpay_analytics
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
];
