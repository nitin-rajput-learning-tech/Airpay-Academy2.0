<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

defined('MOODLE_INTERNAL') || die();

/**
 * Cache definitions for local_airpay_cart.
 *
 * Phase 8.2 re-audit follow-up: added `callback_drop_dedupe` to back the
 * silent-404 IP-drop log dedupe in callback.php. The dedupe stops a stuck
 * scanner from flooding error_log; the log itself stays useful for ops
 * because each distinct dropped IP gets one log entry per hour.
 */
$definitions = [

    'callback_drop_dedupe' => [
        'mode'        => cache_store::MODE_APPLICATION,
        'ttl'         => 3600,   // one hour — matches the floor(time()/3600) bucket key
        'simplekeys'  => true,
        'staticacceleration' => true,
    ],

];
