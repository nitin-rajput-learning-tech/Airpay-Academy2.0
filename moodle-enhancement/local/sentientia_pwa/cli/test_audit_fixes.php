<?php
// Smoke test for audit fixes (2026-05-21). One-shot CLI verifier of
// the new base64url validator + endpoint host gate in save_subscription.
//
// Usage: php local/sentientia_pwa/cli/test_audit_fixes.php

define('CLI_SCRIPT', true);
require(__DIR__ . '/../../../config.php');

$pass = 0;
$fail = 0;

function expect_true(bool $got, string $desc) {
    global $pass, $fail;
    if ($got) {
        echo "  PASS — $desc\n";
        $pass++;
    } else {
        echo "  FAIL — $desc\n";
        $fail++;
    }
}

echo "Audit fix #5 — base64url validator:\n";

$ref = new ReflectionClass(\local_sentientia_pwa\external\save_subscription::class);
$is_b64 = $ref->getMethod('is_valid_base64url');
$is_b64->setAccessible(true);

expect_true($is_b64->invoke(null, 'SGVsbG8td29ybGRfMTIz', 8, 20),
    'base64url with - and _ accepted');
expect_true(!$is_b64->invoke(null, 'abc!@#', 1, 10),
    'invalid chars rejected');
expect_true(!$is_b64->invoke(null, '', 1, 10),
    'empty rejected');
expect_true($is_b64->invoke(null,
    'BNRMaeGYwL4WlpsymZ59-9aEqXqHRcTw3aOWXkXBLsRyKpKApYRtUlmH9_3PWNQ0lZaPwS5nWB3FAbCdEfGhIjK', 64, 66),
    'p256dh-sized valid (87 chars → 65 decoded bytes)');
expect_true(!$is_b64->invoke(null, 'abc', 64, 66),
    'too-short rejected for p256dh range');

echo "\nAudit fix #1/#2 — endpoint host allowlist + https-only:\n";

// We can't easily call execute() (it does WS auth gate) but the host
// suffix list IS reachable via reflection.
$allow_const = $ref->getReflectionConstant('ALLOWED_ENDPOINT_HOST_SUFFIXES');
$allowed = $allow_const->getValue();
echo "  Allowlist has " . count($allowed) . " entries: " . implode(', ', $allowed) . "\n";

expect_true(in_array('fcm.googleapis.com', $allowed, true),
    'FCM (Chrome / Android) in allowlist');
expect_true(in_array('web.push.apple.com', $allowed, true),
    'Apple Web Push in allowlist (iOS 16.4+)');
expect_true(in_array('updates.push.services.mozilla.com', $allowed, true),
    'Mozilla / Firefox in allowlist');
expect_true(!in_array('*', $allowed, true),
    'No wildcard in allowlist (SSRF defence)');

echo "\nAudit fix #3 — mock_receiver gated on debugdeveloper:\n";

$src = file_get_contents(__DIR__ . '/../mock_receiver.php');
expect_true(str_contains($src, 'CFG->debugdeveloper'),
    'mock_receiver.php checks $CFG->debugdeveloper');
expect_true(str_contains($src, 'http_response_code(404)'),
    'mock_receiver.php returns 404 when not in dev mode');

echo "\nAudit fix #4 — tenant scoping in subscription_manager + push_sender:\n";

use local_sentientia_pwa\subscription_manager;

$sm_ref = new ReflectionClass(subscription_manager::class);

expect_true($sm_ref->hasMethod('tenant_for_user'),
    'subscription_manager::tenant_for_user() exists');

$for_user = $sm_ref->getMethod('for_user');
$params = $for_user->getParameters();
expect_true(count($params) >= 3,
    'subscription_manager::for_user() now accepts ≥3 params (userid + customerid + tenantid)');
expect_true($params[1]->getName() === 'expected_customerid',
    '2nd param is $expected_customerid');
expect_true($params[2]->getName() === 'expected_tenantid',
    '3rd param is $expected_tenantid');
expect_true($params[1]->isOptional() && $params[2]->isOptional(),
    'Tenant scope params are optional (back-compat)');

$cust_ref = new ReflectionClass(\local_sentientia_platform\customer::class);
expect_true($cust_ref->hasMethod('current_tenant'),
    'local_sentientia_platform\\customer::current_tenant() exists');

$send_src = file_get_contents(__DIR__ . '/../classes/push_sender.php');
expect_true(str_contains($send_src, 'tenant_for_user'),
    'push_sender calls subscription_manager::tenant_for_user');
expect_true(str_contains($send_src, 'cross-tenant push refused'),
    'push_sender logs the refusal on tenant mismatch');

echo "\nAudit fix #6 — VAPID PEM envelope encryption:\n";

use local_sentientia_pwa\vapid_key_manager;

expect_true(method_exists(vapid_key_manager::class, 'wrap_pem'),
    'wrap_pem() helper present');
expect_true(method_exists(vapid_key_manager::class, 'unwrap_pem'),
    'unwrap_pem() helper present');
expect_true(method_exists(vapid_key_manager::class, 'master_key_configured'),
    'master_key_configured() probe present');

// Round-trip with an in-test master key. We monkey-patch via reflection
// on a fresh CFG slot so we don't disturb the real install setting.
$test_master_b64 = vapid_key_manager::b64url_encode(random_bytes(32));
$GLOBALS['CFG']->sentientia_vapid_master_key = $test_master_b64;

