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
 * Privacy provider for the airpay payment gateway.
 *
 * @package    paygw_airpay
 * @copyright  2024 Moodle India <support@moodle.com
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace paygw_airpay\privacy;

defined('MOODLE_INTERNAL') || die();

use core_payment\privacy\paygw_provider;
use core_privacy\local\metadata\collection;
use core_privacy\local\request\approved_contextlist;
use core_privacy\local\request\approved_userlist;
use core_privacy\local\request\contextlist;
use core_privacy\local\request\userlist;
use core_privacy\local\request\writer;

/**
 * Privacy provider for paygw_airpay.
 *
 * REPLACES A null_provider (2026-11-13). This class was a copy of core's paygw_paypal provider, which declares
 * null_provider because paygw_paypal's table holds no user column. Airpay's three tables are keyed on the buyer's
 * user id, so the registry wrongly reported no personal data, an export returned no logs, and an erasure left
 * every log row. Core's test_table_coverage found it. The copy also read `pp_orderid` (PayPal's column) instead of
 * `ap_orderid`, so a payment export carried no order id.
 *
 * Tables, and what erasure does (Nitin, 2026-11-13: anonymise, keep the rows):
 *   - paygw_airpay (one row per order)
 *       paid orders: unchanged behaviour. core_payment erases them with the payment, through
 *       delete_data_for_payment_sql().
 *       unpaid or orphaned orders (no payments row): userid anonymised to 0 here; the order stays.
 *   - paygw_course_enrolmentlog (course, transaction id, order id, amount per paid course)
 *       userid anonymised to 0 and the buyer's name (username) blanked; the transaction row stays for
 *       reconciliation and audit, the same rule as local_sentientia_cart's ledger and invoices.
 *   - paygw_airpay_errorlog (order state and gateway messages)
 *       userid anonymised to 0, and the `userid "(N)"` the gateway writes into the message text becomes
 *       `userid "(0)"`; the row stays.
 * Export gives the buyer every row of all three tables.
 *
 * Every table is site-level (rows reference courses and orders by id), so the data lives in the system context.
 *
 * @copyright  2020 Shamim Rezaie <shamim@moodle.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class provider implements
    \core_privacy\local\metadata\provider,
    paygw_provider,
    \core_privacy\local\request\plugin\provider,
    \core_privacy\local\request\core_userlist_provider {

    /** @var string Orders. */
    private const ORDERS = 'paygw_airpay';

    /** @var string Paid-course enrolment log. */
    private const ENROLMENTLOG = 'paygw_course_enrolmentlog';

    /** @var string Order-state and error log. */
    private const ERRORLOG = 'paygw_airpay_errorlog';

    /**
     * The three tables, and the buyer data sent to Airpay at checkout.
     *
     * @param collection $collection
     * @return collection
     */
    public static function get_metadata(collection $collection): collection {
        $collection->add_database_table(self::ORDERS, [
            'userid'      => 'privacy:metadata:paygw_airpay:userid',
            'component'   => 'privacy:metadata:paygw_airpay:component',
            'paymentarea' => 'privacy:metadata:paygw_airpay:paymentarea',
            'itemid'      => 'privacy:metadata:paygw_airpay:itemid',
            'ap_orderid'  => 'privacy:metadata:paygw_airpay:ap_orderid',
            'cost'        => 'privacy:metadata:paygw_airpay:cost',
            'paymentid'   => 'privacy:metadata:paygw_airpay:paymentid',
            'status'      => 'privacy:metadata:paygw_airpay:status',
            'timecreated' => 'privacy:metadata:paygw_airpay:timecreated',
        ], 'privacy:metadata:paygw_airpay');

        $collection->add_database_table(self::ENROLMENTLOG, [
            'userid'        => 'privacy:metadata:paygw_course_enrolmentlog:userid',
            'username'      => 'privacy:metadata:paygw_course_enrolmentlog:username',
            'courseid'      => 'privacy:metadata:paygw_course_enrolmentlog:courseid',
            'coursename'    => 'privacy:metadata:paygw_course_enrolmentlog:coursename',
            'transactionid' => 'privacy:metadata:paygw_course_enrolmentlog:transactionid',
            'ap_orderid'    => 'privacy:metadata:paygw_course_enrolmentlog:ap_orderid',
            'amount'        => 'privacy:metadata:paygw_course_enrolmentlog:amount',
            'status'        => 'privacy:metadata:paygw_course_enrolmentlog:status',
            'timecreated'   => 'privacy:metadata:paygw_course_enrolmentlog:timecreated',
        ], 'privacy:metadata:paygw_course_enrolmentlog');

        $collection->add_database_table(self::ERRORLOG, [
            'userid'      => 'privacy:metadata:paygw_airpay_errorlog:userid',
            'courseid'    => 'privacy:metadata:paygw_airpay_errorlog:courseid',
            'airpay_id'   => 'privacy:metadata:paygw_airpay_errorlog:airpay_id',
            'error'       => 'privacy:metadata:paygw_airpay_errorlog:error',
            'order_state' => 'privacy:metadata:paygw_airpay_errorlog:order_state',
            'paymentarea' => 'privacy:metadata:paygw_airpay_errorlog:paymentarea',
            'timecreated' => 'privacy:metadata:paygw_airpay_errorlog:timecreated',
        ], 'privacy:metadata:paygw_airpay_errorlog');

        // pay.php posts these to the Airpay checkout.
        $collection->add_external_location_link('airpay', [
            'email'     => 'privacy:metadata:airpay:email',
            'phone'     => 'privacy:metadata:airpay:phone',
            'firstname' => 'privacy:metadata:airpay:firstname',
            'lastname'  => 'privacy:metadata:airpay:lastname',
            'city'      => 'privacy:metadata:airpay:city',
            'state'     => 'privacy:metadata:airpay:state',
            'country'   => 'privacy:metadata:airpay:country',
            'amount'    => 'privacy:metadata:airpay:amount',
            'orderid'   => 'privacy:metadata:airpay:orderid',
        ], 'privacy:metadata:airpay');

        return $collection;
    }

    /**
     * Export all user data for the specified payment record, and the given context.
     *
     * @param \context $context Context
     * @param array $subcontext The location within the current context that the payment data belongs
     * @param \stdClass $payment The payment record
     */
    public static function export_payment_data(\context $context, array $subcontext, \stdClass $payment) {
        global $DB;

        $subcontext[] = get_string('gatewayname', 'paygw_airpay');
        $record = $DB->get_record(self::ORDERS, ['paymentid' => $payment->id]);
        if (!$record) {
            return;
        }

        writer::with_context($context)->export_data($subcontext, (object) [
            'orderid' => $record->ap_orderid,
        ]);
    }

    /**
     * Delete all user data related to the given payments.
     *
     * @param string $paymentsql SQL query that selects payment.id field for the payments
     * @param array $paymentparams Array of parameters for $paymentsql
     */
    public static function delete_data_for_payment_sql(string $paymentsql, array $paymentparams) {
        global $DB;

        $DB->delete_records_select(self::ORDERS, "paymentid IN ({$paymentsql})", $paymentparams);
    }

    /**
     * The system context, when the user appears in any of the three tables.
     *
     * @param int $userid
     * @return contextlist
     */
    public static function get_contexts_for_userid(int $userid): contextlist {
        global $DB;
        $contextlist = new contextlist();
        foreach ([self::ORDERS, self::ENROLMENTLOG, self::ERRORLOG] as $table) {
            if ($DB->record_exists($table, ['userid' => $userid])) {
                $contextlist->add_system_context();
                break;
            }
        }
        return $contextlist;
    }

    /**
     * Every buyer named in the three tables.
     *
     * @param userlist $userlist
     */
    public static function get_users_in_context(userlist $userlist): void {
        if (!$userlist->get_context() instanceof \context_system) {
            return;
        }
        foreach ([self::ORDERS, self::ENROLMENTLOG, self::ERRORLOG] as $table) {
            $userlist->add_from_sql('userid', "SELECT userid FROM {{$table}} WHERE userid > 0", []);
        }
    }

    /**
     * Every row of the three tables that names the user.
     *
     * @param approved_contextlist $contextlist
     */
    public static function export_user_data(approved_contextlist $contextlist): void {
        global $DB;
        $userid = (int) $contextlist->get_user()->id;
        $context = \context_system::instance();
        if (!in_array((int) $context->id, array_map('intval', $contextlist->get_contextids()), true)) {
            return;
        }
        $root = get_string('pluginname', 'paygw_airpay');
        $date = fn($t) => empty($t) ? null : userdate((int) $t);

        $orders = [];
        foreach ($DB->get_records(self::ORDERS, ['userid' => $userid], 'timecreated ASC') as $r) {
            $orders[] = [
                'orderid'     => $r->ap_orderid,
                'component'   => $r->component,
                'paymentarea' => $r->paymentarea,
                'itemid'      => $r->itemid,
                'cost'        => $r->cost,
                'paymentid'   => $r->paymentid,
                'status'      => $r->status,
                'timecreated' => $date($r->timecreated),
            ];
        }
        if ($orders) {
            writer::with_context($context)->export_data(
                [$root, get_string('privacy:metadata:paygw_airpay', 'paygw_airpay')], (object) ['orders' => $orders]);
        }

        $enrolments = [];
        foreach ($DB->get_records(self::ENROLMENTLOG, ['userid' => $userid], 'timecreated ASC') as $r) {
            $enrolments[] = [
                'courseid'      => $r->courseid,
                'coursename'    => $r->coursename,
                'username'      => $r->username,
                'transactionid' => $r->transactionid,
                'orderid'       => $r->ap_orderid,
                'amount'        => $r->amount,
                'status'        => $r->status,
                'timecreated'   => $date($r->timecreated),
            ];
        }
        if ($enrolments) {
            writer::with_context($context)->export_data(
                [$root, get_string('privacy:metadata:paygw_course_enrolmentlog', 'paygw_airpay')],
                (object) ['enrolments' => $enrolments]);
        }

        $events = [];
        foreach ($DB->get_records(self::ERRORLOG, ['userid' => $userid], 'timecreated ASC') as $r) {
            $events[] = [
                'orderid'     => $r->airpay_id,
                'courseid'    => $r->courseid,
                'order_state' => $r->order_state,
                'message'     => $r->error,
                'paymentarea' => $r->paymentarea,
                'timecreated' => $date($r->timecreated),
            ];
        }
        if ($events) {
            writer::with_context($context)->export_data(
                [$root, get_string('privacy:metadata:paygw_airpay_errorlog', 'paygw_airpay')],
                (object) ['events' => $events]);
        }
    }

    /**
     * Anonymise the user in all three tables (see the class doc block).
     *
     * @param approved_contextlist $contextlist
     */
    public static function delete_data_for_user(approved_contextlist $contextlist): void {
        $context = \context_system::instance();
        if (!in_array((int) $context->id, array_map('intval', $contextlist->get_contextids()), true)) {
            return;
        }
        self::anonymise([(int) $contextlist->get_user()->id]);
    }

    /**
     * Anonymise the listed users in all three tables.
     *
     * @param approved_userlist $userlist
     */
    public static function delete_data_for_users(approved_userlist $userlist): void {
        if (!$userlist->get_context() instanceof \context_system) {
            return;
        }
        $userids = array_map('intval', $userlist->get_userids());
        if ($userids) {
            self::anonymise($userids);
        }
    }

    /**
     * Anonymise every buyer in all three tables; the rows stay.
     *
     * @param \context $context
     */
    public static function delete_data_for_all_users_in_context(\context $context): void {
        global $DB;
        if (!$context instanceof \context_system) {
            return;
        }
        $userids = [];
        foreach ([self::ORDERS, self::ENROLMENTLOG, self::ERRORLOG] as $table) {
            $userids = array_merge($userids, $DB->get_fieldset_select($table, 'DISTINCT userid', 'userid > 0'));
        }
        $userids = array_values(array_unique(array_map('intval', $userids)));
        foreach (array_chunk($userids, 1000) as $chunk) {
            self::anonymise($chunk);
        }
    }

    /**
     * Set the user id to 0 (and blank the buyer's name) and keep the rows.
     *
     * Paid orders are left to core_payment, which erases them together with the payment.
     *
     * @param int[] $userids
     */
    private static function anonymise(array $userids): void {
        global $DB;
        [$insql, $params] = $DB->get_in_or_equal($userids, SQL_PARAMS_NAMED, 'u');

        // The message text names the buyer too: 'by user with userid "(N)"'.
        $rows = $DB->get_recordset_select(self::ERRORLOG, "userid {$insql}", $params, '', 'id, userid, error');
        foreach ($rows as $row) {
            $error = preg_replace('/userid "\(' . (int) $row->userid . '\)"/', 'userid "(0)"', (string) $row->error);
            if ($error !== $row->error) {
                $DB->set_field(self::ERRORLOG, 'error', $error, ['id' => $row->id]);
            }
        }
        $rows->close();
        $DB->set_field_select(self::ERRORLOG, 'userid', 0, "userid {$insql}", $params);

        $DB->execute("UPDATE {" . self::ENROLMENTLOG . "} SET userid = 0, username = '' WHERE userid {$insql}", $params);

        $DB->execute("UPDATE {" . self::ORDERS . "}
                         SET userid = 0
                       WHERE userid {$insql}
                         AND (paymentid IS NULL
                              OR NOT EXISTS (SELECT 1 FROM {payments} p
                                              WHERE p.id = {" . self::ORDERS . "}.paymentid))", $params);
    }
}
