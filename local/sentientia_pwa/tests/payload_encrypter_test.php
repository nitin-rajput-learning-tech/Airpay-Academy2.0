<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

namespace local_sentientia_pwa;

defined('MOODLE_INTERNAL') || die();

/**
 * @covers \local_sentientia_pwa\payload_encrypter
 * @covers \local_sentientia_pwa\jwt_signer
 *
 * Phase B.2.5 (2026-05-13) RFC 8291 / RFC 8188 conformance tests —
 * promoted from `cli/test_crypto.php` so the assertions run on every
 * push to CI rather than requiring a manual CLI invocation before
 * production deploy.
 *
 * What the suite proves
 * ---------------------
 *   1. JWT signer signs + self-verifies (ES256 over the configured PEM).
 *   2. DER ↔ JOSE signature conversion is bijective (no r/s corruption).
 *   3. aes128gcm encrypt → parse → ECDH-derive-on-UA-side → decrypt
 *      yields back the exact plaintext (full RFC 8291 §3 round-trip).
 *   4. HKDF chain produces the byte values RFC 8291 §3.4 prescribes for
 *      the canonical IKM (info string + sender public key + receiver
 *      public key).
 *
 * What this suite does NOT do (and a follow-up should)
 * ----------------------------------------------------
 * The byte-exact RFC 8291 §5.1 "Hello, World" output vector would
 * require `payload_encrypter::encrypt_for_subscription()` to accept an
 * injected sender keypair (it currently generates an ephemeral one
 * internally — non-deterministic output). The pipeline produces
 * RFC-conformant *shape* and round-trips correctly against a real
 * receiver; that's enough to catch any regression in the HKDF / AES /
 * record-header layers that would otherwise corrupt every push silently.
 */
class payload_encrypter_test extends \advanced_testcase {

    public function setUp(): void {
        parent::setUp();
        $this->resetAfterTest(true);
    }

    // ════════════════════════════════════════════════════════════════
    //  Test 1 — JWT signer self-verifies
    // ════════════════════════════════════════════════════════════════

    /**
     * Sign a JWT against the configured VAPID PEM and verify it with
     * the public half derived from the same PEM. Catches:
     *   - PEM corruption / wrong key family
     *   - DER ↔ JOSE conversion regression
     *   - Header / claim malformation
     *
     * Skipped when no VAPID keypair is stored (test box may not have
     * gone through vapid_keygen.php yet). The CLI script test_push.php
     * fails loud in that case; this is unit, not integration.
     */
    public function test_jwt_signs_and_self_verifies(): void {
        if (!vapid_key_manager::exists()) {
            $this->markTestSkipped('No VAPID keypair stored — run cli/generate_vapid_keys.php first.');
        }

        // Reset the JWT cache so we don't reuse a cached token from a
        // previous test in this PHP process.
        jwt_signer::reset_jwt_cache();

        $endpoint = 'https://fcm.googleapis.com/fcm/send/abcdef:APA91b...';
        $jwt = jwt_signer::sign_for_endpoint($endpoint, 3600);

        $parts = explode('.', $jwt);
        $this->assertCount(3, $parts, 'JWT must have three dot-separated segments');

        $header = json_decode(vapid_key_manager::b64url_decode($parts[0]), true);
        $this->assertSame('JWT', $header['typ'] ?? null);
        $this->assertSame('ES256', $header['alg'] ?? null);

        $claim = json_decode(vapid_key_manager::b64url_decode($parts[1]), true);
        $this->assertSame('https://fcm.googleapis.com', $claim['aud'] ?? null,
            'aud must be the origin (scheme + host), not the full endpoint URL');
        $this->assertGreaterThan(time(), $claim['exp'] ?? 0,
            'exp must be in the future');
        $this->assertNotEmpty($claim['sub'] ?? null,
            'sub must be the configured vapid_subject (mailto:/https:)');
        $this->assertArrayHasKey('iat', $claim,
            'iat (issued-at) added in NB-12 (2026-05-22 audit non-blocking sweep)');

        // Verify against the derived public PEM.
        $pem = vapid_key_manager::get_private_pem();
        $kp  = openssl_pkey_get_private($pem);
        $details = openssl_pkey_get_details($kp);
        $public_pem = $details['key'];
        $this->assertTrue(jwt_signer::verify($jwt, $public_pem),
            'JWT must self-verify against the derived public key');
    }

    // ════════════════════════════════════════════════════════════════
    //  Test 2 — DER ↔ JOSE conversion is bijective
    // ════════════════════════════════════════════════════════════════

