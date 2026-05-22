<?php
// One-shot: re-encrypt the existing VAPID PEM under the configured
// master key WITHOUT regenerating the keypair (so existing subscriptions
// stay valid). Idempotent — running on already-encrypted blob is a no-op.

define('CLI_SCRIPT', true);
require(__DIR__ . '/../../../config.php');

use local_sentientia_pwa\vapid_key_manager;

if (!vapid_key_manager::master_key_configured()) {
    fwrite(STDERR, "Master key not configured. Set SENTIENTIA_VAPID_MASTER_KEY env var\n"
        . "or \$CFG->sentientia_vapid_master_key in config.php first.\n");
    exit(1);
}

// Read raw stored value (do NOT use get_private_pem — that unwraps,
// which is what we'd want except we need to detect already-wrapped state).
$stored = get_config('local_sentientia_pwa', 'vapid_private_pem');
if ($stored === false || $stored === '') {
    fwrite(STDERR, "No VAPID PEM stored. Run vapid_keygen.php first.\n");
    exit(1);
}

if (strpos($stored, 'enc:v1:') === 0) {
    echo "Already encrypted (enc:v1: prefix). Nothing to do.\n";
    exit(0);
}

echo "Stored value is legacy plaintext PEM (" . strlen($stored) . " bytes).\n";
echo "Wrapping with master-key envelope...\n";

$wrapped = vapid_key_manager::wrap_pem($stored);
if (strpos($wrapped, 'enc:v1:') !== 0) {
    fwrite(STDERR, "wrap_pem() returned plaintext — master key not picked up?\n");
    exit(1);
}

set_config('vapid_private_pem', $wrapped, 'local_sentientia_pwa');
echo "Stored " . strlen($wrapped) . " bytes under enc:v1: envelope.\n";

// Verify round-trip
$roundtrip = vapid_key_manager::get_private_pem();
if ($roundtrip === $stored) {
    echo "Round-trip decrypt: IDENTICAL to original plaintext (PASS)\n";
    exit(0);
}
fwrite(STDERR, "Round-trip MISMATCH — DB now holds an unreadable blob. ROLLBACK.\n");
set_config('vapid_private_pem', $stored, 'local_sentientia_pwa');
fwrite(STDERR, "Rolled back to original plaintext PEM.\n");
exit(2);
