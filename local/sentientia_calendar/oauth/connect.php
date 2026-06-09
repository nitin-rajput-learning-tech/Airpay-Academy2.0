<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

/**
 * OAuth connect entry — Tier 2.6 Phase 2.1.
 *
 * Builds the provider's authorize URL with PKCE + state and redirects
 * the browser there. The browser then bounces back to oauth/callback.php
 * after the user consents on the provider side.
 *
 * Gates:
 *   1. require_login() — caller must be authenticated.
 *   2. require_sesskey() — defends against CSRF on the click that starts
 *      the OAuth dance. Without this, an attacker could trick a
 *      logged-in user into binding their account to the attacker's
 *      provider account (account-takeover via OAuth).
 *   3. The capability local/sentientia_calendar:subscribe — the same
 *      capability that gates the user-facing subscription page.
 *   4. The master feature flag — assert_feature_flag_enabled() throws
 *      `error_flag_off` when OFF. Default OFF on every Sentientia
 *      instance.
 *   5. The per-provider client_id setting — build_authorize_url() throws
 *      `error_oauth_clientid_missing` when blank.
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

// assert_feature_flag_enabled() lives inside build_authorize_url —
// throws `error_flag_off` when off. Reaches here only when flag is ON.
$authorize_url = $class::build_authorize_url((int) $USER->id);

redirect($authorize_url);
