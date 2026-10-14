<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

namespace local_sentientia_cart;

defined('MOODLE_INTERNAL') || die();

require_once(__DIR__ . '/gst_helpers.php');

use core_external\external_api;
use local_sentientia_cart\external\list_orders;

/**
 * The buyer's payment receipt, the links to it, and the PDFs (spec 2026-10-14, section 8).
 *
 * Held here: access (the owner yes; another tenant's administrator no; an imported order has none; the flag decides
 * whether the page exists), what the receipt says (the order, what was paid, the refunds, "not a tax invoice", the
 * tax invoice link or "will follow"), the Documents links of the order history (and that nothing changes while both
 * flags are OFF), the link in the order-paid message, and that every PDF is a PDF.
 *
 * @package    local_sentientia_cart
 * @category   test
 * @covers     \local_sentientia_cart\receipts
 * @covers     \local_sentientia_cart\pdf_renderer
 * @covers     \local_sentientia_cart\notifier::order_paid
 * @covers     \local_sentientia_cart\external\list_orders
 *
 * @group local_sentientia_cart
 * @group local_sentientia_cart_gst
 * @group tenant_isolation
 */
final class receipt_test extends \advanced_testcase {

    use gst_helpers;

    protected function setUp(): void {
        parent::setUp();
        $this->gst_setup();
        $this->flag(flags::RECEIPTS, true);
    }

    // ── access ───────────────────────────────────────────────────────────

    public function test_the_owner_opens_their_receipt(): void {
        $buyer = $this->user_at('/77');
        $order = $this->paid_order_for($buyer);
        receipts::require_view_access($order, (int) $buyer->id);
        $this->assertTrue(receipts::exists_for($order));
    }

    public function test_a_tenant_administrator_opens_a_receipt_inside_their_tenant_only(): void {
        $order = $this->paid_order_for($this->user_at('/77'));
        receipts::require_view_access($order, (int) $this->tenant_admin('/77')->id);
        receipts::require_view_access($order, (int) get_admin()->id);

        $this->assert_refused(fn() => receipts::require_view_access($order, (int) $this->tenant_admin('/1')->id),
            'error_outoftenant', "another tenant's administrator must not open it");
        $this->assert_refused(fn() => receipts::require_view_access($order, (int) $this->tenant_admin('/177')->id),
            'error_outoftenant');
        $this->assert_refused(fn() => receipts::require_view_access($order, (int) $this->user_at('/77')->id),
            'error_outoftenant', 'nor another buyer');
        $this->assert_refused(fn() => receipts::require_view_access($order, (int) $this->tenant_admin('')->id),
            'error_outoftenant', 'nor an administrator whose tenant does not resolve');
    }

    public function test_an_imported_order_has_no_receipt(): void {
        $buyer = $this->user_at('/77');
        $order = $this->paid_order_for($buyer, ['legacy_source' => 'bizlms']);
        $this->assertFalse(receipts::exists_for($order));
        foreach ([$buyer, $this->tenant_admin('/77'), get_admin()] as $who) {
            $this->assert_refused(fn() => receipts::require_view_access($order, (int) $who->id), 'error_outoftenant',
                'imported money history is admin-only: it has no receipt for anybody');
        }
    }

    public function test_without_the_flag_there_is_no_receipt_page(): void {
        $this->flag(flags::RECEIPTS, false);
        $buyer = $this->user_at('/77');
        $order = $this->paid_order_for($buyer);
        $this->assertFalse(receipts::exists_for($order));
        $this->assert_refused(fn() => receipts::require_view_access($order, (int) $buyer->id), 'error_notavailable');
    }

    public function test_the_flag_is_resolved_for_the_orders_tenant(): void {
        $this->flag(flags::RECEIPTS, false);
        $this->flag(flags::RECEIPTS, true, 77);   // on for tenant 77 only
        $this->assertTrue(receipts::exists_for($this->paid_order_for($this->user_at('/77'))));
        $this->assertFalse(receipts::exists_for($this->paid_order_for($this->user_at('/177'))));
        $this->assertTrue(receipts::enabled_for_user($this->user_at('/77')));
        $this->assertFalse(receipts::enabled_for_user($this->user_at('/1')));
    }

    public function test_an_order_that_was_not_paid_has_no_receipt(): void {
        $buyer = $this->user_at('/77');
        foreach (['pending', 'failed', 'open', 'cancelled'] as $status) {
            $order = $this->order_for($buyer, ['status' => $status]);
            $this->assertFalse(receipts::exists_for($order), $status);
            $this->assert_refused(fn() => receipts::require_view_access($order, (int) $buyer->id), 'error_notavailable');
        }
    }

