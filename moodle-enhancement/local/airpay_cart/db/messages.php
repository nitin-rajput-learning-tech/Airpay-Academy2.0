<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

defined('MOODLE_INTERNAL') || die();

$messageproviders = [
    'order_placed' => [
        'capability' => 'local/airpay_cart:view',
        'defaults'   => [
            'email'   => MESSAGE_PERMITTED + MESSAGE_DEFAULT_ENABLED,
            'popup'   => MESSAGE_PERMITTED + MESSAGE_DEFAULT_ENABLED,
        ],
    ],
    'payment_received' => [
        'capability' => 'local/airpay_cart:view',
        'defaults'   => [
            'email'   => MESSAGE_PERMITTED + MESSAGE_DEFAULT_ENABLED,
            'popup'   => MESSAGE_PERMITTED + MESSAGE_DEFAULT_ENABLED,
        ],
    ],
    'order_failed' => [
        'capability' => 'local/airpay_cart:view',
        'defaults'   => [
            'email'   => MESSAGE_PERMITTED + MESSAGE_DEFAULT_ENABLED,
            'popup'   => MESSAGE_PERMITTED + MESSAGE_DEFAULT_ENABLED,
        ],
    ],
    'refund_processed' => [
        'capability' => 'local/airpay_cart:view',
        'defaults'   => [
            'email'   => MESSAGE_PERMITTED + MESSAGE_DEFAULT_ENABLED,
            'popup'   => MESSAGE_PERMITTED + MESSAGE_DEFAULT_ENABLED,
        ],
    ],
    'admin_new_order' => [
        'capability' => 'local/airpay_cart:viewallorders',
        'defaults'   => [
            'email'   => MESSAGE_PERMITTED + MESSAGE_DEFAULT_ENABLED,
            'popup'   => MESSAGE_PERMITTED + MESSAGE_DEFAULT_ENABLED,
        ],
    ],
];
