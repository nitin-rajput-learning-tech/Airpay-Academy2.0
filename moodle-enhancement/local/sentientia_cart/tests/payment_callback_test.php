<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

namespace local_sentientia_cart;

use local_sentientia_cart\gateway\airpay_gateway;

defined('MOODLE_INTERNAL') || die();

/**
 * Tests for the payment callback path.
 *
 * @covers \local_sentientia_cart\gateway\airpay_gateway
 * @covers \local_sentientia_cart\cart_manager::mark_paid
 *
 * THIS PLUGIN SHIPPED WITH NO tests/ DIRECTORY AT ALL.
 * ---------------------------------------------------
 * It is the only surface in the product that moves money and grants a paid
 * entitlement, and it had zero automated coverage. The 2026-09-22 audit found
 * verify_callback() failing OPEN:
 *
 *   $secret = get_config('local_sentientia_cart', 'airpay_secret');  // ships ''
 *   $expected = self::compute_checksum($payload, $secret);
 *   return hash_equals($expected, $payload['checksum']);
 *
 * With the shipped default secret of '', compute_checksum($payload, '') is
 * fully computable by anyone who has read this open-source file. A
 * self-registered learner could sign their own callback and receive a free
 * enrolment plus a genuine tax invoice. The IP allowlist in callback.php
 * narrows who can reach the endpoint; it is not a signature check, and it is
 * empty by default too.
 *
 * test_an_unconfigured_gateway_refuses_a_self_signed_callback() below performs
 * that exact attack and asserts it now fails.
 *
 * @package    local_sentientia_cart
 * @category   test
 *
 * @group tenant_isolation
 */
final class payment_callback_test extends \advanced_testcase {

    /** @var string A configured secret for the positive cases. */
    private const SECRET = 'test-merchant-secret-value';

    /**
     * Reproduce the gateway's checksum scheme exactly as the plugin computes
     * it. Deliberately written out here rather than calling the private
     * method: if someone changes the scheme, this test should notice.
     *
     * @param array $params
     * @param string $secret
     * @return string
     */
    private function sign(array $params, string $secret): string {
        unset($params['checksum']);
        ksort($params);
        $base = '';
        foreach ($params as $k => $v) {
            $base .= $k . '=' . $v . '|';
        }
        return hash('sha256', $base . 'secret=' . $secret);
    }

    /**
     * A well-formed success payload for an order.
     *
     * @param string $orderid
     * @param string $amount
     * @return array
     */
    private function payload(int $orderid, string $amount = '1500.00'): array {
        return [
            'TRANSACTIONID' => 'TXN-0001',
            'TRANSACTIONSTATUS' => '200',
            'order_id' => $orderid,
            'amount' => $amount,
            'currency_code' => 'INR',
        ];
    }

    // ── the defect ───────────────────────────────────────────────────────

    public function test_an_unconfigured_gateway_refuses_a_self_signed_callback(): void {
        $this->resetAfterTest();

        // The shipped default. No admin has touched the setting.
        set_config('airpay_secret', '', 'local_sentientia_cart');

        $gateway = new airpay_gateway();

        // The attack: an attacker who has read this open-source repository
        // knows the scheme, and the secret is the empty string, so they can
        // produce a checksum that verifies.
        $payload = $this->payload(10001);
        $payload['checksum'] = $this->sign($payload, '');

        $this->assertFalse($gateway->verify_callback($payload),
            'a callback signed with the shipped empty secret must be refused; '
            . 'accepting it grants a free enrolment and issues a real invoice');
    }

    public function test_a_whitespace_only_secret_is_also_unconfigured(): void {
        $this->resetAfterTest();
        set_config('airpay_secret', "  \n\t ", 'local_sentientia_cart');

        $gateway = new airpay_gateway();
        $payload = $this->payload(10002);
        $payload['checksum'] = $this->sign($payload, "  \n\t ");

        $this->assertFalse($gateway->verify_callback($payload),
            'a secret of only whitespace is a misconfiguration, not a secret');
    }

    // ── the normal path still works ──────────────────────────────────────

    public function test_a_correctly_signed_callback_is_accepted(): void {
        $this->resetAfterTest();
        set_config('airpay_secret', self::SECRET, 'local_sentientia_cart');

        $gateway = new airpay_gateway();
        $payload = $this->payload(10003);
        $payload['checksum'] = $this->sign($payload, self::SECRET);

        $this->assertTrue($gateway->verify_callback($payload),
            'the guard must not break the configured gateway - otherwise this '
            . 'suite would pass with verify_callback() hardcoded to false');
    }

    public function test_a_mutated_field_invalidates_the_signature(): void {
        $this->resetAfterTest();
        set_config('airpay_secret', self::SECRET, 'local_sentientia_cart');

        $gateway = new airpay_gateway();

        foreach (['amount' => '1.00',
                  'order_id' => '19999',
                  'currency_code' => 'USD',
                  'TRANSACTIONSTATUS' => '200 '] as $field => $tampered) {
            $payload = $this->payload(10004);
            $payload['checksum'] = $this->sign($payload, self::SECRET);
            $payload[$field] = $tampered;

            $this->assertFalse($gateway->verify_callback($payload),
                "changing {$field} after signing must invalidate the checksum");
        }
    }

    public function test_a_missing_checksum_is_refused(): void {
        $this->resetAfterTest();
        set_config('airpay_secret', self::SECRET, 'local_sentientia_cart');

        $gateway = new airpay_gateway();
        $payload = $this->payload(10005);

        $this->assertFalse($gateway->verify_callback($payload));

        $payload['checksum'] = '';
        $this->assertFalse($gateway->verify_callback($payload));
    }

