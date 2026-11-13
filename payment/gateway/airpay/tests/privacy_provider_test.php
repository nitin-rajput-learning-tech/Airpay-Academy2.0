<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * Initial PHPUnit coverage for paygw_airpay\privacy\provider.
 *
 * Scope (NIGHT-RUN-PLAYBOOK B1):
 * - Implements expected privacy interfaces (null_provider + paygw_provider)
 * - get_reason() returns the expected lang-string key
 * - delete_data_for_payment_sql() invokes the right $DB call shape
 *
 * @package    paygw_airpay
 * @category   test
 * @copyright  2026 Airpay Payment Services
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace paygw_airpay\privacy;

defined('MOODLE_INTERNAL') || die();

/**
 * Tests for the paygw_airpay\privacy\provider class.
 *
 * @covers \paygw_airpay\privacy\provider
 */
final class privacy_provider_test extends \advanced_testcase {

    /**
     * Not a null_provider any more (2026-11-13): the gateway's three tables are
     * keyed on the buyer, so it declares them and handles export and erasure.
     * It still implements paygw_provider, so core_payment can dispatch the
     * per-payment export and delete.
     */
    public function test_provider_implements_required_interfaces(): void {
        $interfaces = (new \ReflectionClass(provider::class))->getInterfaceNames();
        $this->assertNotContains(\core_privacy\local\metadata\null_provider::class, $interfaces);
        foreach ([
            \core_privacy\local\metadata\provider::class,
            \core_payment\privacy\paygw_provider::class,
            \core_privacy\local\request\plugin\provider::class,
            \core_privacy\local\request\core_userlist_provider::class,
        ] as $interface) {
            $this->assertContains($interface, $interfaces);
        }
    }

    /**
     * All three tables and the Airpay external link are declared.
     */
    public function test_metadata_declares_every_table(): void {
        $names = array_map(fn($item) => $item->get_name(),
            provider::get_metadata(new \core_privacy\local\metadata\collection('paygw_airpay'))->get_collection());
        foreach (['paygw_airpay', 'paygw_course_enrolmentlog', 'paygw_airpay_errorlog', 'airpay'] as $name) {
            $this->assertContains($name, $names);
        }
    }

    /**
     * delete_data_for_payment_sql() must remove records from the
     * paygw_airpay table for every payment id matched by the supplied
     * SQL. This test asserts that after the call, no paygw_airpay row
     * remains for the targeted payment id.
     *
     * The test directly inserts a row into paygw_airpay (the table is
     * created by install.xml in the test reset cycle), then runs
     * delete_data_for_payment_sql() with a trivial paymentid IN (?) SQL.
     */
    public function test_delete_data_for_payment_sql_removes_matching_records(): void {
        global $DB;
        $this->resetAfterTest();

        // Seed a paygw_airpay row to delete.
        $paymentid = 4242;
        $id = $DB->insert_record('paygw_airpay', (object) [
            'component'   => 'enrol_fee',
            'paymentarea' => 'fee',
            'itemid'      => 1,
            'userid'      => 1,
            'ap_orderid'  => '1700000000_1',
            'accountid'   => 1,
            'cost'        => '99.00',
            'paymentid'   => $paymentid,
            'status'      => 0,
            'timecreated' => time(),
            'modified'    => time(),
        ]);
        $this->assertTrue($DB->record_exists('paygw_airpay', ['id' => $id]));

        // Use a sub-SELECT that returns the payment id we just seeded.
        $paymentsql = "SELECT :pid AS id";
        $params = ['pid' => $paymentid];
        provider::delete_data_for_payment_sql($paymentsql, $params);

        $this->assertFalse(
            $DB->record_exists('paygw_airpay', ['id' => $id]),
            'paygw_airpay row should be removed after delete_data_for_payment_sql'
        );
    }

