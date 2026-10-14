<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

namespace local_sentientia_cart;

defined('MOODLE_INTERNAL') || die();

/**
 * The GST arithmetic of tax invoices and credit notes (spec 2026-10-14, sections 5 and 7), without a database.
 *
 * What is held here: the tax split (CGST + SGST inside the supplier's state, each half with the odd paisa to SGST;
 * IGST anywhere else), the place of supply (the GSTIN's state for B2B, the buyer's state for B2C), every figure
 * taken from what the order recorded (never recomputed at today's rate), the lines that add up to the totals to the
 * paisa, the credit note figures, the Indian money format and the state list.
 *
 * @package    local_sentientia_cart
 * @category   test
 * @covers     \local_sentientia_cart\gst
 * @covers     \local_sentientia_cart\gst_states
 * @covers     \local_sentientia_cart\docseq
 * @covers     \local_sentientia_cart\einvoice
 *
 * @group local_sentientia_cart
 * @group local_sentientia_cart_gst
 */
final class gst_arithmetic_test extends \advanced_testcase {

    /** An order as checkout records it: lines and totals, as paise-exact rupee amounts. */
    private function order(array $items, float $subtotal, float $discount, float $tax, float $total, $rate = 18): \stdClass {
        return (object) [
            'items_json' => json_encode($items), 'subtotal' => $subtotal, 'discount_amount' => $discount,
            'tax_amount' => $tax, 'total_amount' => $total, 'gst_rate' => $rate,
        ];
    }

    private function line(int $id, float $price, int $pct = 0): array {
        return ['courseid' => $id, 'name' => "Course $id", 'price' => $price, 'discount_pct' => $pct];
    }

    // ── the tax split ────────────────────────────────────────────────────

    public function test_the_tax_is_cgst_and_sgst_inside_the_state_each_half(): void {
        $this->assertSame([9000, 9000, 0], gst::split(18000, true));
    }

    public function test_the_odd_paisa_goes_to_sgst(): void {
        $this->assertSame([1771, 1772, 0], gst::split(3543, true), '35.43 -> 17.71 CGST and 17.72 SGST');
        $this->assertSame([0, 1, 0], gst::split(1, true));
        $this->assertSame([0, 0, 0], gst::split(0, true));
    }

    public function test_the_tax_is_igst_outside_the_state(): void {
        $this->assertSame([0, 0, 18000], gst::split(18000, false));
        $this->assertSame([0, 0, 3543], gst::split(3543, false));
    }

    // ── the place of supply ──────────────────────────────────────────────

    public function test_a_b2c_buyers_place_of_supply_is_their_state(): void {
        $this->assertSame('29', gst::place_of_supply('29', '', '27'));
        $this->assertSame('27', gst::place_of_supply('27', '', '27'));
    }

    public function test_a_b2b_buyers_place_of_supply_is_the_state_in_the_gstin(): void {
        $this->assertSame('29', gst::place_of_supply('27', '29AAAAA0000A1Z5', '27'),
            'the GSTIN decides, even if the chosen state disagrees (checkout refuses that, so it cannot be stored)');
        $this->assertSame('29', gst::place_of_supply('', ' 29aaaaa0000a1z5 ', '27'), 'a GSTIN is tidied first');
    }

    public function test_an_order_with_no_state_at_all_is_supplied_at_the_suppliers_own_location(): void {
        $this->assertSame('27', gst::place_of_supply('', '', '27'));
        $this->assertSame('27', gst::place_of_supply('99', '', '27'), 'not a GST state code');
    }

    public function test_a_gstin_and_a_state_that_disagree_are_caught(): void {
        $this->assertTrue(gst::state_matches_gstin('27', '27AAAAA0000A1Z5'));
        $this->assertFalse(gst::state_matches_gstin('29', '27AAAAA0000A1Z5'));
        $this->assertTrue(gst::state_matches_gstin('29', ''), 'nothing to compare');
        $this->assertTrue(gst::state_matches_gstin('27', ' 27aaaaa0000a1z5 '));
        $this->assertFalse(gst::state_matches_gstin('27', 'ABCDE'), 'no state digits at all');
    }

