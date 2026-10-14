<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

namespace local_sentientia_cart;

defined('MOODLE_INTERNAL') || die();

require_once(__DIR__ . '/gst_helpers.php');

/**
 * GST tax invoices (spec 2026-10-14, sections 2 to 6 and 8): the flags, the tax split, the figures taken from what
 * the order recorded, issuing after the payment (never inside it), the scheduled task, incomplete settings, and the
 * document itself.
 *
 * Section 11 of the spec lists what this suite holds:
 *   - the tax split: intra and inter state, B2C by state, B2B by GSTIN, figures from the recorded tax;
 *   - flags: with the invoice flag OFF, nothing is issued;
 *   - an invoice failure never rolls back the payment;
 *   - the task issues due documents;
 *   - incomplete settings: nothing is issued and the administrators are warned.
 *
 * @package    local_sentientia_cart
 * @category   test
 * @covers     \local_sentientia_cart\invoicer
 * @covers     \local_sentientia_cart\flags
 * @covers     \local_sentientia_cart\gst_settings
 * @covers     \local_sentientia_cart\cart_manager::mark_paid
 * @covers     \local_sentientia_cart\task\issue_due_documents
 *
 * @group local_sentientia_cart
 * @group local_sentientia_cart_gst
 * @group tenant_isolation
 */
final class tax_invoice_test extends \advanced_testcase {

    use gst_helpers;

    protected function setUp(): void {
        parent::setUp();
        $this->gst_setup();
    }

    // ── flags ────────────────────────────────────────────────────────────

    public function test_with_the_invoice_flag_off_nothing_is_issued(): void {
        global $DB;
        $buyer = $this->user_at('/77');
        $order = $this->paid_order_for($buyer);

        $this->assertNull(invoicer::issue_for_order($order), 'flag OFF: no invoice');
        $this->assertSame(0, $DB->count_records('local_sentientia_cart_invoices'));
        $this->assertSame(0, $DB->count_records('local_sentientia_cart_docseq'), 'and no number was taken');
    }

    public function test_the_flags_default_to_off_and_are_registered(): void {
        $registry = \local_sentientia_platform\feature_flags::load_registry();
        foreach ([flags::INVOICES, flags::RECEIPTS] as $key) {
            $this->assertArrayHasKey($key, $registry);
            $this->assertFalse($registry[$key]['default'], "$key must default to OFF");
            $this->assertFalse(flags::enabled_for_tenant($key, 77));
            $this->assertFalse(flags::enabled_for_tenant($key, 0));
        }
    }

    public function test_a_payment_with_the_flag_off_marks_nothing_due_and_issues_nothing(): void {
        global $DB;
        $buyer = $this->user_at('/77');
        $order = $this->order_for($buyer);

        $this->assertTrue(cart_manager::mark_paid((int) $order->id, 'TXN-1', ['a' => 1]));

        $paid = $this->order((int) $order->id);
        $this->assertSame('paid', $paid->status);
        $this->assertSame(0, (int) $paid->invoice_due, 'with the flag OFF the order is not even marked due');
        $this->assertFalse($this->invoice_of((int) $order->id));
        $this->assertSame(1, $DB->count_records('local_sentientia_cart_ledger', ['historyid' => $order->id]),
            'the payment itself is recorded as always');
    }

    public function test_the_flag_is_resolved_for_the_orders_tenant_not_for_the_current_user(): void {
        $this->flag(flags::INVOICES, true, 77);   // on for tenant 77 only

        $a = $this->paid_order_for($this->user_at('/1'));
        $b = $this->paid_order_for($this->user_at('/77'));

        // The caller is nobody in particular (a callback, the cron): only the order's tenant decides.
        $this->setUser(0);
        $this->assertNull(invoicer::issue_for_order($a), 'tenant 1: flag OFF');
        $this->assertNotNull(invoicer::issue_for_order($b), 'tenant 77: flag ON');
        $this->assertFalse($this->invoice_of((int) $a->id));
    }

    public function test_the_receipt_flag_does_not_issue_invoices(): void {
        $this->flag(flags::RECEIPTS, true);
        $order = $this->paid_order_for($this->user_at('/77'));
        $this->assertNull(invoicer::issue_for_order($order));
    }

