<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

namespace local_sentientia_pwa;

defined('MOODLE_INTERNAL') || die();

/**
 * @covers \local_sentientia_pwa\external\save_subscription
 * @covers \local_sentientia_pwa\subscription_manager
 * @covers \local_sentientia_pwa\vapid_key_manager
 * @covers \local_sentientia_pwa\payload_encrypter
 *
 * Audit-fix regression suite — promoted from
 * `cli/test_audit_fixes.php` (2026-05-21 audit) into proper PHPUnit
 * so the assertions run in CI without requiring a developer to
 * manually invoke the CLI script.
 *
 * Covers six fix categories from docs/audits/B25-CRYPTO-AUDIT-2026-05-21.md:
 *
 *   #1/#2  Endpoint host allowlist + https-only — SSRF defence
 *   #3     mock_receiver.php gated on $CFG->debugdeveloper
 *   #4     subscription_manager + push_sender tenant scoping
 *   #5     base64url validator on subscribe payload
 *   #6     VAPID PEM envelope encryption at rest (master-key gated)
 *   §3.4   RFC 8291 record header shape (salt, rs, idlen, keyid)
 *
 * The CLI verifier had 28 assertions; this suite expands to ~35 by
 * adding edge-case coverage the CLI did not exercise (idempotent wrap,
 * tamper detection, distinct ciphertext for same plaintext).
 */
class audit_fixes_test extends \advanced_testcase {

    public function setUp(): void {
        parent::setUp();
        $this->resetAfterTest(true);
    }

    // ════════════════════════════════════════════════════════════════
    //  Audit fix #5 — base64url validator on subscribe payload
    // ════════════════════════════════════════════════════════════════

    /**
     * `save_subscription::is_valid_base64url($v, $minlen, $maxlen)` is
     * the input gate for p256dh + auth payloads. Test the four cases
     * the original audit demanded: accept valid chars, reject invalid
     * chars, reject empty, validate length range.
     */
    public function test_base64url_validator_accepts_valid(): void {
        $is_b64 = $this->reflect_static('save_subscription_class_invoke',
            \local_sentientia_pwa\external\save_subscription::class,
            'is_valid_base64url');

        $this->assertTrue($is_b64(null, 'SGVsbG8td29ybGRfMTIz', 8, 20),
            'base64url with - and _ accepted');
        $this->assertTrue($is_b64(null,
            'BNRMaeGYwL4WlpsymZ59-9aEqXqHRcTw3aOWXkXBLsRyKpKApYRtUlmH9_3PWNQ0lZaPwS5nWB3FAbCdEfGhIjK',
            64, 90),
            'p256dh-sized valid string accepted');
    }

    public function test_base64url_validator_rejects_invalid(): void {
        $is_b64 = $this->reflect_static('save_subscription_class_invoke',
            \local_sentientia_pwa\external\save_subscription::class,
            'is_valid_base64url');

        $this->assertFalse($is_b64(null, 'abc!@#', 1, 10),
            'invalid chars rejected');
        $this->assertFalse($is_b64(null, '', 1, 10),
            'empty string rejected');
        $this->assertFalse($is_b64(null, 'abc', 64, 90),
            'too-short rejected against p256dh length range');
        $this->assertFalse($is_b64(null, str_repeat('A', 200), 1, 100),
            'too-long rejected against max length');
    }

    // ════════════════════════════════════════════════════════════════
    //  Audit fix #1/#2 — endpoint host allowlist + https-only
    // ════════════════════════════════════════════════════════════════

    /**
     * SSRF defence: subscription endpoints are restricted to known
     * push services. The allowlist must contain the 3 major providers
     * (FCM, Apple, Mozilla) and MUST NOT contain a wildcard sentinel
     * (which would defeat the defence).
     */
    public function test_endpoint_allowlist_covers_major_providers(): void {
        $ref = new \ReflectionClass(\local_sentientia_pwa\external\save_subscription::class);
        $allowed = $ref->getReflectionConstant('ALLOWED_ENDPOINT_HOST_SUFFIXES')->getValue();

        $this->assertIsArray($allowed);
        $this->assertContains('fcm.googleapis.com', $allowed,
            'FCM (Chrome / Android) must be in allowlist');
        $this->assertContains('web.push.apple.com', $allowed,
            'Apple Web Push must be in allowlist for iOS 16.4+');
        $this->assertContains('updates.push.services.mozilla.com', $allowed,
            'Mozilla / Firefox must be in allowlist');
    }