    public function test_the_gstin_format_is_a_sanity_check(): void {
        $this->assertTrue(gst::is_gstin_format('27AAAAA0000A1Z5'));
        $this->assertTrue(gst::is_gstin_format('29ABCDE1234F1Z5'));
        $this->assertFalse(gst::is_gstin_format('27AAAAA0000A1Z'), '14 characters');
        $this->assertFalse(gst::is_gstin_format('27AAAAA0000A1Z55'), '16 characters');
        $this->assertFalse(gst::is_gstin_format('AAAAAAA0000A1Z5'), 'must start with the two-digit state');
        $this->assertFalse(gst::is_gstin_format('27aaaaa0000a1z5'), 'upper-case only: normalise first');
    }

    public function test_the_supplier_state_is_the_setting_or_the_first_two_digits_of_the_gstin(): void {
        $this->assertSame('27', gst::supplier_state('', '27AAAAA0000A1Z5'));
        $this->assertSame('29', gst::supplier_state('29', '27AAAAA0000A1Z5'), 'the setting wins');
        $this->assertSame('', gst::supplier_state('', ''), 'neither: invoicing refuses to start');
        $this->assertSame('', gst::supplier_state('', 'XXAAAAA0000A1Z5'), 'not a state');
        $this->assertSame('', gst::supplier_state('99', '27AAAAA0000A1Z5'), 'a configured code that is not a state');
        $this->assertSame('97', gst::supplier_state('97', ''));
    }

    // ── figures from what the order recorded ─────────────────────────────

    public function test_an_intra_state_invoice_is_cgst_and_sgst_of_the_recorded_tax(): void {
        $calc = gst::compute($this->order([$this->line(1, 1000)], 1000, 0, 180, 1180), '27', '27', '999293');
        $this->assertTrue($calc['intrastate']);
        $this->assertEqualsWithDelta(1000.00, $calc['taxable'], 0.0001);
        $this->assertEqualsWithDelta(90.00, $calc['cgst'], 0.0001);
        $this->assertEqualsWithDelta(90.00, $calc['sgst'], 0.0001);
        $this->assertEqualsWithDelta(0.00, $calc['igst'], 0.0001);
        $this->assertEqualsWithDelta(1180.00, $calc['total'], 0.0001);
        $this->assertEqualsWithDelta(18.0, $calc['rate'], 0.0001);
        $this->assertSame('27', $calc['place_of_supply']);
    }

    public function test_an_inter_state_invoice_is_igst(): void {
        $calc = gst::compute($this->order([$this->line(1, 1000)], 1000, 0, 180, 1180), '29', '27', '999293');
        $this->assertFalse($calc['intrastate']);
        $this->assertEqualsWithDelta(0.00, $calc['cgst'], 0.0001);
        $this->assertEqualsWithDelta(0.00, $calc['sgst'], 0.0001);
        $this->assertEqualsWithDelta(180.00, $calc['igst'], 0.0001);
    }

    public function test_the_figures_come_from_the_recorded_tax_not_from_todays_rate(): void {
        // The buyer was charged 10%. The configured rate is 18% today. The invoice must say what was charged.
        set_config('gst_rate', '18', 'local_sentientia_cart');
        $calc = gst::compute($this->order([$this->line(1, 1000)], 1000, 0, 100, 1100, 10), '27', '27', '999293');
        $this->assertEqualsWithDelta(1000.00, $calc['taxable'], 0.0001);
        $this->assertEqualsWithDelta(100.00, $calc['cgst'] + $calc['sgst'] + $calc['igst'], 0.0001);
        $this->assertEqualsWithDelta(1100.00, $calc['total'], 0.0001);
        $this->assertEqualsWithDelta(10.0, $calc['rate'], 0.0001);
    }

