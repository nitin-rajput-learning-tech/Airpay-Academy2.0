<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

namespace local_sentientia_cart;

defined('MOODLE_INTERNAL') || die();

require_once(__DIR__ . '/gst_helpers.php');

/**
 * Document numbering (spec 2026-10-14, section 4): sequential, gap-free after a rollback, at most 16 characters,
 * seeded from the numbers already issued.
 *
 * The old invoicer counted the invoices of the year and checked that its candidate was free, so two payments at the
 * same moment read the same count. A number now comes from one counter row per series, taken inside the transaction
 * that inserts the document: the UPDATE holds the row lock, and a rollback gives the number back.
 *
 * @package    local_sentientia_cart
 * @category   test
 * @covers     \local_sentientia_cart\docseq
 *
 * @group local_sentientia_cart
 * @group local_sentientia_cart_gst
 */
final class docseq_test extends \advanced_testcase {

    use gst_helpers;

    protected function setUp(): void {
        parent::setUp();
        $this->gst_setup();
    }

    /**
     * Allocate one number in its own committed transaction.
     *
     * @param string $prefix
     * @param int $year
     * @return string
     */
    private function next(string $prefix = 'AIRPAY', int $year = 2026): string {
        global $DB;
        docseq::ensure_series($prefix, $year);
        $tx = $DB->start_delegated_transaction();
        $number = docseq::allocate($prefix, $year);
        $tx->allow_commit();
        return $number;
    }

    private function lastno(string $series): int {
        global $DB;
        return (int) $DB->get_field('local_sentientia_cart_docseq', 'lastno', ['series' => $series]);
    }

    public function test_numbers_are_sequential(): void {
        $this->assertSame('AIRPAY-2026-0001', $this->next());
        $this->assertSame('AIRPAY-2026-0002', $this->next());
        $this->assertSame('AIRPAY-2026-0003', $this->next());
        $this->assertSame(3, $this->lastno('AIRPAY-2026'));
    }

    public function test_each_series_counts_on_its_own(): void {
        $this->assertSame('AIRPAY-2026-0001', $this->next('AIRPAY', 2026));
        $this->assertSame('AIRPAY-2027-0001', $this->next('AIRPAY', 2027), 'a new year starts again');
        $this->assertSame('AIRCN-2026-0001', $this->next('AIRCN', 2026), 'credit notes have their own series');
        $this->assertSame('AIRPAY-2026-0002', $this->next('AIRPAY', 2026));
    }

    public function test_a_rollback_releases_the_number_so_there_is_no_gap(): void {
        global $DB;
        $this->assertSame('AIRPAY-2026-0001', $this->next());

        docseq::ensure_series('AIRPAY', 2026);
        $tx = $DB->start_delegated_transaction();
        $this->assertSame('AIRPAY-2026-0002', docseq::allocate('AIRPAY', 2026));
        try {
            $tx->rollback(new \moodle_exception('error_invalidstate', 'local_sentientia_cart'));
            $this->fail('rollback() rethrows');
        } catch (\moodle_exception $e) {
            $this->assertSame('error_invalidstate', $e->errorcode);
        }
        $this->assertSame(1, $this->lastno('AIRPAY-2026'), 'the counter went back with the rollback');

        $this->assertSame('AIRPAY-2026-0002', $this->next(), 'the next document takes the number the failed one released');
        $this->assertSame('AIRPAY-2026-0003', $this->next());
    }

    public function test_a_failed_first_allocation_leaves_no_trace_either(): void {
        global $DB;
        $tx = $DB->start_delegated_transaction();
        docseq::allocate('AIRPAY', 2026);
        try {
            $tx->rollback(new \Exception('boom'));
        } catch (\Exception $e) {
            $this->assertSame('boom', $e->getMessage());
        }
        $this->assertSame('AIRPAY-2026-0001', $this->next(), 'still starts at one');
    }

    public function test_a_number_may_be_sixteen_characters_and_no_more(): void {
        global $DB;
        $this->assertSame(16, strlen($this->next('AIRPAY')), 'AIRPAY-2026-0001 is exactly 16');

        // A prefix of 7 characters is 17 with the year and four digits: refused, and nothing is used up.
        docseq::ensure_series('AIRPAYX', 2026);
        $tx = $DB->start_delegated_transaction();
        try {
            docseq::allocate('AIRPAYX', 2026);
            $this->fail('a 17 character number must be refused');
        } catch (\moodle_exception $e) {
            $this->assertSame('error_numbertoolong', $e->errorcode);
            $this->assertSame(17, $e->a->length);
            $this->assertSame(16, $e->a->max);
            $this->assertSame('AIRPAYX-2026-0001', $e->a->number);
        }
        try {
            $tx->rollback(new \Exception('the caller rolls back'));
        } catch (\Exception $e) {
            $this->assertSame('the caller rolls back', $e->getMessage());
        }
        $this->assertSame(0, $this->lastno('AIRPAYX-2026'), 'the refused number was released');
    }