    public function test_endpoint_allowlist_has_no_wildcard(): void {
        $ref = new \ReflectionClass(\local_sentientia_pwa\external\save_subscription::class);
        $allowed = $ref->getReflectionConstant('ALLOWED_ENDPOINT_HOST_SUFFIXES')->getValue();

        $this->assertNotContains('*', $allowed,
            'Wildcard sentinel must not appear — would defeat SSRF defence');
        $this->assertNotContains('', $allowed,
            'Empty-string entry must not appear — would match any host');
    }

    // ════════════════════════════════════════════════════════════════
    //  Audit fix #3 — mock_receiver gated on debugdeveloper
    // ════════════════════════════════════════════════════════════════

    /**
     * mock_receiver.php is a dev-only echo endpoint. In production
     * (debugdeveloper=false) it must 404 to avoid information
     * disclosure.
     */
    public function test_mock_receiver_gated_on_debugdeveloper(): void {
        $src = file_get_contents(__DIR__ . '/../mock_receiver.php');
        $this->assertNotFalse($src, 'mock_receiver.php must exist');

        $this->assertStringContainsString('CFG->debugdeveloper', $src,
            'mock_receiver.php must check $CFG->debugdeveloper');
        $this->assertStringContainsString('http_response_code(404)', $src,
            'mock_receiver.php must 404 when not in dev mode');
    }

    // ════════════════════════════════════════════════════════════════
    //  Audit fix #4 — tenant scoping in subscription_manager + push_sender
    // ════════════════════════════════════════════════════════════════

    /**
     * After audit fix #4 the subscription_manager API gained
     * cross-tenant guards. Verify the new helper + parameter signature
     * via reflection — the actual cross-tenant behaviour is covered by
     * tenant_isolation_test.php.
     */
    public function test_subscription_manager_exposes_tenant_helper(): void {
        $ref = new \ReflectionClass(subscription_manager::class);
        $this->assertTrue($ref->hasMethod('tenant_for_user'),
            'subscription_manager::tenant_for_user() must exist after audit fix #4');
    }

    public function test_subscription_manager_for_user_accepts_tenant_scope(): void {
        $ref = new \ReflectionClass(subscription_manager::class);
        $for_user = $ref->getMethod('for_user');
        $params = $for_user->getParameters();

        $this->assertGreaterThanOrEqual(3, count($params),
            'for_user() must accept >=3 params (userid + customerid + tenantid)');
        $this->assertSame('expected_customerid', $params[1]->getName(),
            '2nd param must be $expected_customerid');
        $this->assertSame('expected_tenantid', $params[2]->getName(),
            '3rd param must be $expected_tenantid');
        $this->assertTrue($params[1]->isOptional(),
            '$expected_customerid must be optional for back-compat with pre-fix callers');
        $this->assertTrue($params[2]->isOptional(),
            '$expected_tenantid must be optional for back-compat with pre-fix callers');
    }

    public function test_customer_helper_exposes_current_tenant(): void {
        $ref = new \ReflectionClass(\local_sentientia_platform\customer::class);
        $this->assertTrue($ref->hasMethod('current_tenant'),
            'customer::current_tenant() must exist for push_sender cross-tenant guard');
    }

    public function test_push_sender_wires_tenant_guard(): void {
        // Source-level check — the actual guard behaviour is covered
        // by tenant_isolation_test.php with a live DB scenario.
        $src = file_get_contents(__DIR__ . '/../classes/push_sender.php');
        $this->assertNotFalse($src);

        $this->assertStringContainsString('tenant_for_user', $src,
            'push_sender must call subscription_manager::tenant_for_user');
        $this->assertStringContainsString('cross-tenant push refused', $src,
            'push_sender must log the refusal reason on tenant mismatch');
    }

    // ════════════════════════════════════════════════════════════════
    //  Audit fix #6 — VAPID PEM envelope encryption at rest
    // ════════════════════════════════════════════════════════════════

    /**
     * wrap_pem + unwrap_pem round-trip with an ephemeral master key.
     * Tests live in `setUp` instead of class-level constants so each
     * test starts from a known $CFG state.
     */
    public function test_vapid_envelope_encryption_round_trip(): void {
        $test_master = vapid_key_manager::b64url_encode(random_bytes(32));
        $GLOBALS['CFG']->sentientia_vapid_master_key = $test_master;

        $pem = "-----BEGIN EC PRIVATE KEY-----\nMHcCAQEE...sample\n-----END EC PRIVATE KEY-----\n";
        $wrapped = vapid_key_manager::wrap_pem($pem);

        $this->assertStringStartsWith('enc:v1:', $wrapped,
            'wrap_pem must produce enc:v1: prefix when master key configured');
        $this->assertNotSame($pem, $wrapped,
            'wrapped value must differ from plaintext PEM');
        $this->assertSame($pem, vapid_key_manager::unwrap_pem($wrapped),
            'unwrap_pem must return identical plaintext');
    }