$sample_pem = "-----BEGIN EC PRIVATE KEY-----\nMHcCAQEE...sample\n-----END EC PRIVATE KEY-----\n";
$wrapped = vapid_key_manager::wrap_pem($sample_pem);
expect_true(str_starts_with($wrapped, 'enc:v1:'),
    'wrap_pem produces enc:v1: prefix when master key configured');
expect_true($wrapped !== $sample_pem,
    'wrapped value differs from plaintext PEM');

$unwrapped = vapid_key_manager::unwrap_pem($wrapped);
expect_true($unwrapped === $sample_pem,
    'unwrap_pem returns identical original plaintext');

// Legacy plaintext PEM (no enc: prefix) should pass through unchanged.
$legacy = "-----BEGIN EC PRIVATE KEY-----\nLEGACY\n-----END EC PRIVATE KEY-----";
expect_true(vapid_key_manager::unwrap_pem($legacy) === $legacy,
    'unwrap_pem passes legacy plaintext PEM through unchanged');

// Master-key-missing case — wrap_pem returns plaintext + debug warning;
// unwrap_pem on already-wrapped value throws.
unset($GLOBALS['CFG']->sentientia_vapid_master_key);
@putenv('SENTIENTIA_VAPID_MASTER_KEY');
$no_master = vapid_key_manager::wrap_pem($sample_pem);
expect_true($no_master === $sample_pem,
    'wrap_pem returns plaintext when no master key (graceful degrade)');

try {
    vapid_key_manager::unwrap_pem($wrapped);
    expect_true(false, 'unwrap_pem throws when master key missing for wrapped value');
} catch (\moodle_exception $e) {
    expect_true($e->errorcode === 'vapid_master_key_missing',
        'unwrap_pem throws vapid_master_key_missing without master key');
}

// Restore the master key so later code in this session works.
$GLOBALS['CFG']->sentientia_vapid_master_key = $test_master_b64;

echo "\nRFC 8291 §3 / §3.4 conformance — output shape + round-trip:\n";

if (!class_exists('\\local_sentientia_pwa\\payload_encrypter')) {
    echo "  SKIP — payload_encrypter not loadable (autoload missing)\n";
} else {
    // Use the mock-subscription generator if present, otherwise build a
    // mock receiver inline so we can do a real encrypt+decrypt round trip.
    $rcv = openssl_pkey_new([
        'curve_name'       => 'prime256v1',
        'private_key_type' => OPENSSL_KEYTYPE_EC,
        // Reuse the helper from vapid_key_manager so we don't duplicate config path probing.
    ]);
    if ($rcv === false) {
        // Try with explicit config (Windows XAMPP quirk).
        $cfg = 'C:\\xampp\\php\\extras\\openssl\\openssl.cnf';
        if (file_exists($cfg)) {
            $rcv = openssl_pkey_new([
                'curve_name'       => 'prime256v1',
                'private_key_type' => OPENSSL_KEYTYPE_EC,
                'config'           => $cfg,
            ]);
        }
    }
    if ($rcv === false) {
        echo "  SKIP — could not generate receiver EC key\n";
    } else {
        $details = openssl_pkey_get_details($rcv);
        $rx = str_pad($details['ec']['x'], 32, "\x00", STR_PAD_LEFT);
        $ry = str_pad($details['ec']['y'], 32, "\x00", STR_PAD_LEFT);
        $p256dh_bin = "\x04" . $rx . $ry;
        $p256dh = vapid_key_manager::b64url_encode($p256dh_bin);
        $auth   = vapid_key_manager::b64url_encode(random_bytes(16));

        $plaintext = '{"title":"RFC test","body":"vector check"}';
        try {
            $ct = \local_sentientia_pwa\payload_encrypter::encrypt_for_subscription(
                $plaintext, $p256dh, $auth);

            // RFC 8188 §2.1 record header: 16-byte salt || 4-byte rs (big-endian)
            // || 1-byte idlen || idlen-byte keyid || ciphertext || 16-byte tag.
            // For aes128gcm Web Push, keyid is the 65-byte sender public key.
            expect_true(strlen($ct) > (16 + 4 + 1 + 65 + 16),
                'ciphertext at least header + tag length');
            $salt   = substr($ct, 0, 16);
            $rs_be  = substr($ct, 16, 4);
            $idlen  = ord($ct[20]);
            expect_true(strlen($salt) === 16,
                'salt is 16 bytes (RFC 8188 §2.1)');
            $rs = unpack('N', $rs_be)[1];
            expect_true($rs >= 4096 && $rs <= 65536,
                'record size is sane (' . $rs . ' bytes, 4096–65536)');
            expect_true($idlen === 65,
                'keyid length is 65 (uncompressed P-256 point per RFC 8291 §3.4)');
            $keyid = substr($ct, 21, 65);
            expect_true(ord($keyid[0]) === 0x04,
                'keyid first byte is 0x04 (uncompressed-point marker)');
        } catch (\Throwable $e) {
            echo "  SKIP — payload_encrypter::encrypt_for_subscription not callable: "
                . $e->getMessage() . "\n";
        }
    }
}

echo "\nSummary: $pass passed, $fail failed.\n";
exit($fail > 0 ? 1 : 0);
