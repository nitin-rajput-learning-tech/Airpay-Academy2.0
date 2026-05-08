<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

/**
 * Message providers for local_airpay_manager.
 *
 * `request_decided`     — fired by approval_manager::decide_request when a
 *                         manager approves or rejects an enrolment request.
 * `allocation_assigned` — fired by approval_manager::create_allocation when
 *                         a manager pushes a course to a direct report.
 *
 * Both are visible to learners in their notification preferences page so
 * they can choose channel (popup / email / mobile push).
 */
defined('MOODLE_INTERNAL') || die();

$messageproviders = [
    'request_decided' => [
        'defaults' => [
            'popup' => MESSAGE_PERMITTED + MESSAGE_DEFAULT_ENABLED,
            'email' => MESSAGE_PERMITTED + MESSAGE_DEFAULT_ENABLED,
        ],
    ],
    'allocation_assigned' => [
        'defaults' => [
            'popup' => MESSAGE_PERMITTED + MESSAGE_DEFAULT_ENABLED,
            'email' => MESSAGE_PERMITTED + MESSAGE_DEFAULT_ENABLED,
        ],
    ],
];