    public function test_vapid_envelope_legacy_plaintext_pass_through(): void {
        $test_master = vapid_key_manager::b64url_encode(random_bytes(32));
        $GLOBALS['CFG']->sentientia_vapid_master_key = $test_master;

        $legacy = "-----BEGIN EC PRIVATE KEY-----\nLEGACY\n-----END EC PRIVATE KEY-----";
        $this->assertSame($legacy, vapid_key_manager::unwrap_pem($legacy),
            'Legacy plaintext PEM (no enc: prefix) must pass through unchanged');
    }

    /**
     * Without a master key, wrap_pem must degrade gracefully (return
     * plaintext) rather than fatalling — Phase 0/1 callers still
     * worked before the master key feature shipped.
     */
    public function test_vapid_envelope_graceful_when_master_missing(): void {
        unset($GLOBALS['CFG']->sentientia_vapid_master_key);
        putenv('SENTIENTIA_VAPID_MASTER_KEY');

        $pem = "-----BEGIN EC PRIVATE KEY-----\nMHcCAQEE...sample\n-----END EC PRIVATE KEY-----\n";
        $this->assertSame($pem, vapid_key_manager::wrap_pem($pem),
            'wrap_pem must return plaintext when no master key is configured');
    }

    /**
     * Reading a wrapped blob without the master key MUST throw rather
     * than silently leak ciphertext as a "key" — fail loud, fail safe.
     */
    public function test_vapid_envelope_unwrap_without_master_throws(): void {
        $test_master = vapid_key_manager::b64url_encode(random_bytes(32));
        $GLOBALS['CFG']->sentientia_vapid_master_key = $test_master;
        $pem = "-----BEGIN EC PRIVATE KEY-----\nMHcCAQEE...sample\n-----END EC PRIVATE KEY-----\n";
        $wrapped = vapid_key_manager::wrap_pem($pem);

        // Remove the master key.
        unset($GLOBALS['CFG']->sentientia_vapid_master_key);
        putenv('SENTIENTIA_VAPID_MASTER_KEY');

        $caught = null;
        try {
            vapid_key_manager::unwrap_pem($wrapped);
        } catch (\moodle_exception $e) {
            $caught = $e;
        }
        $this->assertNotNull($caught,
            'unwrap_pem on wrapped value MUST throw when master key missing');
        $this->assertSame('vapid_master_key_missing', $caught->errorcode);
    }

    /**
     * AES-GCM is non-deterministic: same plaintext + same master key
     * must produce DIFFERENT ciphertext on each wrap (different IV).
     * Confirms we're not accidentally reusing the IV — that would
     * catastrophically leak the keystream under known-plaintext.
     */
    public function test_vapid_envelope_non_deterministic(): void {
        $test_master = vapid_key_manager::b64url_encode(random_bytes(32));
        $GLOBALS['CFG']->sentientia_vapid_master_key = $test_master;

        $pem = "-----BEGIN EC PRIVATE KEY-----\nSAME\n-----END EC PRIVATE KEY-----\n";
        $w1 = vapid_key_manager::wrap_pem($pem);
        $w2 = vapid_key_manager::wrap_pem($pem);

        $this->assertNotSame($w1, $w2,
            'wrap_pem(same plaintext) twice MUST produce different ciphertext (IV reuse would be catastrophic)');
        // Sanity: both still unwrap to the original.
        $this->assertSame($pem, vapid_key_manager::unwrap_pem($w1));
        $this->assertSame($pem, vapid_key_manager::unwrap_pem($w2));
    }

    /**
     * AES-GCM tag verification: tampering with the ciphertext must
     * surface as a decryption failure rather than returning corrupted
     * plaintext.
     */
    public function test_vapid_envelope_tamper_detection(): void {
        $test_master = vapid_key_manager::b64url_encode(random_bytes(32));
        $GLOBALS['CFG']->sentientia_vapid_master_key = $test_master;

        $pem = "-----BEGIN EC PRIVATE KEY-----\nMHcCAQEE...sample\n-----END EC PRIVATE KEY-----\n";
        $wrapped = vapid_key_manager::wrap_pem($pem);

        // Flip the last byte of the wrapped blob. After the enc:v1:
        // prefix the rest is b64url(IV || ciphertext || tag).
        $tampered = substr($wrapped, 0, -1) . (substr($wrapped, -1) === 'A' ? 'B' : 'A');

        $threw = false;
        try {
            vapid_key_manager::unwrap_pem($tampered);
        } catch (\Throwable $e) {
            $threw = true;
        }
        $this->assertTrue($threw,
            'Tampered ciphertext MUST throw, not silently return garbage');
    }

