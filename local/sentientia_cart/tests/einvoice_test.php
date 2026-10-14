<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

namespace local_sentientia_cart;

defined('MOODLE_INTERNAL') || die();

require_once(__DIR__ . '/gst_helpers.php');

/**
 * B2B documents and the e-invoice IRN (spec 2026-10-14, section 6).
 *
 * A tax invoice or credit note issued to a buyer who gave a GSTIN waits (status awaiting_irn) for the IRN that Finance
 * registers on the government portal and records on einvoice.php. Until then the buyer does not see it; after, the
 * IRN, the acknowledgement and the QR print on it. Sentientia has no connection to the portal.
 *
 * @package    local_sentientia_cart
 * @category   test
 * @covers     \local_sentientia_cart\einvoice
 * @covers     \local_sentientia_cart\invoicer::require_view_access
 *
 * @group local_sentientia_cart
 * @group local_sentientia_cart_gst
 * @group tenant_isolation
 */
final class einvoice_test extends \advanced_testcase {

    use gst_helpers;

    private const IRN = 'ab12ab12ab12ab12ab12ab12ab12ab12ab12ab12ab12ab12ab12ab12ab12ab12';
    private const IRN2 = 'cd34cd34cd34cd34cd34cd34cd34cd34cd34cd34cd34cd34cd34cd34cd34cd34';
    private const QR = 'eyJhbGciOiJSUzI1NiIsImtpZCI6IkVEQzc3In0.eyJTZWxsZXJHc3RpbiI6IjI3QUFBQUEwMDAwQTFaNSJ9.c2lnbmF0dXJl';

    protected function setUp(): void {
        parent::setUp();
        $this->gst_setup();
        $this->flag(flags::INVOICES, true);
    }

    /**
     * A B2B invoice (the buyer gave a GSTIN) that awaits its IRN.
     *
     * @param string $path
     * @param string $gstin
     * @return array [order, invoice]
     */
    private function b2b_invoice(string $path = '/77', string $gstin = '29ABCDE1234F1Z5'): array {
        $order = $this->paid_order_for($this->user_at($path), ['billing_state' => substr($gstin, 0, 2), 'billing_gstn' => $gstin]);
        $invoice = invoicer::issue_for_order($order);
        return [$order, $invoice];
    }

    private function record(\stdClass $doc, int $userid, string $irn = self::IRN, string $type = einvoice::TYPE_INVOICE): \stdClass {
        return einvoice::record($type, (int) $doc->id, $irn, '112210000123456', time() - 120, self::QR, $userid);
    }

    public function test_a_b2b_invoice_awaits_its_irn_and_a_b2c_one_is_issued_at_once(): void {
        [, $b2b] = $this->b2b_invoice();
        $this->assertSame('awaiting_irn', $b2b->status);
        $this->assertNotEmpty($b2b->invoice_number, 'it is numbered: the number goes to the portal');
        $this->assertNull($b2b->irn);

        $b2c = invoicer::issue_for_order($this->paid_order_for($this->user_at('/77')));
        $this->assertSame('issued', $b2c->status);
    }

    public function test_with_the_setting_off_a_b2b_invoice_is_issued_at_once(): void {
        $this->configure_tax(['einvoice_b2b' => '0']);
        [, $invoice] = $this->b2b_invoice();
        $this->assertSame('issued', $invoice->status);
    }

    public function test_the_buyer_does_not_see_an_invoice_that_awaits_its_irn(): void {
        [$order, $invoice] = $this->b2b_invoice();
        $this->assert_refused(fn() => invoicer::require_view_access($invoice, (int) $order->userid), 'error_notavailable',
            'the owner is not shown a B2B invoice before the IRN is recorded');

        // Finance, a tenant administrator and a site administrator can open it (Finance registers it on the portal).
        invoicer::require_view_access($invoice, (int) $this->finance_user('/77')->id);
        invoicer::require_view_access($invoice, (int) $this->tenant_admin('/77')->id);
        invoicer::require_view_access($invoice, (int) get_admin()->id);
        $this->assert_refused(fn() => invoicer::require_view_access($invoice, (int) $this->finance_user('/1')->id),
            'error_outoftenant');
    }

    public function test_an_administrator_who_opens_it_is_told_it_is_pending(): void {
        [, $invoice] = $this->b2b_invoice();
        $html = invoicer::render_html($invoice);
        $this->assertStringContainsString('awaiting its e-invoice IRN', $html);
        $this->assertStringNotContainsString('data:image/svg+xml', $html, 'no QR yet');
    }

