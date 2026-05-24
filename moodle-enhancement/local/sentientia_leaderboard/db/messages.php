<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

defined('MOODLE_INTERNAL') || die();

/**
 * Message provider registration — Phase L.1 (2026-05-24).
 *
 * `rank_change` is the only provider local_sentientia_leaderboard ships.
 * It's the channel through which {@see \local_sentientia_leaderboard\message_helper}
 * delivers "you moved up", "you dropped", and "you cracked the top 10"
 * notifications. Defaults follow the airpay_notifications pattern:
 * permitted on every channel, popup + email enabled out of the box so
 * learners get the in-app toast even before they configure preferences.
 *
 * Learners can still mute the provider individually via
 * /message/notificationpreferences.php — Moodle's standard
 * preferences UI lists this provider because the file below registers it.
 */
$messageproviders = [
    'rank_change' => [
        'defaults' => [
            'popup' => MESSAGE_PERMITTED + MESSAGE_DEFAULT_ENABLED,
            'email' => MESSAGE_PERMITTED + MESSAGE_DEFAULT_ENABLED,
        ],
    ],
];