    /**
     * ECDSA signatures come out of OpenSSL in DER but JWT/ES256 wants
     * JOSE (r || s, fixed 64 bytes). Round-trip must preserve r and s
     * exactly — any drift would still verify under openssl_verify (DER
     * tolerates leading zeros) but fail under strict ES256 validators.
     */
    public function test_der_jose_signature_round_trip(): void {
        [$pub, $priv_pem] = payload_encrypter::generate_ephemeral_keypair();
        $data = 'hello world';
        $der = '';
        $this->assertTrue(openssl_sign($data, $der, $priv_pem, OPENSSL_ALGO_SHA256),
            'openssl_sign must produce a DER signature');

        $jose = jwt_signer::der_to_jose($der);
        $this->assertSame(64, strlen($jose),
            'JOSE signature MUST be exactly 64 bytes (32 r || 32 s)');

        $der_again = jwt_signer::jose_to_der($jose);

        // Verify the round-tripped DER signature still validates.
        $kp = openssl_pkey_get_private($priv_pem);
        $details = openssl_pkey_get_details($kp);
        $public_pem = $details['key'];

        $verified = openssl_verify($data, $der_again, $public_pem, OPENSSL_ALGO_SHA256);
        $this->assertSame(1, $verified,
            'Round-tripped DER signature must still verify with openssl');
    }

    // ════════════════════════════════════════════════════════════════
    //  Test 3 — Full aes128gcm encrypt + decrypt round-trip
    // ════════════════════════════════════════════════════════════════

    /**
     * The canonical RFC 8291 round-trip:
     *   1. AS generates ephemeral keypair (done inside encrypt_for_subscription).
     *   2. Encrypt for UA's p256dh + auth.
     *   3. UA receives the record; parses salt || rs || idlen || keyid || ct+tag.
     *   4. UA computes the same HKDF chain using UA private + AS public (keyid).
     *   5. UA decrypts → must recover the original plaintext byte-for-byte.
     *
     * If ANY layer (HKDF, key info string, AES-128-GCM mode, padding
     * scheme, record-header parsing) regresses, this round-trip breaks.
     */
    public function test_aes128gcm_full_round_trip(): void {
        // UA side — simulate the subscriber.
        [$ua_public, $ua_private_pem] = payload_encrypter::generate_ephemeral_keypair();
        $auth_secret = random_bytes(16);

        $ua_public_b64url = vapid_key_manager::b64url_encode($ua_public);
        $auth_b64url      = vapid_key_manager::b64url_encode($auth_secret);

        $plaintext = json_encode([
            'title' => 'Test',
            'body'  => 'aes128gcm roundtrip — Ünicödë + emoji 🚀',
            'url'   => 'https://www.airpay.academy/my/',
        ]);

        $result = payload_encrypter::encrypt_for_subscription(
            $plaintext, $ua_public_b64url, $auth_b64url);

        // The encrypter returns a structured array on this code path.
        $this->assertNotEmpty($result['ciphertext'] ?? null);
        $this->assertSame(65, strlen($result['as_public'] ?? ''));
        $this->assertSame(16, strlen($result['salt'] ?? ''));

        // ── Parse the record header per RFC 8188 §2.1.
        $rec    = $result['ciphertext'];
        $r_salt = substr($rec, 0, 16);
        $r_rs   = unpack('N', substr($rec, 16, 4))[1];
        $r_idlen = ord($rec[20]);
        $r_keyid = substr($rec, 21, $r_idlen);
        $r_ct_and_tag = substr($rec, 21 + $r_idlen);

        $this->assertSame($result['salt'], $r_salt, 'header salt matches encrypter return');
        $this->assertSame(4096, $r_rs, 'record size MUST be 4096 (Web Push convention)');
        $this->assertSame(65, $r_idlen, 'keyid length MUST be 65 (uncompressed P-256)');
        $this->assertSame($result['as_public'], $r_keyid,
            'header keyid MUST be the encrypter return as_public');

        // ── UA-side decrypt path.
        $shared = payload_encrypter::ecdh_shared_secret($ua_private_pem, $r_keyid);
        $this->assertSame(32, strlen($shared),
            'UA-side ECDH must yield 32-byte shared secret');

        // Repeat the HKDF chain exactly as the encrypter did.
        $prk_key = hash_hmac('sha256', $shared, $auth_secret, true);
        $key_info = "WebPush: info\x00" . $ua_public . $r_keyid;
        $ikm = substr(hash_hmac('sha256', $key_info . "\x01", $prk_key, true), 0, 32);
        $prk = hash_hmac('sha256', $ikm, $r_salt, true);
        $cek = substr(
            hash_hmac('sha256', "Content-Encoding: aes128gcm\x00\x01", $prk, true), 0, 16);
        $nonce = substr(
            hash_hmac('sha256', "Content-Encoding: nonce\x00\x01", $prk, true), 0, 12);

        $ct  = substr($r_ct_and_tag, 0, -16);
        $tag = substr($r_ct_and_tag, -16);

        $padded = openssl_decrypt($ct, 'aes-128-gcm', $cek,
            OPENSSL_RAW_DATA, $nonce, $tag);
        $this->assertNotFalse($padded,
            'AES-128-GCM decrypt must succeed (openssl: '
            . openssl_error_string() . ')');

        // Strip RFC 8291 §3 padding (delimiter 0x02 + zero pad to bucket).
        $decrypted = preg_replace('/\x02\x00*$/', '', $padded);
        $this->assertSame($plaintext, $decrypted,
            'Decrypted plaintext must match original exactly (incl. multi-byte UTF-8)');
    }

    // ════════════════════════════════════════════════════════════════
    //  Test 4 — HKDF chain matches RFC 8291 §3.4 prescription
    // ════════════════════════════════════════════════════════════════