    public function test_a_refunded_order_still_has_its_receipt(): void {
        $buyer = $this->user_at('/77');
        $order = $this->paid_order_for($buyer);
        cart_manager::refund((int) $order->id, 0, 'Gone', (int) get_admin()->id);
        receipts::require_view_access($this->order((int) $order->id), (int) $buyer->id);
    }

    // ── what it says ─────────────────────────────────────────────────────

    public function test_the_receipt_states_what_was_paid_and_that_it_is_not_a_tax_invoice(): void {
        global $OUTPUT;
        $buyer = $this->user_at('/77');
        $order = $this->paid_order_for($buyer, [
            'items_json' => json_encode([
                ['courseid' => 1, 'name' => 'Payments Basics', 'price' => 1000.00, 'discount_pct' => 10],
                ['courseid' => 2, 'name' => 'Risk 101', 'price' => 500.00, 'discount_pct' => 0],
            ]),
            'subtotal' => 1500.00, 'discount_amount' => 100.00, 'tax_amount' => 252.00, 'total_amount' => 1652.00,
            'gateway' => 'airpay', 'gateway_ref' => 'TXN-ABC-123', 'billing_name' => 'Asha Rao',
            'billing_email' => 'asha@example.com',
        ]);

        $ctx = receipts::context($order);
        $this->assertSame((int) $order->orderid, $ctx['orderid']);
        $this->assertSame('Asha Rao', $ctx['buyer_name']);
        $this->assertSame('asha@example.com', $ctx['buyer_email']);
        $this->assertCount(2, $ctx['items']);
        $this->assertSame('₹1,000.00', $ctx['items'][0]['price']);
        $this->assertTrue($ctx['items'][0]['has_discount']);
        $this->assertSame('₹100.00', $ctx['items'][0]['discount']);
        $this->assertFalse($ctx['items'][1]['has_discount']);
        $this->assertSame('₹1,500.00', $ctx['subtotal']);
        $this->assertSame('₹252.00', $ctx['tax']);
        $this->assertSame('₹1,652.00', $ctx['total']);
        $this->assertSame('TXN-ABC-123', $ctx['gateway_ref']);

        $html = $OUTPUT->render_from_template('local_sentientia_cart/receipt', $ctx + ['pdf_url' => 'https://example.com/r.pdf']);
        foreach (['Payment receipt', '#' . $order->orderid, 'Asha Rao', 'asha@example.com', 'Payments Basics', 'Risk 101',
                '₹1,652.00', 'GST charged', 'Total paid', 'airpay', 'TXN-ABC-123',
                'This is a payment receipt, not a tax invoice.', 'Download PDF'] as $needle) {
            $this->assertStringContainsString($needle, $html, "the receipt shows: $needle");
        }
        $this->assertStringNotContainsString('tax invoice will follow', $html);
    }

    public function test_the_receipt_lists_the_refunds_so_far(): void {
        $buyer = $this->user_at('/77');
        $order = $this->paid_order_for($buyer);
        $admin = (int) get_admin()->id;
        cart_manager::refund((int) $order->id, 300.00, 'Part', $admin);
        cart_manager::refund((int) $order->id, 80.50, 'More', $admin);

        $ctx = receipts::context($this->order((int) $order->id));
        $this->assertTrue($ctx['has_refunds']);
        $this->assertSame(['₹300.00', '₹80.50'], array_column($ctx['refunds'], 'amount'));
        $this->assertNotSame('', $ctx['refunds'][0]['date']);
        $this->assertSame('Partial refund', $ctx['status_label']);

        $none = receipts::context($this->paid_order_for($buyer));
        $this->assertFalse($none['has_refunds']);
    }

    public function test_the_receipt_links_the_tax_invoice_when_it_is_issued(): void {
        $this->flag(flags::INVOICES, true);
        $order = $this->paid_order_for($this->user_at('/77'));
        $invoice = invoicer::issue_for_order($order);

        $ctx = receipts::context($order);
        $this->assertTrue($ctx['has_invoice']);
        $this->assertSame($invoice->invoice_number, $ctx['invoice_number']);
        $this->assertStringContainsString('/local/sentientia_cart/invoice.php?id=' . $invoice->id, $ctx['invoice_url']);
        $this->assertFalse($ctx['invoice_follows']);
    }