    // ── the payment never depends on the invoice ─────────────────────────

    public function test_a_payment_with_the_flag_on_is_invoiced_right_after_it_commits(): void {
        $this->flag(flags::INVOICES, true);
        $buyer = $this->user_at('/77');
        $order = $this->order_for($buyer);

        $this->assertTrue(cart_manager::mark_paid((int) $order->id, 'TXN-1'));

        $invoice = $this->invoice_of((int) $order->id);
        $this->assertNotFalse($invoice, 'the same request issued the invoice, in its own transaction');
        $this->assertSame(0, (int) $this->order((int) $order->id)->invoice_due, 'and cleared the due mark');
        $this->assertStringStartsWith('AIRPAY-' . date('Y'), $invoice->invoice_number);
    }

    public function test_an_invoice_failure_never_rolls_back_the_payment(): void {
        global $DB;
        $this->flag(flags::INVOICES, true);
        $this->configure_tax(['sac_code' => '']);   // the invoice cannot be issued
        $buyer = $this->user_at('/77');
        $order = $this->order_for($buyer);

        $this->assertTrue(cart_manager::mark_paid((int) $order->id, 'TXN-1'), 'the payment goes through');
        $this->assertDebuggingCalled(null, DEBUG_DEVELOPER, 'the failure is logged');

        $paid = $this->order((int) $order->id);
        $this->assertSame('paid', $paid->status, 'still paid');
        $this->assertSame(1, (int) $paid->invoice_due, 'the invoice stays due');
        $this->assertFalse($this->invoice_of((int) $order->id), 'and nothing was issued');
        $this->assertSame(1, $DB->count_records('local_sentientia_cart_ledger',
            ['historyid' => $order->id, 'event_type' => 'payment_received']), 'the payment is in the ledger');
        $this->assertNotEmpty($paid->gateway_ref, 'and the gateway reference is on the order');
    }

    public function test_a_number_that_would_be_too_long_never_rolls_back_the_payment_either(): void {
        global $DB;
        $this->flag(flags::INVOICES, true);
        $this->configure_tax(['invoice_prefix' => 'AIRPAYX']);   // AIRPAYX-2026-0001 is 17 characters
        $order = $this->order_for($this->user_at('/77'));

        $this->assertTrue(cart_manager::mark_paid((int) $order->id, 'TXN-1'));
        $this->assertDebuggingCalled(null, DEBUG_DEVELOPER);

        $this->assertSame('paid', $this->order((int) $order->id)->status);
        $this->assertSame(1, (int) $this->order((int) $order->id)->invoice_due);
        $this->assertFalse($this->invoice_of((int) $order->id));
        $this->assertSame(0, (int) $DB->get_field('local_sentientia_cart_docseq', 'lastno',
            ['series' => 'AIRPAYX-' . docseq::series_year(time(), 'calendar')]), 'the refused number was released');
    }

    public function test_a_payment_inside_someone_elses_transaction_is_not_invoiced_until_the_task_runs(): void {
        global $DB;
        $this->flag(flags::INVOICES, true);
        $order = $this->order_for($this->user_at('/77'));

        $outer = $DB->start_delegated_transaction();
        $this->assertTrue(cart_manager::mark_paid((int) $order->id, 'TXN-1'));
        $this->assertFalse($this->invoice_of((int) $order->id),
            'the commit has not happened yet: an invoice issued now could outlive a rollback of the payment');
        $this->assertSame(1, (int) $this->order((int) $order->id)->invoice_due);
        $outer->allow_commit();

        $this->expectOutputRegex('/invoices issued=1/');
        (new task\issue_due_documents())->execute();
        $this->assertNotFalse($this->invoice_of((int) $order->id));
        $this->assertSame(0, (int) $this->order((int) $order->id)->invoice_due);
    }

    // ── the tax split ────────────────────────────────────────────────────

