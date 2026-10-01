<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

namespace local_sentientia_cart;

defined('MOODLE_INTERNAL') || die();

use core_privacy\local\metadata\collection;
use core_privacy\local\request\approved_contextlist;
use core_privacy\local\request\writer;
use core_privacy\tests\provider_testcase;
use local_sentientia_cart\privacy\provider;

/**
 * Privacy of the history the BizLMS importer wrote (ADR-032, mapping doc section 13, code fix 7).
 *
 * The ledger and the credit journal are money records: on erasure they stay, with the person taken out. An imported
 * ledger row keeps the buyer's user id FIRST in its payload ({"userid":N,...}), which the provider finds by a prefix
 * match and anonymises. An order slot that a paid order refers to is the order's number, so it is anonymised and
 * never deleted; an unreferenced basket slot and the balance are deleted as before. Imported orders and invoice
 * references carry no billing details, and erasure leaves them alone.
 *
 * @package    local_sentientia_cart
 * @category   test
 * @covers     \local_sentientia_cart\privacy\provider
 *
 * @group local_sentientia_cart
 * @group bizlms_import
 */
final class imported_privacy_test extends provider_testcase {

    /**
     * @param array $more
     * @return \stdClass
     */
    private function user(array $more = []): \stdClass {
        return $this->getDataGenerator()->create_user($more);
    }

    /**
     * An imported ledger row whose payload names the buyer, the way the importer writes it.
     *
     * @param int $buyer
     * @param int $actor
     * @param string $type
     * @param int $historyid
     * @return int
     */
    private function imported_ledger(int $buyer, int $actor, string $type = 'legacy_credit_redeemed', int $historyid = 0): int {
        global $DB;
        return (int) $DB->insert_record('local_sentientia_cart_ledger', (object) [
            'historyid' => $historyid, 'orderid' => 0, 'event_type' => $type, 'amount' => -10, 'currency' => 'INR',
            'gateway' => 'credits', 'initiatedby' => $actor, 'reason' => 'Refund for Asha Rao',
            'payload_json' => json_encode(['userid' => $buyer, 'identifier' => 0, 'itemname' => 'Safety 101']),
            'timecreated' => 1700000000,
        ]);
    }

    /**
     * @param int $userid
     * @param int $actor
     * @return int
     */
    private function txn(int $userid, int $actor): int {
        global $DB;
        return (int) $DB->insert_record('local_sentientia_cart_credit_txn', (object) [
            'userid' => $userid, 'costcenterid' => 1, 'event_type' => 'redeemed', 'amount' => -10, 'balance_after' => 90,
            'currency' => 'INR', 'initiatedby' => $actor, 'reason' => 'Because', 'timecreated' => 1700000000,
            'timemodified' => 1700000000,
        ]);
    }

    /**
     * @param \stdClass $user
     * @return approved_contextlist
     */
    private function contextlist(\stdClass $user): approved_contextlist {
        return new approved_contextlist($user, 'local_sentientia_cart', [\context_system::instance()->id]);
    }

    public function test_the_new_tables_and_columns_are_declared(): void {
        $this->resetAfterTest();
        $collection = provider::get_metadata(new collection('local_sentientia_cart'));
        $declared = [];
        foreach ($collection->get_collection() as $item) {
            $declared[$item->get_name()] = array_keys($item->get_privacy_fields());
        }
        $this->assertArrayHasKey('local_sentientia_cart_credit_txn', $declared);
        foreach (['userid', 'initiatedby', 'amount', 'balance_after', 'reason'] as $column) {
            $this->assertContains($column, $declared['local_sentientia_cart_credit_txn']);
        }
        $this->assertContains('initiatedby', $declared['local_sentientia_cart_ledger']);
        $this->assertContains('payload_json', $declared['local_sentientia_cart_ledger']);
    }

