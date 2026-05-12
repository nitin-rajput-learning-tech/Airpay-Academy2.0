<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->libdir . '/messagelib.php');  // ensure MESSAGE_PERMITTED defined on first install

$messageproviders = [
    'waitlist_promoted' => [
        'capability' => 'local/airpay_classroom:view',
        'defaults'   => [
            'email' => MESSAGE_PERMITTED + MESSAGE_DEFAULT_LOGGEDIN + MESSAGE_DEFAULT_LOGGEDOFF,
            'popup' => MESSAGE_PERMITTED + MESSAGE_DEFAULT_LOGGEDIN,
        ],
    ],
];
