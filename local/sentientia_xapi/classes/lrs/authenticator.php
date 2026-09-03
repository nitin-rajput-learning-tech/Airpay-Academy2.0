<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

namespace local_sentientia_xapi\lrs;

defined('MOODLE_INTERNAL') || die();

/**
 * LRS endpoint authenticator.
 *
 * Authenticates inbound xAPI client requests via:
 *   1. Bearer token (Authorization: Bearer <token>)
 *   2. HTTP Basic auth (Authorization: Basic <base64>)
 *
 * Credentials are stored in {local_sentientia_xapi_clients} as
 * SHA-256 hashes. The plain-text token is NEVER stored.
 *
 * Falls back to the admin-configured site-wide token/credentials when
 * the clients table has no matching row.
 *
 * @package    local_sentientia_xapi
 * @copyright  2026 Airpay Payment Services
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class authenticator {

    /**
     * Synthetic clientid for a request authenticated via the admin-
     * configured site-wide Bearer token (no row in
     * {local_sentientia_xapi_clients}). H3 fix (UAT-SECURITY-POSTURE-
     * 2026-09-03) — the rate limiter needs a stable per-credential key
     * even for this fallback identity.
     */
    public const SITE_BEARER_CLIENTID = -1;

    /**
     * Synthetic clientid for the site-wide Basic-auth fallback credential.
     */
    public const SITE_BASIC_CLIENTID = -2;

    /**
     * Authenticate an HTTP request to the LRS endpoint.
     *
     * @return array{ok: bool, costcenterid: int, clientid: int}
     *   ok=true when authenticated; costcenterid=0 means accept all tenants
     *   (platform credential), >0 means scoped to that tenant. clientid
     *   identifies WHICH credential matched — the {local_sentientia_xapi_
     *   clients} row id when a registered client matched, or one of the
     *   SITE_*_CLIENTID sentinels for the site-wide fallback credentials
     *   — so callers (the LRS rate limiter) can meter each credential
     *   separately. 0 when ok=false (never consumed).
     */
    public function authenticate_request(): array {
        $auth_header = $this->get_authorization_header();
        if (empty($auth_header)) {
            return ['ok' => false, 'costcenterid' => 0, 'clientid' => 0];
        }

        // Bearer token.
        if (stripos($auth_header, 'Bearer ') === 0) {
            $token = trim(substr($auth_header, 7));
            return $this->check_bearer($token);
        }

        // Basic auth.
        if (stripos($auth_header, 'Basic ') === 0) {
            $decoded = base64_decode(trim(substr($auth_header, 6)), true);
            if ($decoded === false || strpos($decoded, ':') === false) {
                return ['ok' => false, 'costcenterid' => 0, 'clientid' => 0];
            }
            [$user, $pass] = explode(':', $decoded, 2);
            return $this->check_basic($user, $pass);
        }

        return ['ok' => false, 'costcenterid' => 0, 'clientid' => 0];
    }

    /**
     * Check a Bearer token.
     *
     * Checks {local_sentientia_xapi_clients} first, then falls back to
     * the admin-configured site-wide token.
     *
     * @param string $token Raw (unhashed) token from the request.
     * @return array{ok: bool, costcenterid: int, clientid: int}
     */
    private function check_bearer(string $token): array {
        global $DB;

        // Constant-time hash comparison prevents timing attacks.
        $hash = hash('sha256', $token);

        $client = $DB->get_record_select(
            'local_sentientia_xapi_clients',
            'token_hash = :h AND enabled = 1',
            ['h' => $hash],
            'id, costcenterid'
        );

        if ($client) {
            return ['ok' => true, 'costcenterid' => (int) $client->costcenterid, 'clientid' => (int) $client->id];
        }

        // Fallback to site-wide token stored in plugin config.
        $site_token = get_config('local_sentientia_xapi', 'lrs_token');
        if (!empty($site_token) && hash_equals(hash('sha256', $site_token), $hash)) {
            return ['ok' => true, 'costcenterid' => 0, 'clientid' => self::SITE_BEARER_CLIENTID];
        }

        return ['ok' => false, 'costcenterid' => 0, 'clientid' => 0];
    }

    /**
     * Check HTTP Basic credentials.
     *
     * @param string $username Raw username.
     * @param string $password Raw password.
     * @return array{ok: bool, costcenterid: int, clientid: int}
     */
    private function check_basic(string $username, string $password): array {
        global $DB;

        if (empty($username) || empty($password)) {
            return ['ok' => false, 'costcenterid' => 0, 'clientid' => 0];
        }

        $pass_hash = hash('sha256', $password);

        $client = $DB->get_record_select(
            'local_sentientia_xapi_clients',
            'basic_user = :u AND enabled = 1',
            ['u' => $username],
            'id, costcenterid, basic_pass_hash'
        );

        if ($client && hash_equals($client->basic_pass_hash, $pass_hash)) {
            return ['ok' => true, 'costcenterid' => (int) $client->costcenterid, 'clientid' => (int) $client->id];
        }

        // Fallback to site-wide basic credentials.
        $site_user = get_config('local_sentientia_xapi', 'lrs_basic_user');
        $site_pass = get_config('local_sentientia_xapi', 'lrs_basic_pass');
        if (!empty($site_user) && !empty($site_pass)
                && $site_user === $username
                && hash_equals(hash('sha256', $site_pass), $pass_hash)) {
            return ['ok' => true, 'costcenterid' => 0, 'clientid' => self::SITE_BASIC_CLIENTID];
        }

        return ['ok' => false, 'costcenterid' => 0, 'clientid' => 0];
    }

    /**
     * Retrieve the Authorization header from the PHP superglobal.
     *
     * Handles servers that expose it differently.
     */
    private function get_authorization_header(): string {
        // Standard.
        if (!empty($_SERVER['HTTP_AUTHORIZATION'])) {
            return $_SERVER['HTTP_AUTHORIZATION'];
        }
        // Apache (mod_rewrite stripping).
        if (!empty($_SERVER['REDIRECT_HTTP_AUTHORIZATION'])) {
            return $_SERVER['REDIRECT_HTTP_AUTHORIZATION'];
        }
        // PHP-FPM / some NGINX setups expose via getallheaders().
        if (function_exists('getallheaders')) {
            $headers = getallheaders();
            // Case-insensitive search.
            foreach ($headers as $key => $value) {
                if (strtolower($key) === 'authorization') {
                    return $value;
                }
            }
        }
        return '';
    }
}