    public function test_a_user_who_only_holds_credit_history_is_found(): void {
        $this->resetAfterTest();
        $user = $this->user();
        $this->txn($user->id, 0);
        $contexts = provider::get_contexts_for_userid($user->id)->get_contextids();
        $this->assertSame([\context_system::instance()->id], array_map('intval', $contexts));

        $userlist = new \core_privacy\local\request\userlist(\context_system::instance(), 'local_sentientia_cart');
        provider::get_users_in_context($userlist);
        $this->assertContains((int) $user->id, array_map('intval', $userlist->get_userids()));
    }

    public function test_export_includes_the_credit_history_and_the_payments_that_name_the_user(): void {
        $this->resetAfterTest();
        $user = $this->user();
        $other = $this->user();
        $this->txn($user->id, $other->id);
        $this->txn($other->id, $user->id);
        $this->imported_ledger($user->id, $other->id);
        $this->imported_ledger($other->id, $other->id);
        $this->imported_ledger($other->id, $user->id, 'legacy_credit_payout');

        $this->export_context_data_for_user($user->id, \context_system::instance(), 'local_sentientia_cart');
        $writer = writer::with_context(\context_system::instance());
        $this->assertTrue($writer->has_any_data());
        $plugin = get_string('pluginname', 'local_sentientia_cart');

        $credit = $writer->get_data([$plugin, 'credit_history']);
        $this->assertCount(1, $credit->credit_history, 'only the bookings of this user');
        $this->assertSame('redeemed', $credit->credit_history[0]['event']);

        $payments = $writer->get_data([$plugin, 'payments']);
        $this->assertCount(2, $payments->payments, 'the row naming the user as buyer, and the one the user booked');
    }

