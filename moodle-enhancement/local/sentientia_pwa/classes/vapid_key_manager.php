<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

namespace local_sentientia_pwa;

defined('MOODLE_INTERNAL') || die();

/**
 * VAPID keypair manager — Phase B.2.b.
 *
 * Generates / loads the P-256 (prime256v1) ECDSA keypair used for Web
 * Push VAPID authentication (RFC 8292). The keypair is generated ONCE
 * per Sentientia LMS install and stored in mdl_config_plugin under
 * plugin='local_sentientia_pwa'.
 *
 * Three artefacts are stored:
 *  - vapid_public_b64url   65-byte uncompressed point (0x04 || X || Y), base64url
 *  - vapid_private_b64url  32-byte raw d value, base64url (for hand-rolled JWT)
 *  - vapid_private_pem     PEM-encoded EC private key (for openssl_sign())
 *
 * The PEM and raw form are kept in sync — Phase B.2.5's JWT signer uses
 * the PEM via openssl_sign(); the b64url form is the canonical reference
 * for tests and re-derivation.
 *
 * Regenerating the keypair invalidates every existing push subscription
 * (the browser-side subscription is bound to the public key), so the
 * regenerate() path nukes mdl_local_sentientia_push_subs in the same
 * transaction. Callers MUST be deliberate.
 *
 * @package local_sentientia_pwa
 */
class vapid_key_manager {

    /** Plugin name used as the mdl_config_plugin scope. */
    public const CONFIG_PLUGIN = 'local_sentientia_pwa';

    /** Setting names — kept as constants so refactors don't drift. */
    public const PUBLIC_KEY_NAME  = 'vapid_public_b64url';
    public const PRIVATE_KEY_NAME = 'vapid_private_b64url';
    public const PEM_KEY_NAME     = 'vapid_private_pem';
    public const SUBJECT_NAME     = 'vapid_subject';
    public const GENERATED_AT_NAME = 'vapid_generated_at';

    /**
     * Default VAPID subject. Used as the JWT "sub" claim — push providers
     * (Google FCM, Mozilla autopush, etc.) see this and use it for abuse
     * reports. RFC 8292 §2.1 requires mailto: or https: scheme.
     *
     * Override per-deployment via:
     *   php admin/cli/cfg.php --component=local_sentientia_pwa \
     *       --name=vapid_subject --set='mailto:tech@airpay.co.in'
     */
    public const DEFAULT_SUBJECT = 'mailto:academy@airpay.co.in';

    /**
     * @return bool True if a keypair is already stored.
     */
    public static function exists(): bool {
        return !empty(get_config(self::CONFIG_PLUGIN, self::PUBLIC_KEY_NAME))
            && !empty(get_config(self::CONFIG_PLUGIN, self::PRIVATE_KEY_NAME));
    }

    /**
     * @return string|null Base64url-encoded uncompressed public key, or
     *                     null if no keypair is stored.
     */
    public static function get_public_key(): ?string {
        $value = get_config(self::CONFIG_PLUGIN, self::PUBLIC_KEY_NAME);
        return $value !== false && $value !== '' ? $value : null;
    }

    /**
     * @return string|null Base64url-encoded private d value (32 bytes), or null.
     */
    public static function get_private_key(): ?string {
        $value = get_config(self::CONFIG_PLUGIN, self::PRIVATE_KEY_NAME);
        return $value !== false && $value !== '' ? $value : null;
    }

    /**
     * @return string|null PEM-encoded EC private key (for openssl_sign), or null.
     *
     * Audit fix #6 (2026-05-21) — the stored value may be wrapped with
     * the AES-256-GCM envelope (prefix `enc:v1:`). Unwrap transparently
     * so callers always get a plaintext PEM. Legacy plaintext PEMs (from
     * Phase B.2.b before the audit) continue to load unchanged.
     */
    public static function get_private_pem(): ?string {
        $value = get_config(self::CONFIG_PLUGIN, self::PEM_KEY_NAME);
        if ($value === false || $value === '') {
            return null;
        }
        return self::unwrap_pem((string) $value);
    }

    /**
     * @return string VAPID subject (mailto: or https: URL).
     */
    public static function get_subject(): string {
        $subject = get_config(self::CONFIG_PLUGIN, self::SUBJECT_NAME);
        return ($subject !== false && $subject !== '') ? $subject : self::DEFAULT_SUBJECT;
    }

