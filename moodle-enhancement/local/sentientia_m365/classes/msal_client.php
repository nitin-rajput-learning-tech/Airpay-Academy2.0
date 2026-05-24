<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

namespace local_sentientia_m365;

defined('MOODLE_INTERNAL') || die();

/**
 * Microsoft identity platform (MSAL-equivalent) OAuth2 client for
 * Sentientia LMS — Phase C.1 scaffold.
 *
 * Implements the OAuth2 Authorization Code grant with PKCE per
 * RFC 7636. The PKCE pair (code_verifier + code_challenge) defeats the
 * authorization-code interception attack that would otherwise leak the
 * Microsoft access token to anybody who can read the browser's
 * intermediate redirect URI.
 *
 * Phase C.1 ships:
 *
 *   - Authorization URL builder (build_authorize_url) — Phase C.2 wires
 *     this to a real /local/sentientia_m365/connect.php redirect entry.
 *   - PKCE verifier + challenge generator (generate_pkce_pair).
 *   - Token persistence with \core\encryption round-trip
 *     (store_tokens / load_tokens / decrypt_token).
 *   - Refresh-required check (needs_refresh).
 *   - exchange_code() — POSTs the auth code to
 *     /oauth2/v2.0/token to exchange for tokens — refuses to fire
 *     unless the sentientia_m365_enabled flag is ON.
 *
 * What this class does NOT do in Phase C.1:
 *
 *   - It does NOT hit login.microsoftonline.com.
 *     exchange_code() is gated behind the feature flag check (which is
 *     OFF by default). Until the flag flips ON, the method returns the
 *     'feature_off' sentinel and the call is a no-op. Even when the
 *     flag is ON, the actual cURL call is left for Phase C.2.
 *   - It does NOT hit Microsoft's revocation endpoint. revoke() in
 *     Phase C.1 only deletes the local token row.
 *
 * Per ADR notes / .claude/rules/api.md: NEVER log token values, NEVER
 * include client_secret in error_detail, ALWAYS read secret + tenant +
 * client ID from get_config() (admin settings), NEVER hardcode.
 *
 * @package local_sentientia_m365
 */
class msal_client {

    /** Authorize endpoint template (the {tenant_id} placeholder is
     *  swapped in build_authorize_url). */
    public const AUTHORIZE_ENDPOINT_TEMPLATE =
        'https://login.microsoftonline.com/{tenant_id}/oauth2/v2.0/authorize';

    /** Token endpoint template (Phase C.2 will POST here). */
    public const TOKEN_ENDPOINT_TEMPLATE =
        'https://login.microsoftonline.com/{tenant_id}/oauth2/v2.0/token';

    /** Refresh tokens older than this many days are considered expired
     *  even if Microsoft would still honour them. Forces re-consent. */
    public const REFRESH_TOKEN_MAX_AGE_DAYS = 60;

    /** Refresh window: refresh access tokens 60 s before stated expiry
     *  to absorb clock skew + the cost of the refresh round trip. */
    public const REFRESH_WINDOW_SECONDS = 60;

    /** PKCE verifier length (RFC 7636 §4.1: 43..128 chars). */
    public const PKCE_VERIFIER_LENGTH = 64;

    /**
     * Top-level dispatcher — is the OAuth flow currently usable?
     *
     * Used by the connection UI to decide whether to show the
     * "Connect to Microsoft 365" button or a "Feature disabled by
     * administrator" notice. Same shape as
     * anthropic_client::is_live_ready() in local_sentientia_aiquiz so
     * the patterns rhyme.
     *
     * @return bool
     */
    public static function is_ready(): bool {
        if (!class_exists('\\local_airpay_core\\feature_flags')) {
            return false;
        }
        if (!\local_airpay_core\feature_flags::is_enabled('sentientia_m365_enabled')) {
            return false;
        }
        $tenant   = get_config('local_sentientia_m365', 'azure_tenant_id');
        $client   = get_config('local_sentientia_m365', 'azure_client_id');
        $redirect = get_config('local_sentientia_m365', 'redirect_uri');
        return !empty($tenant) && !empty($client) && !empty($redirect);
    }

