<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

defined('MOODLE_INTERNAL') || die();

$defaults = [
    'email' => MESSAGE_PERMITTED + MESSAGE_DEFAULT_ENABLED,
    'popup' => MESSAGE_PERMITTED + MESSAGE_DEFAULT_ENABLED,
];

$messageproviders = [
    'request_submitted' => ['capability' => 'local/sentientia_request:request', 'defaults' => $defaults],
    'request_pending'   => ['capability' => 'local/sentientia_request:approve', 'defaults' => $defaults],
    'request_decided'   => ['capability' => 'local/sentientia_request:request', 'defaults' => $defaults],
    'request_escalated' => ['capability' => 'local/sentientia_request:approve', 'defaults' => $defaults],
];
