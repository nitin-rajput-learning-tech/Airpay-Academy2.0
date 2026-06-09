<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

namespace local_sentientia_pwa;

defined('MOODLE_INTERNAL') || die();

/**
 * Web Push payload encrypter (aes128gcm content coding) — Phase B.2.5.
 *
 * Implements:
 *   - RFC 8291 §3 — Message Encryption for Web Push
 *   - RFC 8188 §2 — Encrypted Content-Encoding for HTTP (aes128gcm)
 *
 * The encoding wraps an arbitrary plaintext (typically a JSON push
 * payload) into the binary form push services expect:
 *
 *   salt(16) || rs(4, BE) || idlen(1) || keyid || ciphertext(N) || tag(16)
 *
 * Where:
 *   salt   = 16 random bytes (input to HKDF for CEK + NONCE)
 *   rs     = record size, big-endian uint32. We use 4096 (single record).
 *   idlen  = length of keyid. For Web Push this is 65 (uncompressed point).
 *   keyid  = our ephemeral as_public_key, uncompressed (0x04 || X || Y)
 *   ciphertext = AES-128-GCM(CEK, NONCE, plaintext_padded)
 *   tag    = AES-GCM authentication tag (16 bytes, separate from ciphertext)
 *
 * Plaintext padding (RFC 8188 §2.1):
 *   plaintext_padded = plaintext || 0x02 || 0x00 * pad_len
 *   The 0x02 is the "last record" delimiter (since we only emit one record).
 *
 * SECURITY: see SECURITY NOTE in jwt_signer.php — this is hand-rolled
 * crypto. cli/test_crypto_vectors validates against RFC 8291 §5 vectors.
 *
 * @package local_sentientia_pwa
 */
class payload_encrypter {

    /** Single-record size hint. Push services typically cap at 4096 total bytes. */
    public const RECORD_SIZE = 4096;

    /** Length of ECDH-derived shared secret (P-256). */
    private const ECDH_SECRET_LENGTH = 32;

    /** Auth secret (auth) from PushSubscription is always 16 bytes. */
    private const AUTH_SECRET_LENGTH = 16;

    /** Subscription public key (p256dh) is uncompressed point — 65 bytes. */
    private const UA_PUBLIC_LENGTH = 65;

