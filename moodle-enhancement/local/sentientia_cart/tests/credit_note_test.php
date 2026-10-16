<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

namespace local_sentientia_cart;

defined('MOODLE_INTERNAL') || die();

require_once(__DIR__ . '/gst_helpers.php');

/**
 * GST credit notes on refunds (CGST Act section 34; spec 2026-10-14, section 7).
 *
 * A refund of an order that has an issued (or awaiting_irn) tax invoice, while the invoice flag is ON for the order's
 * tenant, raises a credit note after the refund commits: created due, then numbered and issued in its own
 * transaction. The amount is gross, the tax is backed out of it and split as the invoice was, the credit note names
 * the invoice's number and date, and the credit notes of an invoice never total more than the invoice.
 *
 * @package    local_sentientia_cart
 * @category   test
 * @covers     \local_sentientia_cart\credit_notes
 * @covers     \local_sentientia_cart\cart_manager::refund
 *
 * @group local_sentientia_cart
 * @group local_sentientia_cart_gst
 * @group tenant_isolation
 */
final class credit_note_test extends \advanced_testcase {

    use gst_helpers;

    protected function setUp(): void {
        parent::setUp();
        $this->gst_setup();
        $this->flag(flags::INVOICES, true);
    }

    /**
     * A paid order with an issued invoice.
     *
     * @param array $more order columns
     * @param string $path the buyer's tenant path
     * @return array [order, invoice]
     */
    private function invoiced(array $more = [], string $path = '/77'): array {
        $order = $this->paid_order_for($this->user_at($path), $more);
        $invoice = invoicer::issue_for_order($order);
        $this->assertNotNull($invoice);
        return [$order, $invoice];
    }

    private function credit_notes_of(int $historyid): array {
        global $DB;
        return array_values($DB->get_records('local_sentientia_cart_creditnotes', ['historyid' => $historyid], 'id ASC'));
    }

    public function test_a_full_refund_raises_a_credit_note_for_the_whole_invoice(): void {
        [$order, $invoice] = $this->invoiced();
        $year = date('Y');

        $this->assertTrue(cart_manager::refund((int) $order->id, 0, 'Customer request', (int) get_admin()->id));

        $notes = $this->credit_notes_of((int) $order->id);
        $this->assertCount(1, $notes);
        $cn = $notes[0];
        $this->assertSame("AIRCN-$year-0001", $cn->cn_number);
        $this->assertSame('issued', $cn->status);
        $this->assertSame((int) $invoice->id, (int) $cn->invoiceid);
        $this->assertEqualsWithDelta(1000.00, (float) $cn->taxable, 0.001);
        $this->assertEqualsWithDelta(90.00, (float) $cn->cgst, 0.001);
        $this->assertEqualsWithDelta(90.00, (float) $cn->sgst, 0.001);
        $this->assertEqualsWithDelta(0.00, (float) $cn->igst, 0.001);
        $this->assertEqualsWithDelta(1180.00, (float) $cn->total, 0.001);
        $this->assertSame('Customer request', $cn->reason);
        $this->assertSame('27', $cn->place_of_supply);
        $this->assertSame('999293', $cn->sac_code);
        $this->assertNotEmpty($cn->supplier_json, 'the supplier as printed on the invoice');
        $this->assertSame('refunded', $this->order((int) $order->id)->status);
    }

    public function test_a_partial_refund_credits_its_share_of_the_taxable_value_and_the_tax(): void {
        [$order, $invoice] = $this->invoiced();

        cart_manager::refund((int) $order->id, 590.00, 'Half', (int) get_admin()->id);

        $cn = $this->credit_notes_of((int) $order->id)[0];
        $this->assertEqualsWithDelta(500.00, (float) $cn->taxable, 0.001, 'round(590 / 1.18, 2)');
        $this->assertEqualsWithDelta(45.00, (float) $cn->cgst, 0.001);
        $this->assertEqualsWithDelta(45.00, (float) $cn->sgst, 0.001);
        $this->assertEqualsWithDelta(590.00, (float) $cn->total, 0.001, 'the refunded amount is gross');
        $this->assertSame('partial_refund', $this->order((int) $order->id)->status);
    }