    /**
     * Generate a PKCE verifier + challenge pair per RFC 7636.
     *
     * The verifier is a cryptographically random URL-safe string. The
     * challenge is base64url(sha256(verifier)) — the S256 method that
     * Microsoft requires.
     *
     * The verifier MUST be persisted in the user's Moodle session (NOT
     * in the database) between build_authorize_url() and exchange_code()
     * so a stolen DB dump cannot also replay the consent. The session
     * binding ties the verifier to the same browser that started the flow.
     *
     * @return array{verifier: string, challenge: string}
     */
    public static function generate_pkce_pair(): array {
        $bytes    = random_bytes(self::PKCE_VERIFIER_LENGTH);
        $verifier = self::base64url_encode($bytes);
        $verifier = substr($verifier, 0, self::PKCE_VERIFIER_LENGTH);

        $challenge_raw = hash('sha256', $verifier, true);
        $challenge     = self::base64url_encode($challenge_raw);

        return [
            'verifier'  => $verifier,
            'challenge' => $challenge,
        ];
    }

    /**
     * Build the Microsoft identity platform authorize URL.
     *
     * The caller is responsible for:
     *   - Generating a unique `state` value (CSRF defence) and storing
     *     it in the user's session.
     *   - Generating the PKCE pair (via generate_pkce_pair) and storing
     *     the verifier in the user's session.
     *   - Redirecting the browser to the returned URL.
     *
     * @param string $state             CSRF state token (caller-supplied)
     * @param string $code_challenge    PKCE challenge (S256)
     * @param string[] $extra_scopes    Additional scopes beyond defaults
     * @return string                   Fully-built authorize URL
     * @throws \moodle_exception        When tenant/client/redirect not configured
     */
    public static function build_authorize_url(string $state, string $code_challenge, array $extra_scopes = []): string {
        $tenant   = (string)get_config('local_sentientia_m365', 'azure_tenant_id');
        $client   = (string)get_config('local_sentientia_m365', 'azure_client_id');
        $redirect = (string)get_config('local_sentientia_m365', 'redirect_uri');

        if ($tenant === '' || $client === '' || $redirect === '') {
            throw new \moodle_exception('error_not_configured', 'local_sentientia_m365');
        }

        // Default scopes — always request these. Admins extend via
        // allowed_scopes in settings.
        $scopes = ['openid', 'profile', 'offline_access', 'User.Read'];
        foreach ($extra_scopes as $scope) {
            if (is_string($scope) && $scope !== '' && !in_array($scope, $scopes, true)) {
                $scopes[] = $scope;
            }
        }

        $params = [
            'client_id'             => $client,
            'response_type'         => 'code',
            'redirect_uri'          => $redirect,
            'response_mode'         => 'query',
            'scope'                 => implode(' ', $scopes),
            'state'                 => $state,
            'code_challenge'        => $code_challenge,
            'code_challenge_method' => 'S256',
        ];

        $endpoint = str_replace('{tenant_id}', rawurlencode($tenant), self::AUTHORIZE_ENDPOINT_TEMPLATE);
        return $endpoint . '?' . http_build_query($params, '', '&', PHP_QUERY_RFC3986);
    }

    /**
     * Exchange an authorization code for tokens.
     *
     * Phase C.1 gates this behind the feature flag and stops short of
     * actually hitting login.microsoftonline.com. The shape is committed
     * so Phase C.2 can drop in the cURL call without touching callers.
     *
     * @param string $code            Authorization code from Microsoft
     * @param string $code_verifier   PKCE verifier from the session
     * @param int    $userid          Moodle user the consent is for
     * @param int    $customerid      Customer scope (1 for Airpay)
     * @return array{mode: string, error: ?string}
     * @throws \moodle_exception
     */
    public static function exchange_code(string $code, string $code_verifier, int $userid, int $customerid = 1): array {

        // Phase C.1 hard gate — feature flag must be ON.
        if (!self::is_ready()) {
            return [
                'mode'  => 'feature_off',
                'error' => 'sentientia_m365_enabled is OFF or settings missing',
            ];
        }

        // Phase C.1 stops here. Phase C.2 wires the cURL call.
        // We deliberately throw confirm_required so any caller that
        // somehow reaches this code path in Phase C.1 cannot proceed
        // without an explicit lift of the safety gate.
        throw new \moodle_exception('confirm_required', 'local_sentientia_m365');
    }