    /**
     * @return int|null Unix timestamp when current keypair was generated.
     */
    public static function get_generated_at(): ?int {
        $value = get_config(self::CONFIG_PLUGIN, self::GENERATED_AT_NAME);
        return ($value !== false && $value !== '') ? (int) $value : null;
    }

    /**
     * Generate a new P-256 keypair and persist to mdl_config_plugin.
     *
     * Refuses to overwrite an existing keypair unless $force is true —
     * callers needing regeneration should use regenerate() which also
     * invalidates push subscriptions.
     *
     * @param bool $force Allow overwriting an existing keypair.
     * @return array{public:string,private:string,pem:string} Generated material.
     * @throws \moodle_exception On openssl failures or extension missing.
     */
    public static function generate_and_save(bool $force = false): array {
        if (self::exists() && !$force) {
            throw new \moodle_exception('vapid_already_exists', 'local_sentientia_pwa');
        }

        if (!extension_loaded('openssl')) {
            throw new \moodle_exception('vapid_openssl_required', 'local_sentientia_pwa');
        }

        // Generate a P-256 EC keypair. OPENSSL_KEYTYPE_EC + curve_name is
        // available since PHP 7.1; we're on 8.2 so this is safe.
        //
        // Windows quirk: openssl_pkey_new() with EC curves fails with
        // "system library::No such process" if the OPENSSL_CONF env var
        // isn't set AND the default search path doesn't find a usable
        // openssl.cnf. Resolved by passing the config file path explicitly.
        // On Linux this is a no-op because the OS-default path normally
        // resolves on its own — we only override if a candidate exists.
        $config_args = [
            'curve_name'       => 'prime256v1',  // = NIST P-256 = secp256r1
            'private_key_type' => OPENSSL_KEYTYPE_EC,
        ];
        $config_path = self::find_openssl_config();
        if ($config_path !== null) {
            $config_args['config'] = $config_path;
        }

        $resource = openssl_pkey_new($config_args);

        if ($resource === false) {
            throw new \moodle_exception('vapid_generation_failed',
                'local_sentientia_pwa', '', openssl_error_string() ?: 'unknown openssl error');
        }

        $details = openssl_pkey_get_details($resource);
        if (!isset($details['ec']['x'], $details['ec']['y'], $details['ec']['d'])) {
            throw new \moodle_exception('vapid_generation_failed',
                'local_sentientia_pwa', '',
                'EC key components missing from openssl_pkey_get_details() — check OpenSSL >= 1.0.2 and PHP >= 7.1');
        }

        // Public key: uncompressed point format (RFC 5480 §2.2):
        //   0x04 || X || Y     — 65 bytes total
        // X and Y are big-endian unsigned integers, MUST be left-padded
        // to exactly 32 bytes each (openssl may return 31-byte values
        // when the high bit happens to be zero).
        $x = str_pad($details['ec']['x'], 32, "\x00", STR_PAD_LEFT);
        $y = str_pad($details['ec']['y'], 32, "\x00", STR_PAD_LEFT);
        $public_key_bin = "\x04" . $x . $y;

        if (strlen($public_key_bin) !== 65) {
            throw new \moodle_exception('vapid_generation_failed',
                'local_sentientia_pwa', '',
                'Public key length ' . strlen($public_key_bin) . ' != 65');
        }

        // Private key: raw 32-byte d value.
        $private_key_bin = str_pad($details['ec']['d'], 32, "\x00", STR_PAD_LEFT);
        if (strlen($private_key_bin) !== 32) {
            throw new \moodle_exception('vapid_generation_failed',
                'local_sentientia_pwa', '',
                'Private key length ' . strlen($private_key_bin) . ' != 32');
        }

        // Export PEM — Phase B.2.5's JWT signer uses this via openssl_sign().
        // openssl_pkey_export() ALSO needs the config arg on Windows when
        // the OS-default openssl.cnf isn't on the search path — same root
        // cause as openssl_pkey_new() above. Pass our autodetected path.
        $private_pem = '';
        $export_opts = $config_path !== null ? ['config' => $config_path] : [];
        $export_ok = openssl_pkey_export($resource, $private_pem, null, $export_opts);
        if (!$export_ok || empty($private_pem)) {
            throw new \moodle_exception('vapid_generation_failed',
                'local_sentientia_pwa', '',
                'openssl_pkey_export failed: ' . (openssl_error_string() ?: 'unknown'));
        }

        // Base64url encode (RFC 4648 §5 — no padding).
        $public_b64url  = self::b64url_encode($public_key_bin);
        $private_b64url = self::b64url_encode($private_key_bin);

        // Persist. set_config() returns true on success.
        // Audit fix #6 — wrap the PEM in AES-256-GCM if a master key is
        // configured. The other two artefacts (public key + raw private)
        // are not as sensitive (public is by definition public; raw
        // private is identical info to the PEM but harder to misuse via
        // CLI tools), but the PEM is what an attacker would dump and
        // feed straight to openssl. Defence in depth.
        $pem_for_storage = self::wrap_pem($private_pem);

        set_config(self::PUBLIC_KEY_NAME,   $public_b64url,  self::CONFIG_PLUGIN);
        set_config(self::PRIVATE_KEY_NAME,  $private_b64url, self::CONFIG_PLUGIN);
        set_config(self::PEM_KEY_NAME,      $pem_for_storage, self::CONFIG_PLUGIN);
        set_config(self::GENERATED_AT_NAME, time(),          self::CONFIG_PLUGIN);

        return [
            'public'  => $public_b64url,
            'private' => $private_b64url,
            'pem'     => $private_pem,
        ];
    }

