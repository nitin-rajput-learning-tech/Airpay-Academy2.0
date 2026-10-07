<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

namespace local_sentientia_cart;

defined('MOODLE_INTERNAL') || die();

/**
 * The dev-copy masking of the cart's money tables (finance cluster, owner follow-up 2026-10-07).
 *
 * local_sentientia_platform/cli/mask_pii_for_dev.php used to mask the order header's billing phone and address and
 * nothing else of the cart. After the ADR-032 import, a dev or UAT copy built from an imported database still carried
 * the free-text reasons (the BizLMS annotation of a ledger row, the reason of a credit booking), the id of the user
 * who made each booking (initiatedby) and the buyer's user id inside the ledger's payload_json. dev_mask masks them.
 *
 * What this locks in: reasons become NULL; initiatedby becomes 0; every userid and usermodified key of a payload,
 * at any depth, becomes 0 while the rest of the payload, the amounts, event types, order numbers and timestamps stay;
 * a second run changes nothing; the CLI calls it.
 *
 * @package    local_sentientia_cart
 * @category   test
 * @copyright  2026 Airpay Payment Services
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \local_sentientia_cart\dev_mask
 */
final class dev_mask_test extends \advanced_testcase {

    protected function setUp(): void {
        parent::setUp();
        $this->resetAfterTest();
    }

    /** One ledger row. */
    private function ledger(array $override = []): int {
        global $DB;
        return (int) $DB->insert_record('local_sentientia_cart_ledger', (object) ($override + [
            'historyid' => 0, 'orderid' => 1000001, 'event_type' => 'payment_received', 'amount' => 1180.00,
            'currency' => 'INR', 'gateway' => 'online', 'gateway_ref' => 'TXN-1', 'initiatedby' => 0, 'reason' => null,
            'payload_json' => null, 'timecreated' => 1700000000,
        ]));
    }

    /** One credit booking. */
    private function credit(array $override = []): int {
        global $DB;
        return (int) $DB->insert_record('local_sentientia_cart_credit_txn', (object) ($override + [
            'userid' => 0, 'costcenterid' => 77, 'event_type' => 'earned_cancellation', 'amount' => 500.00,
            'balance_after' => 500.00, 'currency' => 'INR', 'initiatedby' => 0, 'reason' => null,
            'timecreated' => 1700000000, 'timemodified' => 1700000000,
        ]));
    }

    public function test_reasons_become_null_and_the_money_stays(): void {
        global $DB;
        $user = $this->getDataGenerator()->create_user();
        $l = $this->ledger(['reason' => 'Refund asked by Asha Rao, phone 9999999999', 'event_type' => 'refund_partial', 'amount' => -100.00]);
        $quiet = $this->ledger();
        $c = $this->credit(['userid' => (int) $user->id, 'reason' => 'Cancelled by the learner, see mail from asha@example.com']);

        $out = dev_mask::run();

        $this->assertNull($DB->get_field('local_sentientia_cart_ledger', 'reason', ['id' => $l]));
        $this->assertNull($DB->get_field('local_sentientia_cart_credit_txn', 'reason', ['id' => $c]));
        $this->assertSame(1, $out['local_sentientia_cart_ledger.reason'], 'Only the row that had a reason is counted.');
        $this->assertSame(1, $out['local_sentientia_cart_credit_txn.reason']);

        // Nothing a developer needs is touched.
        $row = $DB->get_record('local_sentientia_cart_ledger', ['id' => $l], '*', MUST_EXIST);
        $this->assertSame('refund_partial', $row->event_type);
        $this->assertEqualsWithDelta(-100.00, (float) $row->amount, 0.001);
        $this->assertSame('INR', $row->currency);
        $this->assertSame(1000001, (int) $row->orderid);
        $this->assertSame(1700000000, (int) $row->timecreated);
        $credit = $DB->get_record('local_sentientia_cart_credit_txn', ['id' => $c], '*', MUST_EXIST);
        $this->assertEqualsWithDelta(500.00, (float) $credit->amount, 0.001);
        $this->assertSame((int) $user->id, (int) $credit->userid, 'The holder id is only an integer once the user rows are masked.');
        $this->assertNull($DB->get_field('local_sentientia_cart_ledger', 'reason', ['id' => $quiet]));
    }

