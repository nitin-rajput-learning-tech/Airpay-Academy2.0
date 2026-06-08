<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

/**
 * Token regeneration endpoint.
 *
 * Revokes the user's current subscription token and issues a new one.
 * Redirects back to index.php on success so the user can copy the new
 * URL.
 *
 * Hardened against CSRF via sesskey() — without that, an attacker who
 * tricks the user into visiting this URL could silently invalidate
 * their calendar subscription. Annoying, not catastrophic, but worth
 * defending.
 *
 * @package local_sentientia_calendar
 */

require(__DIR__ . '/../../config.php');

require_login();
require_sesskey();

global $USER;

$context = \context_user::instance($USER->id);
require_capability('local/sentientia_calendar:subscribe', $context);

// Master feature flag — refuse regeneration when off (otherwise an
// attacker could spam-create rows even when the feature is disabled).
if (class_exists('\\local_sentientia_platform\\feature_flags')) {
    if (!\local_sentientia_platform\feature_flags::is_enabled('sentientia.calendar_sync.enabled')) {
        throw new \moodle_exception('error_flag_off', 'local_sentientia_calendar');
    }
}

\local_sentientia_calendar\token_manager::regenerate_for_user((int) $USER->id);

redirect(
    new \moodle_url('/local/sentientia_calendar/index.php'),
    get_string('regenerate_success', 'local_sentientia_calendar'),
    null,
    \core\output\notification::NOTIFY_SUCCESS
);