    public function test_the_series_runs_out_at_ten_thousand_numbers(): void {
        global $DB;
        // 9999 is the last number a six character prefix can have: AIRPAY-2026-10000 is 17 characters.
        $DB->insert_record('local_sentientia_cart_docseq', (object) [
            'series' => 'AIRPAY-2026', 'lastno' => 9998, 'timemodified' => time()]);
        $this->assertSame('AIRPAY-2026-9999', $this->next());

        $tx = $DB->start_delegated_transaction();
        try {
            docseq::allocate('AIRPAY', 2026);
            $this->fail('AIRPAY-2026-10000 is 17 characters');
        } catch (\moodle_exception $e) {
            $this->assertSame('error_numbertoolong', $e->errorcode);
        }
        try {
            $tx->rollback(new \Exception('x'));
        } catch (\Exception $e) {
            $this->assertSame('x', $e->getMessage());
        }
        $this->assertSame(9999, $this->lastno('AIRPAY-2026'), 'the refused number was released');
    }

    public function test_the_first_number_of_a_series_follows_the_highest_one_already_issued(): void {
        global $DB;
        // Invoices that were issued before the counter table existed (UAT): they must not be issued again.
        foreach (['AIRPAY-2026-0007', 'AIRPAY-2026-0012', 'AIRPAY-2026-0003'] as $n) {
            $this->insert_invoice($n);
        }
        $this->assertSame(12, docseq::highest_in_use('AIRPAY', 2026));
        $this->assertSame('AIRPAY-2026-0013', $this->next());
        $this->assertSame('AIRPAY-2026-0014', $this->next());
        $this->assertSame(14, $this->lastno('AIRPAY-2026'));
    }

    public function test_seeding_only_looks_at_its_own_series(): void {
        $this->insert_invoice('AIRPAY-2025-0500');
        $this->insert_invoice('ERPNEXT-77');
        $this->insert_invoice('AIRPAYX-2026-0900');
        $this->insert_invoice('AIRPAY-2026-ABCD');
        $this->assertSame(0, docseq::highest_in_use('AIRPAY', 2026), 'other years, prefixes and non-numbers are not this series');
        $this->assertSame('AIRPAY-2026-0001', $this->next());
    }

    public function test_an_underscore_in_a_prefix_is_not_a_wildcard(): void {
        $this->insert_invoice('ABXC-2026-0050');
        $this->assertSame(0, docseq::highest_in_use('AB_C', 2026), "'_' must not match any character");
        $this->insert_invoice('AB_C-2026-0007');
        $this->assertSame(7, docseq::highest_in_use('AB_C', 2026));
    }

    public function test_a_shared_prefix_shares_one_counter_across_invoices_and_credit_notes(): void {
        $this->insert_invoice('AIRPAY-2026-0004');
        $this->insert_credit_note('AIRPAY-2026-0009');
        $this->assertSame(9, docseq::highest_in_use('AIRPAY', 2026));
        $this->assertSame('AIRPAY-2026-0010', $this->next());
    }

    public function test_a_number_that_is_already_taken_is_stepped_over(): void {
        global $DB;
        // The counter says 2, but 0003 and 0004 exist (hand-made, or a restore brought them past the counter).
        $DB->insert_record('local_sentientia_cart_docseq', (object) [
            'series' => 'AIRPAY-2026', 'lastno' => 2, 'timemodified' => time()]);
        $this->insert_invoice('AIRPAY-2026-0003');
        $this->insert_credit_note('AIRPAY-2026-0004');
        $this->assertSame('AIRPAY-2026-0005', $this->next());
        $this->assertSame(5, $this->lastno('AIRPAY-2026'));
    }

    public function test_allocating_outside_a_transaction_is_a_coding_error(): void {
        $this->expectException(\coding_exception::class);
        docseq::allocate('AIRPAY', 2026);
    }

    public function test_ensure_series_is_idempotent(): void {
        global $DB;
        docseq::ensure_series('AIRPAY', 2026);
        docseq::ensure_series('AIRPAY', 2026);
        $this->assertSame(1, $DB->count_records('local_sentientia_cart_docseq', ['series' => 'AIRPAY-2026']));
    }

    /**
     * @param string $number
     * @return void
     */
    private function insert_invoice(string $number): void {
        global $DB;
        $DB->insert_record('local_sentientia_cart_invoices', (object) [
            'historyid' => 0, 'orderid' => 0, 'invoice_number' => $number, 'userid' => 0, 'costcenterid' => 0,
            'billing_name' => 'x', 'line_items_json' => '[]', 'subtotal' => 0, 'total' => 0, 'currency' => 'INR',
            'status' => 'issued', 'timecreated' => time(),
        ]);
    }

    /**
     * @param string $number
     * @return void
     */
    private function insert_credit_note(string $number): void {
        global $DB;
        $DB->insert_record('local_sentientia_cart_creditnotes', (object) [
            'invoiceid' => 0, 'historyid' => 0, 'cn_number' => $number, 'userid' => 0, 'costcenterid' => 0,
            'billing_name' => 'x', 'taxable' => 0, 'total' => 0, 'currency' => 'INR', 'status' => 'issued',
            'timecreated' => time(),
        ]);
    }
}
