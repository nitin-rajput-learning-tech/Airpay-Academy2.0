<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

/**
 * Mock push receiver — Phase B verification helper.
 *
 * WEB endpoint (not CLI). When push_sender POSTs to a mock subscription
 * endpoint, it lands here. We:
 *   1. Read the binary RFC 8188 aes128gcm body
 *   2. Look up the mock subscriber's private key (saved by
 *      cli/setup_mock_subscription.php)
 *   3. Parse the record header (salt + rs + idlen + keyid)
 *   4. ECDH-derive the shared secret using mock_priv + as_public
 *   5. HKDF derive CEK + NONCE
 *   6. AES-128-GCM decrypt → strip 0x02 delimiter → write to side-channel
 *      file <dataroot>/sentientia_pwa_mock/last_received.txt
 *   7. Return 201 Created so push_sender records success
 *
 * SAFETY: this file refuses to do anything if:
 *   - $CFG->dataroot . '/sentientia_pwa_mock/mock_subscriber.json' doesn't exist
 *   - The 'u' query param doesn't match the userid in that file
 *
 * In other words, even if this file ships to production, it's a no-op
 * unless someone explicitly ran setup_mock_subscription.php on the
 * same install.
 *
 * @package local_sentientia_pwa
 */

// No login required — push services don't have Moodle credentials.
// We secure by requiring valid creds file + binary payload shape.
define('NO_MOODLE_COOKIES', true);
define('NO_DEBUG_DISPLAY',  true);

require(__DIR__ . '/../../config.php');

global $CFG, $DB;

// Audit fix #3 — refuse to run unless we're in developer-debug mode.
// This file is a deliberate crypto oracle for the e2e self-test; on a
// production install (debugdeveloper off) it must be a no-op even if
// an attacker plants mock_subscriber.json. See
// `docs/audits/B25-CRYPTO-AUDIT-2026-05-21.md` finding #3.
if (empty($CFG->debugdeveloper)) {
    http_response_code(404);
    exit;
}

// Helper to fail with a status + plain-text body.
$fail = function (int $code, string $msg): void {
    http_response_code($code);
    header('Content-Type: text/plain');
    echo $msg;
    exit;
};

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    $fail(405, 'POST required');
}

// ── Verify the mock cred file exists ──
$mock_dir = $CFG->dataroot . '/sentientia_pwa_mock';
$creds_file = $mock_dir . '/mock_subscriber.json';
if (!file_exists($creds_file)) {
    // No mock set up — this endpoint is a no-op on a normal install.
    $fail(410, 'mock receiver inactive — run setup_mock_subscription.php first');
}

$creds = json_decode(file_get_contents($creds_file), true);
if (!is_array($creds) || empty($creds['private_pem']) || empty($creds['userid'])) {
    $fail(500, 'mock credentials malformed');
}

$expected_userid = (int) $creds['userid'];
$received_userid = (int) ($_GET['u'] ?? 0);
if ($received_userid !== $expected_userid) {
    $fail(403, 'userid mismatch — receiver only validates the configured mock userid');
}

// ── Read the binary body ──
$body = file_get_contents('php://input');
if ($body === false || $body === '') {
    $fail(400, 'empty body');
}

// ── Parse RFC 8188 record header ──
//   salt(16) || rs(4 BE) || idlen(1) || keyid(idlen) || ciphertext+tag
if (strlen($body) < 16 + 4 + 1) {
    $fail(400, 'body too short for header');
}
$offset = 0;
$salt    = substr($body, $offset, 16);                $offset += 16;
$rs      = unpack('N', substr($body, $offset, 4))[1]; $offset += 4;
$idlen   = ord($body[$offset]);                       $offset += 1;
if (strlen($body) < $offset + $idlen + 16) {
    $fail(400, 'body too short for keyid + tag');
}
$keyid   = substr($body, $offset, $idlen);            $offset += $idlen;  // = as_public
$ct_tag  = substr($body, $offset);
$tag     = substr($ct_tag, -16);
$ct      = substr($ct_tag, 0, -16);

if (strlen($keyid) !== 65 || $keyid[0] !== "\x04") {
    $fail(400, 'keyid must be 65-byte uncompressed P-256 point (got ' . strlen($keyid) . ')');
}

// ── ECDH derive shared secret using mock_priv + as_public (keyid) ──
$ua_public_b64url = $creds['ua_public_b64url'];
$auth_b64url      = $creds['auth_b64url'];
$ua_public_bin    = \local_sentientia_pwa\vapid_key_manager::b64url_decode($ua_public_b64url);
$auth_bin         = \local_sentientia_pwa\vapid_key_manager::b64url_decode($auth_b64url);

