<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

defined('MOODLE_INTERNAL') || die();

// Moodle 5 message-provider defaults. Constants live in
// message/lib.php: MESSAGE_PERMITTED=0x08, MESSAGE_DEFAULT_ENABLED=0x01.
// The Moodle-4 constants MESSAGE_DEFAULT_LOGGEDIN / _LOGGEDOFF were
// removed in Moodle 5 — the inline-integer workaround that used to
// live here would have meant the wrong bitmask in Moodle 5.
$defaults = [
    'email' => MESSAGE_PERMITTED + MESSAGE_DEFAULT_ENABLED,
    'popup' => MESSAGE_PERMITTED + MESSAGE_DEFAULT_ENABLED,
];

// Both notices are addressed to the LEARNER whose completion is about to expire or was just reset, so neither
// provider names a capability. message_send() delivers a notification only to a user who holds the provider's
// capability (message_get_providers_for_user()), and local/sentientia_recompletion:view is a manager capability
// (db/access.php): with it here, every notice to an ordinary learner was dropped, with nothing but a debugging()
// line, and nobody was ever told. The learner's own message preferences (the defaults above) still apply, and a
// provider without a capability is the convention of the other learner-facing local_sentientia_* providers.
$messageproviders = [
    'recompletion_due_soon' => ['capability' => null, 'defaults' => $defaults],
    'recompletion_reset'    => ['capability' => null, 'defaults' => $defaults],
];
