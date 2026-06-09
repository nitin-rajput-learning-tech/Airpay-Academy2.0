<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

/**
 * Mock-subscriber setup — Phase B verification helper.
 *
 * Inserts a fake push subscription so cli/run_push_e2e.php can exercise
 * the full delivery pipeline without needing a real browser to call
 * pushManager.subscribe(). The mock subscriber:
 *   - has a real P-256 ECDH keypair (generated below)
 *   - has a real 16-byte auth_secret
 *   - has its endpoint pointing at /local/sentientia_pwa/mock_receiver.php
 *     on this same Moodle host (which decrypts the payload using the
 *     subscriber-side private key we just saved).
 *
 * Private credentials are saved to <dataroot>/sentientia_pwa_mock/
 * (NOT a git-tracked location — this is dev/test infrastructure only).
 * The receiver reads from there.
 *
 * Usage:
 *   php local/sentientia_pwa/cli/setup_mock_subscription.php --userid=N
 *
 * @package local_sentientia_pwa
 */

define('CLI_SCRIPT', true);

require(__DIR__ . '/../../../config.php');
require_once($CFG->libdir . '/clilib.php');

[$options, $unrecognized] = cli_get_params([
    'help'   => false,
    'userid' => 0,
], [
    'h' => 'help',
    'u' => 'userid',
]);

if ($options['help'] || empty($options['userid'])) {
    echo <<<EOT

Set up a mock push subscription for end-to-end testing.

Usage:
  php local/sentientia_pwa/cli/setup_mock_subscription.php --userid=N

The mock subscription points at /local/sentientia_pwa/mock_receiver.php
which decrypts the incoming push payload using credentials we save to
{moodledataroot}/sentientia_pwa_mock/.

To exercise the full delivery pipeline after this runs:
  1. Toggle sentientia.pwa.push.enabled ON (Switchboard or CLI)
  2. php local/sentientia_pwa/cli/test_push.php --userid=N
  3. Inspect {moodledataroot}/sentientia_pwa_mock/last_received.txt

To tear down:
  php local/sentientia_pwa/cli/teardown_mock.php --userid=N


EOT;
    exit($options['help'] ? 0 : 1);
}

$userid = (int) $options['userid'];

global $DB;
$user = $DB->get_record('user', ['id' => $userid], 'id, firstname, lastname, deleted, suspended');
if (!$user || $user->deleted || $user->suspended) {
    cli_writeln('User #' . $userid . ' not found, deleted, or suspended.');
    exit(1);
}

// ── Generate P-256 keypair for the mock subscriber ──
cli_writeln('Generating mock subscriber P-256 keypair...');

$cfg_path = \local_sentientia_pwa\vapid_key_manager::b64url_encode('init');  // touches the class autoloader
$openssl_conf = find_openssl_conf();

$keygen_args = [
    'curve_name'       => 'prime256v1',
    'private_key_type' => OPENSSL_KEYTYPE_EC,
];
if ($openssl_conf !== null) {
    $keygen_args['config'] = $openssl_conf;
}

$resource = openssl_pkey_new($keygen_args);
if ($resource === false) {
    cli_writeln('!! ERROR: openssl_pkey_new failed: ' . openssl_error_string());
    exit(1);
}

$details = openssl_pkey_get_details($resource);
$x = str_pad($details['ec']['x'], 32, "\x00", STR_PAD_LEFT);
$y = str_pad($details['ec']['y'], 32, "\x00", STR_PAD_LEFT);
$d = str_pad($details['ec']['d'], 32, "\x00", STR_PAD_LEFT);

// Subscriber's public key — what we put in p256dh.
$ua_public_bin = "\x04" . $x . $y;
$ua_public_b64url = \local_sentientia_pwa\vapid_key_manager::b64url_encode($ua_public_bin);

// Subscriber's private key — saved for the mock receiver to use.
$private_pem = '';
$export_opts = $openssl_conf !== null ? ['config' => $openssl_conf] : [];
$ok = openssl_pkey_export($resource, $private_pem, null, $export_opts);
if (!$ok || empty($private_pem)) {
    cli_writeln('!! ERROR: openssl_pkey_export failed: ' . openssl_error_string());
    exit(1);
}

$auth_secret_bin = random_bytes(16);
$auth_secret_b64url = \local_sentientia_pwa\vapid_key_manager::b64url_encode($auth_secret_bin);

// ── Save credentials for the receiver ──
$mock_dir = $CFG->dataroot . '/sentientia_pwa_mock';
if (!is_dir($mock_dir)) {
    if (!mkdir($mock_dir, 0700, true)) {
        cli_writeln('!! ERROR: could not create ' . $mock_dir);
        exit(1);
    }
}

$creds = [
    'userid'           => $userid,
    'ua_public_b64url' => $ua_public_b64url,
    'auth_b64url'      => $auth_secret_b64url,
    'private_pem'      => $private_pem,
    'created_at'       => time(),
];
file_put_contents($mock_dir . '/mock_subscriber.json',
    json_encode($creds, JSON_PRETTY_PRINT));
chmod($mock_dir . '/mock_subscriber.json', 0600);

cli_writeln('Saved credentials to: ' . $mock_dir . '/mock_subscriber.json');

// ── Insert a push_subs row pointing at the mock receiver ──
$endpoint = $CFG->wwwroot . '/local/sentientia_pwa/mock_receiver.php?u=' . $userid;

$sub_id = \local_sentientia_pwa\subscription_manager::save(
    $userid,
    $endpoint,
    $ua_public_b64url,
    $auth_secret_b64url,
    'Sentientia LMS mock subscriber (' . php_uname() . ')'
);

cli_writeln('');
cli_writeln('Mock subscription created.');
cli_writeln('  userid:        ' . $userid);
cli_writeln('  sub_id:        ' . $sub_id);
cli_writeln('  endpoint:      ' . $endpoint);
cli_writeln('  p256dh (16ch): ' . substr($ua_public_b64url, 0, 16) . '...');
cli_writeln('');
cli_writeln('Next:');
cli_writeln('  php local/sentientia_pwa/cli/run_push_e2e.php --userid=' . $userid);
cli_writeln('OR manually:');
cli_writeln('  1. Toggle sentientia.pwa.push.enabled ON');
cli_writeln('  2. php local/sentientia_pwa/cli/test_push.php --userid=' . $userid);
cli_writeln('  3. cat ' . $mock_dir . '/last_received.txt');
cli_writeln('');

exit(0);


/**
 * Same openssl.cnf autodetect as vapid_key_manager — duplicated here
 * so the mock setup is self-contained even if vapid_key_manager
 * autoloading changes.
 */
function find_openssl_conf(): ?string {
    $candidates = [
        getenv('OPENSSL_CONF'),
        'C:\\xampp\\php\\extras\\openssl\\openssl.cnf',
        'C:\\xampp\\apache\\conf\\openssl.cnf',
        '/etc/ssl/openssl.cnf',
        '/etc/pki/tls/openssl.cnf',
        '/usr/lib/ssl/openssl.cnf',
        '/usr/local/etc/openssl/openssl.cnf',
        '/opt/homebrew/etc/openssl/openssl.cnf',
    ];
    foreach ($candidates as $p) {
        if ($p && is_string($p) && file_exists($p) && is_readable($p)) {
            return $p;
        }
    }
    return null;
}