    public function test_the_rate_of_an_older_order_is_worked_back_from_its_recorded_figures(): void {
        $calc = gst::compute($this->order([$this->line(1, 1000)], 1000, 0, 120, 1120, null), '27', '27', '999293');
        $this->assertEqualsWithDelta(12.0, $calc['rate'], 0.0001);
        $this->assertSame(0.0, gst::order_rate($this->order([], 0, 0, 0, 0, null)), 'no taxable value: no rate');
    }

    public function test_the_taxable_value_is_the_subtotal_less_the_discount(): void {
        // 2000 less a 25% discount on one line (250): taxable 1750, 18% = 315, total 2065.
        $calc = gst::compute($this->order([$this->line(1, 1000, 25), $this->line(2, 1000)], 2000, 250, 315, 2065),
            '27', '27', '999293');
        $this->assertEqualsWithDelta(1750.00, $calc['taxable'], 0.0001);
        $this->assertEqualsWithDelta(315.00, $calc['tax'], 0.0001);
        $this->assertEqualsWithDelta(2065.00, $calc['total'], 0.0001);
    }

    public function test_an_odd_paisa_in_the_order_tax_goes_to_sgst_on_the_invoice(): void {
        $calc = gst::compute($this->order([$this->line(1, 197)], 197, 0, 35.46, 232.46), '27', '27', '999293');
        $this->assertEqualsWithDelta(17.73, $calc['cgst'], 0.0001);
        $this->assertEqualsWithDelta(17.73, $calc['sgst'], 0.0001);
        $calc = gst::compute($this->order([$this->line(1, 197)], 197, 0, 35.43, 232.43), '27', '27', '999293');
        $this->assertEqualsWithDelta(17.71, $calc['cgst'], 0.0001);
        $this->assertEqualsWithDelta(17.72, $calc['sgst'], 0.0001);
    }

    public function test_every_line_carries_the_sac_and_the_lines_add_up_to_the_totals_exactly(): void {
        // Three lines that each round on their own: 333.33 + 333.33 + 333.34 = 1000.00; tax 180.00.
        $order = $this->order([$this->line(1, 333.33), $this->line(2, 333.33), $this->line(3, 333.34)],
            1000.00, 0, 180.00, 1180.00);
        foreach ([true, false] as $intrastate) {
            $calc = gst::compute($order, $intrastate ? '27' : '29', '27', '999293');
            $this->assertCount(3, $calc['lines']);
            $sum = fn(string $k) => round(array_sum(array_column($calc['lines'], $k)), 2);
            $this->assertEqualsWithDelta($calc['taxable'], $sum('taxable'), 0.0001);
            $this->assertEqualsWithDelta($calc['tax'], $sum('tax'), 0.0001);
            $this->assertEqualsWithDelta($calc['cgst'], $sum('cgst'), 0.0001);
            $this->assertEqualsWithDelta($calc['sgst'], $sum('sgst'), 0.0001);
            $this->assertEqualsWithDelta($calc['igst'], $sum('igst'), 0.0001);
            foreach ($calc['lines'] as $line) {
                $this->assertSame('999293', $line['sac']);
                $this->assertEqualsWithDelta(18.0, $line['rate'], 0.0001);
            }
        }
    }

    public function test_lines_with_a_rounding_residue_still_add_up_when_the_order_tax_is_odd(): void {
        // Three lines of 33.33 (99.99), 18% charged on the order as 18.00 (rounded from 17.9982).
        $order = $this->order([$this->line(1, 33.33), $this->line(2, 33.33), $this->line(3, 33.33)],
            99.99, 0, 18.00, 117.99);
        $calc = gst::compute($order, '27', '27', '999293');
        $this->assertEqualsWithDelta(99.99, array_sum(array_column($calc['lines'], 'taxable')), 0.0001);
        $this->assertEqualsWithDelta(18.00, array_sum(array_column($calc['lines'], 'tax')), 0.0001);
        $this->assertEqualsWithDelta(9.00, array_sum(array_column($calc['lines'], 'cgst')), 0.0001);
        $this->assertEqualsWithDelta(9.00, array_sum(array_column($calc['lines'], 'sgst')), 0.0001);
    }