    public function test_an_inter_state_credit_note_is_igst(): void {
        [$order] = $this->invoiced(['billing_state' => '29']);
        cart_manager::refund((int) $order->id, 590.00, 'Half', (int) get_admin()->id);
        $cn = $this->credit_notes_of((int) $order->id)[0];
        $this->assertEqualsWithDelta(500.00, (float) $cn->taxable, 0.001);
        $this->assertEqualsWithDelta(90.00, (float) $cn->igst, 0.001);
        $this->assertEqualsWithDelta(0.00, (float) $cn->cgst + (float) $cn->sgst, 0.001);
        $this->assertSame('29', $cn->place_of_supply);
    }

    public function test_the_credit_notes_of_several_refunds_add_up_to_the_invoice_exactly(): void {
        [$order, $invoice] = $this->invoiced();
        $admin = (int) get_admin()->id;

        cart_manager::refund((int) $order->id, 333.33, 'Part 1', $admin);
        cart_manager::refund((int) $order->id, 333.33, 'Part 2', $admin);
        cart_manager::refund((int) $order->id, 0, 'The rest', $admin);   // 0 = the whole remaining balance, 513.34

        $notes = $this->credit_notes_of((int) $order->id);
        $this->assertCount(3, $notes);
        $sum = fn(string $k) => round(array_sum(array_map(fn($n) => (float) $n->$k, $notes)), 2);
        $this->assertEqualsWithDelta((float) $invoice->subtotal, $sum('taxable'), 0.0001, 'taxable adds up to the invoice');
        $this->assertEqualsWithDelta((float) $invoice->cgst, $sum('cgst'), 0.0001);
        $this->assertEqualsWithDelta((float) $invoice->sgst, $sum('sgst'), 0.0001);
        $this->assertEqualsWithDelta((float) $invoice->total, $sum('total'), 0.0001, 'and so does the gross');
        $this->assertSame(['AIRCN-' . date('Y') . '-0001', 'AIRCN-' . date('Y') . '-0002', 'AIRCN-' . date('Y') . '-0003'],
            array_map(fn($n) => $n->cn_number, $notes));
    }

    public function test_the_credit_notes_of_an_invoice_may_not_total_more_than_the_invoice(): void {
        [$order] = $this->invoiced();
        $order = $this->order((int) $order->id);

        credit_notes::create_due($order, 1000.00, 9001, 'First');
        $this->assert_refused(fn() => credit_notes::create_due($order, 180.02, 9002, 'Too much'), 'error_creditnotelimit',
            '1000.00 + 180.02 is more than the invoice (1180.00)');
        $this->assertCount(1, $this->credit_notes_of((int) $order->id), 'the refused one left nothing behind');

        // Up to a paisa over is a rounding slip (refund() itself allows it): the credit note takes what is left.
        $last = credit_notes::create_due($order, 180.01, 9003, 'Last');
        $this->assertEqualsWithDelta(180.00, (float) $last->total, 0.001);
        $this->assert_refused(fn() => credit_notes::create_due($order, 0.02, 9004, 'Nothing is left'), 'error_creditnotelimit');
    }

    public function test_one_credit_note_per_refund(): void {
        [$order] = $this->invoiced();
        $order = $this->order((int) $order->id);
        $first = credit_notes::create_due($order, 100.00, 777, 'Once');
        $second = credit_notes::create_due($order, 100.00, 777, 'Once');
        $this->assertSame((int) $first->id, (int) $second->id, 'the same refund never gets two credit notes');
        $this->assertCount(1, $this->credit_notes_of((int) $order->id));
    }

    public function test_with_the_flag_off_no_credit_note_is_issued(): void {
        [$order] = $this->invoiced();
        $this->flag(flags::INVOICES, false);

        $this->assertTrue(cart_manager::refund((int) $order->id, 0, 'Off', (int) get_admin()->id), 'the refund itself works');
        $this->assertCount(0, $this->credit_notes_of((int) $order->id), 'flag OFF: no credit note');
    }

    public function test_an_order_without_an_invoice_gets_no_credit_note(): void {
        $order = $this->paid_order_for($this->user_at('/77'));   // paid, never invoiced
        cart_manager::refund((int) $order->id, 0, 'No invoice', (int) get_admin()->id);
        $this->assertCount(0, $this->credit_notes_of((int) $order->id));
    }