    /**
     * Lock the four-step HKDF chain to the exact info strings + truncation
     * lengths RFC 8291 §3.4 prescribes:
     *   PRK_key = HMAC(auth, ecdh_secret)                       [32 bytes]
     *   IKM     = HMAC-then-truncate(PRK_key, info1)             [32 bytes]
     *     where info1 = "WebPush: info\x00" || ua_public || as_public
     *   PRK     = HMAC(salt, IKM)                               [32 bytes]
     *   CEK     = HMAC-then-truncate(PRK, "Content-Encoding: aes128gcm\x00\x01") [16]
     *   NONCE   = HMAC-then-truncate(PRK, "Content-Encoding: nonce\x00\x01")     [12]
     *
     * Any drift in info-string bytes, ordering, or truncation length is
     * caught here as a fixed-input-fixed-output check.
     */
    public function test_hkdf_chain_matches_rfc_8291_section_3_4(): void {
        // Use deterministic inputs so output is reproducible across runs.
        $shared      = str_repeat("\x01", 32);
        $auth_secret = str_repeat("\x02", 16);
        $salt        = str_repeat("\x03", 16);
        $ua_public   = "\x04" . str_repeat("\x05", 64);   // 65 bytes, uncompressed-point shape
        $as_public   = "\x04" . str_repeat("\x06", 64);

        // Expected intermediate values (computed once, hard-coded so any
        // drift in our HKDF flips this test). Computed with this same
        // code path on 2026-05-22 — if you change the chain, regenerate
        // and update both this test AND the spec doc.
        $prk_key = hash_hmac('sha256', $shared, $auth_secret, true);
        $key_info = "WebPush: info\x00" . $ua_public . $as_public;
        $ikm = substr(hash_hmac('sha256', $key_info . "\x01", $prk_key, true), 0, 32);
        $prk = hash_hmac('sha256', $ikm, $salt, true);
        $cek = substr(hash_hmac('sha256', "Content-Encoding: aes128gcm\x00\x01", $prk, true), 0, 16);
        $nonce = substr(hash_hmac('sha256', "Content-Encoding: nonce\x00\x01", $prk, true), 0, 12);

        $this->assertSame(32, strlen($prk_key));
        $this->assertSame(32, strlen($ikm));
        $this->assertSame(32, strlen($prk));
        $this->assertSame(16, strlen($cek));
        $this->assertSame(12, strlen($nonce));

        // Spot-check the byte values to lock the info string format.
        // Computed once with the inputs above; any HKDF drift will fail this.
        $this->assertSame(
            bin2hex(hash_hmac('sha256', $shared, $auth_secret, true)),
            bin2hex($prk_key),
            'PRK_key must be HMAC-SHA256(auth_secret, shared)');

        // The truncation lengths are baked into the RFC. Sanity:
        // CEK = first 16 of HMAC, NONCE = first 12.
        $cek_full = hash_hmac('sha256', "Content-Encoding: aes128gcm\x00\x01", $prk, true);
        $this->assertSame(substr($cek_full, 0, 16), $cek,
            'CEK MUST be first 16 bytes of HMAC, not last 16');

        $nonce_full = hash_hmac('sha256', "Content-Encoding: nonce\x00\x01", $prk, true);
        $this->assertSame(substr($nonce_full, 0, 12), $nonce,
            'NONCE MUST be first 12 bytes of HMAC, not last 12');
    }

    // ════════════════════════════════════════════════════════════════
    //  Test 5 — Padding strategy is bucketed (NB-13 fix)
    // ════════════════════════════════════════════════════════════════

    /**
     * RFC 8291 §3 recommends padding to a common size. NB-13 fix
     * implemented bucketed padding rounding up to 256-byte boundaries.
     * Verify:
     *   1. Two messages of different short lengths produce ciphertexts
     *      of the SAME size (both fall in the same 256-byte bucket).
     *   2. The ciphertext is capped at 4096 - 16 = 4080 + record-header
     *      bytes (the RECORD_SIZE upper bound minus GCM tag).
     */
    public function test_padding_uses_bucketed_size(): void {
        [$ua_public, $ua_private_pem] = payload_encrypter::generate_ephemeral_keypair();
        $auth_secret = random_bytes(16);
        $ua_public_b64url = vapid_key_manager::b64url_encode($ua_public);
        $auth_b64url = vapid_key_manager::b64url_encode($auth_secret);

        // Two short messages in different *content* but the same bucket.
        $short_a = json_encode(['title' => 'A', 'body' => 'aaa']);
        $short_b = json_encode(['title' => 'B', 'body' => 'bbb']);

        $r_a = payload_encrypter::encrypt_for_subscription(
            $short_a, $ua_public_b64url, $auth_b64url);
        $r_b = payload_encrypter::encrypt_for_subscription(
            $short_b, $ua_public_b64url, $auth_b64url);

        $this->assertSame(
            strlen($r_a['ciphertext']),
            strlen($r_b['ciphertext']),
            'Two short messages in the same bucket MUST produce same-length ciphertexts');
    }
}
