<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

/**
 * OAuth 2.0 Authorization Code + PKCE base class — Tier 2.6 Phase 2 scaffolding.
 *
 * Carries the protocol bits that are identical across providers:
 *   - PKCE verifier / S256 challenge generation (RFC 7636)
 *   - CSRF state-token generation + session-bound verification
 *   - Redirect-URI canonicalisation
 *   - Scaffolding for the four lifecycle methods that subclasses must
 *     implement: build_authorize_url(), handle_callback(),
 *     refresh_token(), revoke().
 *
 * IMPORTANT — this chip ships SCAFFOLDING ONLY:
 *   - No subclass talks to {@see login.microsoftonline.com} or
 *     {@see oauth2.googleapis.com} in this build. Every "live" lifecycle
 *     method that would make an HTTP call throws
 *     `oauth_not_live` so a careless caller cannot accidentally trigger
 *     live OAuth before per-customer rollout.
 *   - Feature flag `sentientia.calendar_sync.oauth.enabled` gates
 *     authorize_url construction itself. When the flag is OFF (the
 *     default) the page surface, the callback, and the refresh job all
 *     return 404 / no-op early.
 *
 * The DB token storage layer is {@see token_vault}.
 *
 * @package local_sentientia_calendar
 */

namespace local_sentientia_calendar\oauth;

defined('MOODLE_INTERNAL') || die();

/**
 * Abstract provider base. Microsoft Graph and Google Calendar concrete
 * subclasses extend this and supply provider-specific URLs + scopes.
 */
abstract class oauth_base {

    /** Master feature-flag key. When OFF every method on this class is a no-op. */
    public const FEATURE_FLAG = 'sentientia.calendar_sync.oauth.enabled';

    /**
     * Provider identifier — must match the `provider` column in
     * {local_sentientia_calendar_oauth} (max 20 chars). One of:
     *   - 'm365'    (Microsoft 365 / Microsoft Graph)
     *   - 'google'  (Google Calendar API)
     */
    public const PROVIDER = '';

    /**
     * Recommended PKCE verifier length per RFC 7636 §4.1: 43..128 chars,
     * unreserved alphabet `[A-Za-z0-9-._~]`. 64 bytes of randomness
     * produces an 86-char base64url string after stripping padding —
     * well within the upper bound and a comfortable margin above the
     * lower bound.
     */
    public const PKCE_VERIFIER_RANDOM_BYTES = 64;

    /** Maximum age of a pending state token (10 minutes — most flows complete in seconds). */
    public const STATE_TTL_SECONDS = 600;

    // ─────────────────────────────────────────────────────────────────
    // Abstract — every concrete provider must answer these.
    // ─────────────────────────────────────────────────────────────────

    /** Return the provider's authorize endpoint, e.g. login.microsoftonline.com/... */
    abstract public static function get_authorize_endpoint(): string;

    /** Return the provider's token endpoint, e.g. oauth2.googleapis.com/token */
    abstract public static function get_token_endpoint(): string;

    /** Return the provider's revoke endpoint or '' if revocation is implicit. */
    abstract public static function get_revoke_endpoint(): string;

    /** Space-separated scope string requested at authorize time. */
    abstract public static function get_scopes(): string;

    /**
     * Configured client ID (from admin → site settings).
     * Returns '' when the customer hasn't registered an app yet.
     */
    abstract public static function get_client_id(): string;

    // ─────────────────────────────────────────────────────────────────
    // Public lifecycle entry points.
    // ─────────────────────────────────────────────────────────────────

    /**
     * Build the user-facing authorize URL. Returns a `moodle_url` that
     * /local/sentientia_calendar/oauth_connect.php redirects the user to.
     *
     * The URL is NOT followed here — we just construct it. The browser
     * follows the redirect; the provider then bounces back to our
     * registered redirect URI. No outbound HTTP traffic from this method.
     *
     * Side effect: stores the PKCE verifier + state in $SESSION so the
     * callback handler can complete the exchange. Stored state expires
     * after {@see STATE_TTL_SECONDS} regardless of session lifetime.
     *
     * @param int $userid The user starting the OAuth dance.
     * @return \moodle_url
     * @throws \moodle_exception when feature flag is OFF or client ID is empty.
     */
    public static function build_authorize_url(int $userid): \moodle_url {
        self::assert_feature_flag_enabled();

        $clientid = static::get_client_id();
        if ($clientid === '') {
            throw new \moodle_exception('error_oauth_clientid_missing',
                'local_sentientia_calendar', '', static::PROVIDER);
        }

        $verifier  = self::generate_pkce_verifier();
        $challenge = self::generate_pkce_challenge($verifier);
        $state     = self::generate_state_token();

        self::store_pending_state($userid, static::PROVIDER, $state, $verifier);

        $params = [
            'client_id'             => $clientid,
            'response_type'         => 'code',
            'redirect_uri'          => self::get_redirect_uri(),
            'scope'                 => static::get_scopes(),
            'state'                 => $state,
            'code_challenge'        => $challenge,
            'code_challenge_method' => 'S256',
        ];

        // Per-provider extra params — concrete classes may override.
        $params = array_merge($params, static::get_extra_authorize_params());

        return new \moodle_url(static::get_authorize_endpoint(), $params);
    }

