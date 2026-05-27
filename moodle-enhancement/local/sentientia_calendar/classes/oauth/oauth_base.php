<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

/**
 * OAuth 2.0 Authorization Code + PKCE base class — Tier 2.6 Phase 2.1
 * (Wave C4 P2 plugin-maturation chip — live HTTP wired).
 *
 * Carries the protocol bits that are identical across providers:
 *   - PKCE verifier / S256 challenge generation (RFC 7636)
 *   - CSRF state-token generation + session-bound verification
 *   - Redirect-URI canonicalisation
 *   - The four lifecycle methods that concrete providers inherit:
 *     {@see build_authorize_url}, {@see handle_callback},
 *     {@see refresh_token}, {@see revoke}.
 *
 * Phase 2 (P3-N, 2026-05-24) shipped this class as SCAFFOLDING — every
 * method that would have made an HTTP call threw `oauth_not_live`.
 * Phase 2.1 (this chip) wires up the live POST to the provider's token
 * endpoint behind two independent kill-switches:
 *
 *   1. The master feature flag {@see FEATURE_FLAG}. Default OFF on every
 *      Sentientia LMS instance. When OFF, every lifecycle method throws
 *      `error_flag_off` before any HTTP traffic could possibly leave the
 *      server. The flag is the production gate.
 *
 *   2. The {@see set_http_handler_for_testing} hook. When tests set a
 *      callable here, all outbound HTTP is routed through it (returning
 *      canned responses) instead of through Moodle's `\curl`. The hook
 *      stays `null` in production — PHPUnit + Behat populate it inside
 *      their setUp(). This is the testing gate.
 *
 * Together the gates mean: live OAuth runs only when (a) an admin has
 * explicitly flipped the flag for the customer AND (b) no test has
 * registered a mock handler. Either condition flipping back to the
 * default closes the door instantly.
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

    /** Master feature-flag key. When OFF every lifecycle method is a no-op. */
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

    /**
     * Refresh window: tokens within this many seconds of expiry are
     * treated as already expired. Avoids the race where an access_token
     * with 2 s of validity left is fetched and the HTTP request to the
     * provider's API arrives after the actual expiry.
     */
    public const REFRESH_WINDOW_SECONDS = 60;

    /** Outbound HTTP timeout (seconds) for the token endpoint POST. */
    public const HTTP_TIMEOUT_SECONDS = 30;

    /**
     * Testing override for the outbound HTTP call. When non-null, every
     * `http_post_form()` call routes through this callable instead of
     * Moodle's `\curl`. Production code MUST leave this `null` — tests
     * populate it via {@see set_http_handler_for_testing()}.
     *
     * Signature: `function(string $url, array $params): array`
     * returning `['http_code' => int, 'body' => string]`.
     *
     * @var callable|null
     */
    private static $http_handler = null;

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

    /**
     * Configured client secret (from admin → site settings). Confidential-
     * client model: secret is required to call the token endpoint.
     * Returns '' when not configured.
     */
    abstract public static function get_client_secret(): string;

    // ─────────────────────────────────────────────────────────────────
    // Public lifecycle entry points.
    // ─────────────────────────────────────────────────────────────────

    /**
     * Build the user-facing authorize URL. Returns a `moodle_url` that
     * /local/sentientia_calendar/oauth/connect.php redirects the user to.
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
     * Steps:
     *   1. Verify the state matches a pending entry in $SESSION for this
     *      user+provider, recovering the stored PKCE verifier.
     *      Mismatch / TTL / missing = `error_oauth_state_invalid`.
     *   2. Verify a non-empty `code` was returned. Empty = the provider
     *      rejected consent or the user hit "cancel"; we surface
     *      `error_oauth_code_missing`.
     *   3. POST to the token endpoint with grant_type=authorization_code,
     *      the recovered code_verifier, and the confidential-client
     *      secret. The provider mints access_token + refresh_token +
     *      expires_in + scope.
     *   4. Encrypt both tokens via {@see token_vault::store_tokens}.
     *
     * The whole flow is wrapped in {@see assert_feature_flag_enabled}
     * so a malicious caller invoking this directly (bypassing
     * oauth/callback.php) still hits the kill switch.
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

        $params = [
            'grant_type'    => 'authorization_code',
            'code'          => $code,
            'code_verifier' => $verifier,
            'redirect_uri'  => self::get_redirect_uri(),
            'client_id'     => static::get_client_id(),
        ];
        $secret = static::get_client_secret();
        if ($secret !== '') {
            $params['client_secret'] = $secret;
        }

        $response = self::http_post_form(static::get_token_endpoint(), $params);
        $tokens   = self::decode_token_response($response, $userid, false);

        $access     = (string) $tokens['access_token'];
        $refresh    = (string) ($tokens['refresh_token'] ?? '');
        $expires_in = (int) ($tokens['expires_in'] ?? 3600);
        // Provider tells us which scopes it actually granted — may be a
        // subset of what we requested if the user denied one. Fall back
        // to the requested scopes if the response omits this field.
        $granted    = isset($tokens['scope']) && $tokens['scope'] !== ''
            ? (string) $tokens['scope']
            : static::get_scopes();

        $customerid = self::resolve_customerid();

        token_vault::store_tokens($userid, $customerid, static::PROVIDER,
            $access, $refresh, time() + $expires_in, $granted);
    }

    /**
     * Refresh an expired access token using the stored refresh_token.
     *
     * Behaviour:
     *   - No stored row → throws `error_oauth_no_refresh_token`. Caller
     *     should redirect the user to the connect flow.
     *   - `invalid_grant` from the provider → the refresh_token has been
     *     revoked at the provider side (user logged out, MFA reset,
     *     90-day inactivity, etc.). We DROP the local row so the next
     *     interactive use forces a fresh connect, and re-raise
     *     `error_oauth_invalid_grant` so the caller can surface a
     *     "please reconnect" notification.
     *   - Non-200 with any other body → bubble up as
     *     `error_oauth_token_response` without dropping the local row
     *     (it's likely a transient network or provider outage).
     *
     * Provider rotation: Microsoft + Google both occasionally rotate the
     * refresh_token on a refresh call. If `refresh_token` is present in
     * the response we store it; otherwise we keep the existing value.
     *
     * @param int $userid
     * @return void
     * @throws \moodle_exception
     */
    public static function refresh_token(int $userid): void {
        self::assert_feature_flag_enabled();

        $existing = token_vault::get_tokens($userid, static::PROVIDER);
        if ($existing === null || $existing->refresh_token === '') {
            throw new \moodle_exception('error_oauth_no_refresh_token',
                'local_sentientia_calendar', '', static::PROVIDER);
        }

        $params = [
            'grant_type'    => 'refresh_token',
            'refresh_token' => $existing->refresh_token,
            'client_id'     => static::get_client_id(),
        ];
        $secret = static::get_client_secret();
        if ($secret !== '') {
            $params['client_secret'] = $secret;
        }
        // Google requires the scope on refresh; Microsoft ignores it.
        // Always send it — keeps Google happy, harmless for Microsoft.
        $params['scope'] = $existing->scopes !== ''
            ? $existing->scopes
            : static::get_scopes();

        $response = self::http_post_form(static::get_token_endpoint(), $params);
        $tokens   = self::decode_token_response($response, $userid, true);

        $access     = (string) $tokens['access_token'];
        // Rotation: keep new refresh_token if provider sent one, else
        // re-encrypt the existing one (so timemodified advances).
        $refresh    = isset($tokens['refresh_token']) && $tokens['refresh_token'] !== ''
            ? (string) $tokens['refresh_token']
            : $existing->refresh_token;
        $expires_in = (int) ($tokens['expires_in'] ?? 3600);
        $granted    = isset($tokens['scope']) && $tokens['scope'] !== ''
            ? (string) $tokens['scope']
            : $existing->scopes;

        token_vault::store_tokens($userid, (int) $existing->customerid, static::PROVIDER,
            $access, $refresh, time() + $expires_in, $granted);
    }

    /**
     * Revoke the user's tokens with this provider.
     *
     * Best-effort POST to the provider's revoke endpoint (when one
     * exists) FIRST, then unconditionally drops the local DB row. The
     * provider call is wrapped in try/catch — if the provider is down or
     * the token is already invalid, we still want the local row gone so
     * the user can re-connect cleanly.
     *
     * Microsoft Graph does NOT publish a standalone revoke endpoint; we
     * skip the provider call for `m365` and only the user can revoke at
     * account.microsoft.com. Google supports RFC 7009-style POST with
     * `token=<value>`.
     *
     * Local revoke runs regardless of the feature-flag state — the
     * caller might be cleaning up after a flag flip-off. Provider revoke
     * runs only when the flag is ON (no point hitting providers when the
     * feature is disabled).
     *
     * @param int $userid
     * @return bool true when a row existed and was deleted
     */
    public static function revoke(int $userid): bool {
        $existing = token_vault::get_tokens($userid, static::PROVIDER);
        if ($existing === null) {
            return false;
        }

        $revoke_url = static::get_revoke_endpoint();
        if ($revoke_url !== '' && self::is_flag_enabled()) {
            // Prefer revoking the refresh_token — Google's revoke
            // invalidates both tokens minted from the same consent. Fall
            // back to access_token if no refresh was minted.
            $token = $existing->refresh_token !== ''
                ? $existing->refresh_token
                : $existing->access_token;
            try {
                self::http_post_form($revoke_url, ['token' => $token]);
            } catch (\Throwable $e) {
                // Swallow — local revoke MUST succeed even when the
                // provider is unreachable. Log at DEVELOPER for ops.
                debugging(
                    'Provider revoke failed for ' . static::PROVIDER
                    . ' (continuing with local revoke): ' . $e->getMessage(),
                    DEBUG_DEVELOPER
                );
            }
        }

        return token_vault::revoke_tokens($userid, static::PROVIDER);
    }

    /**
     * Return a decrypted access token, refreshing first if it's within
     * {@see REFRESH_WINDOW_SECONDS} of expiry. Returns null when there's
     * no stored row (caller should redirect to the connect flow).
     *
     * Wraps the common "fetch + refresh if needed + return" dance that
     * every Phase 2.2 Graph / Calendar API caller will need.
     *
     * @param int $userid
     * @return string|null
     */
    public static function get_valid_access_token(int $userid): ?string {
        self::assert_feature_flag_enabled();

        $existing = token_vault::get_tokens($userid, static::PROVIDER);
        if ($existing === null) {
            return null;
        }
        if (self::is_expired($existing->expires)) {
            static::refresh_token($userid);
            $existing = token_vault::get_tokens($userid, static::PROVIDER);
            if ($existing === null) {
                return null;
            }
        }
        return $existing->access_token;
    }

    /**
     * Compute a snapshot of connection status for the user. Used by
     * {@see index.php} to render the connect/disconnect UI without
     * leaking the token plaintext.
     *
     * @param int $userid
     * @return array{provider:string, connected:bool, expired:bool,
     *                expires_at:int, scopes:string, client_configured:bool}
     */
    public static function describe_connection(int $userid): array {
        $row = token_vault::get_tokens($userid, static::PROVIDER);
        $connected = $row !== null;
        return [
            'provider'          => static::PROVIDER,
            'connected'         => $connected,
            'expired'           => $connected && self::is_expired($row->expires),
            'expires_at'        => $connected ? (int) $row->expires : 0,
            'scopes'            => $connected ? (string) $row->scopes : '',
            'client_configured' => static::get_client_id() !== '',
        ];
    }

    // ─────────────────────────────────────────────────────────────────
    // HTTP helpers (mockable via set_http_handler_for_testing).
    // ─────────────────────────────────────────────────────────────────

    /**
     * Register a fake HTTP handler for tests. PHPUnit + Behat call this
     * in setUp() to route token-endpoint POSTs to a stub. Pass `null` to
     * revert to real `\curl`. Production code never calls this.
     *
     * @param callable|null $handler `function(string $url, array $params): array`
     *                                  returning ['http_code'=>int, 'body'=>string]
     * @return void
     */
    public static function set_http_handler_for_testing(?callable $handler): void {
        self::$http_handler = $handler;
    }

    /**
     * POST `application/x-www-form-urlencoded` payload, return decoded
     * status + body. Routes through the testing handler when one is set.
     *
     * @param string $url
     * @param array  $params
     * @return array{http_code:int, body:string}
     * @throws \moodle_exception when the transport itself fails
     */
    protected static function http_post_form(string $url, array $params): array {
        if (self::$http_handler !== null) {
            $result = (self::$http_handler)($url, $params);
            if (!is_array($result)
                    || !array_key_exists('http_code', $result)
                    || !array_key_exists('body', $result)) {
                throw new \coding_exception(
                    'Test HTTP handler must return [http_code, body]');
            }
            return [
                'http_code' => (int) $result['http_code'],
                'body'      => (string) $result['body'],
            ];
        }

        global $CFG;
        require_once($CFG->libdir . '/filelib.php');

        $curl = new \curl(['cache' => false]);
        $curl->setHeader('Content-Type: application/x-www-form-urlencoded');
        $curl->setHeader('Accept: application/json');

        // Build an x-www-form-urlencoded body string. Passing the array
        // directly would make \curl emit multipart/form-data (cURL's
        // behaviour for an array CURLOPT_POSTFIELDS), which OAuth token
        // endpoints reject. This mirrors core oauthlib::build_post_data().
        $body = $curl->post($url, http_build_query($params, '', '&'), [
            'CURLOPT_RETURNTRANSFER' => 1,
            'CURLOPT_FOLLOWLOCATION' => 0,
            'CURLOPT_TIMEOUT'        => self::HTTP_TIMEOUT_SECONDS,
            'CURLOPT_CONNECTTIMEOUT' => 10,
        ]);

        $info = $curl->get_info();
        $http_code = (int) ($info['http_code'] ?? 0);

        if ($http_code === 0 || !is_string($body)) {
            // Transport failure (DNS, TLS, timeout). Don't drop tokens
            // — caller treats as transient and retries.
            throw new \moodle_exception('error_oauth_http_failure',
                'local_sentientia_calendar', '', static::PROVIDER);
        }

        return ['http_code' => $http_code, 'body' => $body];
    }

    /**
     * Decode a token-endpoint JSON response and apply the
     * `invalid_grant` policy.
     *
     * @param array{http_code:int, body:string} $response
     * @param int  $userid               Owner of the row (for invalid_grant cleanup)
     * @param bool $drop_on_invalid_grant true on refresh; false on initial code-exchange
     * @return array<string, mixed> decoded JSON body
     * @throws \moodle_exception
     */
    private static function decode_token_response(array $response, int $userid,
                                                    bool $drop_on_invalid_grant): array {
        $decoded = json_decode((string) $response['body'], true);

        if ($response['http_code'] >= 400) {
            $errcode = is_array($decoded) ? (string) ($decoded['error'] ?? '') : '';
            if ($drop_on_invalid_grant && $errcode === 'invalid_grant') {
                token_vault::revoke_tokens($userid, static::PROVIDER);
                throw new \moodle_exception('error_oauth_invalid_grant',
                    'local_sentientia_calendar', '', static::PROVIDER);
            }
            throw new \moodle_exception('error_oauth_token_response',
                'local_sentientia_calendar', '', $errcode !== '' ? $errcode : (string) $response['http_code']);
        }

        if (!is_array($decoded) || empty($decoded['access_token'])) {
            throw new \moodle_exception('error_oauth_token_response',
                'local_sentientia_calendar', '', 'malformed');
        }
        return $decoded;
    }

    /**
     * Resolve the customer ID for the current request. Uses the
     * `\local_airpay_core\customer::current()` helper when available,
     * falls back to 1 (Airpay-customer-zero) when the resolver plugin
     * isn't installed.
     */
    private static function resolve_customerid(): int {
        if (class_exists('\\local_airpay_core\\customer')) {
            return (int) \local_airpay_core\customer::current();
        }
        return 1;
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
        return $CFG->wwwroot . '/local/sentientia_calendar/oauth/callback.php';
    }

    /**
     * Has the stored access token reached its refresh window?
     *
     * @param int $expires_at Unix ts
     * @return bool
     */
    public static function is_expired(int $expires_at): bool {
        return $expires_at <= 0
            || ($expires_at - time()) <= self::REFRESH_WINDOW_SECONDS;
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
     */
    private static function pending_state_key(int $userid, string $provider): string {
        return $provider . ':' . $userid;
    }

    /**
     * Throw `error_flag_off` when the master OAuth feature flag is OFF.
     *
     * @return void
     * @throws \moodle_exception
     */
    public static function assert_feature_flag_enabled(): void {
        if (!self::is_flag_enabled()) {
            throw new \moodle_exception('error_flag_off', 'local_sentientia_calendar');
        }
    }

    /**
     * Soft check of the master OAuth feature flag. Returns false when
     * the resolver plugin is missing (graceful degradation — treat as OFF).
     */
    public static function is_flag_enabled(): bool {
        if (!class_exists('\\local_airpay_core\\feature_flags')) {
            return false;
        }
        return \local_airpay_core\feature_flags::is_enabled(self::FEATURE_FLAG);
    }

    /**
     * Map a provider identifier to its concrete subclass. Centralises
     * the validation logic the three endpoints share so an unknown
     * provider string throws a single, well-defined exception.
     *
     * @param string $provider
     * @return class-string<self>
     * @throws \moodle_exception when the provider isn't recognised
     */
    public static function provider_class(string $provider): string {
        switch ($provider) {
            case 'm365':
                return m365_oauth::class;
            case 'google':
                return google_oauth::class;
            default:
                throw new \moodle_exception('error_oauth_unknown_provider',
                    'local_sentientia_calendar', '', $provider);
        }
    }
}
