<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

namespace local_sentientia_cart;

defined('MOODLE_INTERNAL') || die();

require_once(__DIR__ . '/gst_helpers.php');

use core_privacy\local\metadata\collection;
use core_privacy\local\request\approved_contextlist;
use core_privacy\local\request\approved_userlist;
use core_privacy\local\request\writer;
use core_privacy\tests\provider_testcase;
use local_sentientia_cart\privacy\provider;

/**
 * Privacy of the GST tax documents (spec 2026-10-14, section 9; DPDP).
 *
 * The tax invoice and the credit note are records the law makes the supplier keep (CGST Act section 36: 72 months from
 * the due date of the annual return). So an erasure request:
 *   - keeps a document younger than invoice_retention_years (default 8) exactly as it is, buyer details included;
 *   - blanks the buyer's name, e-mail, phone, address and GSTIN on one older than that, counted from the document date;
 *   - leaves the order history to its existing rules (blanked at once);
 *   - leaves an ERPNext reference imported from BizLMS alone (it has no details).
 * An export gives the user every invoice, credit note and receipt of theirs.
 *
 * @package    local_sentientia_cart
 * @category   test
 * @covers     \local_sentientia_cart\privacy\provider
 *
 * @group local_sentientia_cart
 * @group local_sentientia_cart_gst
 */
final class gst_privacy_test extends provider_testcase {

    use gst_helpers;

    protected function setUp(): void {
        parent::setUp();
        $this->gst_setup();
        $this->flag(flags::INVOICES, true);
    }

    /**
     * @param \stdClass $user
     * @return approved_contextlist
     */
    private function contextlist(\stdClass $user): approved_contextlist {
        return new approved_contextlist($user, 'local_sentientia_cart', [\context_system::instance()->id]);
    }

    /**
     * A paid order with an invoice and a credit note, both dated $when.
     *
     * @param \stdClass $buyer
     * @param int $when The document date of the invoice and the credit note.
     * @return array [order, invoice, credit note]
     */
    private function documents(\stdClass $buyer, int $when): array {
        global $DB;
        $order = $this->paid_order_for($buyer, ['billing_name' => 'Asha Rao', 'billing_email' => 'asha@example.com',
            'billing_phone' => '9876543210', 'billing_address' => '12 Park Street', 'billing_gstn' => '27ABCDE1234F1Z5']);
        $invoice = invoicer::issue_for_order($order);
        cart_manager::refund((int) $order->id, 590.00, 'Half', (int) get_admin()->id);
        $cn = $DB->get_record('local_sentientia_cart_creditnotes', ['historyid' => $order->id], '*', MUST_EXIST);
        $DB->set_field('local_sentientia_cart_invoices', 'timecreated', $when, ['id' => $invoice->id]);
        $DB->set_field('local_sentientia_cart_creditnotes', 'timecreated', $when, ['id' => $cn->id]);
        return [
            $this->order((int) $order->id),
            $DB->get_record('local_sentientia_cart_invoices', ['id' => $invoice->id], '*', MUST_EXIST),
            $DB->get_record('local_sentientia_cart_creditnotes', ['id' => $cn->id], '*', MUST_EXIST),
        ];
    }

    /**
     * A time that many years (and a few days more, or fewer when negative) in the past.
     *
     * @param int $years
     * @param int $extradays Days further back; negative for days nearer to now.
     * @return int
     */
    private function years_ago(int $years, int $extradays = 0): int {
        return (new \DateTimeImmutable('now'))
            ->modify("-{$years} years")
            ->modify(($extradays >= 0 ? '-' : '+') . abs($extradays) . ' days')
            ->getTimestamp();
    }

    private function assert_buyer_details_blanked(\stdClass $doc, string $why): void {
        $this->assertSame('(redacted)', $doc->billing_name, $why);
        foreach (['billing_email', 'billing_phone', 'billing_address', 'billing_gstn'] as $field) {
            $this->assertSame('', $doc->$field, "$why: $field");
        }
    }

    private function assert_buyer_details_kept(\stdClass $doc, string $why): void {
        $this->assertSame('Asha Rao', $doc->billing_name, $why);
        $this->assertSame('asha@example.com', $doc->billing_email, $why);
        $this->assertSame('9876543210', $doc->billing_phone, $why);
        $this->assertSame('12 Park Street', $doc->billing_address, $why);
        $this->assertSame('27ABCDE1234F1Z5', $doc->billing_gstn, $why);
    }

