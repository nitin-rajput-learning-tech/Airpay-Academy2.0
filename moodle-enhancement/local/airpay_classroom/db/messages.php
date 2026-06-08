<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

defined('MOODLE_INTERNAL') || die();

// Moodle 5 message-provider defaults (see sentientia_recompletion/db/messages.php
// for the same rationale).
$messageproviders = [
    'waitlist_promoted' => [
        'capability' => 'local/airpay_classroom:view',
        'defaults'   => [
            'email' => MESSAGE_PERMITTED + MESSAGE_DEFAULT_ENABLED,
            'popup' => MESSAGE_PERMITTED + MESSAGE_DEFAULT_ENABLED,
        ],
    ],
];
