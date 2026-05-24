<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

/**
 * Google Calendar OAuth 2.0 provider — Tier 2.6 Phase 2.
 *
 * SCAFFOLDING ONLY: supplies the Google-specific endpoint URLs, scope
 * string, and client-ID lookup. Lifecycle methods inherited from
 * {@see oauth_base} do NOT perform live HTTP calls in this chip.
 *
 * Google notes
 * ------------
 * - `access_type=offline` is REQUIRED at authorize-time to get a
 *   refresh_token in the callback. Without it Google issues only a
 *   short-lived access_token and the user has to redo the dance.
 * - `prompt=consent` forces Google to show the consent screen on every
 *   authorize, which forces a fresh refresh_token even if the user has
 *   previously consented. Without this, re-running the flow returns the
 *   access_token but NO refresh_token, leaving us with no way to
 *   re-mint when the access_token expires. Setting `prompt=consent` is
 *   the right call for an opt-in feature where we own the token store.
 * - The Google calendar.events scope grants read + write to events
 *   the user creates via our app. calendar.events.owned is narrower
 *   (only events owned by the user) which is what we want for Sentientia.
 *
 * Google client SECRETS
 * ---------------------
 * Google's web-flow REQUIRES the client_secret in the token-endpoint
 * POST. (Microsoft Graph also accepts client_secret on confidential
 * clients; this class follows the confidential-client model for both.)
 * The secret is stored in Moodle's `config_plugins` table — admin only
 * — and never reaches the browser, never appears in logs, never is
 * returned by any public method except {@see get_client_secret}.
 *
 * @package local_sentientia_calendar
 */

namespace local_sentientia_calendar\oauth;

defined('MOODLE_INTERNAL') || die();

class google_oauth extends oauth_base {

    /** {@inheritdoc} */
    public const PROVIDER = 'google';

    /** Google's user-consent endpoint. */
    private const AUTHORIZE_URL = 'https://accounts.google.com/o/oauth2/v2/auth';

    /** Google's token-mint + token-refresh endpoint (same URL, distinguished by grant_type). */
    private const TOKEN_URL = 'https://oauth2.googleapis.com/token';

    /** Google's revoke endpoint (RFC 7009-style). */
    private const REVOKE_URL = 'https://oauth2.googleapis.com/revoke';

    /**
     * Scopes requested for Google Calendar access.
     *
     * `calendar.events.owned` is the narrow scope: read+write on events
     * the app itself creates in the user's primary calendar. Wider scopes
     * like `calendar` or `calendar.events` would let the app see ALL the
     * user's events — overreach for a one-way "push my deadlines in"
     * feature.
     */
    private const SCOPES = 'https://www.googleapis.com/auth/calendar.events.owned';

    #[\Override]
    public static function get_authorize_endpoint(): string {
        return self::AUTHORIZE_URL;
    }

    #[\Override]
    public static function get_token_endpoint(): string {
        return self::TOKEN_URL;
    }

    #[\Override]
    public static function get_revoke_endpoint(): string {
        return self::REVOKE_URL;
    }

    #[\Override]
    public static function get_scopes(): string {
        return self::SCOPES;
    }

    /**
     * Google's authorize redirect needs two extra params (vs Microsoft's
     * default offline_access scope):
     *   access_type=offline      → mint a refresh_token in the callback
     *   prompt=consent           → force the consent screen every time
     *                              (Google won't re-issue a refresh_token
     *                              without it)
     *
     * @return array<string, string>
     */
    #[\Override]
    public static function get_extra_authorize_params(): array {
        return [
            'access_type' => 'offline',
            'prompt'      => 'consent',
        ];
    }

    /**
     * Read the Google OAuth client ID from admin settings.
     * Returns '' when no app is registered yet — callers treat this as
     * "OAuth for Google unavailable".
     *
     * @return string
     */
    #[\Override]
    public static function get_client_id(): string {
        $value = get_config('local_sentientia_calendar', 'google_client_id');
        return is_string($value) ? trim($value) : '';
    }

    /**
     * Read the Google OAuth client secret from admin settings.
     *
     * SCAFFOLDING — only consumed in Phase 2.1 token-endpoint POST.
     * Phase 2 scaffolding does NOT log, return-from-public-API, or
     * persist this value to the audit log.
     *
     * @return string
     */
    public static function get_client_secret(): string {
        $value = get_config('local_sentientia_calendar', 'google_client_secret');
        return is_string($value) ? trim($value) : '';
    }
}