    /**
     * delete_data_for_payment_sql() with a sql that matches nothing
     * should NOT throw and should NOT delete unrelated rows.
     */
    public function test_delete_data_for_payment_sql_is_safe_with_no_matches(): void {
        global $DB;
        $this->resetAfterTest();

        // Seed a row whose paymentid will NOT match the delete query.
        $id = $DB->insert_record('paygw_airpay', (object) [
            'component'   => 'enrol_fee',
            'paymentarea' => 'fee',
            'itemid'      => 2,
            'userid'      => 1,
            'ap_orderid'  => '1700000001_1',
            'accountid'   => 1,
            'cost'        => '49.00',
            'paymentid'   => 5555,
            'status'      => 0,
            'timecreated' => time(),
            'modified'    => time(),
        ]);

        // Run delete with a paymentid that matches nothing.
        $paymentsql = "SELECT :pid AS id WHERE 1=0";
        $params = ['pid' => 9999];
        provider::delete_data_for_payment_sql($paymentsql, $params);

        // The unrelated row should still be there.
        $this->assertTrue(
            $DB->record_exists('paygw_airpay', ['id' => $id]),
            'Unrelated paygw_airpay row was deleted — query scoping is broken'
        );
    }

    // ------------------------------------------------- request provider (2026-11-13)

    /**
     * Seed one buyer's order, enrolment-log row and error-log row.
     *
     * @return array<string,int> table => row id
     */
    private function seed_buyer(int $userid, ?int $paymentid, string $orderid): array {
        global $DB;
        return [
            'paygw_airpay' => (int) $DB->insert_record('paygw_airpay', (object) [
                'component' => 'enrol_fee', 'paymentarea' => 'fee', 'itemid' => 1, 'userid' => $userid,
                'accountid' => 1, 'ap_orderid' => $orderid, 'cost' => 99, 'paymentid' => $paymentid,
                'status' => 0, 'timecreated' => time(), 'timemodified' => time(),
            ]),
            'paygw_course_enrolmentlog' => (int) $DB->insert_record('paygw_course_enrolmentlog', (object) [
                'courseid' => 2, 'coursename' => 'AML Basics', 'userid' => $userid, 'username' => 'Asha Rao',
                'transactionid' => 'T' . $orderid, 'ap_orderid' => $orderid, 'amount' => 99, 'status' => 1,
                'timecreated' => time(),
            ]),
            'paygw_airpay_errorlog' => (int) $DB->insert_record('paygw_airpay_errorlog', (object) [
                'airpay_id' => $orderid, 'courseid' => 2, 'userid' => $userid,
                'error' => 'Order id -"' . $orderid . '" created for course "(2)" by user with userid "(' . $userid . ')".',
                'order_state' => 'Order Created', 'paymentarea' => 'fee', 'timecreated' => time(),
                'timemodified' => time(),
            ]),
        ];
    }

    /**
     * Erasure anonymises the buyer in all three tables, in the message text too,
     * keeps every row, and leaves another buyer untouched. A paid order is left
     * to core_payment.
     */
    public function test_erasure_anonymises_and_keeps_the_rows(): void {
        global $DB;
        $this->resetAfterTest();
        $gen = $this->getDataGenerator();
        $buyer = $gen->create_user();
        $other = $gen->create_user();
        $mine = $this->seed_buyer((int) $buyer->id, null, 'O-1');
        $theirs = $this->seed_buyer((int) $other->id, null, 'O-2');

        // A paid order of the buyer: its payments row exists, so core_payment owns its erasure.
        $paymentid = (int) $DB->insert_record('payments', (object) [
            'component' => 'enrol_fee', 'paymentarea' => 'fee', 'itemid' => 1, 'userid' => $buyer->id,
            'amount' => '99', 'currency' => 'INR', 'accountid' => 1, 'gateway' => 'airpay',
            'timecreated' => time(), 'timemodified' => time(),
        ]);
        $paid = $this->seed_buyer((int) $buyer->id, $paymentid, 'O-3')['paygw_airpay'];

        $sys = \context_system::instance();
        $this->assertSame([(int) $sys->id],
            array_map('intval', provider::get_contexts_for_userid((int) $buyer->id)->get_contextids()));

        provider::delete_data_for_user(new \core_privacy\local\request\approved_contextlist(
            $buyer, 'paygw_airpay', [$sys->id]));

        foreach (['paygw_course_enrolmentlog', 'paygw_airpay_errorlog'] as $table) {
            $this->assertSame(0, $DB->count_records($table, ['userid' => $buyer->id]), $table);
            $this->assertSame(0, (int) $DB->get_field($table, 'userid', ['id' => $mine[$table]]), $table);
        }
        $this->assertSame(0, (int) $DB->get_field('paygw_airpay', 'userid', ['id' => $mine['paygw_airpay']]));
        $this->assertSame((int) $buyer->id, (int) $DB->get_field('paygw_airpay', 'userid', ['id' => $paid]),
            'a paid order is erased by core_payment with its payment, not here');
        $log = $DB->get_record('paygw_course_enrolmentlog', ['id' => $mine['paygw_course_enrolmentlog']]);
        $this->assertSame('', $log->username);
        $this->assertSame('TO-1', $log->transactionid, 'the transaction trail stays');
        $error = $DB->get_field('paygw_airpay_errorlog', 'error', ['id' => $mine['paygw_airpay_errorlog']]);
        $this->assertStringContainsString('userid "(0)"', $error);
        $this->assertStringNotContainsString('userid "(' . $buyer->id . ')"', $error);

        foreach ($theirs as $table => $id) {
            $this->assertSame((int) $other->id, (int) $DB->get_field($table, 'userid', ['id' => $id]), $table);
        }
        // Three buyers' worth of seeds (mine, theirs, the paid order) in each table: no row was deleted.
        foreach (['paygw_airpay', 'paygw_course_enrolmentlog', 'paygw_airpay_errorlog'] as $table) {
            $this->assertSame(3, $DB->count_records($table), $table);
        }
    }