    public function test_an_invoice_from_before_this_release_gets_no_credit_note(): void {
        global $DB;
        $buyer = $this->user_at('/77');
        $order = $this->paid_order_for($buyer);
        $DB->insert_record('local_sentientia_cart_invoices', (object) [
            'historyid' => $order->id, 'orderid' => $order->orderid, 'invoice_number' => 'AIRPAY-2026-0001',
            'userid' => $buyer->id, 'costcenterid' => 77, 'billing_name' => 'Old', 'line_items_json' => '[]',
            'subtotal' => 1000, 'cgst' => 0, 'sgst' => 0, 'igst' => 180, 'total' => 1180, 'currency' => 'INR',
            'status' => 'issued', 'timecreated' => 1700000000,
        ]);
        cart_manager::refund((int) $order->id, 0, 'Old invoice', (int) get_admin()->id);
        $this->assertCount(0, $this->credit_notes_of((int) $order->id),
            'such an invoice has no place of supply, SAC or rate to repeat: Finance handles it as before');
    }

    public function test_a_credit_note_failure_never_rolls_back_the_refund(): void {
        global $DB;
        [$order] = $this->invoiced();
        $this->configure_tax(['sac_code' => '']);   // the credit note cannot be numbered

        $this->assertTrue(cart_manager::refund((int) $order->id, 0, 'Settings broke', (int) get_admin()->id));
        $this->assertDebuggingCalled(null, DEBUG_DEVELOPER);

        $this->assertSame('refunded', $this->order((int) $order->id)->status, 'the refund stands');
        $this->assertSame(1, $DB->count_records('local_sentientia_cart_ledger',
            ['historyid' => $order->id, 'event_type' => 'refund_full']));
        $notes = $this->credit_notes_of((int) $order->id);
        $this->assertCount(1, $notes, 'the credit note was created');
        $this->assertSame('due', $notes[0]->status, 'and waits for a number');
        $this->assertNull($notes[0]->cn_number);

        // The settings are fixed: the task numbers it.
        $this->configure_tax();
        $this->expectOutputRegex('/credit notes issued=1/');
        (new task\issue_due_documents())->execute();
        $cn = $this->credit_notes_of((int) $order->id)[0];
        $this->assertSame('issued', $cn->status);
        $this->assertSame('AIRCN-' . date('Y') . '-0001', $cn->cn_number);
    }

    public function test_a_due_credit_note_is_not_a_document_yet(): void {
        [$order] = $this->invoiced();
        $order = $this->order((int) $order->id);
        $cn = credit_notes::create_due($order, 100.00, 5, 'Due');
        $this->assert_refused(fn() => credit_notes::require_view_access($cn, (int) get_admin()->id), 'error_notavailable');
    }

    public function test_issuing_is_idempotent_and_does_not_burn_numbers(): void {
        global $DB;
        [$order] = $this->invoiced();
        $order = $this->order((int) $order->id);
        $cn = credit_notes::create_due($order, 100.00, 5, 'Once');

        $first = credit_notes::issue((int) $cn->id);
        $second = credit_notes::issue((int) $cn->id);
        $this->assertSame($first->cn_number, $second->cn_number);
        $this->assertSame(1, (int) $DB->get_field('local_sentientia_cart_docseq', 'lastno',
            ['series' => 'AIRCN-' . date('Y')]));
    }

    public function test_a_b2b_credit_note_awaits_the_irn_like_its_invoice(): void {
        $this->configure_tax(['einvoice_b2b' => '1']);
        [$order, $invoice] = $this->invoiced(['billing_state' => '29', 'billing_gstn' => '29ABCDE1234F1Z5']);
        $this->assertSame('awaiting_irn', $invoice->status);

        cart_manager::refund((int) $order->id, 0, 'B2B', (int) get_admin()->id);
        $cn = $this->credit_notes_of((int) $order->id)[0];
        $this->assertSame('awaiting_irn', $cn->status);
        $this->assertNotEmpty($cn->cn_number, 'it is numbered; only the IRN is missing');
        $this->assertSame('29ABCDE1234F1Z5', $cn->billing_gstn);
    }