    /**
     * Optional per-provider extra params for the authorize redirect.
     * Google needs `access_type=offline` + `prompt=consent` to mint a
     * refresh token; Microsoft Graph accepts the default `offline_access`
     * scope. Concrete subclasses override.
     *
     * @return array<string, string>
     */
    public static function get_extra_authorize_params(): array {
        return [];
    }

    /**
     * Handle the provider's redirect back to our callback URI.
     *
     * SCAFFOLDING ONLY in this chip: validates state + recovers the
     * stored PKCE verifier, but does NOT perform the live token
     * exchange. Throws `oauth_not_live` so callers can see the
     * scaffolding is wired up without accidentally hitting the provider.
     *
     * Phase 2.1 will replace the throw with a `curl` POST to
     * {@see get_token_endpoint()} using the verifier; on success it'll
     * call {@see token_vault::store_tokens()}.
     *
     * @param int    $userid Authenticated Moodle user from require_login().
     * @param string $code   `code` query param from the provider redirect.
     * @param string $state  `state` query param from the provider redirect.
     * @return void
     * @throws \moodle_exception
     */
    public static function handle_callback(int $userid, string $code, string $state): void {
        self::assert_feature_flag_enabled();

        $verifier = self::consume_pending_state($userid, static::PROVIDER, $state);
        if ($verifier === null) {
            throw new \moodle_exception('error_oauth_state_invalid',
                'local_sentientia_calendar');
        }

        if ($code === '') {
            throw new \moodle_exception('error_oauth_code_missing',
                'local_sentientia_calendar');
        }

        // ─── SCAFFOLDING GATE ────────────────────────────────────────
        // This is where Phase 2.1 will:
        //   1. POST to static::get_token_endpoint() with grant_type=
        //      authorization_code, code=$code, code_verifier=$verifier,
        //      redirect_uri=self::get_redirect_uri(), client_id=...
        //   2. Parse the JSON response — access_token, refresh_token,
        //      expires_in, scope
        //   3. Call token_vault::store_tokens(userid, customer, provider,
        //      access_token, refresh_token, time()+expires_in, scope)
        //
        // For Phase 2 SCAFFOLDING we deliberately throw — keeps the
        // outbound HTTP surface OFF until per-customer rollout.
        throw new \moodle_exception('oauth_not_live', 'local_sentientia_calendar',
            '', static::PROVIDER);
    }

    /**
     * Refresh an expired access token using the stored refresh_token.
     *
     * SCAFFOLDING ONLY in this chip: validates a refresh token row
     * exists for the user but does NOT perform the live refresh.
     *
     * Phase 2.1 will replace the throw with a `curl` POST to
     * {@see get_token_endpoint()} using grant_type=refresh_token.
     *
     * @param int $userid
     * @return void
     * @throws \moodle_exception
     */
    public static function refresh_token(int $userid): void {
        self::assert_feature_flag_enabled();

        $existing = token_vault::get_tokens($userid, static::PROVIDER);
        if ($existing === null) {
            throw new \moodle_exception('error_oauth_no_refresh_token',
                'local_sentientia_calendar', '', static::PROVIDER);
        }

        // Phase 2.1 will POST grant_type=refresh_token to the token endpoint.
        throw new \moodle_exception('oauth_not_live', 'local_sentientia_calendar',
            '', static::PROVIDER);
    }

    /**
     * Revoke the user's tokens with this provider.
     *
     * Always drops the local DB row (irrevocable on our side).
     * SCAFFOLDING: does NOT call the provider's revoke endpoint — that
     * arrives in Phase 2.1.
     *
     * @param int $userid
     * @return bool true when a row existed and was deleted
     */
    public static function revoke(int $userid): bool {
        return token_vault::revoke_tokens($userid, static::PROVIDER);
    }

    // ─────────────────────────────────────────────────────────────────
    // PKCE + state helpers (shared across providers).
    // ─────────────────────────────────────────────────────────────────

    /**
     * Generate a PKCE code_verifier per RFC 7636 §4.1.
     *
     * Output: base64url-encoded 64 random bytes (no padding) = 86 chars.
     * Character set is the RFC-mandated unreserved alphabet
     * `[A-Za-z0-9-._~]` because base64url uses `[A-Za-z0-9-_]` — `.` and
     * `~` are never produced, but the verifier still validates against
     * the RFC's regex.
     *
     * @return string
     */
    public static function generate_pkce_verifier(): string {
        $bytes = random_bytes(self::PKCE_VERIFIER_RANDOM_BYTES);
        // base64url: replace + with -, / with _, strip = padding.
        return rtrim(strtr(base64_encode($bytes), '+/', '-_'), '=');
    }