    public function test_a_maharashtra_b2c_buyer_pays_cgst_and_sgst_not_igst(): void {
        $this->flag(flags::INVOICES, true);
        // The old invoicer gave every buyer without a GSTIN IGST, even one in the supplier's own state.
        $order = $this->paid_order_for($this->user_at('/77'), ['billing_state' => '27', 'billing_gstn' => '']);
        $invoice = invoicer::issue_for_order($order);

        $this->assertEqualsWithDelta(90.00, (float) $invoice->cgst, 0.001);
        $this->assertEqualsWithDelta(90.00, (float) $invoice->sgst, 0.001);
        $this->assertEqualsWithDelta(0.00, (float) $invoice->igst, 0.001);
        $this->assertSame('27', $invoice->place_of_supply);
        $this->assertSame('27', $invoice->billing_state);
    }

    public function test_a_buyer_in_another_state_pays_igst(): void {
        $this->flag(flags::INVOICES, true);
        $order = $this->paid_order_for($this->user_at('/77'), ['billing_state' => '29']);
        $invoice = invoicer::issue_for_order($order);

        $this->assertEqualsWithDelta(0.00, (float) $invoice->cgst, 0.001);
        $this->assertEqualsWithDelta(0.00, (float) $invoice->sgst, 0.001);
        $this->assertEqualsWithDelta(180.00, (float) $invoice->igst, 0.001);
        $this->assertSame('29', $invoice->place_of_supply);
    }

    public function test_a_b2b_buyers_place_of_supply_is_the_state_in_their_gstin(): void {
        $this->flag(flags::INVOICES, true);
        $this->configure_tax(['einvoice_b2b' => '0']);
        // Checkout refuses a GSTIN and a state that disagree, so a stored order agrees; the GSTIN is what decides.
        $order = $this->paid_order_for($this->user_at('/77'), ['billing_state' => '29', 'billing_gstn' => '29ABCDE1234F1Z5']);
        $invoice = invoicer::issue_for_order($order);

        $this->assertSame('29', $invoice->place_of_supply);
        $this->assertEqualsWithDelta(180.00, (float) $invoice->igst, 0.001);
        $this->assertSame('29ABCDE1234F1Z5', $invoice->billing_gstn);

        $own = $this->paid_order_for($this->user_at('/77'), ['billing_state' => '27', 'billing_gstn' => '27ABCDE1234F1Z5']);
        $invoice = invoicer::issue_for_order($own);
        $this->assertSame('27', $invoice->place_of_supply);
        $this->assertEqualsWithDelta(90.00, (float) $invoice->cgst, 0.001);
    }

    public function test_the_supplier_state_is_the_one_in_our_gstin(): void {
        $this->flag(flags::INVOICES, true);
        // Supplies made from Karnataka: the GSTIN starts 29, so a Karnataka buyer is intra-state.
        $this->configure_tax(['our_gstn' => '29AAAAA0000A1Z5']);
        $inkarnataka = invoicer::issue_for_order($this->paid_order_for($this->user_at('/77'), ['billing_state' => '29']));
        $this->assertEqualsWithDelta(90.00, (float) $inkarnataka->cgst, 0.001);
        $this->assertEqualsWithDelta(0.00, (float) $inkarnataka->igst, 0.001);
        $inmaharashtra = invoicer::issue_for_order($this->paid_order_for($this->user_at('/77'), ['billing_state' => '27']));
        $this->assertEqualsWithDelta(180.00, (float) $inmaharashtra->igst, 0.001);
    }

    public function test_a_supplier_state_setting_that_disagrees_with_our_gstin_refuses_to_start(): void {
        global $DB;
        // Review 2026-10-14, should-fix 6: a Maharashtra GSTIN with the state set to Karnataka would print one state
        // and split the tax by the other.
        $this->flag(flags::INVOICES, true);
        $this->configure_tax(['supplier_state_code' => '29']);   // our_gstn starts 27
        $this->assertContains('supplier_state', gst_settings::problems());
        $this->assert_refused(fn() => invoicer::issue_for_order($this->paid_order_for($this->user_at('/77'))),
            'error_invoicesettings', 'nothing is issued while the two disagree');
        $this->assertSame(0, $DB->count_records('local_sentientia_cart_invoices'));

        $this->configure_tax(['supplier_state_code' => '27']);   // the same state as the GSTIN: fine
        $this->assertSame([], gst_settings::problems());
    }

    public function test_our_gstin_must_be_a_gstin(): void {
        $this->configure_tax(['our_gstn' => '27-NOT-A-GSTIN']);
        $this->assertContains('our_gstn', gst_settings::problems());
    }

