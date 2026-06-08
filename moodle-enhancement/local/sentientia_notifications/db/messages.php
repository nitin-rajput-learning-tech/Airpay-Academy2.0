<?php
defined('MOODLE_INTERNAL') || die();

$messageproviders = [
    'smart_alert' => [
        'defaults' => [
            'popup'  => MESSAGE_PERMITTED + MESSAGE_DEFAULT_ENABLED,
            'email'  => MESSAGE_PERMITTED + MESSAGE_DEFAULT_ENABLED,
        ],
    ],
];