    // ── credit note figures ──────────────────────────────────────────────

    private function invoice(float $taxable, float $cgst, float $sgst, float $igst, float $total): \stdClass {
        return (object) ['subtotal' => $taxable, 'cgst' => $cgst, 'sgst' => $sgst, 'igst' => $igst, 'total' => $total];
    }

    private function nothing_yet(): array {
        return ['taxable' => 0, 'cgst' => 0, 'sgst' => 0, 'igst' => 0, 'total' => 0];
    }

    public function test_a_partial_credit_note_backs_the_tax_out_of_the_gross_refund(): void {
        $f = gst::credit_note_figures($this->invoice(1000, 90, 90, 0, 1180), 590.00, 18.0, true, $this->nothing_yet());
        $this->assertEqualsWithDelta(500.00, $f['taxable'], 0.0001);
        $this->assertEqualsWithDelta(45.00, $f['cgst'], 0.0001);
        $this->assertEqualsWithDelta(45.00, $f['sgst'], 0.0001);
        $this->assertEqualsWithDelta(0.00, $f['igst'], 0.0001);
        $this->assertEqualsWithDelta(590.00, $f['total'], 0.0001);
    }

    public function test_an_inter_state_credit_note_is_igst(): void {
        $f = gst::credit_note_figures($this->invoice(1000, 0, 0, 180, 1180), 590.00, 18.0, false, $this->nothing_yet());
        $this->assertEqualsWithDelta(500.00, $f['taxable'], 0.0001);
        $this->assertEqualsWithDelta(90.00, $f['igst'], 0.0001);
        $this->assertEqualsWithDelta(0.00, $f['cgst'] + $f['sgst'], 0.0001);
    }

    public function test_the_credit_note_that_completes_the_invoice_takes_exactly_what_is_left(): void {
        // 1180.00 in three parts that do not divide evenly: the last one is the remainder, to the paisa.
        $invoice = $this->invoice(1000, 90, 90, 0, 1180);
        $first = gst::credit_note_figures($invoice, 333.33, 18.0, true, $this->nothing_yet());
        $earlier = ['taxable' => gst::to_paise($first['taxable']), 'cgst' => gst::to_paise($first['cgst']),
            'sgst' => gst::to_paise($first['sgst']), 'igst' => 0, 'total' => gst::to_paise($first['total'])];
        $second = gst::credit_note_figures($invoice, 333.33, 18.0, true, $earlier);
        foreach (['taxable', 'cgst', 'sgst', 'igst', 'total'] as $k) {
            $earlier[$k] += gst::to_paise($second[$k]);
        }
        $last = gst::credit_note_figures($invoice, 513.34, 18.0, true, $earlier);
        $this->assertEqualsWithDelta(1000.00, $first['taxable'] + $second['taxable'] + $last['taxable'], 0.0001);
        $this->assertEqualsWithDelta(90.00, $first['cgst'] + $second['cgst'] + $last['cgst'], 0.0001);
        $this->assertEqualsWithDelta(90.00, $first['sgst'] + $second['sgst'] + $last['sgst'], 0.0001);
        $this->assertEqualsWithDelta(1180.00, $first['total'] + $second['total'] + $last['total'], 0.0001);
    }

    // ── money, states, numbers ───────────────────────────────────────────