    /**
     * Regenerate the keypair. Invalidates every existing push subscription
     * because the browser-side subscription is cryptographically bound to
     * the OLD public key — pushes signed with the new private key will be
     * rejected with 410 Gone or 401 Unauthorized by the push service.
     *
     * Returns the count of invalidated subscriptions so the caller can warn.
     *
     * @return array{public:string,private:string,pem:string,invalidated:int}
     * @throws \moodle_exception
     */
    public static function regenerate(): array {
        global $DB;

        $transaction = $DB->start_delegated_transaction();
        try {
            $invalidated = $DB->count_records('local_sentientia_push_subs');
            $DB->delete_records('local_sentientia_push_subs');
            $result = self::generate_and_save(true);
            $transaction->allow_commit();
        } catch (\Throwable $e) {
            $transaction->rollback($e);
            throw $e;
        }

        $result['invalidated'] = $invalidated;
        return $result;
    }

    /**
     * Locate an openssl.cnf file for EC key generation. Returns null if
     * none of the known candidates exist, in which case the openssl
     * runtime default is used (works on properly-configured Linux).
     *
     * Order:
     *   1. OPENSSL_CONF env var, if set and the file exists
     *   2. XAMPP-bundled openssl.cnf (Windows dev)
     *   3. Standard Linux locations
     *   4. macOS Homebrew location
     */
    private static function find_openssl_config(): ?string {
        $candidates = [];

        $env = getenv('OPENSSL_CONF');
        if (!empty($env)) {
            $candidates[] = $env;
        }

        // XAMPP on Windows — the dev environment we know is used here.
        $candidates[] = 'C:\\xampp\\php\\extras\\openssl\\openssl.cnf';
        $candidates[] = 'C:\\xampp\\apache\\conf\\openssl.cnf';

        // Linux production.
        $candidates[] = '/etc/ssl/openssl.cnf';
        $candidates[] = '/etc/pki/tls/openssl.cnf';     // RHEL-family
        $candidates[] = '/usr/lib/ssl/openssl.cnf';     // Debian-family

        // macOS Homebrew.
        $candidates[] = '/usr/local/etc/openssl/openssl.cnf';
        $candidates[] = '/opt/homebrew/etc/openssl/openssl.cnf';

        foreach ($candidates as $path) {
            if ($path && is_string($path) && file_exists($path) && is_readable($path)) {
                return $path;
            }
        }

        return null;
    }

    /** Audit fix #6 — prefix marker for envelope-encrypted PEMs. */
    private const ENC_PREFIX = 'enc:v1:';

    /**
     * Audit fix #6 (2026-05-21) — AES-256-GCM envelope encryption for
     * the VAPID private PEM at rest. Wraps in:
     *
     *   enc:v1:<base64url(iv || tag || ciphertext)>
     *
     * IV is 12 random bytes (NIST 800-38D recommended), tag is 16 bytes,
     * ciphertext is variable. Total overhead = 28 bytes raw, ~37 chars
     * base64url'd.
     *
     * No master key configured?  Returns the plaintext unchanged so the
     * upgrade path doesn't break installs that haven't set up the master
     * key yet — but logs a developer-debug warning so the gap is visible.
     *
     * See `docs/audits/B25-CRYPTO-AUDIT-2026-05-21.md` finding #6 for
     * the threat model + remediation rationale.
     */
    public static function wrap_pem(string $pem): string {
        $master = self::master_key();
        if ($master === null) {
            debugging('[sentientia_pwa] VAPID master key not set — PEM stored plaintext '
                . '(set SENTIENTIA_VAPID_MASTER_KEY env var or $CFG->sentientia_vapid_master_key)',
                DEBUG_DEVELOPER);
            return $pem;
        }
        $iv  = random_bytes(12);
        $tag = '';
        $ct  = openssl_encrypt($pem, 'aes-256-gcm', $master,
            OPENSSL_RAW_DATA, $iv, $tag, '', 16);
        if ($ct === false || strlen($tag) !== 16) {
            // Fall through to plaintext on encryption failure rather
            // than losing the key — but log loudly.
            debugging('[sentientia_pwa] VAPID PEM wrap failed: '
                . (openssl_error_string() ?: 'unknown'), DEBUG_NORMAL);
            return $pem;
        }
        return self::ENC_PREFIX . self::b64url_encode($iv . $tag . $ct);
    }