// Re-build the SPKI PEM for the as_public (sender's ephemeral key)
// so openssl_pkey_get_public accepts it. The encrypter already has
// this routine — reuse it.
$as_public_pem = \local_sentientia_pwa\payload_encrypter::raw_public_to_pem($keyid);
$peer_key = openssl_pkey_get_public($as_public_pem);
if ($peer_key === false) {
    $fail(500, 'openssl_pkey_get_public failed: ' . openssl_error_string());
}

// openssl_pkey_derive needs the mock subscriber's private key.
$shared = openssl_pkey_derive($peer_key, $creds['private_pem'], 32);
if ($shared === false) {
    $fail(500, 'openssl_pkey_derive failed: ' . openssl_error_string());
}
if (strlen($shared) !== 32) {
    $shared = str_pad($shared, 32, "\x00", STR_PAD_LEFT);
}

// ── Re-run the HKDF chain that the encrypter ran ──
// PRK_key = HMAC-SHA-256(auth, ecdh_secret)
$prk_key = hash_hmac('sha256', $shared, $auth_bin, true);
// key_info = "WebPush: info" || 0x00 || ua_public || as_public
$key_info = "WebPush: info\x00" . $ua_public_bin . $keyid;
// IKM = HKDF-Expand(PRK_key, key_info, 32) — single-block since L=32.
$ikm = substr(hash_hmac('sha256', $key_info . "\x01", $prk_key, true), 0, 32);

// PRK = HMAC-SHA-256(salt, IKM)
$prk = hash_hmac('sha256', $ikm, $salt, true);
// CEK = HKDF-Expand(PRK, "Content-Encoding: aes128gcm" || 0x00, 16)
$cek = substr(hash_hmac('sha256',
    "Content-Encoding: aes128gcm\x00\x01", $prk, true), 0, 16);
// NONCE = HKDF-Expand(PRK, "Content-Encoding: nonce" || 0x00, 12)
$nonce = substr(hash_hmac('sha256',
    "Content-Encoding: nonce\x00\x01", $prk, true), 0, 12);

// ── Decrypt ──
$plaintext_padded = openssl_decrypt(
    $ct, 'aes-128-gcm', $cek, OPENSSL_RAW_DATA, $nonce, $tag);
if ($plaintext_padded === false) {
    $fail(500, 'AES-128-GCM decrypt failed (likely wrong CEK/NONCE chain): ' .
        openssl_error_string());
}

// Strip the 0x02 last-record delimiter + zero padding.
$plaintext = preg_replace('/\x02\x00*$/', '', $plaintext_padded);

// ── Inspect request headers ──
// $_SERVER['HTTP_AUTHORIZATION'] is reliable on most setups, but some
// Apache/FastCGI configurations strip Authorization before reaching
// PHP. Fall back to apache_request_headers() or getallheaders() which
// preserve the original header.
$auth_header = $_SERVER['HTTP_AUTHORIZATION']
    ?? $_SERVER['REDIRECT_HTTP_AUTHORIZATION']
    ?? null;
if ($auth_header === null && function_exists('apache_request_headers')) {
    $headers = apache_request_headers();
    foreach ($headers as $name => $value) {
        if (strcasecmp($name, 'Authorization') === 0) {
            $auth_header = $value;
            break;
        }
    }
}
if ($auth_header === null && function_exists('getallheaders')) {
    $headers = getallheaders();
    foreach ($headers as $name => $value) {
        if (strcasecmp($name, 'Authorization') === 0) {
            $auth_header = $value;
            break;
        }
    }
}

// ── Write to side-channel ──
$received_log = $mock_dir . '/last_received.txt';
$entry = [
    'received_at' => date('c'),
    'plaintext'   => $plaintext,
    'ct_bytes'    => strlen($ct),
    'rs'          => $rs,
    'idlen'       => $idlen,
    'has_authorization' => $auth_header !== null,
    'auth_header_prefix' => $auth_header !== null
        ? substr($auth_header, 0, 30) . '...'
        : null,
    'content_encoding' => $_SERVER['HTTP_CONTENT_ENCODING'] ?? null,
];
file_put_contents($received_log,
    json_encode($entry, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
chmod($received_log, 0600);

// ── Respond with 201 Created so push_sender records success ──
http_response_code(201);
header('Content-Type: text/plain');
echo "OK — decrypted " . strlen($plaintext) . " bytes\n";
exit;
