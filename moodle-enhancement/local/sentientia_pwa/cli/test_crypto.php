<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

/**
 * Self-consistency tests for the Phase B.2.5 hand-rolled crypto pipeline.
 *
 * Runs ROUNDTRIP tests to verify:
 *   1. VAPID JWT signs + verifies with our own public key
 *   2. RFC 8291 aes128gcm encryption + decryption are mutually consistent
 *      (encrypt with as_keypair, decrypt with ua_keypair — match plaintext)
 *   3. DER ↔ JOSE signature conversion is bidirectionally consistent
 *
 * Usage:
 *   php local/sentientia_pwa/cli/test_crypto.php
 *
 * Exit code 0 = all green. Exit code 1 = at least one test failed.
 * Run this BEFORE flipping sentientia.pwa.push.enabled ON in production.
 *
 * @package local_sentientia_pwa
 */

define('CLI_SCRIPT', true);

require(__DIR__ . '/../../../config.php');
require_once($CFG->libdir . '/clilib.php');

$failed = 0;
$passed = 0;

function test_assert(string $name, bool $cond, string $detail = ''): void {
    global $failed, $passed;
    if ($cond) {
        cli_writeln('  [PASS] ' . $name);
        $passed++;
    } else {
        cli_writeln('  [FAIL] ' . $name . ($detail !== '' ? ' — ' . $detail : ''));
        $failed++;
    }
}

cli_writeln('');
cli_writeln('=== Sentientia LMS PWA — Phase B.2.5 crypto self-tests ===');
cli_writeln('');

// ── Test 1: VAPID JWT roundtrip ──────────────────────────────────────
cli_writeln('Test 1: VAPID JWT sign + verify');

if (!\local_sentientia_pwa\vapid_key_manager::exists()) {
    cli_writeln('  [SKIP] No VAPID keypair stored. Run cli/generate_vapid_keys.php first.');
    cli_writeln('');
} else {
    try {
        $endpoint = 'https://fcm.googleapis.com/fcm/send/abcdef:APA91b...';
        $jwt = \local_sentientia_pwa\jwt_signer::sign_for_endpoint($endpoint, 3600);

        $parts = explode('.', $jwt);
        test_assert('JWT has three segments', count($parts) === 3,
            'got ' . count($parts) . ' segments');

        // Decode and inspect header + claim.
        $header = json_decode(
            \local_sentientia_pwa\vapid_key_manager::b64url_decode($parts[0]),
            true
        );
        test_assert('JWT header.typ === "JWT"', ($header['typ'] ?? '') === 'JWT');
        test_assert('JWT header.alg === "ES256"', ($header['alg'] ?? '') === 'ES256');

        $claim = json_decode(
            \local_sentientia_pwa\vapid_key_manager::b64url_decode($parts[1]),
            true
        );
        test_assert('JWT claim.aud === endpoint origin',
            ($claim['aud'] ?? '') === 'https://fcm.googleapis.com');
        test_assert('JWT claim.exp is in future',
            isset($claim['exp']) && $claim['exp'] > time());
        test_assert('JWT claim.sub set from vapid_subject',
            !empty($claim['sub']));

        // Verify signature using our public key (derived from PEM via openssl).
        $pem = \local_sentientia_pwa\vapid_key_manager::get_private_pem();
        $key_resource = openssl_pkey_get_private($pem);
        $details = openssl_pkey_get_details($key_resource);
        $public_pem = $details['key'];
        $verified = \local_sentientia_pwa\jwt_signer::verify($jwt, $public_pem);
        test_assert('JWT self-verifies with derived public key', $verified);
    } catch (\Throwable $e) {
        test_assert('JWT test threw exception', false, $e->getMessage());
    }
    cli_writeln('');
}

// ── Test 2: DER ↔ JOSE signature roundtrip ────────────────────────────
cli_writeln('Test 2: DER ↔ JOSE signature conversion');

try {
    // Generate a fresh ephemeral keypair to sign with.
    [$pub, $priv_pem] = \local_sentientia_pwa\payload_encrypter::generate_ephemeral_keypair();
    $data = 'hello world';
    $der = '';
    openssl_sign($data, $der, $priv_pem, OPENSSL_ALGO_SHA256);

    $jose = \local_sentientia_pwa\jwt_signer::der_to_jose($der);
    test_assert('DER → JOSE result is exactly 64 bytes', strlen($jose) === 64,
        'got ' . strlen($jose));

    $der_again = \local_sentientia_pwa\jwt_signer::jose_to_der($jose);

    // Verify the round-trip DER signature still verifies (i.e. we didn't
    // corrupt r||s in the conversion).
    // Extract our public key from the same key resource.
    $kp = openssl_pkey_get_private($priv_pem);
    $details = openssl_pkey_get_details($kp);
    $public_pem = $details['key'];

    $verified = openssl_verify($data, $der_again, $public_pem, OPENSSL_ALGO_SHA256);
    test_assert('Round-tripped signature verifies', $verified === 1,
        'openssl_verify returned ' . var_export($verified, true));
} catch (\Throwable $e) {
    test_assert('DER ↔ JOSE test threw exception', false, $e->getMessage());
}
cli_writeln('');