    /**
     * Encrypt a plaintext payload for a specific subscription.
     *
     * @param string $plaintext       The bytes to encrypt (typically JSON).
     * @param string $ua_public_b64url Subscription.keys.p256dh (base64url).
     * @param string $auth_b64url      Subscription.keys.auth (base64url).
     * @return array{
     *           ciphertext: string,    // binary, includes salt+rs+idlen+keyid+ct+tag
     *           as_public:  string,    // our ephemeral public key (raw 65 bytes)
     *           salt:       string     // raw 16-byte salt
     *         }
     * @throws \moodle_exception On crypto failure.
     */
    public static function encrypt_for_subscription(string $plaintext,
                                                     string $ua_public_b64url,
                                                     string $auth_b64url): array {
        $ua_public = vapid_key_manager::b64url_decode($ua_public_b64url);
        $auth      = vapid_key_manager::b64url_decode($auth_b64url);

        if (strlen($ua_public) !== self::UA_PUBLIC_LENGTH) {
            throw new \moodle_exception('invalid_ua_public', 'local_sentientia_pwa',
                '', 'p256dh length ' . strlen($ua_public) . ' != 65');
        }
        if (strlen($auth) !== self::AUTH_SECRET_LENGTH) {
            throw new \moodle_exception('invalid_auth_secret', 'local_sentientia_pwa',
                '', 'auth length ' . strlen($auth) . ' != 16');
        }

        // 1. Generate ephemeral P-256 keypair (one per message — never reused).
        [$as_public, $as_private_pem] = self::generate_ephemeral_keypair();

        // 2. Random 16-byte salt.
        $salt = random_bytes(16);

        // 3. ECDH shared secret using our ephemeral private + subscriber's public.
        $ecdh_secret = self::ecdh_shared_secret($as_private_pem, $ua_public);

        // 4. Derive IKM = HKDF-Expand(HMAC(auth, ecdh_secret), key_info, 32)
        //    where key_info = "WebPush: info\x00" || ua_public || as_public
        $prk_key = self::hmac_sha256($auth, $ecdh_secret);
        $key_info = "WebPush: info\x00" . $ua_public . $as_public;
        $ikm = self::hkdf_expand($prk_key, $key_info, 32);

        // 5. Derive CEK (content encryption key, 16 bytes) and NONCE (12 bytes).
        //    RFC 8188 §2.2: PRK = HMAC-SHA-256(salt, IKM)
        $prk = self::hmac_sha256($salt, $ikm);
        $cek = self::hkdf_expand($prk, "Content-Encoding: aes128gcm\x00", 16);
        $nonce = self::hkdf_expand($prk, "Content-Encoding: nonce\x00", 12);

        // 6. Pad plaintext: append 0x02 (last-record delimiter) + zeros.
        //    Audit fix NB-13 (2026-05-22) — bucket the padded length to
        //    a 256-byte boundary so a network observer can't read off
        //    the exact plaintext size. RFC 8291 §3 explicitly
        //    recommends padding: "applications SHOULD pad messages to
        //    a common size to avoid leaking message length". The
        //    receiver strips trailing zeros via the existing
        //    preg_replace('/\x02\x00*$/', ...) — so the bucketed
        //    padding is transparent to mock_receiver + real browsers.
        $plaintext_padded = $plaintext . "\x02";
        $target_size = self::pad_target_size(strlen($plaintext_padded));
        if (strlen($plaintext_padded) < $target_size) {
            $plaintext_padded .= str_repeat("\x00",
                $target_size - strlen($plaintext_padded));
        }

        // 7. AES-128-GCM encryption — PHP returns ciphertext sans tag, tag fills $tag.
        $tag = '';
        $ciphertext = openssl_encrypt(
            $plaintext_padded,
            'aes-128-gcm',
            $cek,
            OPENSSL_RAW_DATA,
            $nonce,
            $tag,
            '',  // no associated data
            16   // tag length
        );

        if ($ciphertext === false) {
            throw new \moodle_exception('encryption_failed', 'local_sentientia_pwa',
                '', openssl_error_string() ?: 'aes-128-gcm failed');
        }

        // 8. Assemble the aes128gcm-encoded record:
        //    salt(16) || rs(4 BE) || idlen(1) || keyid(65) || ciphertext+tag
        $rs_bytes = pack('N', self::RECORD_SIZE);  // big-endian uint32
        $idlen_byte = chr(self::UA_PUBLIC_LENGTH);  // wait — keyid IS as_public

        // Correction: keyid IS our ephemeral public key (as_public), not ua_public.
        // The subscriber's public key is implicit (they already know it).
        $header = $salt . $rs_bytes . chr(strlen($as_public)) . $as_public;
        $body = $ciphertext . $tag;

        return [
            'ciphertext' => $header . $body,
            'as_public'  => $as_public,
            'salt'       => $salt,
        ];
    }

    /**
     * HKDF-Expand per RFC 5869 §2.3.
     *
     * Audit fix NB-10 (2026-05-22) — promoted from "≤ 32-byte only" to
     * the full RFC 5869 §2.3 loop. Web Push itself never needs more
     * than 32 bytes per call, but future related features (encrypted
     * push receipts, expanded record types) might, and the spec-
     * compliant loop is cheap to implement.
     *
     *     T(0) = ""
     *     T(N) = HMAC-SHA-256(PRK, T(N-1) || info || N)   for N = 1..ceil(L/32)
     *     OKM  = first L bytes of T(1) || T(2) || ... || T(N)
     *
     * Max output: 255 * 32 = 8160 bytes (RFC 5869 §2.3 limit).
     *
     * @param string $prk        Pseudorandom key (32 bytes — HMAC PRK).
     * @param string $info       Context-and-application-specific info.
     * @param int    $output_len Desired output length in bytes (1..8160).
     * @return string $output_len bytes.
     */
    public static function hkdf_expand(string $prk, string $info, int $output_len): string {
        if ($output_len <= 0 || $output_len > (255 * 32)) {
            throw new \moodle_exception('hkdf_bad_length', 'local_sentientia_pwa',
                '', 'HKDF output length must be 1..' . (255 * 32)
                . '; got ' . $output_len);
        }
        $n = (int) ceil($output_len / 32);
        $okm = '';
        $previous = '';
        for ($i = 1; $i <= $n; $i++) {
            $previous = self::hmac_sha256($prk, $previous . $info . chr($i));
            $okm .= $previous;
        }
        return substr($okm, 0, $output_len);
    }