    public function test_the_figures_are_what_the_order_recorded_not_todays_rate(): void {
        $this->flag(flags::INVOICES, true);
        // The buyer was charged 10% (tax 100.00, total 1100.00). The setting says 18% today.
        $order = $this->paid_order_for($this->user_at('/77'), ['tax_amount' => 100.00, 'total_amount' => 1100.00,
            'gst_rate' => 10.00]);
        $invoice = invoicer::issue_for_order($order);

        $this->assertEqualsWithDelta(1000.00, (float) $invoice->subtotal, 0.001, 'taxable = subtotal - discount');
        $this->assertEqualsWithDelta(100.00, (float) $invoice->cgst + (float) $invoice->sgst + (float) $invoice->igst, 0.001,
            'tax = tax_amount');
        $this->assertEqualsWithDelta(1100.00, (float) $invoice->total, 0.001, 'total = total_amount');
        $this->assertEqualsWithDelta(10.00, (float) $invoice->gst_rate, 0.001);
        $line = json_decode($invoice->line_items_json, true)[0];
        $this->assertEqualsWithDelta(10.00, (float) $line['rate'], 0.001);
    }

    public function test_checkout_records_the_rate_with_the_tax(): void {
        global $DB;
        set_config('gst_rate', '12', 'local_sentientia_cart');
        $buyer = $this->user_at('/77');
        $order = $this->order_for($buyer, ['status' => 'open', 'gst_rate' => null]);
        $order->items_json = json_encode([['courseid' => 1, 'name' => 'X', 'price' => 500.00, 'discount_pct' => 0]]);

        $order = cart_manager::recompute_totals($order);

        $stored = $DB->get_record('local_sentientia_cart_history', ['id' => $order->id], '*', MUST_EXIST);
        $this->assertEqualsWithDelta(12.0, (float) $stored->gst_rate, 0.001, 'the rate the tax was computed at');
        $this->assertEqualsWithDelta(60.00, (float) $stored->tax_amount, 0.001);
        $this->assertEqualsWithDelta(560.00, (float) $stored->total_amount, 0.001);
    }

    public function test_an_order_with_no_recorded_rate_is_invoiced_at_the_rate_its_figures_imply(): void {
        $this->flag(flags::INVOICES, true);
        $order = $this->paid_order_for($this->user_at('/77'), ['gst_rate' => null, 'billing_state' => null]);
        $invoice = invoicer::issue_for_order($order);
        $this->assertEqualsWithDelta(18.0, (float) $invoice->gst_rate, 0.001);
        $this->assertSame('27', $invoice->place_of_supply, 'no state on the order: supplied at the supplier location');
        $this->assertNull($invoice->billing_state);
    }

    // ── the document ─────────────────────────────────────────────────────

    public function test_the_invoice_carries_the_rule_46_particulars(): void {
        $this->flag(flags::INVOICES, true);
        $order = $this->paid_order_for($this->user_at('/77'));
        $invoice = invoicer::issue_for_order($order);

        $this->assertSame('issued', $invoice->status, 'a B2C invoice is issued at once');
        $this->assertSame('999293', $invoice->sac_code);
        $this->assertSame((int) $order->orderid, (int) $invoice->orderid);
        $this->assertSame((int) $order->id, (int) $invoice->historyid);
        $this->assertEqualsWithDelta((float) $invoice->timecreated, (float) time(), 5);
        $lines = json_decode($invoice->line_items_json, true);
        $this->assertCount(1, $lines);
        $this->assertSame('Course A', $lines[0]['name']);
        $this->assertSame('999293', $lines[0]['sac']);
        $this->assertEqualsWithDelta(1000.00, (float) $lines[0]['taxable'], 0.001);
        $supplier = json_decode($invoice->supplier_json, true);
        $this->assertSame('Airpay Test Pvt Ltd', $supplier['name']);
        $this->assertSame('27AAAAA0000A1Z5', $supplier['gstin']);
        $this->assertSame('27', $supplier['state']);
        $this->assertSame('Authorised signatory', $supplier['signatory']);

        $html = invoicer::render_html($invoice);
        foreach (['Tax Invoice', $invoice->invoice_number, 'Airpay Test Pvt Ltd', '27AAAAA0000A1Z5', '27 - Maharashtra',
                'Place of supply', 'Reverse charge', '999293', 'Course A', 'CGST', 'SGST',
                'Rupees One Thousand One Hundred Eighty only', 'Authorised signatory',
                'This is a computer-generated invoice.', '₹1,180.00', 'Test Buyer'] as $needle) {
            $this->assertStringContainsString($needle, $html, "the invoice shows: $needle");
        }
        $this->assertStringNotContainsString('IGST</th>', $html, 'an intra-state invoice has no IGST column');
    }