    /**
     * Encrypt + persist an access/refresh-token pair for a user.
     *
     * Upserts the (userid, customerid) row. Tokens are encrypted with
     * \core\encryption (Sodium secretbox) before they touch the
     * database. The cipher method tag + base64 wrapper come from
     * core::encrypt() so the column stays ASCII.
     *
     * @param int    $userid
     * @param int    $customerid
     * @param string $access_token       Plaintext access token (short-lived)
     * @param string $refresh_token      Plaintext refresh token (long-lived)
     * @param int    $expires_in_seconds Seconds from now until the access token expires
     * @param string $scopes             Space-separated granted scopes
     * @return int                       Row ID of the persisted token record
     * @throws \moodle_exception
     */
    public static function store_tokens(
        int $userid,
        int $customerid,
        string $access_token,
        string $refresh_token,
        int $expires_in_seconds,
        string $scopes
    ): int {
        global $DB;

        if ($userid <= 0) {
            throw new \moodle_exception('invaliduser', 'error');
        }
        if ($access_token === '' || $refresh_token === '') {
            throw new \moodle_exception('error_empty_token', 'local_sentientia_m365');
        }

        $now = time();
        $row = (object)[
            'userid'            => $userid,
            'customerid'        => $customerid,
            'access_token_enc'  => \core\encryption::encrypt($access_token),
            'refresh_token_enc' => \core\encryption::encrypt($refresh_token),
            'expires'           => $now + max(0, $expires_in_seconds),
            'scopes'            => $scopes,
            'timemodified'      => $now,
        ];

        $existing = $DB->get_record('local_sentientia_m365_tokens',
            ['userid' => $userid, 'customerid' => $customerid]);
        if ($existing) {
            $row->id = $existing->id;
            $DB->update_record('local_sentientia_m365_tokens', $row);
            return (int)$existing->id;
        }

        $row->timecreated = $now;
        return (int)$DB->insert_record('local_sentientia_m365_tokens', $row);
    }

    /**
     * Load a user's stored tokens (encrypted form).
     *
     * Returns the raw record — callers must decrypt via decrypt_token().
     *
     * @param int $userid
     * @param int $customerid
     * @return \stdClass|null
     */
    public static function load_tokens(int $userid, int $customerid = 1): ?\stdClass {
        global $DB;
        $row = $DB->get_record('local_sentientia_m365_tokens',
            ['userid' => $userid, 'customerid' => $customerid]);
        return $row ?: null;
    }

    /**
     * Decrypt a token column value.
     *
     * Wraps \core\encryption::decrypt() — translates any sodium failure
     * into a generic moodle_exception so we do not leak the reason
     * (integrity-check vs. wrong-method vs. truncated ciphertext) to
     * callers. Returns the empty string when input is empty (matches
     * core::encrypt() empty-string passthrough).
     *
     * @param string $ciphertext
     * @return string
     * @throws \moodle_exception when ciphertext cannot be decrypted
     */
    public static function decrypt_token(string $ciphertext): string {
        if ($ciphertext === '') {
            return '';
        }
        try {
            return \core\encryption::decrypt($ciphertext);
        } catch (\Throwable $e) {
            throw new \moodle_exception('error_token_decrypt', 'local_sentientia_m365');
        }
    }

    /**
     * Does the row's access token need refreshing?
     *
     * Returns true within the REFRESH_WINDOW_SECONDS leading up to the
     * stored expiry, or anytime past it. A NULL/0 expires field is
     * treated as expired so missing-data does not silently grant access.
     *
     * @param \stdClass $row local_sentientia_m365_tokens row (raw)
     * @return bool
     */
    public static function needs_refresh(\stdClass $row): bool {
        $expires = isset($row->expires) ? (int)$row->expires : 0;
        if ($expires <= 0) {
            return true;
        }
        return ($expires - time()) <= self::REFRESH_WINDOW_SECONDS;
    }

    /**
     * Revoke a user's connection (local only in Phase C.1).
     *
     * Deletes the (userid, customerid) row. Phase C.2 will additionally
     * POST to Microsoft's logout / revocation endpoint.
     *
     * @param int $userid
     * @param int $customerid
     * @return bool true if a row was deleted
     */
    public static function revoke(int $userid, int $customerid = 1): bool {
        global $DB;
        $existing = $DB->get_record('local_sentientia_m365_tokens',
            ['userid' => $userid, 'customerid' => $customerid]);
        if (!$existing) {
            return false;
        }
        $DB->delete_records('local_sentientia_m365_tokens', ['id' => $existing->id]);
        return true;
    }

    /**
     * RFC 4648 §5 URL-safe base64 (no padding) — Microsoft's PKCE
     * requirement.
     *
     * @param string $bytes Raw binary input
     * @return string
     */
    private static function base64url_encode(string $bytes): string {
        return rtrim(strtr(base64_encode($bytes), '+/', '-_'), '=');
    }
}
