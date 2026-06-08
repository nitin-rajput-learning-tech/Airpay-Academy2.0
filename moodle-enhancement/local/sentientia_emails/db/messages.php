<?php
defined('MOODLE_INTERNAL') || die();

$messageproviders = [
    'notification_alert' => [
        'defaults' => [
            'popup' => MESSAGE_PERMITTED + MESSAGE_DEFAULT_ENABLED,
            'email' => MESSAGE_PERMITTED,
        ],
    ],
];
