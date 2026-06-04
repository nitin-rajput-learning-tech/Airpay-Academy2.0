<?php
defined('MOODLE_INTERNAL') || die();

$messageproviders = [
    'compliance_deadline' => [
        'defaults' => [
            'popup' => MESSAGE_PERMITTED + MESSAGE_DEFAULT_ENABLED,
            'email' => MESSAGE_PERMITTED + MESSAGE_DEFAULT_ENABLED,
        ],
    ],
];
