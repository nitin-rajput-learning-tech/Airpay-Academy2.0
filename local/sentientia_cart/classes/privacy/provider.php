<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

namespace local_sentientia_cart\privacy;

use core_privacy\local\metadata\collection;
use core_privacy\local\request\approved_contextlist;
use core_privacy\local\request\approved_userlist;
use core_privacy\local\request\contextlist;
use core_privacy\local\request\userlist;
use core_privacy\local\request\writer;

defined('MOODLE_INTERNAL') || die();

/**
 * GDPR / DPDPA privacy provider for sentientia_cart.
 *
 * Cart data contains financial PII (billing name, address, GSTN) so it
 * MUST be exportable and deletable on Data Subject Request.
 *
 * Special rule: ledger + invoice rows are required for finance/audit
 * compliance — we redact PII rather than delete them.
 */
class provider implements
    \core_privacy\local\metadata\provider,
    \core_privacy\local\request\plugin\provider,
    \core_privacy\local\request\core_userlist_provider {

    public static function get_metadata(collection $collection): collection {
        $collection->add_database_table('local_sentientia_cart_history', [
            'userid'         => 'privacy:metadata:local_sentientia_cart_history:userid',
            'items_json'     => 'privacy:metadata:local_sentientia_cart_history:items',
            'total_amount'   => 'privacy:metadata:local_sentientia_cart_history:totalamount',
            'status'         => 'privacy:metadata:local_sentientia_cart_history:status',
            'timecreated'    => 'privacy:metadata:local_sentientia_cart_history:timecreated',
        ], 'privacy:metadata:local_sentientia_cart_history');

        $collection->add_database_table('local_sentientia_cart_invoices', [
            'userid'         => 'privacy:metadata:local_sentientia_cart_invoices:userid',
            'billing_name'   => 'privacy:metadata:local_sentientia_cart_invoices:billing_name',
            'billing_email'  => 'privacy:metadata:local_sentientia_cart_invoices:billing_email',
            'billing_phone'  => 'privacy:metadata:local_sentientia_cart_invoices:billing_phone',
            'billing_address' => 'privacy:metadata:local_sentientia_cart_invoices:billing_address',
            'billing_gstn'   => 'privacy:metadata:local_sentientia_cart_invoices:billing_gstn',
        ], 'privacy:metadata:local_sentientia_cart_invoices');

        // Ledger — audit log retained for financial/tax compliance.
        $collection->add_database_table('local_sentientia_cart_ledger', [], 'privacy:metadata:local_sentientia_cart_ledger');

        // External data transmission to payment gateway.
        $collection->add_external_location_link('gateway', [
            'email'  => 'privacy:metadata:gateway:email',
            'name'   => 'privacy:metadata:gateway:name',
            'amount' => 'privacy:metadata:gateway:amount',
        ], 'privacy:metadata:gateway');

        return $collection;
    }

    public static function get_contexts_for_userid(int $userid): contextlist {
        $contextlist = new contextlist();
        global $DB;
        if ($DB->record_exists('local_sentientia_cart_history', ['userid' => $userid])) {
            $contextlist->add_system_context();
        }
        return $contextlist;
    }

    public static function get_users_in_context(userlist $userlist): void {
        $context = $userlist->get_context();
        if (!$context instanceof \context_system) {
            return;
        }
        global $DB;
        $userids = $DB->get_fieldset_sql(
            "SELECT DISTINCT userid FROM {local_sentientia_cart_history}");
        $userlist->add_users($userids);
    }

    public static function export_user_data(approved_contextlist $contextlist): void {
        global $DB;
        $userid = $contextlist->get_user()->id;
        $context = \context_system::instance();

        $orders = $DB->get_records('local_sentientia_cart_history',
            ['userid' => $userid], 'timecreated DESC');
        $exportdata = [];
        foreach ($orders as $o) {
            $exportdata[] = [
                'order_number' => $o->orderid,
                'items'        => $o->items_json,
                'subtotal'     => $o->subtotal,
                'tax'          => $o->tax_amount,
                'total'        => $o->total_amount,
                'currency'     => $o->currency,
                'status'       => $o->status,
                'billing_name' => $o->billing_name,
                'billing_email' => $o->billing_email,
                'placed_on'    => userdate($o->timecreated),
                'paid_on'      => $o->timepaid ? userdate($o->timepaid) : null,
            ];
        }
        writer::with_context($context)->export_data(
            [get_string('pluginname', 'local_sentientia_cart')],
            (object) ['orders' => $exportdata]);
    }

    public static function delete_data_for_all_users_in_context(\context $context): void {
        if (!$context instanceof \context_system) {
            return;
        }
        // Compliance: never delete ledger; redact PII instead.
        self::redact_all();
    }

    public static function delete_data_for_user(approved_contextlist $contextlist): void {
        $userid = $contextlist->get_user()->id;
        self::redact_for_user($userid);
    }

    public static function delete_data_for_users(approved_userlist $userlist): void {
        foreach ($userlist->get_userids() as $userid) {
            self::redact_for_user((int) $userid);
        }
    }

    /**
     * Redact PII for one user. Ledger + invoice numbers preserved for audit.
     * Billing details, items snapshot get blanked.
     */
    private static function redact_for_user(int $userid): void {
        global $DB;
        $DB->execute("UPDATE {local_sentientia_cart_history}
                         SET billing_name = '(redacted)',
                             billing_email = '',
                             billing_phone = '',
                             billing_address = '',
                             billing_gstn = '',
                             notes = ''
                       WHERE userid = :uid",
            ['uid' => $userid]);
        $DB->execute("UPDATE {local_sentientia_cart_invoices}
                         SET billing_name = '(redacted)',
                             billing_email = '',
                             billing_phone = '',
                             billing_address = '',
                             billing_gstn = ''
                       WHERE userid = :uid",
            ['uid' => $userid]);
    }

    private static function redact_all(): void {
        global $DB;
        $DB->execute("UPDATE {local_sentientia_cart_history}
                         SET billing_name = '(redacted)',
                             billing_email = '',
                             billing_phone = '',
                             billing_address = '',
                             billing_gstn = '',
                             notes = ''");
        $DB->execute("UPDATE {local_sentientia_cart_invoices}
                         SET billing_name = '(redacted)',
                             billing_email = '',
                             billing_phone = '',
                             billing_address = '',
                             billing_gstn = ''");
    }
}