    /**
     * Audit fix NB-13 (2026-05-22) — pad-target bucketing.
     *
     * Buckets the (already 0x02-delimited) padded plaintext to the
     * next 256-byte boundary, capped at the record-size minus the
     * AEAD tag (16 bytes) and the record header overhead the caller
     * already accounts for. The cap (RECORD_SIZE - 16) means we
     * never overflow into the next "record" — Web Push is single-
     * record (rs is set high enough to fit), but the cap is the
     * mathematical max.
     *
     * Why 256-byte buckets and not e.g. 1024:
     *   - 256 is the smallest power-of-two boundary that hides the
     *     difference between a 12-char title ("Order shipped") and a
     *     180-char body ("Your KYC course expires …").
     *   - Larger buckets waste bandwidth (per-push cost on FCM/APNs
     *     is per-message, not per-byte, but mobile data isn't free).
     *
     * @param int $current_size Length of the 0x02-delimited plaintext.
     * @return int Target padded length.
     */
    private static function pad_target_size(int $current_size): int {
        $bucket = ((int) ceil($current_size / 256)) * 256;
        $max = self::RECORD_SIZE - 16;  // -16 for the AEAD tag
        return min($bucket, $max);
    }

    /**
     * Compute ECDH shared secret using our private key + subscriber's public key.
     *
     * Uses openssl_pkey_derive() (available PHP 7.3+; we're on 8.2).
     *
     * @param string $as_private_pem Our ephemeral private key (PEM).
     * @param string $ua_public_bin  Subscriber's public key (65 bytes uncompressed point).
     * @return string 32-byte shared secret.
     */
    public static function ecdh_shared_secret(string $as_private_pem,
                                                string $ua_public_bin): string {
        // openssl_pkey_derive needs the peer's public key as a PEM-wrapped
        // EC point. Build it ourselves — there's no high-level constructor
        // in PHP for "EC public key from raw uncompressed point".
        $ua_public_pem = self::raw_public_to_pem($ua_public_bin);

        $peer_key = openssl_pkey_get_public($ua_public_pem);
        if ($peer_key === false) {
            throw new \moodle_exception('invalid_ua_public', 'local_sentientia_pwa',
                '', 'openssl_pkey_get_public: ' . openssl_error_string());
        }

        $shared = openssl_pkey_derive($peer_key, $as_private_pem, self::ECDH_SECRET_LENGTH);
        if ($shared === false) {
            throw new \moodle_exception('ecdh_failed', 'local_sentientia_pwa',
                '', openssl_error_string() ?: 'openssl_pkey_derive failed');
        }
        if (strlen($shared) !== self::ECDH_SECRET_LENGTH) {
            // Pad if openssl emitted leading zeros (rare).
            $shared = str_pad($shared, self::ECDH_SECRET_LENGTH, "\x00", STR_PAD_LEFT);
        }
        return $shared;
    }

    /**
     * Generate ephemeral P-256 keypair for one push message.
     *
     * Returns [public_raw_65_bytes, private_pem].
     */
    public static function generate_ephemeral_keypair(): array {
        $config_args = [
            'curve_name'       => 'prime256v1',
            'private_key_type' => OPENSSL_KEYTYPE_EC,
        ];
        // Reuse the openssl.cnf autodetect from vapid_key_manager.
        $config_path = self::find_openssl_config();
        if ($config_path !== null) {
            $config_args['config'] = $config_path;
        }
        $resource = openssl_pkey_new($config_args);
        if ($resource === false) {
            throw new \moodle_exception('vapid_generation_failed',
                'local_sentientia_pwa', '',
                openssl_error_string() ?: 'ephemeral keypair gen failed');
        }
        $details = openssl_pkey_get_details($resource);
        $x = str_pad($details['ec']['x'], 32, "\x00", STR_PAD_LEFT);
        $y = str_pad($details['ec']['y'], 32, "\x00", STR_PAD_LEFT);
        $public = "\x04" . $x . $y;

        // openssl_pkey_export needs the same Windows config workaround.
        $pem = '';
        $export_opts = $config_path !== null ? ['config' => $config_path] : [];
        $ok = openssl_pkey_export($resource, $pem, null, $export_opts);
        if (!$ok || empty($pem)) {
            throw new \moodle_exception('vapid_generation_failed',
                'local_sentientia_pwa', '',
                'ephemeral openssl_pkey_export failed: '
                . (openssl_error_string() ?: 'unknown'));
        }

        return [$public, $pem];
    }