    public function test_a_refund_that_beat_the_invoice_still_gets_its_credit_note(): void {
        global $DB;
        // Paid, not yet invoiced (flag was off at payment, or the task has not run), then refunded; the invoice
        // is issued afterwards: the refund's credit note is raised with it.
        $this->flag(flags::INVOICES, false);
        $order = $this->paid_order_for($this->user_at('/77'));
        cart_manager::refund((int) $order->id, 400.00, 'Early', (int) get_admin()->id);
        $this->assertCount(0, $this->credit_notes_of((int) $order->id));

        $this->flag(flags::INVOICES, true);
        $invoice = invoicer::issue_for_order($this->order((int) $order->id));
        $this->assertNotNull($invoice);

        $notes = $this->credit_notes_of((int) $order->id);
        $this->assertCount(1, $notes, 'the refund that came first has its credit note');
        $this->assertEqualsWithDelta(400.00, (float) $notes[0]->total, 0.001);
        $this->assertSame('due', $notes[0]->status);
        $ledger = $DB->get_record('local_sentientia_cart_ledger', ['historyid' => $order->id, 'event_type' => 'refund_partial']);
        $this->assertSame((int) $ledger->id, (int) $notes[0]->ledgerid, 'tied to the refund it is for');
    }

    public function test_the_credit_note_page_data_names_the_original_invoice_and_the_reason(): void {
        [$order, $invoice] = $this->invoiced();
        cart_manager::refund((int) $order->id, 590.00, 'Duplicate purchase', (int) get_admin()->id);
        $cn = $this->credit_notes_of((int) $order->id)[0];

        $ctx = doc_view::credit_note($cn);
        $this->assertTrue($ctx['is_creditnote']);
        $this->assertSame($cn->cn_number, $ctx['number']);
        $this->assertSame($invoice->invoice_number, $ctx['original_number']);
        $this->assertSame('Duplicate purchase', $ctx['reason']);
        $this->assertSame('Rupees Five Hundred Ninety only', $ctx['total_words']);

        global $OUTPUT;
        $html = $OUTPUT->render_from_template('local_sentientia_cart/tax_document', $ctx);
        foreach (['Credit Note', $cn->cn_number, 'Against tax invoice', $invoice->invoice_number, 'Duplicate purchase',
                'This is a computer-generated credit note.', 'Credit for the refund of order #' . $order->orderid] as $needle) {
            $this->assertStringContainsString($needle, $html, "the credit note shows: $needle");
        }
    }

    public function test_who_may_open_a_credit_note(): void {
        [$order] = $this->invoiced();
        $buyer = \core_user::get_user((int) $order->userid);
        cart_manager::refund((int) $order->id, 0, 'Open', (int) get_admin()->id);
        $cn = $this->credit_notes_of((int) $order->id)[0];

        credit_notes::require_view_access($cn, (int) $buyer->id);
        credit_notes::require_view_access($cn, (int) $this->tenant_admin('/77')->id);
        credit_notes::require_view_access($cn, (int) get_admin()->id);
        foreach ([$this->user_at('/77'), $this->tenant_admin('/1')] as $no) {
            $this->assert_refused(fn() => credit_notes::require_view_access($cn, (int) $no->id), 'error_outoftenant');
        }
    }

    public function test_a_refund_inside_a_callers_transaction_gets_its_credit_note_from_the_task(): void {
        global $DB;
        // Review 2026-10-14, should-fix 4: inside an outer transaction the refund raises no credit note (a failure
        // there would roll the refund back with it); the task finds the refund without one and raises it.
        [$order] = $this->invoiced();
        $transaction = $DB->start_delegated_transaction();
        $this->assertTrue(cart_manager::refund((int) $order->id, 0, 'Inside a transaction', (int) get_admin()->id));
        $transaction->allow_commit();
        $this->assertCount(0, $this->credit_notes_of((int) $order->id), 'nothing raised inside the transaction');

        $this->expectOutputRegex('/credit notes issued=1/');
        (new task\issue_due_documents())->execute();
        $notes = $this->credit_notes_of((int) $order->id);
        $this->assertCount(1, $notes);
        $this->assertSame('issued', $notes[0]->status);
        $this->assertEqualsWithDelta(1180.00, (float) $notes[0]->total, 0.001);

        // A second run finds nothing more to raise: one credit note per refund.
        (new task\issue_due_documents())->execute();
        $this->assertCount(1, $this->credit_notes_of((int) $order->id));
    }
}
