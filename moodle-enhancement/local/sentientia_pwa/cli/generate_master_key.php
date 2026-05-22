<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

/**
 * VAPID PEM master-key generator — audit fix #6 production gate.
 *
 * Generates a 32-byte cryptographically-random master key suitable
 * for the AES-256-GCM envelope that wraps the VAPID private PEM at
 * rest. Prints the key in base64url form + copy-paste instructions
 * for both env-var (preferred) and $CFG-level installation.
 *
 * The master key NEVER touches the database — that's the entire
 * point of envelope encryption. See
 * `docs/audits/B25-CRYPTO-AUDIT-2026-05-21.md` finding #6.
 *
 * Usage:
 *   php local/sentientia_pwa/cli/generate_master_key.php
 *
 * Production checklist after running:
 *   1. Set SENTIENTIA_VAPID_MASTER_KEY env var (or $CFG-> in config.php)
 *   2. Run vapid_keygen.php --force to re-encrypt the existing PEM
 *      under the new master key (this invalidates every subscription
 *      because regenerate() also nukes the subs table)
 *   3. Coordinate the user-facing "re-enable notifications" message
 *      before flipping sentientia.pwa.push.enabled ON
 *
 * @package local_sentientia_pwa
 */

define('CLI_SCRIPT', true);
require(__DIR__ . '/../../../config.php');

use local_sentientia_pwa\vapid_key_manager;

$master_raw = random_bytes(32);
$master_b64url = vapid_key_manager::b64url_encode($master_raw);

echo "\n";
echo "  Sentientia LMS — VAPID PEM master key generator\n";
echo "  ===============================================\n";
echo "\n";
echo "  Generated 32 random bytes for AES-256-GCM envelope.\n";
echo "  Length: " . strlen($master_raw) . " bytes raw, " . strlen($master_b64url)
    . " chars base64url\n";
echo "\n";
echo "  ───── COPY THE KEY BELOW (base64url, no padding) ─────\n";
echo "\n";
echo "    $master_b64url\n";
echo "\n";
echo "  ───── INSTALLATION (pick ONE) ─────\n";
echo "\n";
echo "  OPTION A — env var (PREFERRED — never on disk):\n";
echo "\n";
echo "    # Linux/macOS — add to systemd unit / docker-compose / .env:\n";
echo "    export SENTIENTIA_VAPID_MASTER_KEY='$master_b64url'\n";
echo "\n";
echo "    # Windows PowerShell — for the apache user:\n";
echo "    [Environment]::SetEnvironmentVariable(\n";
echo "      'SENTIENTIA_VAPID_MASTER_KEY',\n";
echo "      '$master_b64url',\n";
echo "      'Machine')\n";
echo "    # Then restart Apache.\n";
echo "\n";
echo "  OPTION B — config.php (file-system protection only):\n";
echo "\n";
echo "    // Add to <moodle>/public/config.php, BEFORE require_once dirroot/lib/setup.php:\n";
echo "    \$CFG->sentientia_vapid_master_key = '$master_b64url';\n";
echo "\n";
echo "  ───── NEXT STEPS ─────\n";
echo "\n";
echo "  1. Set the key via env or config.php (above)\n";
echo "  2. Restart Apache so the env var (option A) is visible to PHP\n";
echo "  3. Verify the key is reachable from PHP:\n";
echo "       php local/sentientia_pwa/cli/test_audit_fixes.php\n";
echo "       → 'master_key_configured() probe present' should still PASS\n";
echo "  4. Regenerate the VAPID keypair so the PEM is re-encrypted under\n";
echo "     the new master key:\n";
echo "       php local/sentientia_pwa/cli/vapid_keygen.php --force\n";
echo "     ⚠ This INVALIDATES every existing push subscription. Coordinate\n";
echo "     with comms before running in production.\n";
echo "  5. Backup the key OFFLINE (1Password / vault / printed + sealed).\n";
echo "     LOSING IT means losing the ability to read the encrypted PEM →\n";
echo "     every push subscription must be re-collected.\n";
echo "\n";
echo "  ───── SECURITY NOTES ─────\n";
echo "\n";
echo "  - This key is 32 bytes (256 bits) from random_bytes() — CSPRNG.\n";
echo "  - Treat it like a TLS private key: never log it, never email it,\n";
echo "    never commit it. The base64url form printed above is the ONLY\n";
echo "    place it should appear in plaintext.\n";
echo "  - To rotate: generate a new key with this script, set it, then\n";
echo "    run vapid_keygen.php --force. Old encrypted PEMs become\n";
echo "    permanently unreadable — that's the point.\n";
echo "  - The master key does NOT encrypt push payloads (those use a\n";
echo "    separate per-message ephemeral key per RFC 8291 §3). The\n";
echo "    master key only protects the long-lived VAPID PEM at rest.\n";
echo "\n";

exit(0);