    public function test_an_inter_state_invoice_draws_igst_not_cgst_and_sgst(): void {
        $this->flag(flags::INVOICES, true);
        $invoice = invoicer::issue_for_order($this->paid_order_for($this->user_at('/77'), ['billing_state' => '29']));
        $html = invoicer::render_html($invoice);
        $this->assertStringContainsString('IGST', $html);
        $this->assertStringNotContainsString('CGST', $html);
        $this->assertStringContainsString('29 - Karnataka', $html);
    }

    public function test_the_buyers_text_is_escaped(): void {
        $this->flag(flags::INVOICES, true);
        $order = $this->paid_order_for($this->user_at('/77'), [
            'billing_name' => '<script>alert(1)</script>', 'billing_address' => "<b>1 Road</b>\n<img src=x onerror=alert(2)>"]);
        $html = invoicer::render_html(invoicer::issue_for_order($order));
        $this->assertStringNotContainsString('<script>alert(1)', $html);
        $this->assertStringNotContainsString('<img src=x', $html);
        $this->assertStringContainsString('&lt;script&gt;', $html);
        $this->assertStringContainsString('&lt;b&gt;1 Road&lt;/b&gt;', $html);
    }

    public function test_an_issued_invoice_does_not_change_when_the_company_settings_do(): void {
        $this->flag(flags::INVOICES, true);
        $invoice = invoicer::issue_for_order($this->paid_order_for($this->user_at('/77')));
        $this->configure_tax(['company_name' => 'A Different Company', 'our_gstn' => '29ZZZZZ9999Z1Z1',
            'signatory_text' => 'Somebody Else']);

        $html = invoicer::render_html($invoice);
        $this->assertStringContainsString('Airpay Test Pvt Ltd', $html);
        $this->assertStringContainsString('27AAAAA0000A1Z5', $html);
        $this->assertStringNotContainsString('A Different Company', $html);
        $this->assertStringNotContainsString('Somebody Else', $html);
    }

    public function test_an_invoice_from_before_this_release_is_viewable_exactly_as_it_was(): void {
        global $DB;
        $buyer = $this->user_at('/77');
        $order = $this->paid_order_for($buyer);
        $id = $DB->insert_record('local_sentientia_cart_invoices', (object) [
            'historyid' => $order->id, 'orderid' => $order->orderid, 'invoice_number' => 'AIRPAY-2026-0001',
            'userid' => $buyer->id, 'costcenterid' => 77, 'billing_name' => 'Old Buyer', 'billing_email' => 'o@example.com',
            'billing_address' => "1 Old Road", 'billing_gstn' => '', 'line_items_json' => json_encode([['name' => 'Old course',
                'price' => 1000]]), 'subtotal' => 1000, 'cgst' => 0, 'sgst' => 0, 'igst' => 180, 'total' => 1180,
            'currency' => 'INR', 'status' => 'issued', 'timecreated' => 1700000000,
        ]);
        $invoice = $DB->get_record('local_sentientia_cart_invoices', ['id' => $id], '*', MUST_EXIST);

        $html = invoicer::render_html($invoice);   // flags are OFF, and the row has no place of supply
        $this->assertStringContainsString('BILL TO', $html, 'the old layout');
        $this->assertStringContainsString('Old Buyer', $html);
        $this->assertStringNotContainsString('Place of supply', $html);
        invoicer::require_view_access($invoice, (int) $buyer->id);   // the owner still opens it
    }

    // ── numbering through the invoicer ───────────────────────────────────

