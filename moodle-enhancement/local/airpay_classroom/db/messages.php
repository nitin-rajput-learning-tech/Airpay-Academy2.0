<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

defined('MOODLE_INTERNAL') || die();

// Inline integer values to sidestep MESSAGE_PERMITTED bootstrap-order issue.
// MESSAGE_PERMITTED=1, MESSAGE_DEFAULT_LOGGEDIN=8, MESSAGE_DEFAULT_LOGGEDOFF=16
$messageproviders = [
    'waitlist_promoted' => [
        'capability' => 'local/airpay_classroom:view',
        'defaults'   => [
            'email' => 1 + 8 + 16,
            'popup' => 1 + 8,
        ],
    ],
];
