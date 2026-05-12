<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

defined('MOODLE_INTERNAL') || die();

$defaults = [
    'email' => MESSAGE_PERMITTED + MESSAGE_DEFAULT_LOGGEDIN + MESSAGE_DEFAULT_LOGGEDOFF,
    'popup' => MESSAGE_PERMITTED + MESSAGE_DEFAULT_LOGGEDIN,
];

$messageproviders = [
    'session_flagged'  => ['capability' => 'local/airpay_proctoring:review',  'defaults' => $defaults],
    'session_reviewed' => ['capability' => 'local/airpay_proctoring:attempt', 'defaults' => $defaults],
    'identity_failed'  => ['capability' => 'local/airpay_proctoring:attempt', 'defaults' => $defaults],
];