    public function test_issuing_twice_returns_the_same_invoice(): void {
        global $DB;
        $this->flag(flags::INVOICES, true);
        $order = $this->paid_order_for($this->user_at('/77'));
        $first = invoicer::issue_for_order($order);
        $second = invoicer::issue_for_order($order);
        $this->assertSame((int) $first->id, (int) $second->id);
        $this->assertSame(1, $DB->count_records('local_sentientia_cart_invoices', ['historyid' => $order->id]));
    }

    public function test_orders_paid_one_after_the_other_get_consecutive_numbers(): void {
        $this->flag(flags::INVOICES, true);
        $year = date('Y');
        $numbers = [];
        for ($i = 0; $i < 3; $i++) {
            $numbers[] = invoicer::issue_for_order($this->paid_order_for($this->user_at('/77')))->invoice_number;
        }
        $this->assertSame(["AIRPAY-$year-0001", "AIRPAY-$year-0002", "AIRPAY-$year-0003"], $numbers);
    }

    public function test_the_reserved_erpnext_prefix_is_refused(): void {
        $this->flag(flags::INVOICES, true);
        $this->configure_tax(['invoice_prefix' => 'ERPNEXT']);
        $this->assert_refused(fn() => invoicer::issue_for_order($this->paid_order_for($this->user_at('/77'))),
            'error_invalidstate');
    }

    public function test_an_imported_order_is_never_invoiced(): void {
        $this->flag(flags::INVOICES, true);
        $order = $this->paid_order_for($this->user_at('/77'), ['legacy_source' => 'bizlms']);
        $this->assert_refused(fn() => invoicer::issue_for_order($order), 'error_invalidstate');
    }

    public function test_an_order_that_is_not_paid_is_not_invoiced(): void {
        $this->flag(flags::INVOICES, true);
        $this->assert_refused(fn() => invoicer::issue_for_order($this->order_for($this->user_at('/77'))),
            'error_invalidstate');
    }

    // ── incomplete settings ──────────────────────────────────────────────

    /**
     * @return array<string, array{0: string, 1: array}>
     */
    public static function incomplete(): array {
        return [
            'no company name'    => ['company_name', ['company_name' => '']],
            'no company address' => ['company_address', ['company_address' => '  ']],
            'no GSTN'            => ['our_gstn', ['our_gstn' => '']],
            'no SAC code'        => ['sac_code', ['sac_code' => '']],
            'a state that is not one' => ['supplier_state', ['supplier_state_code' => '99']],
            'a GSTN with no state'    => ['supplier_state', ['our_gstn' => 'XXAAAAA0000A1Z5']],
        ];
    }

    /**
     * @dataProvider incomplete
     * @param string $problem The setting that is named as the cause.
     * @param array $settings
     * @return void
     */
    public function test_incomplete_settings_refuse_to_start_and_nothing_is_issued(string $problem, array $settings): void {
        global $DB;
        $this->flag(flags::INVOICES, true);
        $this->configure_tax($settings);
        $this->assertContains($problem, gst_settings::problems());

        $order = $this->paid_order_for($this->user_at('/77'));
        try {
            invoicer::issue_for_order($order);
            $this->fail('the invoice must be refused');
        } catch (\moodle_exception $e) {
            $this->assertSame('error_invoicesettings', $e->errorcode);
        }
        $this->assertSame(0, $DB->count_records('local_sentientia_cart_invoices'));
        $this->assertSame(0, $DB->count_records('local_sentientia_cart_docseq'), 'no number was taken');
    }

    public function test_a_complete_configuration_has_no_problems(): void {
        $this->assertSame([], gst_settings::problems());
        $this->configure_tax(['our_gstn' => '', 'supplier_state_code' => '27']);
        $this->assertSame(['our_gstn'], gst_settings::problems(), 'a state setting does not replace the GSTN');
    }

    // ── the scheduled task ───────────────────────────────────────────────

