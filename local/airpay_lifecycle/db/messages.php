<?php
defined('MOODLE_INTERNAL') || die();

$messageproviders = [
    'compliance_deadline' => [
        'defaults' => [
            'popup' => MESSAGE_PERMITTED + MESSAGE_DEFAULT_LOGGEDIN,
            'email' => MESSAGE_PERMITTED + MESSAGE_DEFAULT_LOGGEDOFF,
        ],
    ],
];
