<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

namespace local_sentientia_cart;

defined('MOODLE_INTERNAL') || die();

require_once(__DIR__ . '/gst_helpers.php');

/**
 * Checkout and the place of supply (spec 2026-10-14, section 5): the buyer's state, the GSTIN-versus-state rule, the
 * rate recorded with the tax, and the end to end path from a basket to a correctly split invoice.
 *
 * While the invoice flag is OFF checkout asks for nothing new and behaves exactly as it always did; while it is ON the
 * state is required, a GSTIN must look like one, and its first two digits must equal the chosen state.
 *
 * @package    local_sentientia_cart
 * @category   test
 * @covers     \local_sentientia_cart\cart_manager::checkout
 * @covers     \local_sentientia_cart\cart_manager::recompute_totals
 * @covers     \local_sentientia_cart\external\checkout
 *
 * @group local_sentientia_cart
 * @group local_sentientia_cart_gst
 * @group tenant_isolation
 */
final class gst_checkout_test extends \advanced_testcase {

    use gst_helpers;

    protected function setUp(): void {
        parent::setUp();
        $this->gst_setup();
    }

    /**
     * A course for sale: an enabled enrol_fee instance with a cost, in the tenant, visible.
     *
     * @param string $path
     * @param float $price
     * @return \stdClass
     */
    private function priced_course_at(string $path, float $price = 1000.00): \stdClass {
        global $DB;
        $course = $this->course_at($path);
        $DB->insert_record('enrol', (object) [
            'enrol' => 'fee', 'status' => ENROL_INSTANCE_ENABLED, 'courseid' => $course->id, 'sortorder' => 9,
            'cost' => number_format($price, 2, '.', ''), 'currency' => 'INR',
            'roleid' => (int) $DB->get_field('role', 'id', ['shortname' => 'student']),
            'timecreated' => time(), 'timemodified' => time(),
        ]);
        return $course;
    }

    /**
     * A buyer with a basket of one priced course.
     *
     * @return \stdClass The buyer.
     */
    private function buyer_with_basket(): \stdClass {
        $buyer = $this->user_at('/77');
        $this->setUser($buyer);
        cart_manager::add_item((int) $buyer->id, (int) $this->priced_course_at('/77')->id);
        return $buyer;
    }

    private function billing(array $more = []): array {
        return $more + ['billing_name' => 'Test Buyer', 'billing_email' => 'buyer@example.com',
            'billing_phone' => '9999999999', 'billing_address' => '1 Test Road', 'billing_gstn' => '',
            'billing_state' => '27'];
    }

    // ── with the flag ON ─────────────────────────────────────────────────

    public function test_the_state_is_required_while_the_invoice_flag_is_on(): void {
        $this->flag(flags::INVOICES, true);
        $buyer = $this->buyer_with_basket();
        $this->assert_refused(fn() => cart_manager::checkout((int) $buyer->id, $this->billing(['billing_state' => '']), 'airpay'),
            'error_statemissing');
        $unset = $this->billing();
        unset($unset['billing_state']);
        $this->assert_refused(fn() => cart_manager::checkout((int) $buyer->id, $unset, 'airpay'), 'error_statemissing');
    }

    public function test_the_state_must_be_a_gst_state_code(): void {
        $this->flag(flags::INVOICES, true);
        $buyer = $this->buyer_with_basket();
        foreach (['99', '00', '39', 'MH', '7'] as $bad) {
            $this->assert_refused(fn() => cart_manager::checkout((int) $buyer->id, $this->billing(['billing_state' => $bad]), 'airpay'),
                'error_statenotvalid', "'$bad' is not a GST state code");
        }
    }

    public function test_a_gstin_and_a_state_that_disagree_refuse_checkout(): void {
        $this->flag(flags::INVOICES, true);
        $buyer = $this->buyer_with_basket();
        $this->assert_refused(fn() => cart_manager::checkout((int) $buyer->id,
            $this->billing(['billing_state' => '27', 'billing_gstn' => '29ABCDE1234F1Z5']), 'airpay'), 'error_gstinstate',
            'a Karnataka GSTIN with Maharashtra chosen');
    }

    public function test_a_gstin_must_look_like_one(): void {
        $this->flag(flags::INVOICES, true);
        $buyer = $this->buyer_with_basket();
        foreach (['27ABCDE', 'not a gstin', '27ABCDE1234F1Z55'] as $bad) {
            $this->assert_refused(fn() => cart_manager::checkout((int) $buyer->id,
                $this->billing(['billing_gstn' => $bad]), 'airpay'), 'error_gstinformat', $bad);
        }
    }

    public function test_a_refused_checkout_burns_no_order_number_and_leaves_the_basket_open(): void {
        global $DB;
        $this->flag(flags::INVOICES, true);
        $buyer = $this->buyer_with_basket();
        $before = $DB->count_records('local_sentientia_cart_id');
        $this->assert_refused(fn() => cart_manager::checkout((int) $buyer->id, $this->billing(['billing_state' => '']), 'airpay'),
            'error_statemissing');
        $this->assertSame($before, $DB->count_records('local_sentientia_cart_id'), 'no order number was reserved');
        $this->assertSame('open', $DB->get_field('local_sentientia_cart_history', 'status', ['userid' => $buyer->id]));
    }