    /**
     * Export gives the buyer every row, and a payment export carries the order id.
     */
    public function test_export_covers_every_table(): void {
        global $DB;
        $this->resetAfterTest();
        $buyer = $this->getDataGenerator()->create_user();
        $this->seed_buyer((int) $buyer->id, 777, 'O-9');
        $sys = \context_system::instance();

        provider::export_user_data(new \core_privacy\local\request\approved_contextlist(
            $buyer, 'paygw_airpay', [$sys->id]));
        $writer = \core_privacy\local\request\writer::with_context($sys);
        $root = get_string('pluginname', 'paygw_airpay');
        $this->assertSame('O-9', $writer->get_data(
            [$root, get_string('privacy:metadata:paygw_airpay', 'paygw_airpay')])->orders[0]['orderid']);
        $this->assertSame('TO-9', $writer->get_data(
            [$root, get_string('privacy:metadata:paygw_course_enrolmentlog', 'paygw_airpay')])->enrolments[0]['transactionid']);
        $this->assertNotEmpty($writer->get_data(
            [$root, get_string('privacy:metadata:paygw_airpay_errorlog', 'paygw_airpay')])->events);

        provider::export_payment_data($sys, ['payment'], (object) ['id' => 777]);
        $this->assertSame('O-9', $writer->get_data(['payment', get_string('gatewayname', 'paygw_airpay')])->orderid,
            'the order id comes from ap_orderid (the copied provider read PayPal\'s pp_orderid)');
    }

    /**
     * The userlist and context-wide routes anonymise too.
     */
    public function test_userlist_and_context_wide_erasure(): void {
        global $DB;
        $this->resetAfterTest();
        $gen = $this->getDataGenerator();
        $a = $gen->create_user();
        $b = $gen->create_user();
        $this->seed_buyer((int) $a->id, null, 'O-a');
        $this->seed_buyer((int) $b->id, null, 'O-b');
        $sys = \context_system::instance();

        $userlist = new \core_privacy\local\request\userlist($sys, 'paygw_airpay');
        provider::get_users_in_context($userlist);
        $found = array_map('intval', $userlist->get_userids());
        sort($found);
        $this->assertSame([(int) $a->id, (int) $b->id], $found);

        provider::delete_data_for_users(new \core_privacy\local\request\approved_userlist(
            $sys, 'paygw_airpay', [$a->id]));
        $this->assertSame(0, $DB->count_records('paygw_course_enrolmentlog', ['userid' => $a->id]));
        $this->assertSame(1, $DB->count_records('paygw_course_enrolmentlog', ['userid' => $b->id]));

        provider::delete_data_for_all_users_in_context($sys);
        foreach (['paygw_airpay', 'paygw_course_enrolmentlog', 'paygw_airpay_errorlog'] as $table) {
            $this->assertSame(2, $DB->count_records($table), $table . ': rows stay');
            $this->assertSame(0, $DB->count_records_select($table, 'userid > 0'), $table . ': nobody named');
        }
    }
}