// ── Test 3: aes128gcm encrypt + decrypt roundtrip ─────────────────────
cli_writeln('Test 3: aes128gcm Web Push roundtrip');

try {
    // Generate a "user agent" keypair — pretend we're the subscriber.
    [$ua_public, $ua_private_pem] = \local_sentientia_pwa\payload_encrypter::generate_ephemeral_keypair();
    $auth_secret = random_bytes(16);

    $ua_public_b64url = \local_sentientia_pwa\vapid_key_manager::b64url_encode($ua_public);
    $auth_b64url      = \local_sentientia_pwa\vapid_key_manager::b64url_encode($auth_secret);

    $plaintext = json_encode([
        'title' => 'Test',
        'body'  => 'aes128gcm roundtrip',
        'url'   => 'https://www.airpay.academy/my/'
    ]);

    $result = \local_sentientia_pwa\payload_encrypter::encrypt_for_subscription(
        $plaintext,
        $ua_public_b64url,
        $auth_b64url
    );

    test_assert('Encrypt returns ciphertext', !empty($result['ciphertext']));
    test_assert('Encrypt returns 65-byte as_public', strlen($result['as_public']) === 65);
    test_assert('Encrypt returns 16-byte salt', strlen($result['salt']) === 16);

    // Now decrypt as the "user agent" would.
    $ciphertext_record = $result['ciphertext'];
    // Parse the record header: salt(16) || rs(4 BE) || idlen(1) || keyid(65) || ct+tag
    $offset = 0;
    $r_salt = substr($ciphertext_record, $offset, 16); $offset += 16;
    $r_rs   = unpack('N', substr($ciphertext_record, $offset, 4))[1]; $offset += 4;
    $r_idlen = ord($ciphertext_record[$offset]); $offset += 1;
    $r_keyid = substr($ciphertext_record, $offset, $r_idlen); $offset += $r_idlen;
    $r_ct_and_tag = substr($ciphertext_record, $offset);

    test_assert('Parsed salt matches encrypt result', $r_salt === $result['salt']);
    test_assert('Parsed rs is 4096', $r_rs === 4096);
    test_assert('Parsed keyid matches as_public', $r_keyid === $result['as_public']);

    // Derive the shared secret from the UA side: ua_private + as_public.
    $shared = \local_sentientia_pwa\payload_encrypter::ecdh_shared_secret(
        $ua_private_pem,
        $r_keyid  // the ephemeral as_public from the header
    );
    test_assert('UA-side ECDH yields 32-byte shared secret', strlen($shared) === 32);

    // Same HKDF chain as the encrypter — must produce identical CEK + NONCE.
    $prk_key = hash_hmac('sha256', $shared, $auth_secret, true);
    $key_info = "WebPush: info\x00" . $ua_public . $r_keyid;
    $ikm = substr(hash_hmac('sha256', $key_info . "\x01", $prk_key, true), 0, 32);

    $prk = hash_hmac('sha256', $ikm, $r_salt, true);
    $cek = substr(hash_hmac('sha256', "Content-Encoding: aes128gcm\x00\x01", $prk, true), 0, 16);
    $nonce = substr(hash_hmac('sha256', "Content-Encoding: nonce\x00\x01", $prk, true), 0, 12);

    // Split ciphertext from tag (last 16 bytes are the GCM tag).
    $ct = substr($r_ct_and_tag, 0, -16);
    $tag = substr($r_ct_and_tag, -16);

    $decrypted_padded = openssl_decrypt($ct, 'aes-128-gcm', $cek,
        OPENSSL_RAW_DATA, $nonce, $tag);

    test_assert('AES-128-GCM decrypt succeeds', $decrypted_padded !== false,
        'openssl_decrypt returned false — openssl: ' . openssl_error_string());

    if ($decrypted_padded !== false) {
        // Strip the 0x02 delimiter + zero padding.
        $decrypted = preg_replace('/\x02\x00*$/', '', $decrypted_padded);
        test_assert('Decrypted plaintext matches original', $decrypted === $plaintext,
            'expected: ' . $plaintext . ' / got: ' . substr($decrypted_padded, 0, 100));
    }
} catch (\Throwable $e) {
    test_assert('aes128gcm test threw exception', false,
        $e->getMessage() . ' @ ' . $e->getFile() . ':' . $e->getLine());
}
cli_writeln('');

// ── Summary ──
cli_writeln('==========');
cli_writeln(sprintf('Passed: %d   Failed: %d', $passed, $failed));
cli_writeln('');

if ($failed > 0) {
    cli_writeln('!! NOT SAFE TO ENABLE PUSH DELIVERY IN PRODUCTION.');
    cli_writeln('!! Fix the failures above before flipping sentientia.pwa.push.enabled ON.');
    exit(1);
}

cli_writeln('All crypto self-tests passed.');
cli_writeln('Note: this verifies INTERNAL consistency. The full proof is sending');
cli_writeln('a real push that an end-user receives — use cli/test_push.php for that.');
exit(0);