    // ── erasure and retention ────────────────────────────────────────────

    public function test_a_document_younger_than_the_retention_period_is_kept_unchanged(): void {
        global $DB;
        $buyer = $this->user_at('/77');
        [$order, $invoice, $cn] = $this->documents($buyer, $this->years_ago(2));

        provider::delete_data_for_user($this->contextlist($buyer));

        $this->assert_buyer_details_kept($DB->get_record('local_sentientia_cart_invoices', ['id' => $invoice->id]),
            'a 2 year old invoice is a tax record the law requires us to keep');
        $this->assert_buyer_details_kept($DB->get_record('local_sentientia_cart_creditnotes', ['id' => $cn->id]),
            'and so is its credit note');
        $kept = $DB->get_record('local_sentientia_cart_invoices', ['id' => $invoice->id]);
        $this->assertSame($invoice->invoice_number, $kept->invoice_number);
        $this->assertEquals($invoice->total, $kept->total);

        // The order history follows the rules it always had: redacted at once.
        $history = $DB->get_record('local_sentientia_cart_history', ['id' => $order->id]);
        $this->assertSame('(redacted)', $history->billing_name);
        $this->assertSame('', $history->billing_email);
        $this->assertSame('', $history->billing_gstn);
        $this->assertNull($history->billing_state);
    }

    public function test_a_document_older_than_the_retention_period_has_the_buyer_blanked(): void {
        global $DB;
        $buyer = $this->user_at('/77');
        [, $invoice, $cn] = $this->documents($buyer, $this->years_ago(9));
        // As if Finance had recorded the IRN: the portal's signed QR (a JWT that carries the buyer's GSTIN).
        $DB->set_field('local_sentientia_cart_invoices', 'signed_qr', 'eyJhbGciOiJSUzI1NiJ9.e30.sig', ['id' => $invoice->id]);
        $DB->set_field('local_sentientia_cart_creditnotes', 'signed_qr', 'eyJhbGciOiJSUzI1NiJ9.e30.sig', ['id' => $cn->id]);

        provider::delete_data_for_user($this->contextlist($buyer));

        $old = $DB->get_record('local_sentientia_cart_invoices', ['id' => $invoice->id]);
        $this->assert_buyer_details_blanked($old, 'a 9 year old invoice is past its retention period');
        $this->assert_buyer_details_blanked($DB->get_record('local_sentientia_cart_creditnotes', ['id' => $cn->id]),
            'and so is its credit note');
        // Review 2026-10-14, should-fix 7: the signed QR carries the buyer's GSTIN, and a credit note's reason is
        // free text, so both go with the buyer details.
        $this->assertNull($old->signed_qr);
        $oldcn = $DB->get_record('local_sentientia_cart_creditnotes', ['id' => $cn->id]);
        $this->assertNull($oldcn->signed_qr);
        $this->assertNull($oldcn->reason);
        // The tax facts stay: the number, the amounts, the place of supply.
        $this->assertSame($invoice->invoice_number, $old->invoice_number);
        $this->assertEquals($invoice->total, $old->total);
        $this->assertSame($invoice->place_of_supply, $old->place_of_supply);
        $this->assertSame((int) $invoice->userid, (int) $old->userid);
    }

    public function test_the_retention_period_is_counted_from_the_document_date(): void {
        global $DB;
        $buyer = $this->user_at('/77');
        [, $justinside] = $this->documents($buyer, $this->years_ago(8, -2));     // 8 years less 2 days old
        [, $justoutside] = $this->documents($buyer, $this->years_ago(8, 2));     // 8 years and 2 days old

        provider::delete_data_for_user($this->contextlist($buyer));

        $this->assert_buyer_details_kept($DB->get_record('local_sentientia_cart_invoices', ['id' => $justinside->id]),
            'not yet 8 years old');
        $this->assert_buyer_details_blanked($DB->get_record('local_sentientia_cart_invoices', ['id' => $justoutside->id]),
            'just over 8 years old');
    }

