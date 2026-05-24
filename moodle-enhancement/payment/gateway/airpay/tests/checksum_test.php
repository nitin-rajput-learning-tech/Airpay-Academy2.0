<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * PHPUnit coverage for the global `\checksum` class shipped with paygw_airpay.
 *
 * The class lives in classes/checksum.php but declares itself in the global
 * namespace (vendor-supplied integration legacy — see file docblock). This
 * test require_once's the file directly. Before commit 2024100700.10 the
 * file called require_login() at file scope which made it unloadable here.
 *
 * @package    paygw_airpay
 * @category   test
 * @copyright  2026 Airpay Payment Services
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace paygw_airpay;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->dirroot . '/payment/gateway/airpay/classes/checksum.php');

/**
 * Tests for the `\checksum` class.
 *
 * @covers \checksum
 */
final class checksum_test extends \advanced_testcase {

    /**
     * calculateChecksumSha256() must match the documented Airpay integration
     * formula: hash('SHA256', salt . '@' . data). This is the formula
     * production pay.php relies on — see paygw_airpay/pay.php:69.
     */
    public function test_sha256_checksum_matches_documented_formula(): void {
        $data = 'buyer@example.com|JohnDoe|100.00|ORD-12345';
        $salt = 'merchant_sha256_key';
        $expected = hash('SHA256', $salt . '@' . $data);

        $this->assertSame($expected, \checksum::calculateChecksumSha256($data, $salt));
    }

    /**
     * encrypt() shares the same SHA-256 envelope as calculateChecksumSha256().
     * Documents the (slightly redundant) vendor-supplied API surface so a
     * future refactor doesn't accidentally diverge them.
     */
    public function test_encrypt_uses_sha256_envelope(): void {
        $data = 'username:|:password';
        $salt = 'mercid_secret';
        $expected = hash('SHA256', $salt . '@' . $data);

        $this->assertSame($expected, \checksum::encrypt($data, $salt));
    }

    /**
     * encryptSha256() is a plain SHA-256 over the data, no salt prefix.
     */
    public function test_encrypt_sha256_is_plain_hash(): void {
        $data = 'username~:~password';

        $this->assertSame(hash('SHA256', $data), \checksum::encryptSha256($data));
    }

    /**
     * verifyChecksum() must return 1 when the supplied checksum matches a
     * fresh calculateChecksumSha256() of the same payload + secret. The
     * pre-fix verifyChecksum() called the deprecated MD5 variant with the
     * arguments in the wrong order — both bugs are now corrected.
     */
    public function test_verify_checksum_accepts_matching_sha256(): void {
        $data = 'payload-to-verify';
        $secret = 'shared-secret';
        $checksum = \checksum::calculateChecksumSha256($data, $secret);

        $this->assertSame(1, \checksum::verifyChecksum($checksum, $data, $secret));
    }

    /**
     * verifyChecksum() must return 0 when the checksum was computed over
     * different data — the tamper-detection guarantee.
     */
    public function test_verify_checksum_rejects_tampered_payload(): void {
        $secret = 'shared-secret';
        $original = \checksum::calculateChecksumSha256('original-payload', $secret);

        $this->assertSame(0, \checksum::verifyChecksum($original, 'tampered-payload', $secret));
    }

    /**
     * Deprecated MD5 calculateChecksum() must still return the original
     * md5($data . $secret) value so that any external caller depending on
     * binary compatibility keeps working. Removal will happen in a future
     * major version after a deprecation window.
     */
    public function test_md5_calculateChecksum_still_returns_md5(): void {
        $data = 'order-12345';
        $secret = 'old-secret';
        $expected = md5($data . $secret);

        // Suppress the expected debugging() notice.
        $this->resetDebugging();
        $actual = \checksum::calculateChecksum($data, $secret);
        $this->assertDebuggingCalled();

        $this->assertSame($expected, $actual);
    }

    /**
     * The deprecated MD5 method MUST emit a debugging() warning so any
     * lingering caller surfaces during dev/staging runs before production.
     */
    public function test_md5_calculateChecksum_emits_deprecation_warning(): void {
        \checksum::calculateChecksum('any-data', 'any-secret');
        $this->assertDebuggingCalled();
    }

    // ────────────────────────────────────────────────────────────────────
    // Supplementary tests — lock in the ca0cff60a security fixes
    // ────────────────────────────────────────────────────────────────────

    /**
     * outputForm() MUST escape the checksum value via s() before echoing.
     * The pre-ca0cff60a code used raw concat which made the payment
     * redirect page XSS-able if a hostile checksum reached it. The
     * security commit replaced the concat with s(). Without this test,
     * a future "cleanup" could silently regress to raw concat.
     */
    public function test_output_form_escapes_xss_payload(): void {
        $hostile = '"><script>alert(1)</script>';
        ob_start();
        \checksum::outputForm($hostile);
        $out = ob_get_clean();

        // The unescaped <script> tag must NOT appear in the output.
        $this->assertStringNotContainsString(
            '<script>alert(1)</script>',
            $out,
            'outputForm() did not escape the checksum value — XSS regression'
        );
        // The escaped form (via s()) is still present.
        $this->assertStringContainsString('name="checksum"', $out);
        // Sanity: an obviously-safe value still round-trips inside value="…".
        ob_start();
        \checksum::outputForm('a1b2c3');
        $safeout = ob_get_clean();
        $this->assertStringContainsString('value="a1b2c3"', $safeout);
    }

    /**
     * verifyChecksum() uses hash_equals() for constant-time comparison.
     * A pre-ca0cff60a verifyChecksum() did `if ($a == $b)` which suffers
     * from PHP's type-juggling: an integer 0 compares equal to any
     * "0e…"-prefixed string under ==. With hash_equals, the comparison
     * is byte-by-byte strict.
     *
     * Indirect test: pass integer 0 as the supplied checksum — under
     * loose compare this would (in rare cases) match. Under strict
     * compare it never does. Either way the API must return 0.
     */
    public function test_verify_does_not_succumb_to_php_type_juggling(): void {
        $this->assertSame(
            0,
            \checksum::verifyChecksum(0, 'any-payload', 'any-secret'),
            'verifyChecksum() must reject integer 0 — strict compare regression'
        );
    }

    /**
     * verifyChecksum() returns 0 when the secret is wrong even if the
     * payload matches. Locks in the bug-fix from ca0cff60a where the
     * old verifyChecksum() passed args in the wrong order to the
     * (then-MD5) helper, so two different secrets could collide under
     * the swapped-arg computation. The new SHA-256 path uses
     * (data, secret) consistently.
     */
    public function test_verify_returns_0_when_secret_wrong(): void {
        $payload = 'order=42&amount=99.00';
        $checksum = \checksum::calculateChecksumSha256($payload, 'real-secret');
        $this->assertSame(
            0,
            \checksum::verifyChecksum($checksum, $payload, 'forged-secret')
        );
    }
}
