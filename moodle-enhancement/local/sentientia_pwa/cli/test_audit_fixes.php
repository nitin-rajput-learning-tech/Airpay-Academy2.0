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

echo "\nSummary: $pass passed, $fail failed.\n";
exit($fail > 0 ? 1 : 0);
