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
     * The provider must declare itself as `null_provider` (stores no
     * personal data beyond what core_payment already tracks) AND as
     * `paygw_provider` (so core_payment knows it implements the
     * gateway-specific export + delete hooks).
     */
    public function test_provider_implements_required_interfaces(): void {
        $reflection = new \ReflectionClass(provider::class);
        $interfaces = $reflection->getInterfaceNames();

        $this->assertContains(
            \core_privacy\local\metadata\null_provider::class,
            $interfaces,
            'provider must implement null_provider'
        );
        $this->assertContains(
            \core_payment\privacy\paygw_provider::class,
            $interfaces,
            'provider must implement paygw_provider so core_payment can dispatch export + delete'
        );
    }

    /**
     * get_reason() returns the lang-string identifier that core privacy
     * uses to render "why this plugin stores no data" in the GDPR
     * export. Pinned so a future copy-paste edit can't silently
     * change the displayed reason.
     */
    public function test_get_reason_returns_expected_lang_key(): void {
        $this->assertSame('privacy:metadata', provider::get_reason());
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
}