    public function test_rupees_are_grouped_the_indian_way(): void {
        $this->assertSame('0.00', gst::money(0));
        $this->assertSame('999.99', gst::money(999.99));
        $this->assertSame('1,000.00', gst::money(1000));
        $this->assertSame('12,345.60', gst::money(12345.6));
        $this->assertSame('1,23,456.78', gst::money(123456.78));
        $this->assertSame('12,34,567.89', gst::money(1234567.89));
        $this->assertSame('1,00,00,000.00', gst::money(10000000));
        $this->assertSame('-1,180.00', gst::money(-1180));
        $this->assertSame('1,234,567.89', gst::money(1234567.89, 'USD'), 'other currencies group in thousands');
    }

    public function test_the_state_list_is_01_to_38_and_97(): void {
        $codes = gst_states::codes();
        $this->assertCount(39, $codes);
        $this->assertSame('01', $codes[0]);
        $this->assertSame('38', $codes[37]);
        $this->assertSame('97', $codes[38]);
        $this->assertTrue(gst_states::is_valid('27'));
        $this->assertTrue(gst_states::is_valid('97'));
        foreach (['00', '39', '96', '98', '99', '7', '027', '', 'MH'] as $bad) {
            $this->assertFalse(gst_states::is_valid($bad), "'$bad' is not a GST state code");
        }
        $this->assertSame('27', gst_states::from_gstin('27AAAAA0000A1Z5'));
        $this->assertSame('', gst_states::from_gstin('AAAAA'));
    }

    public function test_every_state_has_a_name_in_english_and_in_hindi(): void {
        $this->resetAfterTest();
        foreach (gst_states::codes() as $code) {
            $en = gst_states::name($code, 'en');
            $this->assertNotSame('', $en);
            $this->assertStringNotContainsString('[[', $en, "state $code has no English name");
        }
        $this->assertSame('Maharashtra', gst_states::name('27', 'en'));
        $this->assertSame('27 - Maharashtra', gst_states::label('27', 'en'));
        $this->assertSame('Other Territory', gst_states::name('97', 'en'));
        $this->assertCount(39, gst_states::options('en'));
        $hi = get_string_manager()->get_string('gst_state_27', 'local_sentientia_cart', null, 'hi');
        // The Hindi pack ships with the plugin even where the site language pack is not installed.
        $this->assertNotSame('', $hi);
    }

    public function test_the_series_year_is_the_calendar_year_or_the_year_the_financial_year_started(): void {
        // 31 Mar 2027 20:00 UTC is 1 Apr 2027 01:30 in India: a new financial year, in Indian time.
        $afterfy = (new \DateTimeImmutable('2027-03-31 20:00', new \DateTimeZone('UTC')))->getTimestamp();
        $this->assertSame(2027, docseq::series_year($afterfy, docseq::PERIOD_CALENDAR));
        $this->assertSame(2027, docseq::series_year($afterfy, docseq::PERIOD_FINANCIAL));

        $beforefy = (new \DateTimeImmutable('2027-03-31 17:00', new \DateTimeZone('UTC')))->getTimestamp();  // 22:30 IST
        $this->assertSame(2027, docseq::series_year($beforefy, docseq::PERIOD_CALENDAR));
        $this->assertSame(2026, docseq::series_year($beforefy, docseq::PERIOD_FINANCIAL), 'still the 2026-27 year');

        // 31 Dec 2026 20:00 UTC is 1 Jan 2027 01:30 IST: the calendar year has turned.
        $newyear = (new \DateTimeImmutable('2026-12-31 20:00', new \DateTimeZone('UTC')))->getTimestamp();
        $this->assertSame(2027, docseq::series_year($newyear, docseq::PERIOD_CALENDAR));
        $this->assertSame(2026, docseq::series_year($newyear, docseq::PERIOD_FINANCIAL));

        $july = (new \DateTimeImmutable('2026-07-15 10:00', new \DateTimeZone('UTC')))->getTimestamp();
        $this->assertSame(2026, docseq::series_year($july, docseq::PERIOD_CALENDAR));
        $this->assertSame(2026, docseq::series_year($july, docseq::PERIOD_FINANCIAL));
        $this->assertSame(2026, docseq::series_year($july, 'nonsense'), 'anything else is the calendar year');
    }