    public function test_the_task_issues_every_due_invoice(): void {
        global $DB;
        $this->flag(flags::INVOICES, true);
        $due = [];
        foreach ([77, 77, 1] as $tenant) {
            $order = $this->paid_order_for($this->user_at('/' . $tenant), ['invoice_due' => 1]);
            $due[] = (int) $order->id;
        }
        $notdue = $this->paid_order_for($this->user_at('/77'));   // paid before the flag: not due, never invoiced

        $this->expectOutputRegex('/invoices issued=3 skipped=0 failed=0/');
        (new task\issue_due_documents())->execute();

        foreach ($due as $id) {
            $this->assertNotFalse($this->invoice_of($id), "order $id was due");
            $this->assertSame(0, (int) $this->order($id)->invoice_due);
        }
        $this->assertFalse($this->invoice_of((int) $notdue->id), 'an order that was never marked due is left alone');
        $this->assertSame(3, $DB->count_records('local_sentientia_cart_invoices'));
    }

    public function test_the_task_is_scheduled_every_fifteen_minutes(): void {
        global $CFG;
        $tasks = [];
        require($CFG->dirroot . '/local/sentientia_cart/db/tasks.php');
        $this->assertCount(1, $tasks);
        $this->assertSame('local_sentientia_cart\task\issue_due_documents', $tasks[0]['classname']);
        $this->assertSame('*/15', $tasks[0]['minute']);
        $this->assertSame('*', $tasks[0]['hour']);
        $this->assertNotSame('', (new task\issue_due_documents())->get_name());
    }

    public function test_the_task_leaves_a_due_invoice_while_the_flag_is_off(): void {
        $this->flag(flags::INVOICES, true);
        $order = $this->paid_order_for($this->user_at('/77'), ['invoice_due' => 1]);
        $this->flag(flags::INVOICES, false);   // switched off again before the task ran

        $this->expectOutputRegex('/invoices issued=0 skipped=1 failed=0/');
        $sink = $this->redirectMessages();
        (new task\issue_due_documents())->execute();

        $this->assertFalse($this->invoice_of((int) $order->id), 'flag OFF: nothing is issued');
        $this->assertSame(1, (int) $this->order((int) $order->id)->invoice_due, 'it stays due');
        $this->assertCount(0, $sink->get_messages(), 'and nobody is warned: that is the flag doing its job');
        $sink->close();
    }

    public function test_the_task_warns_the_site_admins_once_a_day_while_the_settings_are_incomplete(): void {
        $this->flag(flags::INVOICES, true);
        $this->configure_tax(['sac_code' => '']);
        $order = $this->paid_order_for($this->user_at('/77'), ['invoice_due' => 1]);

        $sink = $this->redirectMessages();
        ob_start();
        (new task\issue_due_documents())->execute();
        $out = ob_get_clean();
        $this->assertDebuggingCalled(null, DEBUG_DEVELOPER, 'the order that could not be invoiced is logged');
        $messages = array_values(array_filter($sink->get_messages(), fn($m) => $m->eventtype === 'gst_admin_warning'));

        $this->assertFalse($this->invoice_of((int) $order->id), 'nothing is issued while the settings are incomplete');
        $this->assertCount(count(get_admins()), $messages, 'every site administrator is warned');
        $this->assertStringContainsString('administrators warned', $out);
        $this->assertSame('GST invoicing needs attention', $messages[0]->subject);
        $this->assertStringContainsString('SAC code of the service', $messages[0]->fullmessage, 'the cause is named');
        $this->assertSame(1, (int) $this->order((int) $order->id)->invoice_due, 'the invoice stays due');

        // The next run, a quarter of an hour later: the cause is the same and nobody is told again.
        ob_start();
        (new task\issue_due_documents())->execute();
        ob_end_clean();
        $this->assertDebuggingCalled(null, DEBUG_DEVELOPER);
        $again = array_filter($sink->get_messages(), fn($m) => $m->eventtype === 'gst_admin_warning');
        $this->assertCount(count(get_admins()), $again, 'still one warning a day');

        // A day on, the warning is sent again.
        $this->assertTrue(invoicer::warn_admins_once_a_day([['code' => 'error_invoicesettings', 'ids' => ['sac_code']]],
            time() + DAYSECS + 1));
        $this->assertCount(2 * count(get_admins()),
            array_filter($sink->get_messages(), fn($m) => $m->eventtype === 'gst_admin_warning'));
        $sink->close();

        // Fixed: the first run after the fix issues the invoice, with no warning.
        $this->configure_tax();
        $sink = $this->redirectMessages();
        $this->expectOutputRegex('/invoices issued=1/');
        (new task\issue_due_documents())->execute();
        $this->assertNotFalse($this->invoice_of((int) $order->id));
        $this->assertCount(0, array_filter($sink->get_messages(), fn($m) => $m->eventtype === 'gst_admin_warning'));
        $sink->close();
    }