    public function test_initiatedby_becomes_zero_in_both_tables(): void {
        global $DB;
        $admin = $this->getDataGenerator()->create_user();
        $l = $this->ledger(['initiatedby' => (int) $admin->id]);
        $c = $this->credit(['initiatedby' => (int) $admin->id]);
        $webhook = $this->ledger(['initiatedby' => 0]);

        $out = dev_mask::run();

        $this->assertSame(0, (int) $DB->get_field('local_sentientia_cart_ledger', 'initiatedby', ['id' => $l]));
        $this->assertSame(0, (int) $DB->get_field('local_sentientia_cart_credit_txn', 'initiatedby', ['id' => $c]));
        $this->assertSame(0, (int) $DB->get_field('local_sentientia_cart_ledger', 'initiatedby', ['id' => $webhook]));
        $this->assertSame(1, $out['local_sentientia_cart_ledger.initiatedby']);
        $this->assertSame(1, $out['local_sentientia_cart_credit_txn.initiatedby']);
    }

    public function test_the_buyers_id_inside_a_payload_is_zeroed_and_the_rest_of_the_payload_stays(): void {
        global $DB;
        $payload = [
            'userid' => 4242, 'identifier' => 1000001, 'itemid' => 17, 'itemname' => 'Safety 101',
            'tax' => 180.0, 'paygw' => ['chosen' => ['ap_orderid' => 'AP-OK-2', 'status' => 2, 'userid' => 4242],
                'others' => [['ap_orderid' => 'AP-FAIL-1', 'status' => 1, 'UserID' => 4242]]],
            'usermodified' => 77,
        ];
        $id = $this->ledger(['payload_json' => json_encode($payload)]);

        $out = dev_mask::run();

        $masked = json_decode((string) $DB->get_field('local_sentientia_cart_ledger', 'payload_json', ['id' => $id]), true);
        $this->assertSame(0, $masked['userid']);
        $this->assertSame(0, $masked['usermodified']);
        $this->assertSame(0, $masked['paygw']['chosen']['userid'], 'At any depth.');
        $this->assertSame(0, $masked['paygw']['others'][0]['UserID'], 'Whatever the case of the key.');
        $this->assertSame(1000001, $masked['identifier']);
        $this->assertSame('Safety 101', $masked['itemname']);
        $this->assertSame('AP-OK-2', $masked['paygw']['chosen']['ap_orderid']);
        $this->assertSame(2, $masked['paygw']['chosen']['status']);
        $this->assertEqualsWithDelta(180.0, (float) $masked['tax'], 0.001);
        $this->assertStringStartsWith('{"userid":0,', (string) $DB->get_field('local_sentientia_cart_ledger', 'payload_json', ['id' => $id]),
            'The key stays first: the privacy provider finds an imported row by that prefix.');
        $this->assertStringNotContainsString('4242', (string) $DB->get_field('local_sentientia_cart_ledger', 'payload_json', ['id' => $id]));
        $this->assertSame(1, $out['local_sentientia_cart_ledger.payload_json']);
    }

