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
 * PHPUnit coverage for paygw_airpay\airpay_helper.
 *
 * Scope:
 * - ORDER_STATUS constants are stable (relied on by pay.php + process.php).
 * - get_unprocessed_order() returns false when no matching row exists.
 * - get_url() returns the Airpay payment endpoint regardless of the
 *   `environment` config value (current documented behaviour — see method
 *   docblock for the open question).
 *
 * Out of scope:
 * - create_order() / update_order() — exercise multiple cross-plugin tables
 *   (local_biz_cart_history, paygw_course_enrolmentlog) that need a much
 *   richer fixture; deferred to a future integration-test session.
 * - check_payment() — current body is fully commented-out vendor code.
 * - process_payment() — depends on core_payment::save_payment which
 *   requires a real payable + account fixture.
 *
 * @package    paygw_airpay
 * @category   test
 * @copyright  2026 Airpay Payment Services
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace paygw_airpay;

defined('MOODLE_INTERNAL') || die();

/**
 * Tests for the paygw_airpay\airpay_helper class.
 *
 * @covers \paygw_airpay\airpay_helper
 */
final class airpay_helper_test extends \advanced_testcase {

    /**
     * ORDER_STATUS_PENDING must remain 0. pay.php's get_unprocessed_order
     * filter and create_order's status-on-insert both hard-code this value
     * via the constant — drift here would silently break order lookup.
     */
    public function test_order_status_pending_is_zero(): void {
        $this->assertSame(0, airpay_helper::ORDER_STATUS_PENDING);
    }

    /**
     * ORDER_STATUS_PAID must remain 1. process_payment() sets this on the
     * order record before deliver_order(); core_payment auditing depends
     * on the value not changing.
     */
    public function test_order_status_paid_is_one(): void {
        $this->assertSame(1, airpay_helper::ORDER_STATUS_PAID);
    }

    /**
     * On a fresh DB with no `paygw_airpay` rows for the buyer, the unprocessed-
     * order lookup must return false — that's the signal create_order() uses
     * to decide whether to create a new row vs reuse an existing pending one.
     */
    public function test_get_unprocessed_order_returns_false_when_none_exists(): void {
        $this->resetAfterTest(true);
        $user = $this->getDataGenerator()->create_user();
        $this->setUser($user);

        $result = airpay_helper::get_unprocessed_order(
            'enrol_fee',  // component
            'fee',        // paymentarea
            12345,        // itemid (no matching row)
            99.00         // cost
        );

        $this->assertFalse($result);
    }

    /**
     * get_url() must return the Airpay live payment endpoint when
     * environment is 'live'. The vendor integration uses a single endpoint
     * across sandbox + live today (see method docblock).
     */
    public function test_get_url_returns_airpay_endpoint_for_live(): void {
        $config = (object) ['environment' => 'live'];
        $this->assertSame(
            'https://payments.airpay.co.in/pay/index.php',
            airpay_helper::get_url($config)
        );
    }

    /**
     * get_url() must return the SAME endpoint when environment is 'sandbox'.
     * This pins down the current documented behaviour — if Airpay support
     * later supplies a real sandbox host, this test fails loudly and forces
     * a deliberate update of both the method and the test.
     */
    public function test_get_url_returns_airpay_endpoint_for_sandbox(): void {
        $config = (object) ['environment' => 'sandbox'];
        $this->assertSame(
            'https://payments.airpay.co.in/pay/index.php',
            airpay_helper::get_url($config)
        );
    }

    /**
     * Unknown environment values must not crash — the method should still
     * return a valid URL (graceful degradation; the gateway settings form
     * constrains the dropdown to live|sandbox but defensive code is cheap).
     */
    public function test_get_url_handles_unknown_environment(): void {
        $config = (object) ['environment' => 'staging-mystery'];
        $this->assertSame(
            'https://payments.airpay.co.in/pay/index.php',
            airpay_helper::get_url($config)
        );
    }

