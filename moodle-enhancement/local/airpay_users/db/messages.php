<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

defined('MOODLE_INTERNAL') || die();

/**
 * P1 #7 (2026-05-16) — message provider definitions for local_airpay_users.
 *
 * `welcome_email` fires when user_manager::create() finishes and the admin
 * ticked the "send welcome email" option. The tenant-scoped mailer
 * (welcome_mailer::send()) substitutes tokens before delivery.
 *
 * NOTE: Moodle 5 removed MESSAGE_DEFAULT_LOGGEDIN / _LOGGEDOFF. Use
 * MESSAGE_DEFAULT_ENABLED instead.
 */
$defaults = [
    'email' => MESSAGE_PERMITTED + MESSAGE_DEFAULT_ENABLED,
    'popup' => MESSAGE_PERMITTED,
];

$messageproviders = [
    'welcome_email' => [
        'capability' => null,
        'defaults'   => $defaults,
    ],
];