    public function test_the_wrong_secret_is_refused(): void {
        $this->resetAfterTest();
        set_config('airpay_secret', self::SECRET, 'local_sentientia_cart');

        $gateway = new airpay_gateway();
        $payload = $this->payload(10006);
        $payload['checksum'] = $this->sign($payload, 'a-different-secret');

        $this->assertFalse($gateway->verify_callback($payload));
    }

    // ── status and reference parsing ─────────────────────────────────────

    public function test_only_a_success_status_counts_as_paid(): void {
        $gateway = new airpay_gateway();

        $this->assertTrue($gateway->is_success(['TRANSACTIONSTATUS' => '200']));
        $this->assertTrue($gateway->is_success(['status' => 'success']));
        $this->assertTrue($gateway->is_success(['status' => 'SUCCESS']));

        foreach ([['TRANSACTIONSTATUS' => '400'],
                  ['TRANSACTIONSTATUS' => '000'],
                  ['status' => 'pending'],
                  ['status' => 'failed'],
                  []] as $payload) {
            $this->assertFalse($gateway->is_success($payload),
                'anything other than an explicit success must not enrol');
        }
    }

    public function test_the_transaction_reference_is_read_from_any_spelling(): void {
        $gateway = new airpay_gateway();

        $this->assertSame('A1', $gateway->extract_reference(['TRANSACTIONID' => 'A1']));
        $this->assertSame('B2', $gateway->extract_reference(['transaction_id' => 'B2']));
        $this->assertSame('C3', $gateway->extract_reference(['ap_transactionid' => 'C3']));
        $this->assertSame('', $gateway->extract_reference([]));
    }

    // ── replay safety ────────────────────────────────────────────────────

    /**
     * Insert a pending order for a user holding one course.
     *
     * @param int $userid
     * @param int $courseid
     * @param float $total
     * @return int history id
     */
    private function pending_order(int $userid, int $courseid, float $total = 1500.00): int {
        global $DB;
        return (int) $DB->insert_record('local_sentientia_cart_history', (object) [
            // orderid is an INT column, not a string - verified against
            // {local_sentientia_cart_history} on the live schema.
            'orderid' => (int) ($userid * 1000 + $courseid),
            'userid' => $userid,
            'costcenterid' => 1,
            'items_json' => json_encode([['courseid' => $courseid,
                                          'price' => $total, 'name' => 'Course']]),
            'subtotal' => $total,
            'discount_amount' => 0,
            'tax_amount' => 0,
            'total_amount' => $total,
            'currency' => 'INR',
            'status' => 'pending',
            'gateway' => 'airpay',
            'gateway_ref' => '',
            'billing_name' => 'Test Buyer',
            'billing_email' => 'buyer@example.com',
            'billing_phone' => '9999999999',
            'billing_address' => '1 Test Road',
            'billing_gstn' => '',
            'notes' => '',
            'timecreated' => time(),
            'timepaid' => 0,
            'timemodified' => time(),
        ]);
    }

    public function test_a_replayed_callback_does_not_charge_or_enrol_twice(): void {
        $this->resetAfterTest();
        global $DB;

        $user = $this->getDataGenerator()->create_user();
        $course = $this->getDataGenerator()->create_course();
        $historyid = $this->pending_order((int) $user->id, (int) $course->id);

        cart_manager::mark_paid($historyid, 'TXN-1', ['first' => true]);

        $ledgerafterfirst = $DB->count_records('local_sentientia_cart_ledger',
            ['historyid' => $historyid, 'event_type' => 'payment_received']);
        $invoicesafterfirst = $DB->count_records('local_sentientia_cart_invoices',
            ['historyid' => $historyid]);

        // A gateway that does not see our 200 will retry. That must be safe.
        $this->assertTrue(cart_manager::mark_paid($historyid, 'TXN-1', ['replay' => true]));

        $this->assertSame($ledgerafterfirst,
            $DB->count_records('local_sentientia_cart_ledger',
                ['historyid' => $historyid, 'event_type' => 'payment_received']),
            'a retried callback must not write a second payment ledger row');
        $this->assertSame($invoicesafterfirst,
            $DB->count_records('local_sentientia_cart_invoices',
                ['historyid' => $historyid]),
            'a retried callback must not issue a second tax invoice');

        $this->assertSame('paid', $DB->get_field('local_sentientia_cart_history',
            'status', ['id' => $historyid]));
    }

    public function test_marking_a_refunded_order_paid_is_refused(): void {
        $this->resetAfterTest();
        global $DB;

        $user = $this->getDataGenerator()->create_user();
        $course = $this->getDataGenerator()->create_course();
        $historyid = $this->pending_order((int) $user->id, (int) $course->id);

        $DB->set_field('local_sentientia_cart_history', 'status', 'refunded',
            ['id' => $historyid]);

        $this->expectException(\moodle_exception::class);
        cart_manager::mark_paid($historyid, 'TXN-2', []);
    }

    public function test_a_failed_order_can_still_be_paid_on_retry(): void {
        $this->resetAfterTest();
        global $DB;

        $user = $this->getDataGenerator()->create_user();
        $course = $this->getDataGenerator()->create_course();
        $historyid = $this->pending_order((int) $user->id, (int) $course->id);

        cart_manager::mark_failed($historyid, 'gateway timeout');
        $this->assertSame('failed', $DB->get_field('local_sentientia_cart_history',
            'status', ['id' => $historyid]));

        // The buyer tries again and it goes through.
        $this->assertTrue(cart_manager::mark_paid($historyid, 'TXN-3', []));
        $this->assertSame('paid', $DB->get_field('local_sentientia_cart_history',
            'status', ['id' => $historyid]));
    }
}
