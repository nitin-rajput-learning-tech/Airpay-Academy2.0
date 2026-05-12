<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

defined('MOODLE_INTERNAL') || die();

// Moodle 5 message-provider defaults. Constants live in
// message/lib.php: MESSAGE_PERMITTED=0x08, MESSAGE_DEFAULT_ENABLED=0x01.
// The Moodle-4 constants MESSAGE_DEFAULT_LOGGEDIN / _LOGGEDOFF were
// removed in Moodle 5 — the inline-integer workaround that used to
// live here would have meant the wrong bitmask in Moodle 5.
$defaults = [
    'email' => MESSAGE_PERMITTED + MESSAGE_DEFAULT_ENABLED,
    'popup' => MESSAGE_PERMITTED + MESSAGE_DEFAULT_ENABLED,
];

$messageproviders = [
    'recompletion_due_soon' => ['capability' => 'local/airpay_recompletion:view', 'defaults' => $defaults],
    'recompletion_reset'    => ['capability' => 'local/airpay_recompletion:view', 'defaults' => $defaults],
];
