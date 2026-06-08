<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

defined('MOODLE_INTERNAL') || die();

/**
 * W1-9 (2026-05-15) — cache definitions.
 *
 * `program_complete_dedupe` — short-lived (24h) per (userid, programid) cache
 * preventing duplicate `program_completed` events when a user re-completes
 * the same final-level course (e.g. recompletion + immediate re-complete).
 */
$definitions = [
    'program_complete_dedupe' => [
        'mode'       => cache_store::MODE_APPLICATION,
        'ttl'        => 86400,  // 24 hours
        'simplekeys' => true,
    ],
];
