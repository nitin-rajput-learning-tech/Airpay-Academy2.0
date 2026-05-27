<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

/**
 * Microsoft 365 (Microsoft Graph) OAuth 2.0 provider — Tier 2.6 Phase 2.1.
 *
 * Concrete provider supplying Microsoft-specific endpoints, scopes, and
 * the client_id / client_secret lookups. Lifecycle (authorize, callback,
 * refresh, revoke) is inherited from {@see oauth_base}.
 *
 * Microsoft tenancy model
 * -----------------------
 * For multi-tenant Azure app registrations the authorize endpoint uses
 * `/common/` — works for any Microsoft account (work, school, personal).
 * For single-tenant registrations admins can swap `/common/` for their
 * Azure AD tenant GUID via the future `microsoft_tenant_id` site
 * setting; this scaffolding hardcodes `/common/` because Airpay
 * customer-zero uses a multi-tenant registration.
 *
 * Microsoft Graph scopes (Calendars.ReadWrite is the minimum needed):
 *   - openid                         identity claim
 *   - profile                        basic user info
 *   - offline_access                 mints a refresh token
 *   - https://graph.microsoft.com/Calendars.ReadWrite
 *                                    read + write user's calendar(s)
 *
 * Revocation: Microsoft does not publish a standalone revoke endpoint.
 * {@see oauth_base::revoke()} treats Microsoft revocation as local-only;
 * the user must revoke at account.microsoft.com to fully cut consent.
 *
 * @package local_sentientia_calendar
 */

namespace local_sentientia_calendar\oauth;

defined('MOODLE_INTERNAL') || die();

class m365_oauth extends oauth_base {

    /** {@inheritdoc} */
    public const PROVIDER = 'm365';

    /**
     * Microsoft authorize endpoint. `/common/` lets users from any
     * Microsoft tenant sign in; per-tenant deployments swap this for
     * `/<tenant-guid>/` via the (future) microsoft_tenant_id setting.
     */
    private const AUTHORIZE_URL = 'https://login.microsoftonline.com/common/oauth2/v2.0/authorize';

    /** Microsoft token endpoint (paired with AUTHORIZE_URL). */
    private const TOKEN_URL = 'https://login.microsoftonline.com/common/oauth2/v2.0/token';

    /**
     * Microsoft Graph does not publish a separate revoke endpoint —
     * the user revokes consent at https://account.microsoft.com or
     * via Azure AD. We treat revocation as local (DB-only).
     */
    private const REVOKE_URL = '';

    /**
     * Scopes requested for Microsoft Graph calendar access.
     *
     * `offline_access` is REQUIRED to mint a refresh_token — without it
     * the access_token expires after 1h and the user has to redo the
     * full OAuth dance.
     *
     * `Calendars.ReadWrite` is the minimum scope to create / update /
     * delete events in the user's primary calendar. Read-only would be
     * `Calendars.Read` but the Tier 2.6 Phase 2 design needs WRITE for
     * bi-directional sync.
     */
    private const SCOPES = 'openid profile offline_access '
        . 'https://graph.microsoft.com/Calendars.ReadWrite';

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
     * Read the Microsoft Azure app's client ID from admin settings.
     * Returns '' (the empty string) when the admin hasn't configured one
     * yet — callers should treat this as "OAuth for m365 unavailable".
     *
     * @return string
     */
    #[\Override]
    public static function get_client_id(): string {
        $value = get_config('local_sentientia_calendar', 'microsoft_client_id');
        return is_string($value) ? trim($value) : '';
    }

    /**
     * Read the Microsoft Azure app's client secret from admin settings.
     *
     * Confidential-client model: the secret is sent in the body of the
     * token-endpoint POST. It NEVER reaches the browser, NEVER appears
     * in a log, and is admin-write-only via {@see configpasswordunmask}.
     *
     * @return string
     */
    #[\Override]
    public static function get_client_secret(): string {
        $value = get_config('local_sentientia_calendar', 'microsoft_client_secret');
        return is_string($value) ? trim($value) : '';
    }
}
