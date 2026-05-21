<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

namespace local_sentientia_pwa;

defined('MOODLE_INTERNAL') || die();

/**
 * ES256 JWT signer for VAPID — Phase B.2.5.
 *
 * Implements RFC 7519 (JWT) + RFC 7518 §3.4 (JWS ES256) + RFC 8292 (VAPID).
 *
 * Produces a JSON Web Token of the form:
 *   header_b64url . claim_b64url . signature_b64url
 * where signature is ECDSA-SHA256 over (header_b64url . "." . claim_b64url),
 * using the P-256 private key from vapid_key_manager.
 *
 * IMPORTANT — DER → raw r||s conversion: openssl_sign() emits ECDSA
 * signatures in DER format (ASN.1 SEQUENCE). JWS ES256 requires raw
 * r||s concatenation, 32 bytes each, no padding bytes. The der_to_jose()
 * method handles that conversion.
 *
 * SECURITY NOTE — Phase B.2.5 ALPHA: this is hand-rolled cryptography.
 * Before flipping sentientia.pwa.push.enabled ON in production, the
 * push_sender + jwt_signer + payload_encrypter trio MUST be reviewed
 * against:
 *   - RFC 7515 §3.1 (JWS Compact Serialization)
 *   - RFC 7518 §3.4 (ECDSA using P-256 and SHA-256)
 *   - RFC 8292 §2 (VAPID JWT claims)
 *   - RFC 8291 (Web Push Message Encryption with aes128gcm)
 * and validated against the published test vectors. The cli/test_crypto_vectors
 * script runs that validation. Do not deploy without a green run there.
 *
 * @package local_sentientia_pwa
 */
class jwt_signer {

    /** JWT expiry — VAPID JWT MUST live ≤ 24h. RFC 8292 §2 best practice: 12h. */
    public const DEFAULT_EXPIRY_SECONDS = 43200;

    /** Maximum allowed expiry per RFC 8292 §2 — push services reject tokens > 24h. */
    public const MAX_EXPIRY_SECONDS = 86400;

    /**
     * Build a signed VAPID JWT for the given push endpoint.
     *
     * @param string $endpoint      The subscription.endpoint URL — we extract
     *                              the origin (scheme://host[:port]) for the
     *                              "aud" claim.
     * @param int    $expiry_seconds How long the JWT is valid. Default 12h.
     * @return string Signed JWT (three base64url segments separated by dots).
     * @throws \moodle_exception On signing failure or missing keypair.
     */
    public static function sign_for_endpoint(string $endpoint,
                                              int $expiry_seconds = self::DEFAULT_EXPIRY_SECONDS): string {
        $expiry_seconds = max(60, min($expiry_seconds, self::MAX_EXPIRY_SECONDS));

        $pem = vapid_key_manager::get_private_pem();
        if (empty($pem)) {
            throw new \moodle_exception('vapid_no_keypair', 'local_sentientia_pwa');
        }

        // RFC 8292 §2: "aud" MUST be the origin of the push service endpoint.
        $origin = self::endpoint_origin($endpoint);
        if ($origin === null) {
            throw new \moodle_exception('invalid_endpoint', 'local_sentientia_pwa');
        }

        $now = time();
        $claim = [
            'aud' => $origin,
            'exp' => $now + $expiry_seconds,
            'sub' => vapid_key_manager::get_subject(),
        ];

        $header = ['typ' => 'JWT', 'alg' => 'ES256'];

        $header_b64 = vapid_key_manager::b64url_encode(self::json_encode_strict($header));
        $claim_b64  = vapid_key_manager::b64url_encode(self::json_encode_strict($claim));
        $signing_input = $header_b64 . '.' . $claim_b64;

        $signature = self::sign_es256($signing_input, $pem);
        $signature_b64 = vapid_key_manager::b64url_encode($signature);

        return $signing_input . '.' . $signature_b64;
    }

    /**
     * Sign data with the VAPID P-256 private key using ECDSA-SHA256.
     *
     * Returns the signature in JWS raw format (64 bytes = r||s, each 32 bytes).
     * openssl_sign() emits DER format; we convert here.
     *
     * @param string $data Bytes to sign.
     * @param string $pem  PEM-encoded EC private key.
     * @return string 64-byte raw signature (r||s).
     * @throws \moodle_exception On openssl failure.
     */
    public static function sign_es256(string $data, string $pem): string {
        $der_signature = '';
        $ok = openssl_sign($data, $der_signature, $pem, OPENSSL_ALGO_SHA256);
        if (!$ok) {
            throw new \moodle_exception('jwt_sign_failed', 'local_sentientia_pwa',
                '', openssl_error_string() ?: 'unknown openssl error');
        }
        return self::der_to_jose($der_signature);
    }

    /**
     * Verify a JWT signature using the VAPID public key. Mainly used by
     * tests — push services do their own verification on the receiving end.
     *
     * @param string $jwt
     * @param string $pem Public key PEM (or matching private key for self-test).
     * @return bool
     */
    public static function verify(string $jwt, string $pem): bool {
        $parts = explode('.', $jwt);
        if (count($parts) !== 3) {
            return false;
        }
        [$header_b64, $claim_b64, $sig_b64] = $parts;
        $signing_input = $header_b64 . '.' . $claim_b64;
        $raw_signature = vapid_key_manager::b64url_decode($sig_b64);
        if (strlen($raw_signature) !== 64) {
            return false;
        }
        $der_signature = self::jose_to_der($raw_signature);
        $result = openssl_verify($signing_input, $der_signature, $pem, OPENSSL_ALGO_SHA256);
        return $result === 1;
    }

