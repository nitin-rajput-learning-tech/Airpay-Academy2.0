<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

/**
 * End-to-end push verification — Phase B verification helper.
 *
 * Orchestrates the full push delivery pipeline:
 *   1. Sets up mock subscription (calls setup_mock_subscription.php logic)
 *   2. Temporarily flips sentientia.pwa.push.enabled ON
 *   3. Calls push_sender::send() with a known plaintext payload
 *   4. Inspects the side-channel file written by mock_receiver.php
 *   5. Verifies the decrypted plaintext matches the original
 *   6. Restores the flag to its previous value
 *   7. Tears down the mock subscription
 *
 * This is the verification gate that proves the full chain — VAPID
 * keypair → JWT signing → ephemeral keypair → ECDH → HKDF → AES-128-GCM
 * → HTTP POST → mock receiver → reverse ECDH → reverse HKDF → AES decrypt
 * → plaintext match — works end-to-end on real network + real openssl.
 *
 * Usage:
 *   php local/sentientia_pwa/cli/run_push_e2e.php --userid=N
 *   php local/sentientia_pwa/cli/run_push_e2e.php --userid=N --keep
 *     (skip teardown so artefacts can be inspected)
 *
 * Exit codes:
 *   0 — all PASS
 *   1 — at least one assertion failed
 *   2 — could not even set up
 *
 * @package local_sentientia_pwa
 */

define('CLI_SCRIPT', true);

require(__DIR__ . '/../../../config.php');
require_once($CFG->libdir . '/clilib.php');

[$options, $unrecognized] = cli_get_params([
    'help'   => false,
    'userid' => 0,
    'keep'   => false,
], [
    'h' => 'help',
    'u' => 'userid',
    'k' => 'keep',
]);

if ($options['help'] || empty($options['userid'])) {
    cli_writeln('Usage: php run_push_e2e.php --userid=N [--keep]');
    cli_writeln('  --keep  skip teardown for inspection');
    exit(2);
}

$userid = (int) $options['userid'];

global $DB;
$user = $DB->get_record('user', ['id' => $userid], 'id, firstname, lastname, deleted, suspended');
if (!$user || $user->deleted || $user->suspended) {
    cli_writeln('User #' . $userid . ' not found / deleted / suspended.');
    exit(2);
}

cli_writeln('');
cli_writeln('=== Sentientia Push end-to-end verification ===');
cli_writeln('User: id=' . $userid . ' name=' . $user->firstname . ' ' . $user->lastname);
cli_writeln('');

$failures = 0;
$assert = function (string $name, bool $cond, string $detail = '') use (&$failures) {
    if ($cond) {
        cli_writeln('  [PASS] ' . $name);
    } else {
        cli_writeln('  [FAIL] ' . $name . ($detail !== '' ? ' — ' . $detail : ''));
        $failures++;
    }
};

// ── Step 0: VAPID keypair must exist ──
cli_writeln('Step 0: VAPID keypair check');
$assert('VAPID keypair exists', \local_sentientia_pwa\vapid_key_manager::exists(),
    'run cli/generate_vapid_keys.php first');
if (!\local_sentientia_pwa\vapid_key_manager::exists()) {
    exit(2);
}
cli_writeln('');

// ── Step 1: Set up mock subscription ──
cli_writeln('Step 1: Set up mock subscription');

// Inline the setup logic (mirrors setup_mock_subscription.php).
$openssl_conf = null;
foreach ([
    getenv('OPENSSL_CONF'),
    'C:\\xampp\\php\\extras\\openssl\\openssl.cnf',
    '/etc/ssl/openssl.cnf',
    '/etc/pki/tls/openssl.cnf',
    '/usr/lib/ssl/openssl.cnf',
] as $p) {
    if ($p && is_string($p) && file_exists($p) && is_readable($p)) {
        $openssl_conf = $p;
        break;
    }
}

$keygen_args = ['curve_name' => 'prime256v1',
                'private_key_type' => OPENSSL_KEYTYPE_EC];
if ($openssl_conf !== null) {
    $keygen_args['config'] = $openssl_conf;
}
$resource = openssl_pkey_new($keygen_args);
if ($resource === false) {
    cli_writeln('!! openssl_pkey_new failed: ' . openssl_error_string());
    exit(2);
}
$details = openssl_pkey_get_details($resource);
$ua_public_bin = "\x04"
    . str_pad($details['ec']['x'], 32, "\x00", STR_PAD_LEFT)
    . str_pad($details['ec']['y'], 32, "\x00", STR_PAD_LEFT);
$ua_public_b64url = \local_sentientia_pwa\vapid_key_manager::b64url_encode($ua_public_bin);

$private_pem = '';
openssl_pkey_export($resource, $private_pem, null,
    $openssl_conf !== null ? ['config' => $openssl_conf] : []);

$auth_bin = random_bytes(16);
$auth_b64url = \local_sentientia_pwa\vapid_key_manager::b64url_encode($auth_bin);

$mock_dir = $CFG->dataroot . '/sentientia_pwa_mock';
if (!is_dir($mock_dir)) {
    @mkdir($mock_dir, 0700, true);
}

$creds = [
    'userid'           => $userid,
    'ua_public_b64url' => $ua_public_b64url,
    'auth_b64url'      => $auth_b64url,
    'private_pem'      => $private_pem,
    'created_at'       => time(),
];
file_put_contents($mock_dir . '/mock_subscriber.json',
    json_encode($creds, JSON_PRETTY_PRINT));

// Remove any prior side-channel write so we can tell the new one fired.
@unlink($mock_dir . '/last_received.txt');