    /**
     * Generate the S256 code_challenge for a verifier per RFC 7636 §4.2.
     *
     *   challenge = base64url(SHA256(verifier))
     *
     * Note hash() is called with $rawoutput=true so we get the 32-byte
     * digest, not the 64-char hex string.
     *
     * @param string $verifier The PKCE verifier
     * @return string Base64url-encoded SHA-256 digest, no padding
     */
    public static function generate_pkce_challenge(string $verifier): string {
        $digest = hash('sha256', $verifier, true);
        return rtrim(strtr(base64_encode($digest), '+/', '-_'), '=');
    }

    /**
     * Generate a CSRF state token. ~256 bits of entropy is the OAuth
     * Threat-Model-document recommendation.
     *
     * @return string 43-char URL-safe random
     */
    public static function generate_state_token(): string {
        return rtrim(strtr(base64_encode(random_bytes(32)), '+/', '-_'), '=');
    }

    /**
     * Canonical redirect URI for this site, matching the value the
     * Azure / Google app registration must have registered. Sites with
     * subdir installs (e.g. /moodle) get the subdir prefix included.
     *
     * The query parameter `provider` distinguishes m365 from google so
     * one callback file handles both.
     *
     * @return string Absolute URL — registered with the IdP verbatim
     */
    public static function get_redirect_uri(): string {
        global $CFG;
        return $CFG->wwwroot . '/local/sentientia_calendar/oauth_callback.php';
    }

    // ─────────────────────────────────────────────────────────────────
    // Pending-state storage (session-scoped, TTL-enforced).
    // ─────────────────────────────────────────────────────────────────

    /**
     * Store a state + PKCE verifier for the in-flight OAuth dance.
     *
     * Session-scoped so a parallel tab can't steal the verifier from a
     * different user, and TTL-stamped so a stale state from a stalled
     * flow can't be replayed days later.
     *
     * @param int    $userid
     * @param string $provider
     * @param string $state
     * @param string $verifier
     * @return void
     */
    public static function store_pending_state(int $userid, string $provider,
                                                string $state, string $verifier): void {
        global $SESSION;
        if (!isset($SESSION->sentientia_calendar_oauth_pending)) {
            $SESSION->sentientia_calendar_oauth_pending = [];
        }
        $key = self::pending_state_key($userid, $provider);
        $SESSION->sentientia_calendar_oauth_pending[$key] = [
            'state'       => $state,
            'verifier'    => $verifier,
            'expires_at'  => time() + self::STATE_TTL_SECONDS,
        ];
    }

    /**
     * Look up a pending state, verify it matches, return the stored
     * verifier, and CONSUME (clear) the entry so it can't be replayed.
     *
     * Returns null when:
     *   - no pending state for this user+provider exists
     *   - state mismatch (CSRF defence)
     *   - TTL exceeded
     *
     * @param int    $userid
     * @param string $provider
     * @param string $state
     * @return string|null The verifier on success, null on any failure mode
     */
    public static function consume_pending_state(int $userid, string $provider,
                                                   string $state): ?string {
        global $SESSION;
        if (empty($SESSION->sentientia_calendar_oauth_pending)) {
            return null;
        }
        $key = self::pending_state_key($userid, $provider);
        if (!isset($SESSION->sentientia_calendar_oauth_pending[$key])) {
            return null;
        }
        $entry = $SESSION->sentientia_calendar_oauth_pending[$key];

        // Single-use, even on failure — clear before we validate so a
        // replay attempt with a tampered state can't try again.
        unset($SESSION->sentientia_calendar_oauth_pending[$key]);

        if (!isset($entry['state'], $entry['verifier'], $entry['expires_at'])) {
            return null;
        }
        if (time() > (int) $entry['expires_at']) {
            return null;
        }
        if (!hash_equals((string) $entry['state'], $state)) {
            return null;
        }
        return (string) $entry['verifier'];
    }

    /**
     * Compose the session storage key for a (user, provider) pair.
     *
     * @param int    $userid
     * @param string $provider
     * @return string
     */
    private static function pending_state_key(int $userid, string $provider): string {
        return $provider . ':' . $userid;
    }

    /**
     * Throw `error_flag_off` when the master OAuth feature flag is OFF.
     *
     * Keeps every public lifecycle method idempotently gated — even a
     * malicious caller invoking refresh_token() / handle_callback()
     * directly cannot bypass the kill-switch.
     *
     * @return void
     * @throws \moodle_exception
     */
    public static function assert_feature_flag_enabled(): void {
        if (!class_exists('\\local_airpay_core\\feature_flags')) {
            // Graceful degradation when the resolver plugin is missing
            // — refuse to do OAuth at all in that case (treat as OFF).
            throw new \moodle_exception('error_flag_off', 'local_sentientia_calendar');
        }
        if (!\local_airpay_core\feature_flags::is_enabled(self::FEATURE_FLAG)) {
            throw new \moodle_exception('error_flag_off', 'local_sentientia_calendar');
        }
    }
}