    /**
     * Inverse of wrap_pem(). Detects the `enc:v1:` prefix and decrypts;
     * if absent, returns the value unchanged (legacy plaintext PEM
     * from Phase B.2.b pre-audit).
     *
     * @throws \moodle_exception When the value is wrapped but the master
     *                            key is missing OR decryption fails (tag
     *                            mismatch = tampering OR wrong key).
     */
    public static function unwrap_pem(string $stored): string {
        if (strpos($stored, self::ENC_PREFIX) !== 0) {
            return $stored;  // legacy plaintext PEM
        }
        $master = self::master_key();
        if ($master === null) {
            throw new \moodle_exception('vapid_master_key_missing',
                'local_sentientia_pwa');
        }
        $blob = self::b64url_decode(substr($stored, strlen(self::ENC_PREFIX)));
        if (strlen($blob) < (12 + 16 + 1)) {
            throw new \moodle_exception('vapid_pem_decrypt_failed',
                'local_sentientia_pwa', '', 'wrapped PEM too short');
        }
        $iv  = substr($blob, 0,  12);
        $tag = substr($blob, 12, 16);
        $ct  = substr($blob, 28);
        $plain = openssl_decrypt($ct, 'aes-256-gcm', $master,
            OPENSSL_RAW_DATA, $iv, $tag);
        if ($plain === false || $plain === '') {
            throw new \moodle_exception('vapid_pem_decrypt_failed',
                'local_sentientia_pwa');
        }
        return $plain;
    }

    /**
     * Resolve the 32-byte AES-256-GCM master key for PEM envelope.
     *
     * Preference order (most secure → least secure):
     *   1. Env var `SENTIENTIA_VAPID_MASTER_KEY` (base64url, 32 bytes
     *      decoded). Best because it never lives on disk in plain text;
     *      systemd / Docker / kubernetes inject it at runtime.
     *   2. `$CFG->sentientia_vapid_master_key` (base64url). Lives in
     *      `config.php` which IS on disk but is only readable by the
     *      PHP process (typically 0600 root:apache). Still much better
     *      than DB-cleartext.
     *
     * Returns `null` when neither is set — caller (wrap_pem) treats
     * `null` as "skip encryption + log warning", so the upgrade path
     * doesn't break.
     */
    private static function master_key(): ?string {
        global $CFG;
        $env = getenv('SENTIENTIA_VAPID_MASTER_KEY');
        if (!empty($env)) {
            $bytes = self::b64url_decode($env);
            if (strlen($bytes) === 32) {
                return $bytes;
            }
        }
        if (!empty($CFG->sentientia_vapid_master_key)) {
            $bytes = self::b64url_decode((string) $CFG->sentientia_vapid_master_key);
            if (strlen($bytes) === 32) {
                return $bytes;
            }
        }
        return null;
    }

    /**
     * Test-only helper — exposes wrap/unwrap state for self-tests.
     * Returns true if a master key is currently configured.
     */
    public static function master_key_configured(): bool {
        return self::master_key() !== null;
    }

    /**
     * Base64url encoding (RFC 4648 §5) — base64 with +/ replaced by -_
     * and no padding. Web Push and JWT both require this format.
     */
    public static function b64url_encode(string $binary): string {
        return rtrim(strtr(base64_encode($binary), '+/', '-_'), '=');
    }

    /**
     * Base64url decoding — inverse of b64url_encode().
     */
    public static function b64url_decode(string $b64url): string {
        $padding = strlen($b64url) % 4;
        if ($padding > 0) {
            $b64url .= str_repeat('=', 4 - $padding);
        }
        return base64_decode(strtr($b64url, '-_', '+/'));
    }
}