    public function test_the_warning_names_a_number_that_would_be_too_long(): void {
        $this->flag(flags::INVOICES, true);
        $this->configure_tax(['invoice_prefix' => 'AIRPAYX']);
        $this->paid_order_for($this->user_at('/77'), ['invoice_due' => 1]);

        $sink = $this->redirectMessages();
        ob_start();
        (new task\issue_due_documents())->execute();
        ob_end_clean();
        $this->assertDebuggingCalled(null, DEBUG_DEVELOPER);
        $messages = array_values(array_filter($sink->get_messages(), fn($m) => $m->eventtype === 'gst_admin_warning'));
        $sink->close();

        $this->assertNotEmpty($messages);
        $this->assertStringContainsString('17 characters', $messages[0]->fullmessage);
        $this->assertStringContainsString('the limit is 16', $messages[0]->fullmessage);
    }

    public function test_one_failing_order_does_not_stop_the_others(): void {
        $this->flag(flags::INVOICES, true);
        $bad = $this->paid_order_for($this->user_at('/77'), ['invoice_due' => 1, 'status' => 'pending']);   // cannot be invoiced
        $good = $this->paid_order_for($this->user_at('/77'), ['invoice_due' => 1]);

        $this->expectOutputRegex('/invoices issued=1 skipped=0 failed=1/');
        $this->redirectMessages();
        (new task\issue_due_documents())->execute();
        $this->assertDebuggingCalled(null, DEBUG_DEVELOPER);

        $this->assertNotFalse($this->invoice_of((int) $good->id));
        $this->assertFalse($this->invoice_of((int) $bad->id));
    }

    // ── who may open an invoice ──────────────────────────────────────────

    public function test_who_may_open_an_issued_invoice(): void {
        $this->flag(flags::INVOICES, true);
        $buyer = $this->user_at('/77');
        $invoice = invoicer::issue_for_order($this->paid_order_for($buyer));

        invoicer::require_view_access($invoice, (int) $buyer->id);
        invoicer::require_view_access($invoice, (int) get_admin()->id);
        invoicer::require_view_access($invoice, (int) $this->tenant_admin('/77')->id);
        invoicer::require_view_access($invoice, (int) $this->finance_user('/77')->id);

        foreach ([$this->user_at('/77'), $this->tenant_admin('/1'), $this->tenant_admin('/177'), $this->finance_user('/1')] as $no) {
            $this->assert_refused(fn() => invoicer::require_view_access($invoice, (int) $no->id), 'error_outoftenant',
                'another buyer, or an administrator of another tenant, must not open it');
        }
    }

    public function test_the_invoice_prefix_erpnext_is_reserved(): void {
        // ADR-032: ERPNEXT-<id> numbers the invoices imported from BizLMS, and invoice_number is unique. Moved here
        // from imported_history_reader_test on 2026-10-14: issue_for_order now reads the order from the database,
        // so the check needs a real paid order and complete GST settings to be reached.
        $this->flag(flags::INVOICES, true);
        $this->configure_tax(['invoice_prefix' => 'erpnext']);
        $order = $this->paid_order_for($this->user_at('/77'));
        $this->assert_refused(fn() => invoicer::issue_for_order($order), 'error_invalidstate',
            'ERPNEXT is the prefix of an imported invoice');
        $this->assertFalse($this->invoice_of((int) $order->id), 'nothing was issued');
    }

    public function test_the_einvoice_page_follows_the_invoice_flag(): void {
        // Review 2026-10-14, should-fix 5: while the flag is OFF everywhere a site administrator gets neither the
        // e-invoice button nor the page.
        $admin = (int) get_admin()->id;
        $this->paid_order_for($this->user_at('/77'));
        $this->flag(flags::INVOICES, false);
        $this->assertFalse(flags::invoices_for_viewer($admin), 'OFF everywhere');
        $this->flag(flags::INVOICES, true, 77);
        $this->assertTrue(flags::invoices_for_viewer($admin), 'ON for a tenant that has orders');
    }
}