    public function test_recording_the_irn_makes_the_invoice_visible_and_prints_the_irn_and_the_qr(): void {
        [$order, $invoice] = $this->b2b_invoice();
        $finance = $this->finance_user('/77');

        $done = $this->record($invoice, (int) $finance->id, strtoupper(self::IRN));

        $this->assertSame('issued', $done->status);
        $this->assertSame(self::IRN, $done->irn, 'stored in lower case');
        $this->assertSame('112210000123456', $done->ack_no);
        $this->assertEqualsWithDelta(time() - 120, (int) $done->ack_date, 5);
        $this->assertSame(self::QR, $done->signed_qr);
        invoicer::require_view_access($done, (int) $order->userid);   // the buyer sees it now

        $html = invoicer::render_html($done);
        $this->assertStringContainsString(self::IRN, $html);
        $this->assertStringContainsString('112210000123456', $html);
        $this->assertStringContainsString('data:image/svg+xml;base64,', $html, 'the signed QR, drawn with core_qrcode');
        $this->assertStringNotContainsString('awaiting its e-invoice IRN', $html);
    }

    public function test_the_qr_is_a_real_qr_code(): void {
        $uri = doc_view::qr_data_uri(self::QR);
        $this->assertStringStartsWith('data:image/svg+xml;base64,', $uri);
        $svg = base64_decode(substr($uri, strlen('data:image/svg+xml;base64,')));
        $this->assertStringContainsString('<svg', $svg);
        $this->assertSame('', doc_view::qr_data_uri(''));
    }

    public function test_only_a_holder_of_manageinvoices_may_record_an_irn(): void {
        [, $invoice] = $this->b2b_invoice();
        foreach ([$this->user_at('/77'), $this->tenant_admin('/77')] as $no) {
            try {
                $this->record($invoice, (int) $no->id);
                $this->fail('a user without :manageinvoices must not record an IRN');
            } catch (\required_capability_exception $e) {
                $this->assertSame('nopermissions', $e->errorcode);
            }
        }
        $this->assertSame('awaiting_irn', $this->reload($invoice)->status, 'nothing was recorded');
        // A site administrator holds every capability.
        $this->assertSame('issued', $this->record($invoice, (int) get_admin()->id)->status);
    }

    public function test_the_capability_has_no_archetype_so_nobody_holds_it_by_default(): void {
        global $CFG, $DB;
        $capabilities = [];
        require($CFG->dirroot . '/local/sentientia_cart/db/access.php');
        $this->assertSame([], $capabilities['local/sentientia_cart:manageinvoices']['archetypes']);
        $roleids = $DB->get_fieldset_select('role_capabilities', 'roleid',
            'capability = :c AND permission = :p', ['c' => 'local/sentientia_cart:manageinvoices', 'p' => CAP_ALLOW]);
        $this->assertSame([], $roleids, 'no role holds it until an administrator grants it');
    }

    public function test_an_irn_is_recorded_only_inside_the_holders_tenant(): void {
        [, $invoice] = $this->b2b_invoice('/77');
        $this->assert_refused(fn() => $this->record($invoice, (int) $this->finance_user('/1')->id), 'error_outoftenant');
        $this->assert_refused(fn() => $this->record($invoice, (int) $this->finance_user('/177')->id), 'error_outoftenant');
        $this->assertSame('awaiting_irn', $this->reload($invoice)->status);
        $this->assertSame('issued', $this->record($invoice, (int) $this->finance_user('/77')->id)->status);
    }

    public function test_an_invalid_irn_is_refused_and_nothing_is_stored(): void {
        [, $invoice] = $this->b2b_invoice();
        $finance = (int) $this->finance_user('/77')->id;
        foreach ([substr(self::IRN, 1), self::IRN . 'a', str_repeat('z', 64), ''] as $bad) {
            $this->assert_refused(fn() => $this->record($invoice, $finance, $bad), 'error_irninvalid');
        }
        $this->assert_refused(fn() => einvoice::record('invoice', (int) $invoice->id, self::IRN, '', time() - 5, self::QR, $finance),
            'error_ackinvalid');
        $this->assert_refused(fn() => einvoice::record('invoice', (int) $invoice->id, self::IRN, '1', time() + 3 * DAYSECS, self::QR, $finance),
            'error_ackdateinvalid');
        $this->assert_refused(fn() => einvoice::record('invoice', (int) $invoice->id, self::IRN, '1', time() - 5, 'not valid qr', $finance),
            'error_qrinvalid');
        $stored = $this->reload($invoice);
        $this->assertSame('awaiting_irn', $stored->status);
        $this->assertNull($stored->irn);
        $this->assertNull($stored->signed_qr);
    }