    public function test_a_payload_that_names_no_one_is_left_alone_and_one_that_is_not_json_is_emptied(): void {
        global $DB;
        $clean = json_encode(['identifier' => 5, 'itemname' => 'Safety 101', 'paygw' => ['chosen' => null, 'others' => []]]);
        $a = $this->ledger(['payload_json' => $clean]);
        $zeroed = $this->ledger(['payload_json' => '{"userid":0,"identifier":6}']);
        $junk = $this->ledger(['payload_json' => 'not json: Asha Rao 4242']);
        $none = $this->ledger(['payload_json' => null]);
        $empty = $this->ledger(['payload_json' => '']);

        $out = dev_mask::run();

        $this->assertSame($clean, $DB->get_field('local_sentientia_cart_ledger', 'payload_json', ['id' => $a]));
        $this->assertSame('{"userid":0,"identifier":6}', $DB->get_field('local_sentientia_cart_ledger', 'payload_json', ['id' => $zeroed]));
        $this->assertSame('{}', $DB->get_field('local_sentientia_cart_ledger', 'payload_json', ['id' => $junk]));
        $this->assertNull($DB->get_field('local_sentientia_cart_ledger', 'payload_json', ['id' => $none]));
        $this->assertSame(1, $out['local_sentientia_cart_ledger.payload_json'], 'Only the unparseable one was rewritten.');
        $this->assertContains((string) $DB->get_field('local_sentientia_cart_ledger', 'payload_json', ['id' => $empty]), ['', null]);
    }

    public function test_a_second_run_changes_nothing(): void {
        global $DB;
        $user = $this->getDataGenerator()->create_user();
        $this->ledger(['reason' => 'x', 'initiatedby' => (int) $user->id,
            'payload_json' => json_encode(['userid' => (int) $user->id, 'identifier' => 1])]);
        $this->credit(['reason' => 'y', 'initiatedby' => (int) $user->id]);

        $first = dev_mask::run();
        $snapshot = [$DB->get_records('local_sentientia_cart_ledger'), $DB->get_records('local_sentientia_cart_credit_txn')];
        $second = dev_mask::run();

        $this->assertSame(1, $first['local_sentientia_cart_ledger.payload_json']);
        $this->assertSame(['local_sentientia_cart_ledger.reason' => 0, 'local_sentientia_cart_ledger.initiatedby' => 0,
            'local_sentientia_cart_credit_txn.reason' => 0, 'local_sentientia_cart_credit_txn.initiatedby' => 0,
            'local_sentientia_cart_ledger.payload_json' => 0], $second, 'Nothing left to mask.');
        $this->assertEquals($snapshot, [$DB->get_records('local_sentientia_cart_ledger'), $DB->get_records('local_sentientia_cart_credit_txn')]);
    }

    public function test_more_rows_than_one_batch_are_all_masked(): void {
        global $DB;
        $rows = [];
        for ($i = 1; $i <= 520; $i++) {
            $rows[] = (object) ['historyid' => 0, 'orderid' => 1000000 + $i, 'event_type' => 'payment_received',
                'amount' => 1.00, 'currency' => 'INR', 'gateway' => 'online', 'initiatedby' => 0,
                'payload_json' => json_encode(['userid' => 9000 + $i, 'identifier' => $i]), 'timecreated' => 1700000000];
        }
        $DB->insert_records('local_sentientia_cart_ledger', $rows);

        $out = dev_mask::run();

        $this->assertSame(520, $out['local_sentientia_cart_ledger.payload_json']);
        $this->assertSame(0, $DB->count_records_select('local_sentientia_cart_ledger',
            $DB->sql_like('payload_json', ':p', true, true, true), ['p' => '{"userid":0,%']),
            'Every payload starts with the zeroed userid.');
    }

    public function test_the_dev_masking_cli_calls_it(): void {
        $cli = \core_component::get_component_directory('local_sentientia_platform') . '/cli/mask_pii_for_dev.php';
        $this->assertFileExists($cli);
        $source = (string) file_get_contents($cli);
        $this->assertStringContainsString("class_exists('\\local_sentientia_cart\\dev_mask')", $source,
            'The platform CLI masks the cart through the cart, and only where the cart is installed.');
        $this->assertStringContainsString('\\local_sentientia_cart\\dev_mask::run()', $source);
    }
}