    public function test_a_document_number_is_prefix_year_and_at_least_four_digits(): void {
        $this->assertSame('AIRPAY-2026-0001', docseq::format_number('AIRPAY', 2026, 1));
        $this->assertSame('AIRPAY-2026-0123', docseq::format_number('AIRPAY', 2026, 123));
        $this->assertSame('AIRPAY-2026-9999', docseq::format_number('AIRPAY', 2026, 9999));
        $this->assertSame('AIRPAY-2026-10000', docseq::format_number('AIRPAY', 2026, 10000));
        $this->assertSame('AIRPAY-2026', docseq::series_key('AIRPAY', 2026));
        $this->assertSame(16, strlen(docseq::format_number('AIRPAY', 2026, 1)), 'six characters is the longest prefix of four digits');
    }

    // ── the e-invoice particulars ────────────────────────────────────────

    public function test_the_irn_is_64_hex_characters(): void {
        $irn = str_repeat('ab12', 16);
        $now = time();
        einvoice::validate($irn, '112210000123456', $now - 60, 'eyJhbGciOiJSUzI1NiJ9.payload.sig', $now);
        einvoice::validate(strtoupper($irn), '1', $now, 'QR', $now);

        foreach ([substr($irn, 0, 63), $irn . 'a', str_repeat('g', 64), '', str_repeat('ab12', 15) . 'ab1!'] as $bad) {
            $this->assert_error('error_irninvalid', fn() => einvoice::validate($bad, '1', $now, 'QR', $now));
        }
    }

    public function test_the_acknowledgement_number_date_and_qr_are_checked(): void {
        $irn = str_repeat('0f', 32);
        $now = 1800000000;
        foreach (['', str_repeat('1', 41), 'AB CD', 'AB;CD'] as $bad) {
            $this->assert_error('error_ackinvalid', fn() => einvoice::validate($irn, $bad, $now, 'QR', $now));
        }
        foreach ([0, -5, $now + DAYSECS + 1] as $bad) {
            $this->assert_error('error_ackdateinvalid', fn() => einvoice::validate($irn, '1', $bad, 'QR', $now));
        }
        foreach (['', 'two words', "line\nbreak", str_repeat('x', einvoice::QR_MAX_LENGTH + 1)] as $bad) {
            $this->assert_error('error_qrinvalid', fn() => einvoice::validate($irn, '1', $now, $bad, $now));
        }
        einvoice::validate($irn, '1', $now + DAYSECS, str_repeat('x', einvoice::QR_MAX_LENGTH), $now);
    }

    public function test_the_acknowledgement_date_is_read_in_indian_time(): void {
        $expected = (new \DateTimeImmutable('2026-10-14 15:42', new \DateTimeZone('Asia/Kolkata')))->getTimestamp();
        $this->assertSame($expected, einvoice::parse_ack_date('2026-10-14T15:42'));
        $this->assertSame($expected, einvoice::parse_ack_date('2026-10-14 15:42'));
        $this->assertSame($expected + 7, einvoice::parse_ack_date('2026-10-14 15:42:07'));
        $this->assertSame(0, einvoice::parse_ack_date('yesterday'));
        $this->assertSame(0, einvoice::parse_ack_date('2026-02-30T10:00'), 'a date that does not exist');
        $this->assertSame(0, einvoice::parse_ack_date(''));
    }

    /**
     * @param string $code
     * @param callable $fn
     * @return void
     */
    private function assert_error(string $code, callable $fn): void {
        try {
            $fn();
            $this->fail("Expected $code");
        } catch (\moodle_exception $e) {
            $this->assertSame($code, $e->errorcode);
        }
    }
}
