<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

defined('MOODLE_INTERNAL') || die();

$defaults = [
    'email' => MESSAGE_PERMITTED + MESSAGE_DEFAULT_ENABLED,
    'popup' => MESSAGE_PERMITTED + MESSAGE_DEFAULT_ENABLED,
];

$messageproviders = [
    'session_flagged'  => ['capability' => 'local/sentientia_proctoring:review',  'defaults' => $defaults],
    'session_reviewed' => ['capability' => 'local/sentientia_proctoring:attempt', 'defaults' => $defaults],
    'identity_failed'  => ['capability' => 'local/sentientia_proctoring:attempt', 'defaults' => $defaults],
];