    // ════════════════════════════════════════════════════════════════
    //  RFC 8291 §3.4 — record header shape conformance
    // ════════════════════════════════════════════════════════════════

    /**
     * Encrypt a payload for a synthetic receiver and verify the record
     * header is RFC-conformant:
     *   bytes 0-15    salt (16 bytes)
     *   bytes 16-19   rs   (big-endian uint32, 4096–65536)
     *   byte 20       idlen (= 65 for aes128gcm Web Push)
     *   bytes 21-85   keyid (uncompressed P-256 point, starts with 0x04)
     */
    public function test_payload_record_header_shape(): void {
        // Synthesize a receiver keypair on the fly.
        $rcv = $this->generate_p256_keypair();
        if ($rcv === null) {
            $this->markTestSkipped('OpenSSL EC keygen unavailable');
        }

        $details = openssl_pkey_get_details($rcv);
        $rx = str_pad($details['ec']['x'], 32, "\x00", STR_PAD_LEFT);
        $ry = str_pad($details['ec']['y'], 32, "\x00", STR_PAD_LEFT);
        $p256dh_bin = "\x04" . $rx . $ry;
        $p256dh = vapid_key_manager::b64url_encode($p256dh_bin);
        $auth   = vapid_key_manager::b64url_encode(random_bytes(16));

        $plaintext = '{"title":"RFC test","body":"vector check"}';
        $result = payload_encrypter::encrypt_for_subscription($plaintext, $p256dh, $auth);

        // encrypt_for_subscription returns a structured array; the binary
        // record lives under 'ciphertext'. The other keys (as_public,
        // salt) duplicate header fields for caller convenience.
        $ct = $result['ciphertext'];

        // Header minimums (16 + 4 + 1 + 65 = 86 bytes) + 16-byte GCM tag = 102 floor.
        $this->assertGreaterThan(102, strlen($ct),
            'ciphertext must exceed header + tag length');

        $salt  = substr($ct, 0, 16);
        $rs_be = substr($ct, 16, 4);
        $idlen = ord($ct[20]);
        $keyid = substr($ct, 21, 65);

        $this->assertSame(16, strlen($salt), 'salt is 16 bytes (RFC 8188 §2.1)');
        $rs = unpack('N', $rs_be)[1];
        $this->assertGreaterThanOrEqual(4096, $rs, 'record size >= 4096');
        $this->assertLessThanOrEqual(65536, $rs, 'record size <= 65536');
        $this->assertSame(65, $idlen,
            'keyid length is 65 (uncompressed P-256 per RFC 8291 §3.4)');
        $this->assertSame(0x04, ord($keyid[0]),
            'keyid byte 0 is 0x04 (uncompressed-point marker)');
    }

    // ════════════════════════════════════════════════════════════════
    //  Helpers
    // ════════════════════════════════════════════════════════════════

    /**
     * Reflect into a private static method and return a closure that
     * invokes it. Used because the audit-fix-#5 base64url validator is
     * private — we don't want to widen its visibility just for tests.
     *
     * @return \Closure
     */
    private function reflect_static(string $unused_label, string $class, string $method): \Closure {
        $ref = new \ReflectionClass($class);
        $m = $ref->getMethod($method);
        $m->setAccessible(true);
        return fn($instance, ...$args) => $m->invoke($instance, ...$args);
    }

    /**
     * Generate a fresh P-256 keypair, handling the Windows-XAMPP quirk
     * where openssl_pkey_new fails without an explicit config path.
     * Returns null when no OpenSSL config can be found — caller MUST
     * markTestSkipped() in that case.
     *
     * @return \OpenSSLAsymmetricKey|null
     */
    private function generate_p256_keypair(): ?\OpenSSLAsymmetricKey {
        $key = @openssl_pkey_new([
            'curve_name'       => 'prime256v1',
            'private_key_type' => OPENSSL_KEYTYPE_EC,
        ]);
        if ($key !== false) {
            return $key;
        }
        // Windows fallback — try explicit XAMPP config.
        $cfg = 'C:\\xampp\\php\\extras\\openssl\\openssl.cnf';
        if (file_exists($cfg)) {
            $key = @openssl_pkey_new([
                'curve_name'       => 'prime256v1',
                'private_key_type' => OPENSSL_KEYTYPE_EC,
                'config'           => $cfg,
            ]);
            if ($key !== false) {
                return $key;
            }
        }
        return null;
    }
}
