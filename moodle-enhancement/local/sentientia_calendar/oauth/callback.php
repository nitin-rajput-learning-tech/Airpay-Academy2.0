<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

/**
 * OAuth callback handler — Tier 2.6 Phase 2.1.
 *
 * The provider redirects the browser here after the user consents
 * (or denies) on its consent screen. We:
 *
 *   1. Verify the master feature flag is ON. If OFF: throw — closes the
 *      oracle that would otherwise let an attacker observe whether the
 *      feature is being trialled for a customer.
 *   2. Verify the state parameter matches a pending entry in
 *      $SESSION for this user + provider. `handle_callback()` consumes
 *      the entry on every call (success OR failure) so a replay attempt
 *      with a tampered state cannot try again.
 *   3. Exchange the authorization code for an access_token +
 *      refresh_token via the provider's token endpoint.
 *   4. Encrypt both tokens via {@see token_vault} and persist.
 *   5. Redirect back to the user-facing /index.php with a success
 *      notification.
 *
 * Provider error path: if `error` is present in the query (the user
 * declined, the provider rejected the redirect URI, etc.), we redirect
 * back to /index.php with a friendly error message instead of throwing.
 *
 * No sesskey check here — the only "session-binding token" we have
 * across the cross-site redirect is the state parameter we issued at
 * authorize-time. consume_pending_state() is the CSRF defence.
 *
 * @package local_sentientia_calendar
 */

require(__DIR__ . '/../../../config.php');

require_login();

global $USER;

$context = \context_user::instance($USER->id);
require_capability('local/sentientia_calendar:subscribe', $context);

$provider     = required_param('provider', PARAM_ALPHANUMEXT);
$code         = optional_param('code', '', PARAM_RAW);
$state        = optional_param('state', '', PARAM_RAW);
$error_code   = optional_param('error', '', PARAM_ALPHANUMEXT);
$error_descr  = optional_param('error_description', '', PARAM_TEXT);

$class = \local_sentientia_calendar\oauth\oauth_base::provider_class($provider);

$return_url = new \moodle_url('/local/sentientia_calendar/index.php');

// Master feature flag — gate AHEAD of any token-vault touch.
\local_sentientia_calendar\oauth\oauth_base::assert_feature_flag_enabled();

// Provider error path — user denied consent, app rejected, etc.
if ($error_code !== '') {
    // Don't trust the provider's text — escape it via PARAM_TEXT
    // (already done by optional_param) and pass through {$a}.
    $a = (object) ['code' => $error_code, 'description' => $error_descr];
    redirect(
        $return_url,
        get_string('connect_error', 'local_sentientia_calendar', $a),
        null,
        \core\output\notification::NOTIFY_ERROR
    );
}

if ($state === '') {
    redirect(
        $return_url,
        get_string('error_oauth_state_invalid', 'local_sentientia_calendar'),
        null,
        \core\output\notification::NOTIFY_ERROR
    );
}

try {
    $class::handle_callback((int) $USER->id, $code, $state);
} catch (\moodle_exception $e) {
    redirect(
        $return_url,
        $e->getMessage(),
        null,
        \core\output\notification::NOTIFY_ERROR
    );
}

redirect(
    $return_url,
    get_string('connect_success_' . $provider, 'local_sentientia_calendar'),
    null,
    \core\output\notification::NOTIFY_SUCCESS
);