    public function test_the_state_and_a_tidied_gstin_are_stored_on_the_order(): void {
        global $DB;
        $this->flag(flags::INVOICES, true);
        $buyer = $this->buyer_with_basket();

        $order = cart_manager::checkout((int) $buyer->id,
            $this->billing(['billing_state' => '29', 'billing_gstn' => ' 29abcde1234f1z5 ']), 'airpay');

        $stored = $DB->get_record('local_sentientia_cart_history', ['id' => $order->id], '*', MUST_EXIST);
        $this->assertSame('pending', $stored->status);
        $this->assertSame('29', $stored->billing_state);
        $this->assertSame('29ABCDE1234F1Z5', $stored->billing_gstn);
        $this->assertEqualsWithDelta(18.0, (float) $stored->gst_rate, 0.001, 'the rate the tax was computed at');
        $this->assertEqualsWithDelta(180.00, (float) $stored->tax_amount, 0.001);
    }

    public function test_a_basket_becomes_a_correctly_split_invoice_end_to_end(): void {
        $this->flag(flags::INVOICES, true);

        // A buyer in Maharashtra (the supplier's own state): CGST + SGST.
        $buyer = $this->buyer_with_basket();
        $order = cart_manager::checkout((int) $buyer->id, $this->billing(['billing_state' => '27']), 'airpay');
        cart_manager::mark_paid((int) $order->id, 'TXN-1');
        $invoice = $this->invoice_of((int) $order->id);
        $this->assertNotFalse($invoice);
        $this->assertEqualsWithDelta(90.00, (float) $invoice->cgst, 0.001);
        $this->assertEqualsWithDelta(90.00, (float) $invoice->sgst, 0.001);
        $this->assertEqualsWithDelta(0.00, (float) $invoice->igst, 0.001);
        $this->assertEqualsWithDelta(1180.00, (float) $invoice->total, 0.001);

        // A buyer in Karnataka: IGST.
        $other = $this->buyer_with_basket();
        $order = cart_manager::checkout((int) $other->id, $this->billing(['billing_state' => '29']), 'airpay');
        cart_manager::mark_paid((int) $order->id, 'TXN-2');
        $invoice = $this->invoice_of((int) $order->id);
        $this->assertEqualsWithDelta(180.00, (float) $invoice->igst, 0.001);
        $this->assertEqualsWithDelta(0.00, (float) $invoice->cgst + (float) $invoice->sgst, 0.001);
        $this->assertSame('29', $invoice->place_of_supply);
    }

    public function test_the_rate_in_force_at_checkout_is_the_rate_on_the_invoice(): void {
        $this->flag(flags::INVOICES, true);
        set_config('gst_rate', '12', 'local_sentientia_cart');
        $buyer = $this->buyer_with_basket();
        $order = cart_manager::checkout((int) $buyer->id, $this->billing(), 'airpay');

        set_config('gst_rate', '28', 'local_sentientia_cart');   // changed before the gateway calls back
        cart_manager::mark_paid((int) $order->id, 'TXN-3');

        $invoice = $this->invoice_of((int) $order->id);
        $this->assertEqualsWithDelta(12.0, (float) $invoice->gst_rate, 0.001);
        $this->assertEqualsWithDelta(120.00, (float) $invoice->cgst + (float) $invoice->sgst + (float) $invoice->igst, 0.001,
            '12% of 1000, as the buyer was charged');
        $this->assertEqualsWithDelta(1120.00, (float) $invoice->total, 0.001);
    }

    // ── with the flag OFF: exactly what it always was ────────────────────

    public function test_with_the_flag_off_checkout_asks_for_nothing_new(): void {
        global $DB;
        $buyer = $this->buyer_with_basket();
        $unset = $this->billing(['billing_gstn' => 'typed as before']);
        unset($unset['billing_state']);

        $order = cart_manager::checkout((int) $buyer->id, $unset, 'airpay');

        $stored = $DB->get_record('local_sentientia_cart_history', ['id' => $order->id], '*', MUST_EXIST);
        $this->assertSame('pending', $stored->status);
        $this->assertNull($stored->billing_state);
        $this->assertSame('typed as before', $stored->billing_gstn, 'stored as typed');
    }

    public function test_with_the_flag_off_a_state_that_is_sent_is_still_checked(): void {
        $buyer = $this->buyer_with_basket();
        $this->assert_refused(fn() => cart_manager::checkout((int) $buyer->id, $this->billing(['billing_state' => '99']), 'airpay'),
            'error_statenotvalid');
        $this->assert_refused(fn() => cart_manager::checkout((int) $buyer->id,
            $this->billing(['billing_state' => '27', 'billing_gstn' => '29ABCDE1234F1Z5']), 'airpay'), 'error_gstinstate');
        $order = cart_manager::checkout((int) $buyer->id, $this->billing(['billing_state' => '29']), 'airpay');
        $this->assertSame('29', $this->order((int) $order->id)->billing_state);
    }

    public function test_with_the_flag_off_a_payment_is_never_invoiced(): void {
        $buyer = $this->buyer_with_basket();
        $order = cart_manager::checkout((int) $buyer->id, $this->billing(), 'airpay');
        cart_manager::mark_paid((int) $order->id, 'TXN-4');
        $this->assertFalse($this->invoice_of((int) $order->id));
        $this->assertSame(0, (int) $this->order((int) $order->id)->invoice_due);
    }

    // ── the web service ──────────────────────────────────────────────────

    public function test_the_checkout_web_service_takes_the_state(): void {
        $params = \local_sentientia_cart\external\checkout::execute_parameters();
        $this->assertArrayHasKey('billing_state', $params->keys);
        $this->assertSame(VALUE_DEFAULT, $params->keys['billing_state']->required, 'optional: older callers keep working');
        $this->assertSame('', $params->keys['billing_state']->default);
    }

    public function test_the_state_select_offers_every_state_with_its_code(): void {
        $options = gst_states::options('en');
        $this->assertSame('27 - Maharashtra', $options['27']);
        $this->assertSame('97 - Other Territory', $options['97']);
        $this->assertSame(['01', '02'], array_slice(array_keys($options), 0, 2));
    }
}
