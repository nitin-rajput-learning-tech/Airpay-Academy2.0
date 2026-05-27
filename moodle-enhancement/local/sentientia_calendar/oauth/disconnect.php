<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

/**
 * OAuth disconnect entry — Tier 2.6 Phase 2.1.
 *
 * Revokes the user's tokens for the named provider:
 *   1. POSTs to the provider's revoke endpoint (Google only — Microsoft
 *      has no standalone revoke endpoint).
 *   2. Drops the local DB row from {local_sentientia_calendar_oauth}.
 *
 * Local revoke is unconditional: even if the feature flag has since
 * flipped OFF or the provider is unreachable, the user still gets a
 * clean local state. The provider call is best-effort — failures are
 * swallowed at the oauth_base::revoke() layer.
 *
 * Gates:
 *   1. require_login() — caller must be authenticated.
 *   2. require_sesskey() — defends against CSRF on the disconnect
 *      click. Without this, a malicious link could silently sever a
 *      user's calendar connection.
 *   3. The capability local/sentientia_calendar:subscribe.
 *
 * @package local_sentientia_calendar
 */

require(__DIR__ . '/../../../config.php');

require_login();
require_sesskey();

global $USER;

$context = \context_user::instance($USER->id);
require_capability('local/sentientia_calendar:subscribe', $context);

$provider = required_param('provider', PARAM_ALPHANUMEXT);

$class = \local_sentientia_calendar\oauth\oauth_base::provider_class($provider);

$return_url = new \moodle_url('/local/sentientia_calendar/index.php');

try {
    $class::revoke((int) $USER->id);
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
    get_string('disconnect_success_' . $provider, 'local_sentientia_calendar'),
    null,
    \core\output\notification::NOTIFY_SUCCESS
);