    public function test_the_retention_setting_decides(): void {
        global $DB;
        $buyer = $this->user_at('/77');
        [, $invoice] = $this->documents($buyer, $this->years_ago(3));

        $this->configure_tax(['invoice_retention_years' => '5']);
        provider::delete_data_for_user($this->contextlist($buyer));
        $this->assert_buyer_details_kept($DB->get_record('local_sentientia_cart_invoices', ['id' => $invoice->id]), '3 < 5 years');

        $this->configure_tax(['invoice_retention_years' => '2']);
        provider::delete_data_for_user($this->contextlist($buyer));
        $this->assert_buyer_details_blanked($DB->get_record('local_sentientia_cart_invoices', ['id' => $invoice->id]), '3 > 2 years');
    }

    public function test_an_unusable_retention_setting_falls_back_to_the_safe_default(): void {
        foreach (['0', '-4', 'abc', ''] as $bad) {
            $this->configure_tax(['invoice_retention_years' => $bad]);
            $this->assertSame(8, gst_settings::retention_years(), "'$bad' must not mean 'keep nothing'");
        }
        $this->configure_tax(['invoice_retention_years' => '10']);
        $this->assertSame(10, gst_settings::retention_years());
    }

    public function test_erasure_touches_only_that_users_documents(): void {
        global $DB;
        $buyer = $this->user_at('/77');
        $other = $this->user_at('/77');
        [, $mine] = $this->documents($buyer, $this->years_ago(9));
        [, $theirs] = $this->documents($other, $this->years_ago(9));

        provider::delete_data_for_user($this->contextlist($buyer));

        $this->assert_buyer_details_blanked($DB->get_record('local_sentientia_cart_invoices', ['id' => $mine->id]), 'mine');
        $this->assert_buyer_details_kept($DB->get_record('local_sentientia_cart_invoices', ['id' => $theirs->id]),
            "somebody else's invoice is not touched");
    }

    public function test_erasing_every_user_applies_the_same_retention_rule(): void {
        global $DB;
        $a = $this->user_at('/77');
        $b = $this->user_at('/77');
        [, $old] = $this->documents($a, $this->years_ago(10));
        [, $young] = $this->documents($b, $this->years_ago(1));

        provider::delete_data_for_all_users_in_context(\context_system::instance());

        $this->assert_buyer_details_blanked($DB->get_record('local_sentientia_cart_invoices', ['id' => $old->id]), 'old');
        $this->assert_buyer_details_kept($DB->get_record('local_sentientia_cart_invoices', ['id' => $young->id]), 'young');
    }

    public function test_erasing_a_list_of_users_applies_the_same_retention_rule(): void {
        global $DB;
        $buyer = $this->user_at('/77');
        [, $old] = $this->documents($buyer, $this->years_ago(10));
        $userlist = new approved_userlist(\context_system::instance(), 'local_sentientia_cart', [(int) $buyer->id]);
        provider::delete_data_for_users($userlist);
        $this->assert_buyer_details_blanked($DB->get_record('local_sentientia_cart_invoices', ['id' => $old->id]), 'old');
    }

    public function test_an_imported_erpnext_reference_is_left_alone(): void {
        global $DB;
        $buyer = $this->user_at('/77');
        $id = $DB->insert_record('local_sentientia_cart_invoices', (object) [
            'historyid' => 0, 'orderid' => 1000001, 'invoice_number' => 'ERPNEXT-INV-1', 'userid' => $buyer->id,
            'costcenterid' => 77, 'billing_name' => '', 'line_items_json' => '[]', 'subtotal' => 10, 'total' => 10,
            'currency' => 'INR', 'status' => 'legacy_external', 'timecreated' => $this->years_ago(10),
        ]);
        provider::delete_data_for_user($this->contextlist($buyer));
        $this->assertSame('', $DB->get_field('local_sentientia_cart_invoices', 'billing_name', ['id' => $id]),
            'it has no details, and is not given the redaction placeholder');
    }

    // ── declared ─────────────────────────────────────────────────────────

