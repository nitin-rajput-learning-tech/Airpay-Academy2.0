<?php
// Verify the full sign → verify chain still works with the encrypted
// PEM in storage. If this passes, push delivery (which signs JWTs)
// will also work — the only thing the migration changed is HOW the
// PEM is stored, not the keypair itself.

define('CLI_SCRIPT', true);
require(__DIR__ . '/../../../config.php');

use local_sentientia_pwa\jwt_signer;
use local_sentientia_pwa\vapid_key_manager;

$endpoint = 'https://fcm.googleapis.com/fcm/send/test-token-' . bin2hex(random_bytes(8));

// Sign — exercises unwrap_pem() + openssl_sign() + der_to_jose()
$jwt = jwt_signer::sign_for_endpoint($endpoint);
echo "JWT signed (" . strlen($jwt) . " chars): " . substr($jwt, 0, 60) . "...\n";

// Decode header + claim to confirm shape
[$h64, $c64, $sig64] = explode('.', $jwt);
$header = json_decode(vapid_key_manager::b64url_decode($h64), true);
$claim  = json_decode(vapid_key_manager::b64url_decode($c64), true);

echo "Header: " . json_encode($header) . "\n";
echo "Claim:  " . json_encode($claim) . "\n";

$tests_passed = 0;
$tests_failed = 0;
function expect($got, $desc) {
    global $tests_passed, $tests_failed;
    if ($got) { echo "  PASS — $desc\n"; $tests_passed++; }
    else      { echo "  FAIL — $desc\n"; $tests_failed++; }
}

expect($header['typ'] === 'JWT', "header typ is JWT");
expect($header['alg'] === 'ES256', "header alg is ES256");
expect($claim['aud'] === 'https://fcm.googleapis.com', "aud is fcm origin");
expect(isset($claim['iat']),  "iat claim present (audit fix NB-12)");
expect(isset($claim['exp']),  "exp claim present");
expect($claim['sub'] === vapid_key_manager::get_subject(), "sub matches configured subject");
expect(strlen(vapid_key_manager::b64url_decode($sig64)) === 64, "signature is 64 bytes (P-256 raw r||s)");

// Verify via the PUBLIC key (we have the private, public is derivable)
$pem = vapid_key_manager::get_private_pem();
expect(jwt_signer::verify($jwt, $pem), "JWT verify with same keypair returns true");

// Run-twice — JWT cache (NB-7) should return identical JWT for same origin
$jwt2 = jwt_signer::sign_for_endpoint($endpoint);
expect($jwt === $jwt2, "JWT cache: 2nd sign for same origin returns identical token");

echo "\nSummary: $tests_passed passed, $tests_failed failed.\n";
exit($tests_failed > 0 ? 1 : 0);