$endpoint = $CFG->wwwroot . '/local/sentientia_pwa/mock_receiver.php?u=' . $userid;
$sub_id = \local_sentientia_pwa\subscription_manager::save(
    $userid, $endpoint, $ua_public_b64url, $auth_b64url,
    'mock e2e test');

cli_writeln('  Mock subscription created (sub_id=' . $sub_id . ')');
cli_writeln('');

// ── Step 2: Temporarily flip flag ON ──
cli_writeln('Step 2: Flip push.enabled flag ON');
$prior_flag = \local_airpay_core\feature_flags::is_enabled('sentientia.pwa.push.enabled');
\local_airpay_core\feature_flags::set('sentientia.pwa.push.enabled', 0, true);
cli_writeln('  Flag was: ' . var_export($prior_flag, true)
    . ' / now: true');
cli_writeln('');

// ── Step 3: Fire push_sender::send ──
// Moodle's curl wrapper has TWO SSRF defences — must relax both:
//   1. curlsecurityblockedhosts — defaults to localhost + private nets
//   2. curlsecurityallowedport — defaults to 80, 443 (XAMPP uses 8080)
// Detect our wwwroot port and add it to allowed list. Restore everything
// after the test.
$prior_blocked = get_config(null, 'curlsecurityblockedhosts');
$prior_allowed_port = get_config(null, 'curlsecurityallowedport');

$wwwroot_port = parse_url($CFG->wwwroot, PHP_URL_PORT) ?: 80;
$new_allowed = $prior_allowed_port === false || $prior_allowed_port === null
    ? (string) $wwwroot_port
    : $prior_allowed_port . "\n" . $wwwroot_port;

set_config('curlsecurityblockedhosts', '');
set_config('curlsecurityallowedport', $new_allowed);
$CFG->curlsecurityblockedhosts = '';
$CFG->curlsecurityallowedport  = $new_allowed;

cli_writeln('Step 3: Call push_sender::send');
cli_writeln('  curlsecurityblockedhosts: cleared');
cli_writeln('  curlsecurityallowedport: + ' . $wwwroot_port);

$payload_title = 'E2E test ' . date('H:i:s');
$payload_body  = 'If the receiver sees this exact string, the chain works.';
$payload_url   = $CFG->wwwroot . '/my/dashboard.php';

$delivered = \local_sentientia_pwa\push_sender::send(
    $userid, $payload_title, $payload_body, $payload_url, 'e2e-test');

// Restore curl security policy immediately.
set_config('curlsecurityblockedhosts', $prior_blocked);
set_config('curlsecurityallowedport', $prior_allowed_port);
$CFG->curlsecurityblockedhosts = $prior_blocked;
$CFG->curlsecurityallowedport  = $prior_allowed_port;

cli_writeln('  push_sender::send() returned: ' . $delivered);
$assert('push_sender delivered at least 1 push', $delivered > 0);
cli_writeln('');

// ── Step 4: Verify the side-channel ──
cli_writeln('Step 4: Verify mock receiver decrypted the payload');
$received_file = $mock_dir . '/last_received.txt';
clearstatcache(true, $received_file);

$assert('side-channel file exists', file_exists($received_file),
    'mock_receiver.php did not write — check Apache error log');

if (file_exists($received_file)) {
    $received = json_decode(file_get_contents($received_file), true);
    $assert('side-channel file is valid JSON', is_array($received));

    if (is_array($received)) {
        $plaintext = $received['plaintext'] ?? '';
        $decoded = json_decode($plaintext, true);
        $assert('plaintext decodes as JSON', is_array($decoded),
            'raw: ' . substr($plaintext, 0, 80));

        if (is_array($decoded)) {
            $assert('payload.title matches', ($decoded['title'] ?? '') === $payload_title,
                'expected: "' . $payload_title . '" / got: "' . ($decoded['title'] ?? '') . '"');
            $assert('payload.body matches',  ($decoded['body']  ?? '') === $payload_body);
            $assert('payload.url matches',   ($decoded['url']   ?? '') === $payload_url);
        }

        $assert('Authorization header was VAPID',
            strpos($received['auth_header_prefix'] ?? '', 'vapid t=') === 0,
            'got: ' . ($received['auth_header_prefix'] ?? 'null'));
        $assert('Content-Encoding header was aes128gcm',
            ($received['content_encoding'] ?? '') === 'aes128gcm',
            'got: ' . ($received['content_encoding'] ?? 'null'));
    }
}

cli_writeln('');

// ── Step 5: Restore flag ──
cli_writeln('Step 5: Restore push.enabled flag');
$set_value = $prior_flag === true ? true : null;
\local_airpay_core\feature_flags::set('sentientia.pwa.push.enabled', 0, $set_value);
cli_writeln('  Flag restored to: ' . var_export($set_value, true));
cli_writeln('');

// ── Step 6: Teardown ──
if (!$options['keep']) {
    cli_writeln('Step 6: Teardown');
    $removed = $DB->delete_records('local_sentientia_push_subs',
        ['id' => $sub_id]);
    @unlink($mock_dir . '/mock_subscriber.json');
    @unlink($mock_dir . '/last_received.txt');
    cli_writeln('  Mock subscription removed.');
    cli_writeln('');
} else {
    cli_writeln('Step 6: Teardown skipped (--keep). Artefacts in:');
    cli_writeln('  ' . $mock_dir);
    cli_writeln('Remember to run cli/teardown_mock.php --userid=' . $userid . ' when done.');
    cli_writeln('');
}

// ── Summary ──
cli_writeln('===========');
if ($failures === 0) {
    cli_writeln('Result: ALL PASS — push pipeline end-to-end works.');
    exit(0);
}
cli_writeln('Result: ' . $failures . ' failure(s).');
exit(1);