    /**
     * HMAC-SHA-256 — convenience wrapper for raw binary input/output.
     */
    public static function hmac_sha256(string $key, string $data): string {
        return hash_hmac('sha256', $data, $key, true);
    }

    /**
     * Convert raw uncompressed EC public key (65 bytes: 0x04 || X || Y)
     * to a PEM-encoded SubjectPublicKeyInfo block that openssl_pkey_get_public
     * will accept.
     *
     * We hand-build the ASN.1 wrapper:
     *   SEQUENCE
     *     SEQUENCE
     *       OID id-ecPublicKey (1.2.840.10045.2.1)
     *       OID prime256v1     (1.2.840.10045.3.1.7)
     *     BIT STRING  (0x00 || raw 65 bytes)
     *
     * The OID bytes are well-known constants below.
     */
    public static function raw_public_to_pem(string $raw_public): string {
        if (strlen($raw_public) !== 65 || $raw_public[0] !== "\x04") {
            throw new \moodle_exception('invalid_ua_public', 'local_sentientia_pwa',
                '', 'Expected 65-byte uncompressed point (0x04 prefix)');
        }

        // Constants — the encoded form of the two OIDs we need, with their
        // ASN.1 tag + length prefixes. These are stable.
        $oid_ec_pub  = "\x06\x07\x2a\x86\x48\xce\x3d\x02\x01";           // id-ecPublicKey
        $oid_p256    = "\x06\x08\x2a\x86\x48\xce\x3d\x03\x01\x07";       // prime256v1

        $algo_seq = "\x30" . chr(strlen($oid_ec_pub) + strlen($oid_p256))
                  . $oid_ec_pub . $oid_p256;

        // BIT STRING: tag 0x03, then length, then 0x00 (unused bits), then raw key.
        $bitstring = "\x03" . chr(strlen($raw_public) + 1) . "\x00" . $raw_public;

        $body = $algo_seq . $bitstring;
        $der = "\x30" . self::asn1_length(strlen($body)) . $body;

        $b64 = chunk_split(base64_encode($der), 64, "\n");
        return "-----BEGIN PUBLIC KEY-----\n" . $b64 . "-----END PUBLIC KEY-----\n";
    }

    /**
     * ASN.1 length encoding. Short form (< 128) = single byte. Long form
     * (>= 128) = 0x81 || len if len < 256, 0x82 || len_hi || len_lo if < 65536.
     * For our SPKI structure the total body is ~91 bytes — always short form,
     * but we implement long form anyway for robustness.
     */
    private static function asn1_length(int $len): string {
        if ($len < 128) {
            return chr($len);
        }
        if ($len < 256) {
            return "\x81" . chr($len);
        }
        if ($len < 65536) {
            return "\x82" . chr(($len >> 8) & 0xff) . chr($len & 0xff);
        }
        throw new \moodle_exception('asn1_length_too_large', 'local_sentientia_pwa');
    }

    /**
     * Same openssl_conf autodetect as vapid_key_manager — duplicated here so
     * the encrypter can stand alone if vapid_key_manager isn't loaded.
     */
    private static function find_openssl_config(): ?string {
        $env = getenv('OPENSSL_CONF');
        $candidates = [
            $env,
            'C:\\xampp\\php\\extras\\openssl\\openssl.cnf',
            'C:\\xampp\\apache\\conf\\openssl.cnf',
            '/etc/ssl/openssl.cnf',
            '/etc/pki/tls/openssl.cnf',
            '/usr/lib/ssl/openssl.cnf',
            '/usr/local/etc/openssl/openssl.cnf',
            '/opt/homebrew/etc/openssl/openssl.cnf',
        ];
        foreach ($candidates as $path) {
            if ($path && is_string($path) && file_exists($path) && is_readable($path)) {
                return $path;
            }
        }
        return null;
    }
}