    public function test_erasure_keeps_the_money_records_and_takes_the_person_out(): void {
        global $DB;
        $this->resetAfterTest();
        $user = $this->user();
        $other = $this->user();

        $mine = $this->imported_ledger($user->id, $user->id);
        $booked = $this->imported_ledger($other->id, $user->id, 'legacy_credit_payout');
        $theirs = $this->imported_ledger($other->id, $other->id);
        $nativeledger = (int) $DB->insert_record('local_sentientia_cart_ledger', (object) [
            'historyid' => 0, 'orderid' => 0, 'event_type' => 'refund_full', 'amount' => -50, 'currency' => 'INR',
            'gateway' => 'airpay', 'initiatedby' => $user->id, 'reason' => 'native refund', 'timecreated' => time(),
        ]);
        $t1 = $this->txn($user->id, $user->id);
        $t2 = $this->txn($other->id, $user->id);
        $t3 = $this->txn($other->id, $other->id);
        $DB->insert_record('local_sentientia_cart_credits', (object) ['userid' => $user->id, 'balance' => 90,
            'currency' => 'INR', 'timemodified' => time()]);

        // An imported order and an imported invoice reference: frozen, with nothing personal to blank.
        $orderid = (int) $DB->insert_record('local_sentientia_cart_history', (object) [
            'userid' => $user->id, 'orderid' => 1000001, 'status' => 'paid', 'legacy_source' => 'bizlms',
            'notes' => 'Imported from BizLMS: identifier 1000001', 'timecreated' => 1700000000, 'timemodified' => 1700000000]);
        $invoiceid = (int) $DB->insert_record('local_sentientia_cart_invoices', (object) [
            'historyid' => $orderid, 'orderid' => 1000001, 'invoice_number' => 'ERPNEXT-X1', 'userid' => $user->id,
            'billing_name' => '', 'line_items_json' => '[]', 'subtotal' => 1, 'total' => 1, 'status' => 'legacy_external',
            'timecreated' => 1700000000]);
        // The order slot a paid order refers to, and a basket slot nothing refers to.
        $DB->insert_record('local_sentientia_cart_history', (object) ['userid' => $user->id, 'orderid' => 77,
            'status' => 'paid', 'billing_name' => 'Asha Rao', 'notes' => 'private', 'timecreated' => time(),
            'timemodified' => time()]);
        $DB->import_record('local_sentientia_cart_id', (object) ['id' => 77, 'userid' => $user->id, 'reserved' => time()]);
        $DB->import_record('local_sentientia_cart_id', (object) ['id' => 78, 'userid' => $user->id, 'reserved' => time()]);

        $ledgercount = $DB->count_records('local_sentientia_cart_ledger');
        $txncount = $DB->count_records('local_sentientia_cart_credit_txn');
        provider::delete_data_for_user($this->contextlist($user));

        $this->assertSame($ledgercount, $DB->count_records('local_sentientia_cart_ledger'), 'no ledger row is deleted');
        $this->assertSame($txncount, $DB->count_records('local_sentientia_cart_credit_txn'), 'no journal row is deleted');

        // The user's own imported row: the buyer and the actor are gone, the free text is cleared, the amount stays.
        $row = $DB->get_record('local_sentientia_cart_ledger', ['id' => $mine], '*', MUST_EXIST);
        $this->assertSame(0, (int) $row->initiatedby);
        $this->assertSame(0, json_decode($row->payload_json, true)['userid']);
        $this->assertSame('Safety 101', json_decode($row->payload_json, true)['itemname']);
        $this->assertNull($row->reason);
        $this->assertEqualsWithDelta(-10.0, (float) $row->amount, 0.0001);
        // A row of somebody else the user booked loses only the user as actor.
        $row = $DB->get_record('local_sentientia_cart_ledger', ['id' => $booked], '*', MUST_EXIST);
        $this->assertSame(0, (int) $row->initiatedby);
        $this->assertSame((int) $other->id, (int) json_decode($row->payload_json, true)['userid'], 'the other buyer stays');
        $row = $DB->get_record('local_sentientia_cart_ledger', ['id' => $theirs], '*', MUST_EXIST);
        $this->assertSame((int) $other->id, (int) $row->initiatedby);
        $this->assertSame('Refund for Asha Rao', $row->reason, 'another person\'s row is not touched');
        $this->assertSame(0, (int) $DB->get_field('local_sentientia_cart_ledger', 'initiatedby', ['id' => $nativeledger]));

        // The credit journal.
        $row = $DB->get_record('local_sentientia_cart_credit_txn', ['id' => $t1], '*', MUST_EXIST);
        $this->assertSame([0, 0], [(int) $row->userid, (int) $row->initiatedby]);
        $this->assertNull($row->reason);
        $this->assertEqualsWithDelta(-10.0, (float) $row->amount, 0.0001);
        $row = $DB->get_record('local_sentientia_cart_credit_txn', ['id' => $t2], '*', MUST_EXIST);
        $this->assertSame([(int) $other->id, 0], [(int) $row->userid, (int) $row->initiatedby]);
        $row = $DB->get_record('local_sentientia_cart_credit_txn', ['id' => $t3], '*', MUST_EXIST);
        $this->assertSame([(int) $other->id, (int) $other->id], [(int) $row->userid, (int) $row->initiatedby]);
        $this->assertSame(0, $DB->count_records('local_sentientia_cart_credits', ['userid' => $user->id]),
            'the balance is deleted, as before');

        // Frozen history is left alone: there was nothing personal to blank.
        $order = $DB->get_record('local_sentientia_cart_history', ['id' => $orderid], '*', MUST_EXIST);
        $this->assertNull($order->billing_name);
        $this->assertSame('Imported from BizLMS: identifier 1000001', $order->notes);
        $this->assertSame('', $DB->get_field('local_sentientia_cart_invoices', 'billing_name', ['id' => $invoiceid]));
        // A native order is redacted as before.
        $native = $DB->get_record('local_sentientia_cart_history', ['orderid' => 77], '*', MUST_EXIST);
        $this->assertSame('(redacted)', $native->billing_name);
        $this->assertSame('', $native->notes);

        // The slot a paid order uses is anonymised and kept: deleting it would let the number be issued twice.
        $slot = $DB->get_record('local_sentientia_cart_id', ['id' => 77]);
        $this->assertNotFalse($slot);
        $this->assertSame(0, (int) $slot->userid);
        $this->assertFalse($DB->record_exists('local_sentientia_cart_id', ['id' => 78]), 'an unreferenced basket slot is deleted');
    }
}