    public function test_the_credit_notes_and_the_new_invoice_columns_are_declared_and_say_they_are_kept(): void {
        $collection = provider::get_metadata(new collection('local_sentientia_cart'));
        $declared = [];
        foreach ($collection->get_collection() as $item) {
            $declared[$item->get_name()] = array_keys($item->get_privacy_fields());
        }
        $this->assertArrayHasKey('local_sentientia_cart_creditnotes', $declared);
        foreach (['userid', 'billing_name', 'billing_email', 'billing_phone', 'billing_address', 'billing_gstn', 'reason'] as $c) {
            $this->assertContains($c, $declared['local_sentientia_cart_creditnotes'], $c);
        }
        foreach (['billing_state', 'place_of_supply', 'irn'] as $c) {
            $this->assertContains($c, $declared['local_sentientia_cart_invoices'], $c);
        }
        $this->assertContains('billing_state', $declared['local_sentientia_cart_history']);

        $sm = get_string_manager();
        foreach (['local_sentientia_cart_invoices', 'local_sentientia_cart_creditnotes'] as $table) {
            $text = $sm->get_string('privacy:metadata:' . $table, 'local_sentientia_cart');
            $this->assertStringContainsString('CGST Act section 36', $text, "$table says why it is kept");
        }
    }

    public function test_a_user_with_only_a_tax_document_is_found(): void {
        global $DB;
        $buyer = $this->user_at('/77');
        [$order] = $this->documents($buyer, time());
        $DB->delete_records('local_sentientia_cart_history', ['id' => $order->id]);   // only the documents remain
        $DB->delete_records('local_sentientia_cart_ledger', ['historyid' => $order->id]);
        $contexts = provider::get_contexts_for_userid((int) $buyer->id)->get_contextids();
        $this->assertSame([\context_system::instance()->id], array_map('intval', $contexts));
    }

    // ── export ───────────────────────────────────────────────────────────

    public function test_the_export_gives_every_invoice_credit_note_and_receipt_of_the_user(): void {
        $buyer = $this->user_at('/77');
        $other = $this->user_at('/77');
        [$order, $invoice, $cn] = $this->documents($buyer, time());
        $this->documents($other, time());

        $this->export_context_data_for_user((int) $buyer->id, \context_system::instance(), 'local_sentientia_cart');
        $writer = writer::with_context(\context_system::instance());
        $plugin = get_string('pluginname', 'local_sentientia_cart');

        $invoices = $writer->get_data([$plugin, 'invoices'])->invoices;
        $this->assertCount(1, $invoices, 'only their own invoice');
        $this->assertSame($invoice->invoice_number, $invoices[0]['invoice_number']);
        $this->assertSame('Asha Rao', $invoices[0]['billing_name']);
        $this->assertSame('27ABCDE1234F1Z5', $invoices[0]['billing_gstn']);
        $this->assertSame('999293', $invoices[0]['sac_code']);
        $this->assertNotEmpty($invoices[0]['lines']);

        $notes = $writer->get_data([$plugin, 'credit_notes'])->credit_notes;
        $this->assertCount(1, $notes);
        $this->assertSame($cn->cn_number, $notes[0]['credit_note_number']);
        $this->assertSame('Half', $notes[0]['reason']);

        $receipts = $writer->get_data([$plugin, 'receipts'])->receipts;
        $this->assertCount(1, $receipts, 'one receipt per paid order');
        $this->assertSame((int) $order->orderid, (int) $receipts[0]['order_number']);
        $this->assertCount(1, $receipts[0]['refunds'], 'with the refunds so far');
        $this->assertEqualsWithDelta(590.00, (float) $receipts[0]['refunds'][0]['amount'], 0.001);
    }

    public function test_the_export_leaves_out_what_a_user_does_not_have(): void {
        $buyer = $this->user_at('/77');
        $this->paid_order_for($buyer);   // paid, never invoiced, never refunded

        $this->export_context_data_for_user((int) $buyer->id, \context_system::instance(), 'local_sentientia_cart');
        $writer = writer::with_context(\context_system::instance());
        $plugin = get_string('pluginname', 'local_sentientia_cart');
        $this->assertEmpty($writer->get_data([$plugin, 'invoices']));
        $this->assertEmpty($writer->get_data([$plugin, 'credit_notes']));
        $this->assertNotEmpty($writer->get_data([$plugin, 'receipts']), 'a paid order has a receipt');
    }
}