    public function test_a_document_that_is_not_waiting_cannot_be_given_an_irn(): void {
        $b2c = invoicer::issue_for_order($this->paid_order_for($this->user_at('/77')));
        $this->assert_refused(fn() => $this->record($b2c, (int) get_admin()->id), 'error_irnnotawaiting');

        [, $b2b] = $this->b2b_invoice();
        $this->record($b2b, (int) get_admin()->id);
        $this->assert_refused(fn() => $this->record($b2b, (int) get_admin()->id, self::IRN2), 'error_irnnotawaiting',
            'an IRN, once recorded, is not overwritten');
        $this->assertSame(self::IRN, $this->reload($b2b)->irn);
    }

    public function test_one_irn_identifies_one_document(): void {
        [, $one] = $this->b2b_invoice();
        [, $two] = $this->b2b_invoice();
        $this->record($one, (int) get_admin()->id);
        $this->assert_refused(fn() => $this->record($two, (int) get_admin()->id), 'error_irnduplicate');
        $this->assertSame('awaiting_irn', $this->reload($two)->status);
    }

    public function test_a_credit_note_takes_an_irn_the_same_way(): void {
        global $DB;
        [$order, $invoice] = $this->b2b_invoice();
        $this->record($invoice, (int) get_admin()->id);
        cart_manager::refund((int) $order->id, 0, 'B2B refund', (int) get_admin()->id);
        $cn = $DB->get_record('local_sentientia_cart_creditnotes', ['historyid' => $order->id], '*', MUST_EXIST);
        $this->assertSame('awaiting_irn', $cn->status);
        $this->assert_refused(fn() => credit_notes::require_view_access($cn, (int) $order->userid), 'error_notavailable');

        $done = einvoice::record(einvoice::TYPE_CREDITNOTE, (int) $cn->id, self::IRN2, '99', time() - 60, self::QR,
            (int) get_admin()->id);
        $this->assertSame('issued', $done->status);
        $this->assertSame(self::IRN2, $done->irn);
        credit_notes::require_view_access($done, (int) $order->userid);
        $this->assertStringContainsString(self::IRN2, \local_sentientia_cart\doc_view::credit_note($done)['irn']);
    }

    public function test_the_listing_is_scoped_to_the_callers_tenant(): void {
        [, $in77] = $this->b2b_invoice('/77');
        [, $in1] = $this->b2b_invoice('/1', '27ABCDE1234F1Z5');
        $done = invoicer::issue_for_order($this->paid_order_for($this->user_at('/77')));   // B2C: never listed

        $numbers = fn() => array_column(einvoice::awaiting(), 'number');

        $this->setUser($this->finance_user('/77'));
        $this->assertSame([$in77->invoice_number], $numbers(), 'a tenant-77 holder sees only tenant 77');

        $this->setUser($this->finance_user('/1'));
        $this->assertSame([$in1->invoice_number], $numbers());

        $this->setUser($this->finance_user('/177'));
        $this->assertSame([], $numbers());

        $this->setUser($this->user_at(''));
        $this->assertSame([], $numbers(), 'a caller whose tenant does not resolve sees nothing');

        $this->setAdminUser();
        $all = $numbers();
        $this->assertContains($in77->invoice_number, $all);
        $this->assertContains($in1->invoice_number, $all);
        $this->assertNotContains($done->invoice_number, $all);
    }

    public function test_the_listing_includes_credit_notes_oldest_first(): void {
        global $DB;
        [$order, $invoice] = $this->b2b_invoice();
        $this->record($invoice, (int) get_admin()->id);
        cart_manager::refund((int) $order->id, 0, 'B2B refund', (int) get_admin()->id);
        [, $later] = $this->b2b_invoice();
        $DB->set_field('local_sentientia_cart_invoices', 'timecreated', time() + 100, ['id' => $later->id]);

        $this->setAdminUser();
        $rows = einvoice::awaiting();
        $this->assertSame([einvoice::TYPE_CREDITNOTE, einvoice::TYPE_INVOICE], array_column($rows, 'type'));
    }

    /**
     * @param \stdClass $doc
     * @return \stdClass
     */
    private function reload(\stdClass $doc): \stdClass {
        global $DB;
        return $DB->get_record('local_sentientia_cart_invoices', ['id' => $doc->id], '*', MUST_EXIST);
    }
}