    /**
     * Convert DER ECDSA signature (openssl output) → JWS raw r||s format.
     *
     * DER format:   30 LL 02 LR r-bytes 02 LS s-bytes
     * JWS format:   r-padded-to-32 || s-padded-to-32
     *
     * Edge cases handled:
     *   - DER integer may have leading 0x00 byte (added when high bit is
     *     set, to avoid being interpreted as negative). We strip it.
     *   - DER integer may be shorter than 32 bytes when leading zeros were
     *     omitted. We left-pad with 0x00 to reach 32 bytes.
     */
    public static function der_to_jose(string $der): string {
        $offset = 0;

        // SEQUENCE tag
        if (ord($der[$offset]) !== 0x30) {
            throw new \moodle_exception('jwt_sign_failed', 'local_sentientia_pwa',
                '', 'DER signature does not start with SEQUENCE tag');
        }
        $offset++;

        // SEQUENCE length (assume short form: < 128 — true for P-256 since
        // a P-256 ECDSA signature is at most 70-72 bytes total).
        $offset++;  // skip length byte

        // INTEGER tag for r
        if (ord($der[$offset]) !== 0x02) {
            throw new \moodle_exception('jwt_sign_failed', 'local_sentientia_pwa',
                '', 'DER r value missing INTEGER tag');
        }
        $offset++;

        $r_len = ord($der[$offset]);
        $offset++;
        $r = substr($der, $offset, $r_len);
        $offset += $r_len;

        // INTEGER tag for s
        if (ord($der[$offset]) !== 0x02) {
            throw new \moodle_exception('jwt_sign_failed', 'local_sentientia_pwa',
                '', 'DER s value missing INTEGER tag');
        }
        $offset++;

        $s_len = ord($der[$offset]);
        $offset++;
        $s = substr($der, $offset, $s_len);

        // Strip leading 0x00 if present (DER positive-integer marker)
        // and left-pad to exactly 32 bytes each.
        $r = self::pad_or_trim_to_32($r);
        $s = self::pad_or_trim_to_32($s);

        return $r . $s;
    }

    /**
     * Convert JWS raw r||s → DER ECDSA SEQUENCE format.
     * Used only by verify() — push services don't need this from us.
     */
    public static function jose_to_der(string $jose): string {
        if (strlen($jose) !== 64) {
            throw new \moodle_exception('invalid_jose_signature', 'local_sentientia_pwa');
        }
        $r = substr($jose, 0, 32);
        $s = substr($jose, 32, 32);

        // Add 0x00 prefix if high bit is set (DER positive-integer requirement).
        if (ord($r[0]) >= 0x80) {
            $r = "\x00" . $r;
        }
        if (ord($s[0]) >= 0x80) {
            $s = "\x00" . $s;
        }

        // Strip leading zeros (one allowed — for positive marker)
        while (strlen($r) > 1 && $r[0] === "\x00" && ord($r[1]) < 0x80) {
            $r = substr($r, 1);
        }
        while (strlen($s) > 1 && $s[0] === "\x00" && ord($s[1]) < 0x80) {
            $s = substr($s, 1);
        }

        $r_seg = "\x02" . chr(strlen($r)) . $r;
        $s_seg = "\x02" . chr(strlen($s)) . $s;
        $seq_body = $r_seg . $s_seg;
        return "\x30" . chr(strlen($seq_body)) . $seq_body;
    }

    /**
     * Helper — left-pad or trim a DER integer payload to exactly 32 bytes.
     */
    private static function pad_or_trim_to_32(string $value): string {
        $len = strlen($value);
        if ($len === 32) {
            return $value;
        }
        if ($len === 33 && $value[0] === "\x00") {
            // Strip leading positive-marker zero.
            return substr($value, 1);
        }
        if ($len < 32) {
            // Left-pad with zeros.
            return str_pad($value, 32, "\x00", STR_PAD_LEFT);
        }
        // Larger than 33 or 33 without leading zero → malformed.
        throw new \moodle_exception('jwt_sign_failed', 'local_sentientia_pwa',
            '', 'DER integer length ' . $len . ' cannot fit P-256 r/s slot');
    }

    /**
     * Extract scheme://host[:port] from a push endpoint URL.
     * Returns null if the URL is unparseable or missing scheme/host.
     */
    public static function endpoint_origin(string $endpoint): ?string {
        $parts = parse_url($endpoint);
        if (!isset($parts['scheme'], $parts['host'])) {
            return null;
        }
        $origin = $parts['scheme'] . '://' . $parts['host'];
        if (isset($parts['port'])) {
            $origin .= ':' . $parts['port'];
        }
        return $origin;
    }

    /**
     * JSON-encode without HTML-escaping (the default escapes /, < etc which
     * is unnecessary for JWT and adds bytes). Throws on encode failure.
     */
    private static function json_encode_strict(array $data): string {
        $json = json_encode($data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        if ($json === false) {
            throw new \moodle_exception('jwt_sign_failed', 'local_sentientia_pwa',
                '', 'JSON encode failed: ' . json_last_error_msg());
        }
        return $json;
    }
}
