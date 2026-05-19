<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

defined('MOODLE_INTERNAL') || die();

/**
 * W1-5 (2026-05-15) — message provider definitions for local_airpay_evaluation.
 *
 * `evaluation_invite` is emitted by `evaluation_engine::send_invite_notification()`
 * when a queued trigger fires. The user can configure delivery channels
 * (in-app, email, mobile push) from their profile.
 *
 * NOTE: Moodle 5 removed `MESSAGE_DEFAULT_LOGGEDIN` and `MESSAGE_DEFAULT_LOGGEDOFF`
 * — `MESSAGE_DEFAULT_ENABLED` is the replacement.
 */
$defaults = [
    'email' => MESSAGE_PERMITTED + MESSAGE_DEFAULT_ENABLED,
    'popup' => MESSAGE_PERMITTED + MESSAGE_DEFAULT_ENABLED,
];

$messageproviders = [

    'evaluation_invite' => [
        'capability' => null,
        'defaults'   => $defaults,
    ],

    // P1 #19 (2026-05-16) — fires from evaluation_manager::submit_response()
    // when an evaluation has notify_admin_on_response=1. Closes audit item
    // #17 from parity-audit-2026-05-15/airpay_evaluation.md.
    //
    // Recipients are siteadmins (Moodle get_admins()). Each admin can opt
    // out per-channel via their notification preferences.
    //
    // `capability` is null because we filter recipients ourselves rather
    // than relying on Moodle's capability-driven dispatch (siteadmins
    // always pass any cap check, which would defeat per-user opt-out).
    'evaluation_response' => [
        'capability' => null,
        'defaults'   => $defaults,
    ],

];