    public function test_a_b2b_receipt_says_the_tax_invoice_will_follow_until_the_irn_is_recorded(): void {
        global $OUTPUT;
        $this->flag(flags::INVOICES, true);
        $order = $this->paid_order_for($this->user_at('/77'), ['billing_state' => '29', 'billing_gstn' => '29ABCDE1234F1Z5']);
        $invoice = invoicer::issue_for_order($order);
        $this->assertSame('awaiting_irn', $invoice->status);

        $ctx = receipts::context($order);
        $this->assertFalse($ctx['has_invoice'], 'the buyer is not shown the invoice yet');
        $this->assertSame('', $ctx['invoice_url']);
        $this->assertTrue($ctx['invoice_follows']);
        $this->assertStringContainsString('Your GST tax invoice will follow.',
            $OUTPUT->render_from_template('local_sentientia_cart/receipt', $ctx + ['pdf_url' => '']));

        einvoice::record('invoice', (int) $invoice->id, str_repeat('ab', 32), '1', time() - 30, 'QRSTRING', (int) get_admin()->id);
        $ctx = receipts::context($order);
        $this->assertTrue($ctx['has_invoice'], 'now it is visible');
        $this->assertFalse($ctx['invoice_follows']);
    }

    public function test_an_order_whose_invoice_is_still_due_is_told_it_will_follow(): void {
        $this->flag(flags::INVOICES, true);
        $order = $this->paid_order_for($this->user_at('/77'), ['invoice_due' => 1]);
        $this->assertTrue(receipts::context($order)['invoice_follows']);

        $this->flag(flags::INVOICES, false);
        $this->assertFalse(receipts::context($order)['invoice_follows'], 'with the invoice flag OFF nothing follows');
    }

    public function test_the_receipt_lists_issued_credit_notes(): void {
        $this->flag(flags::INVOICES, true);
        $order = $this->paid_order_for($this->user_at('/77'));
        invoicer::issue_for_order($order);
        cart_manager::refund((int) $order->id, 0, 'Back', (int) get_admin()->id);

        $ctx = receipts::context($this->order((int) $order->id));
        $this->assertTrue($ctx['has_creditnotes']);
        $this->assertCount(1, $ctx['creditnotes']);
        $this->assertStringStartsWith('AIRCN-', $ctx['creditnotes'][0]['number']);
        $this->assertStringContainsString('/local/sentientia_cart/creditnote.php?id=', $ctx['creditnotes'][0]['url']);
    }

    // ── the order history ────────────────────────────────────────────────

    public function test_the_history_links_the_receipt_the_invoice_and_the_credit_notes(): void {
        $this->flag(flags::INVOICES, true);
        $buyer = $this->user_at('/77');
        $order = $this->paid_order_for($buyer);
        $plain = $this->paid_order_for($buyer);
        invoicer::issue_for_order($order);
        cart_manager::refund((int) $order->id, 0, 'Back', (int) get_admin()->id);

        $rows = [(int) $order->id => $this->order((int) $order->id), (int) $plain->id => $this->order((int) $plain->id)];
        $links = receipts::history_links($rows);

        $html = $links[(int) $order->id];
        $this->assertStringContainsString('/local/sentientia_cart/receipt.php?id=' . $order->id, $html);
        $this->assertStringContainsString('>Receipt</a>', $html);
        $this->assertStringContainsString('>Tax invoice</a>', $html);
        $this->assertStringContainsString('>Credit note</a>', $html);
        $this->assertStringContainsString('/local/sentientia_cart/creditnote.php?id=', $html);
        $this->assertStringContainsString('>Receipt</a>', $links[(int) $plain->id]);
        $this->assertStringNotContainsString('Tax invoice', $links[(int) $plain->id], 'it has no invoice');
    }

    public function test_the_history_hides_an_invoice_that_awaits_its_irn(): void {
        $this->flag(flags::INVOICES, true);
        $order = $this->paid_order_for($this->user_at('/77'), ['billing_state' => '29', 'billing_gstn' => '29ABCDE1234F1Z5']);
        invoicer::issue_for_order($order);
        $links = receipts::history_links([(int) $order->id => $order]);
        $this->assertStringContainsString('>Receipt</a>', $links[(int) $order->id]);
        $this->assertStringNotContainsString('Tax invoice', $links[(int) $order->id]);
    }

    public function test_with_both_flags_off_the_history_is_exactly_what_it_was(): void {
        global $DB;
        $this->flag(flags::RECEIPTS, false);
        $buyer = $this->user_at('/77');
        $order = $this->paid_order_for($buyer);
        $DB->insert_record('local_sentientia_cart_invoices', (object) [   // an old invoice that exists
            'historyid' => $order->id, 'orderid' => $order->orderid, 'invoice_number' => 'AIRPAY-2026-0001',
            'userid' => $buyer->id, 'costcenterid' => 77, 'billing_name' => 'Old', 'line_items_json' => '[]',
            'subtotal' => 1000, 'cgst' => 0, 'sgst' => 0, 'igst' => 180, 'total' => 1180, 'currency' => 'INR',
            'status' => 'issued', 'timecreated' => 1700000000,
        ]);
        $this->assertSame([(int) $order->id => ''], receipts::history_links([(int) $order->id => $order]));
        $this->assertFalse(receipts::history_column_enabled($buyer), 'no Documents column');

        $this->flag(flags::RECEIPTS, true);
        $this->assertTrue(receipts::history_column_enabled($buyer));
    }

