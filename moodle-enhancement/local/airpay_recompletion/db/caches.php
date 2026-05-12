<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

defined('MOODLE_INTERNAL') || die();

$definitions = [
    'warn_dedupe' => [
        'mode'       => cache_store::MODE_APPLICATION,
        'ttl'        => 86400,  // 1 day
        'simplekeys' => true,
    ],
];
