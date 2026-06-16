<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

namespace local_sentientia_api\lti;

defined('MOODLE_INTERNAL') || die();

/**
 * Minimal, dependency-light JWT (RS256) verification for LTI 1.3 launches.
 *
 * LTI 1.3 carries the launch as a signed `id_token` (a JWT) issued by the
 * platform. The tool (us, as a provider) MUST verify:
 *   - the signature against the platform's public key (RS256),
 *   - the `iss`, `aud`, `exp`, `iat`, and `nonce` claims.
 *
 * This class verifies the RS256 signature using PHP's openssl, and validates
 * the registered claims. It intentionally implements only the subset LTI 1.3
 * requires. For production hardening, a platform's JWKS endpoint should be
 * fetched and the `kid` matched; this scaffold supports either an inline PEM
 * public key on the registration or a single fetched JWKS key.
 *
 * SECURITY: 'alg' is pinned to RS256. We never accept 'none', and we never
 * trust the alg field from the token header to pick a verification routine —
 * preventing the classic JWT algorithm-confusion attack.
 *
 * @package local_sentientia_api
 */
class jwt_service {

    /** Allowed signing algorithm — pinned. */
    private const ALG = 'RS256';

    /** Clock skew tolerance (seconds) for exp/iat. */
    private const LEEWAY = 60;

    /**
     * Split + base64url-decode a compact JWS into [header, payload, sigbytes, signinginput].
     *
     * @param string $jwt
     * @return array{0:array,1:array,2:string,3:string}
     * @throws \moodle_exception 'lti_invalid_token' on malformed input.
     */
    public static function decode(string $jwt): array {
        $parts = explode('.', $jwt);
        if (count($parts) !== 3) {
            throw new \moodle_exception('lti_invalid_token', 'local_sentientia_api');
        }
        [$h64, $p64, $s64] = $parts;
        $header = json_decode(self::b64url_decode($h64), true);
        $payload = json_decode(self::b64url_decode($p64), true);
        $sig = self::b64url_decode($s64);
        if (!is_array($header) || !is_array($payload) || $sig === false || $sig === '') {
            throw new \moodle_exception('lti_invalid_token', 'local_sentientia_api');
        }
        return [$header, $payload, $sig, $h64 . '.' . $p64];
    }

    /**
     * Verify an LTI id_token end-to-end.
     *
     * @param string $jwt        The compact JWS.
     * @param string $publickey  PEM public key to verify against.
     * @param string $expectediss Expected issuer (registration's issuer).
     * @param string $expectedaud Expected audience (our client_id).
     * @param string|null $expectednonce Expected nonce (from our stored login nonce).
     * @return array The verified payload (claims).
     * @throws \moodle_exception on any verification failure.
     */
    public static function verify(string $jwt, string $publickey, string $expectediss,
                                   string $expectedaud, ?string $expectednonce = null): array {
        [$header, $payload, $sig, $signinginput] = self::decode($jwt);

        // Pin the algorithm — reject anything that isn't RS256.
        if (($header['alg'] ?? '') !== self::ALG) {
            throw new \moodle_exception('lti_bad_alg', 'local_sentientia_api');
        }

        if (trim($publickey) === '') {
            throw new \moodle_exception('lti_no_key', 'local_sentientia_api');
        }

        // RS256 = RSA + SHA256.
        $ok = openssl_verify($signinginput, $sig, $publickey, OPENSSL_ALGO_SHA256);
        if ($ok !== 1) {
            throw new \moodle_exception('lti_bad_signature', 'local_sentientia_api');
        }

        // Registered-claim validation.
        $now = time();
        if (($payload['iss'] ?? '') !== $expectediss) {
            throw new \moodle_exception('lti_bad_iss', 'local_sentientia_api');
        }
        if (!self::aud_matches($payload['aud'] ?? '', $expectedaud)) {
            throw new \moodle_exception('lti_bad_aud', 'local_sentientia_api');
        }
        if (!isset($payload['exp']) || ($payload['exp'] + self::LEEWAY) < $now) {
            throw new \moodle_exception('lti_expired', 'local_sentientia_api');
        }
        if (isset($payload['iat']) && ($payload['iat'] - self::LEEWAY) > $now) {
            throw new \moodle_exception('lti_bad_iat', 'local_sentientia_api');
        }
        if ($expectednonce !== null && ($payload['nonce'] ?? '') !== $expectednonce) {
            throw new \moodle_exception('lti_bad_nonce', 'local_sentientia_api');
        }

        return $payload;
    }

    /**
     * `aud` may be a string or an array per the JWT spec.
     */
    private static function aud_matches($aud, string $expected): bool {
        if (is_array($aud)) {
            return in_array($expected, $aud, true);
        }
        return $aud === $expected;
    }

    /**
     * URL-safe base64 decode.
     *
     * @param string $data
     * @return string|false
     */
    public static function b64url_decode(string $data) {
        $remainder = strlen($data) % 4;
        if ($remainder) {
            $data .= str_repeat('=', 4 - $remainder);
        }
        return base64_decode(strtr($data, '-_', '+/'), true);
    }

    /**
     * URL-safe base64 encode.
     *
     * @param string $data
     * @return string
     */
    public static function b64url_encode(string $data): string {
        return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
    }
}