    /**
     * Build a $_POST array with a correctly-computed ap_SecureHash for the
     * given credentials, replicating Airpay's documented v4 response formula.
     * Used by the verify_secure_hash() tests to forge a *legitimate* response.
     */
    private function signed_post(string $mercid, string $username, array $overrides = []): array {
        $post = array_merge([
            'TRANSACTIONID'     => 'TXN123',
            'APTRANSACTIONID'   => 'AP456',
            'AMOUNT'            => '199.00',
            'TRANSACTIONSTATUS' => '200',
            'MESSAGE'           => 'success',
        ], $overrides);
        $parts = [
            trim($post['TRANSACTIONID']),
            trim($post['APTRANSACTIONID']),
            trim($post['AMOUNT']),
            trim($post['TRANSACTIONSTATUS']),
            trim((string) ($post['MESSAGE'] ?? '')),
            $mercid,
            $username,
        ];
        if (($post['CHMOD'] ?? '') === 'upi' && isset($post['CUSTOMERVPA'])) {
            $parts[] = trim($post['CUSTOMERVPA']);
        }
        $post['ap_SecureHash'] = sprintf('%u', crc32(implode(':', $parts)));
        return $post;
    }

    /**
     * A response whose ap_SecureHash recomputes correctly (and has all required
     * fields, with mercid/username configured) must verify.
     */
    public function test_verify_secure_hash_accepts_valid_response(): void {
        $this->resetAfterTest(true);
        set_config('mercid', 'MERC001', 'paygw_airpay');
        set_config('username', 'apuser', 'paygw_airpay');

        $post = $this->signed_post('MERC001', 'apuser');

        $this->assertTrue(airpay_helper::verify_secure_hash($post));
    }

    /**
     * REGRESSION TEST for the payment-bypass (2026-06-02). A forged callback
     * carrying TRANSACTIONSTATUS=200 but an attacker-supplied junk hash MUST NOT
     * verify. Before the fix, process.php enrolled on status==200 alone because
     * the guard was commented out; this asserts the verifier rejects the forgery.
     */
    public function test_verify_secure_hash_rejects_forged_success(): void {
        $this->resetAfterTest(true);
        set_config('mercid', 'MERC001', 'paygw_airpay');
        set_config('username', 'apuser', 'paygw_airpay');

        $forged = [
            'TRANSACTIONID'     => 'TXN123',
            'APTRANSACTIONID'   => 'AP456',
            'AMOUNT'            => '199.00',
            'TRANSACTIONSTATUS' => '200',
            'MESSAGE'           => 'success',
            'ap_SecureHash'     => '0000000000',  // attacker garbage
        ];

        $this->assertFalse(airpay_helper::verify_secure_hash($forged));
    }

    /**
     * A response missing a required field (here APTRANSACTIONID) must reject —
     * verify_secure_hash fails closed on absent fields.
     */
    public function test_verify_secure_hash_rejects_missing_field(): void {
        $this->resetAfterTest(true);
        set_config('mercid', 'MERC001', 'paygw_airpay');
        set_config('username', 'apuser', 'paygw_airpay');

        $post = $this->signed_post('MERC001', 'apuser');
        unset($post['APTRANSACTIONID']);

        $this->assertFalse(airpay_helper::verify_secure_hash($post));
    }

    /**
     * UPI responses append CUSTOMERVPA to the hashed payload — a correctly
     * signed UPI response must verify.
     */
    public function test_verify_secure_hash_accepts_valid_upi_response(): void {
        $this->resetAfterTest(true);
        set_config('mercid', 'MERC001', 'paygw_airpay');
        set_config('username', 'apuser', 'paygw_airpay');

        $post = $this->signed_post('MERC001', 'apuser', [
            'CHMOD'       => 'upi',
            'CUSTOMERVPA' => 'buyer@upi',
        ]);

        $this->assertTrue(airpay_helper::verify_secure_hash($post));
    }

    /**
     * With no mercid/username configured the verifier cannot recompute the hash,
     * so it must FAIL CLOSED (reject) rather than silently skip verification.
     */
    public function test_verify_secure_hash_fails_closed_without_config(): void {
        $this->resetAfterTest(true);
        set_config('mercid', '', 'paygw_airpay');
        set_config('username', '', 'paygw_airpay');

        $post = $this->signed_post('MERC001', 'apuser');

        $this->assertFalse(airpay_helper::verify_secure_hash($post));
    }
}
