<?php
// Cache definitions for local_airpay_catalog.
//
// get_in_progress is the heaviest method (4.2s cold for a user with 3
// enrolments — calls get_course() + get_course_progress_percentage() per
// course). Caching for 5 minutes keeps the catalog snappy on every visit
// while progress can refresh on the next minute boundary.
//
// Bust manually after enrolment changes:
//   cache_helper::purge_by_definition('local_airpay_catalog', 'in_progress')

defined('MOODLE_INTERNAL') || die();

$definitions = [
    'in_progress' => [
        'mode'               => cache_store::MODE_APPLICATION,
        'ttl'                => 300,
        'simplekeys'         => true,
        'simpledata'         => true,
        'staticacceleration' => true,
    ],
    'trending' => [
        'mode'               => cache_store::MODE_APPLICATION,
        'ttl'                => 300,
        'simplekeys'         => true,
        'simpledata'         => true,
        'staticacceleration' => true,
    ],
    'new_courses' => [
        'mode'               => cache_store::MODE_APPLICATION,
        'ttl'                => 300,
        'simplekeys'         => true,
        'simpledata'         => true,
        'staticacceleration' => true,
    ],
    'categories' => [
        'mode'               => cache_store::MODE_APPLICATION,
        'ttl'                => 600,  // Categories change rarely
        'simplekeys'         => true,
        'simpledata'         => true,
        'staticacceleration' => true,
    ],
];