    public function test_the_order_list_service_returns_the_document_links_for_the_owner_only(): void {
        $buyer = $this->user_at('/77');
        $order = $this->paid_order_for($buyer);
        $other = $this->user_at('/77');
        $this->paid_order_for($other);

        $this->setUser($buyer);
        $result = external_api::clean_returnvalue(list_orders::execute_returns(), list_orders::execute());
        $this->assertSame(1, $result['total'], 'only their own order');
        $this->assertStringContainsString('receipt.php?id=' . $order->id, $result['rows'][0]['documents']);

        $this->flag(flags::RECEIPTS, false);
        $result = external_api::clean_returnvalue(list_orders::execute_returns(), list_orders::execute());
        $this->assertSame('', $result['rows'][0]['documents'], 'flag OFF: nothing is added');
    }

    // ── the message ──────────────────────────────────────────────────────

    public function test_the_order_paid_message_links_the_receipt_while_the_flag_is_on(): void {
        $buyer = $this->user_at('/77');
        $order = $this->order_for($buyer);

        $sink = $this->redirectMessages();
        cart_manager::mark_paid((int) $order->id, 'TXN-9');
        $messages = $sink->get_messages();
        $sink->close();
        $m = array_values(array_filter($messages, fn($x) => $x->eventtype === 'payment_received'
            && (int) $x->useridto === (int) $buyer->id));
        $this->assertCount(1, $m);
        $this->assertStringContainsString('Your payment receipt: ', $m[0]->fullmessage);
        $this->assertStringContainsString('/local/sentientia_cart/receipt.php?id=' . $order->id, $m[0]->fullmessage);

        $this->flag(flags::RECEIPTS, false);
        $order2 = $this->order_for($buyer);
        $sink = $this->redirectMessages();
        cart_manager::mark_paid((int) $order2->id, 'TXN-10');
        $messages = $sink->get_messages();
        $sink->close();
        $m = array_values(array_filter($messages, fn($x) => $x->eventtype === 'payment_received'));
        $this->assertStringNotContainsString('receipt', strtolower($m[0]->fullmessage), 'flag OFF: the message is as it was');
    }

    // ── the PDFs ─────────────────────────────────────────────────────────

    public function test_the_pdfs_are_pdfs(): void {
        global $DB;
        $this->flag(flags::INVOICES, true);
        $buyer = $this->user_at('/77');
        $order = $this->paid_order_for($buyer);
        $invoice = invoicer::issue_for_order($order);

        $b2b = $this->paid_order_for($this->user_at('/77'), ['billing_state' => '29', 'billing_gstn' => '29ABCDE1234F1Z5',
            'billing_name' => 'Asha रॉय']);
        $b2binvoice = invoicer::issue_for_order($b2b);
        $b2binvoice = einvoice::record('invoice', (int) $b2binvoice->id, str_repeat('ef', 32), '99', time() - 30,
            'eyJhbGciOiJSUzI1NiJ9.payload.signature', (int) get_admin()->id);

        cart_manager::refund((int) $order->id, 590.00, 'Half', (int) get_admin()->id);
        $cn = $DB->get_record('local_sentientia_cart_creditnotes', ['historyid' => $order->id], '*', MUST_EXIST);

        foreach ([
            'receipt' => pdf_renderer::receipt($this->order((int) $order->id)),
            'invoice' => pdf_renderer::invoice($invoice),
            'b2b invoice with IRN and QR' => pdf_renderer::invoice($b2binvoice),
            'credit note' => pdf_renderer::credit_note($cn),
        ] as $what => $pdf) {
            $this->assertStringStartsWith('%PDF-', $pdf, $what);
            $this->assertGreaterThan(2000, strlen($pdf), "$what has content");
            $this->assertStringContainsString('%%EOF', $pdf, $what);
        }
        $this->assertGreaterThan(strlen(pdf_renderer::invoice($invoice)), strlen(pdf_renderer::invoice($b2binvoice)),
            'the QR code and the IRN make the B2B PDF bigger');
    }

    public function test_the_pdf_file_name_is_safe(): void {
        $this->assertSame('invoice-AIRPAY-2026-0001.pdf', pdf_renderer::filename('invoice', 'AIRPAY-2026-0001'));
        $this->assertSame('receipt-5000100.pdf', pdf_renderer::filename('receipt', '5000100'));
        $name = pdf_renderer::filename('invoice', '../evil\\x');
        $this->assertSame('invoice-.._evil_x.pdf', $name);
        $this->assertStringNotContainsString('/', $name);
        $this->assertStringNotContainsString('\\', $name);
    }
}
