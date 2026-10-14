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
 *
 * ADR-032 (2026-10-01): the BizLMS cart import adds frozen money history, and every personal column it adds
 * is declared and handled here:
 *  - local_sentientia_cart_ledger.initiatedby (who booked it) and payload_json (an imported row keeps the buyer's
 *    user id, FIRST in the text, as {"userid":N,...}); both are anonymised on erasure and the rows stay;
 *  - local_sentientia_cart_credit_txn (userid, initiatedby, reason): exported, and anonymised on erasure (the
 *    amounts stay for finance: the journal is a money record, not a basket);
 *  - local_sentientia_cart_id: a slot a paid order refers to is anonymised, never deleted (deleting it would let
 *    the order number be issued twice); only an unreferenced basket slot is deleted.
 * Imported orders and invoice references carry no billing details, so there is nothing to blank in them.
 *
 * GST tax invoices and credit notes (2026-10-14, spec section 9): the invoice and the credit note are tax records the
 * law makes the supplier keep (CGST Act section 36: 72 months from the due date of the annual return). Erasure
 * therefore keeps a document that is younger than the invoice_retention_years setting (default 8) exactly as it is,
 * and blanks the buyer's name, e-mail, phone, address and GSTIN on one that is older, counted from the document date.
 * Export gives the user every invoice, credit note and receipt of theirs. The order history follows the rules it
 * always had.
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
            'billing_state'  => 'privacy:metadata:local_sentientia_cart_history:billing_state',
            'timecreated'    => 'privacy:metadata:local_sentientia_cart_history:timecreated',
        ], 'privacy:metadata:local_sentientia_cart_history');

        $collection->add_database_table('local_sentientia_cart_invoices', [
            'userid'         => 'privacy:metadata:local_sentientia_cart_invoices:userid',
            'billing_name'   => 'privacy:metadata:local_sentientia_cart_invoices:billing_name',
            'billing_email'  => 'privacy:metadata:local_sentientia_cart_invoices:billing_email',
            'billing_phone'  => 'privacy:metadata:local_sentientia_cart_invoices:billing_phone',
            'billing_address' => 'privacy:metadata:local_sentientia_cart_invoices:billing_address',
            'billing_gstn'   => 'privacy:metadata:local_sentientia_cart_invoices:billing_gstn',
            'billing_state'  => 'privacy:metadata:local_sentientia_cart_invoices:billing_state',
            'place_of_supply' => 'privacy:metadata:local_sentientia_cart_invoices:place_of_supply',
            'line_items_json' => 'privacy:metadata:local_sentientia_cart_invoices:line_items',
            'irn'            => 'privacy:metadata:local_sentientia_cart_invoices:irn',
        ], 'privacy:metadata:local_sentientia_cart_invoices');

        // GST credit notes (2026-10-14): a tax record, kept for the retention period like the invoice it reduces.
        $collection->add_database_table('local_sentientia_cart_creditnotes', [
            'userid'          => 'privacy:metadata:local_sentientia_cart_creditnotes:userid',
            'billing_name'    => 'privacy:metadata:local_sentientia_cart_creditnotes:billing_name',
            'billing_email'   => 'privacy:metadata:local_sentientia_cart_creditnotes:billing_email',
            'billing_phone'   => 'privacy:metadata:local_sentientia_cart_creditnotes:billing_phone',
            'billing_address' => 'privacy:metadata:local_sentientia_cart_creditnotes:billing_address',
            'billing_gstn'    => 'privacy:metadata:local_sentientia_cart_creditnotes:billing_gstn',
            'billing_state'   => 'privacy:metadata:local_sentientia_cart_creditnotes:billing_state',
            'total'           => 'privacy:metadata:local_sentientia_cart_creditnotes:total',
            'reason'          => 'privacy:metadata:local_sentientia_cart_creditnotes:reason',
            'irn'             => 'privacy:metadata:local_sentientia_cart_creditnotes:irn',
        ], 'privacy:metadata:local_sentientia_cart_creditnotes');

        // Ledger — audit log retained for financial/tax compliance. ADR-032: who booked a row (initiatedby) and
        // what the previous system recorded for an imported row (payload_json, which names the buyer) are personal.
        $collection->add_database_table('local_sentientia_cart_ledger', [
            'initiatedby'  => 'privacy:metadata:local_sentientia_cart_ledger:initiatedby',
            'payload_json' => 'privacy:metadata:local_sentientia_cart_ledger:payload',
            'amount'       => 'privacy:metadata:local_sentientia_cart_ledger:amount',
            'timecreated'  => 'privacy:metadata:local_sentientia_cart_ledger:timecreated',
        ], 'privacy:metadata:local_sentientia_cart_ledger');

        // Credit bookings imported from BizLMS (ADR-032). A money record: anonymised on erasure, never deleted.
        $collection->add_database_table('local_sentientia_cart_credit_txn', [
            'userid'        => 'privacy:metadata:local_sentientia_cart_credit_txn:userid',
            'initiatedby'   => 'privacy:metadata:local_sentientia_cart_credit_txn:initiatedby',
            'amount'        => 'privacy:metadata:local_sentientia_cart_credit_txn:amount',
            'balance_after' => 'privacy:metadata:local_sentientia_cart_credit_txn:balance_after',
            'event_type'    => 'privacy:metadata:local_sentientia_cart_credit_txn:event_type',
            'reason'        => 'privacy:metadata:local_sentientia_cart_credit_txn:reason',
            'timecreated'   => 'privacy:metadata:local_sentientia_cart_credit_txn:timecreated',
        ], 'privacy:metadata:local_sentientia_cart_credit_txn');

        // External data transmission to payment gateway.
        $collection->add_external_location_link('gateway', [
            'email'  => 'privacy:metadata:gateway:email',
            'name'   => 'privacy:metadata:gateway:name',
            'amount' => 'privacy:metadata:gateway:amount',
        ], 'privacy:metadata:gateway');

        // Added 2026-09-22. These two were owned but undeclared, so the
        // registry under-reported what this plugin holds. Unlike the invoice
        // and ledger rows below, neither is a tax record: an open cart is a
        // shopping basket and a credit balance belongs to the user, so both
        // are DELETED on erasure rather than redacted.
        $collection->add_database_table('local_sentientia_cart_id', [
            'userid'   => 'privacy:metadata:local_sentientia_cart_id:userid',
            'reserved' => 'privacy:metadata:local_sentientia_cart_id:reserved',
        ], 'privacy:metadata:local_sentientia_cart_id');

        $collection->add_database_table('local_sentientia_cart_credits', [
            'userid'          => 'privacy:metadata:local_sentientia_cart_credits:userid',
            'balance'         => 'privacy:metadata:local_sentientia_cart_credits:balance',
            'currency'        => 'privacy:metadata:local_sentientia_cart_credits:currency',
            'lifetime_earned' => 'privacy:metadata:local_sentientia_cart_credits:lifetime_earned',
            'lifetime_spent'  => 'privacy:metadata:local_sentientia_cart_credits:lifetime_spent',
            'timemodified'    => 'privacy:metadata:local_sentientia_cart_credits:timemodified',
        ], 'privacy:metadata:local_sentientia_cart_credits');

        return $collection;
    }

    public static function get_contexts_for_userid(int $userid): contextlist {
        $contextlist = new contextlist();
        global $DB;
        // All three tables, not just history: a user who only ever filled a
        // basket, or who holds a credit balance and has never ordered, was
        // previously reported as having no data here.
        foreach (['local_sentientia_cart_history',
                  'local_sentientia_cart_id',
                  'local_sentientia_cart_credits',
                  'local_sentientia_cart_credit_txn',
                  'local_sentientia_cart_invoices',
                  'local_sentientia_cart_creditnotes'] as $table) {
            if ($DB->record_exists($table, ['userid' => $userid])) {
                $contextlist->add_system_context();
                break;
            }
        }
        return $contextlist;
    }

    public static function get_users_in_context(userlist $userlist): void {
        $context = $userlist->get_context();
        if (!$context instanceof \context_system) {
            return;
        }
        global $DB;
        foreach (['local_sentientia_cart_history',
                  'local_sentientia_cart_id',
                  'local_sentientia_cart_credits',
                  'local_sentientia_cart_credit_txn',
                  'local_sentientia_cart_invoices',
                  'local_sentientia_cart_creditnotes'] as $table) {
            $userlist->add_users($DB->get_fieldset_sql(
                "SELECT DISTINCT userid FROM {" . $table . "} WHERE userid > 0"));
        }
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

        // Open basket and credit balance. Added 2026-09-22 with the two
        // undeclared tables.
        $basket = $DB->get_records('local_sentientia_cart_id', ['userid' => $userid]);
        if (!empty($basket)) {
            $rows = [];
            foreach ($basket as $b) {
                $rows[] = ['reserved' => $b->reserved];
            }
            writer::with_context($context)->export_data(
                [get_string('pluginname', 'local_sentientia_cart'), 'basket'],
                (object) ['basket' => $rows]);
        }

        // ADR-032: the credit journal imported from BizLMS, and the ledger rows that name this user.
        $txns = $DB->get_records('local_sentientia_cart_credit_txn', ['userid' => $userid], 'timecreated ASC, id ASC');
        if (!empty($txns)) {
            $rows = [];
            foreach ($txns as $t) {
                $rows[] = [
                    'event'         => $t->event_type,
                    'amount'        => $t->amount,
                    'balance_after' => $t->balance_after,
                    'currency'      => $t->currency,
                    'order_number'  => $t->orderid,
                    'made_on'       => empty($t->timecreated) ? null : userdate((int) $t->timecreated),
                ];
            }
            writer::with_context($context)->export_data(
                [get_string('pluginname', 'local_sentientia_cart'), 'credit_history'],
                (object) ['credit_history' => $rows]);
        }

        $ledger = self::ledger_rows_of($userid);
        if (!empty($ledger)) {
            $rows = [];
            foreach ($ledger as $l) {
                $rows[] = [
                    'event'    => $l->event_type,
                    'amount'   => $l->amount,
                    'currency' => $l->currency,
                    'gateway'  => $l->gateway,
                    'made_on'  => empty($l->timecreated) ? null : userdate((int) $l->timecreated),
                ];
            }
            writer::with_context($context)->export_data(
                [get_string('pluginname', 'local_sentientia_cart'), 'payments'],
                (object) ['payments' => $rows]);
        }

        self::export_tax_documents($userid, $context);

        $credits = $DB->get_records('local_sentientia_cart_credits', ['userid' => $userid]);
        if (!empty($credits)) {
            $rows = [];
            foreach ($credits as $c) {
                $rows[] = [
                    'balance'         => $c->balance,
                    'currency'        => $c->currency,
                    'lifetime_earned' => $c->lifetime_earned,
                    'lifetime_spent'  => $c->lifetime_spent,
                    'last_changed'    => empty($c->timemodified)
                        ? null : userdate((int) $c->timemodified),
                ];
            }
            writer::with_context($context)->export_data(
                [get_string('pluginname', 'local_sentientia_cart'), 'credits'],
                (object) ['credits' => $rows]);
        }
    }

    /**
     * Export every invoice, credit note and receipt of a user (GST tax invoices spec, section 9).
     *
     * An invoice or credit note is exported whatever its status, with the particulars it carries; a receipt is
     * exported for every paid order of theirs (it is worked out from the order, and so exists whether or not the
     * receipt flag is on). Imported BizLMS orders carry no receipt, and an imported invoice is a reference with no
     * buyer details.
     *
     * @param int $userid
     * @param \context $context
     * @return void
     */
    private static function export_tax_documents(int $userid, \context $context): void {
        global $DB;
        $component = get_string('pluginname', 'local_sentientia_cart');
        $stamp = static fn($t): ?string => empty($t) ? null : userdate((int) $t);

        $invoices = $DB->get_records('local_sentientia_cart_invoices', ['userid' => $userid], 'timecreated ASC, id ASC');
        if ($invoices) {
            $rows = [];
            foreach ($invoices as $i) {
                $rows[] = [
                    'invoice_number'  => $i->invoice_number,
                    'order_number'    => $i->orderid,
                    'status'          => $i->status,
                    'issued_on'       => $stamp($i->timecreated),
                    'billing_name'    => $i->billing_name,
                    'billing_email'   => $i->billing_email,
                    'billing_phone'   => $i->billing_phone,
                    'billing_address' => $i->billing_address,
                    'billing_gstn'    => $i->billing_gstn,
                    'billing_state'   => $i->billing_state,
                    'place_of_supply' => $i->place_of_supply,
                    'sac_code'        => $i->sac_code,
                    'gst_rate'        => $i->gst_rate,
                    'lines'           => json_decode((string) $i->line_items_json, true),
                    'taxable_value'   => $i->subtotal,
                    'cgst'            => $i->cgst,
                    'sgst'            => $i->sgst,
                    'igst'            => $i->igst,
                    'total'           => $i->total,
                    'currency'        => $i->currency,
                    'irn'             => $i->irn,
                    'ack_no'          => $i->ack_no,
                    'ack_date'        => $stamp($i->ack_date),
                ];
            }
            writer::with_context($context)->export_data([$component, 'invoices'], (object) ['invoices' => $rows]);
        }

        $notes = $DB->get_records('local_sentientia_cart_creditnotes', ['userid' => $userid], 'timecreated ASC, id ASC');
        if ($notes) {
            $rows = [];
            foreach ($notes as $n) {
                $rows[] = [
                    'credit_note_number' => $n->cn_number,
                    'against_invoice_id' => $n->invoiceid,
                    'status'          => $n->status,
                    'issued_on'       => $stamp($n->timecreated),
                    'billing_name'    => $n->billing_name,
                    'billing_email'   => $n->billing_email,
                    'billing_phone'   => $n->billing_phone,
                    'billing_address' => $n->billing_address,
                    'billing_gstn'    => $n->billing_gstn,
                    'billing_state'   => $n->billing_state,
                    'place_of_supply' => $n->place_of_supply,
                    'taxable_value'   => $n->taxable,
                    'cgst'            => $n->cgst,
                    'sgst'            => $n->sgst,
                    'igst'            => $n->igst,
                    'total'           => $n->total,
                    'currency'        => $n->currency,
                    'reason'          => $n->reason,
                    'irn'             => $n->irn,
                    'ack_no'          => $n->ack_no,
                    'ack_date'        => $stamp($n->ack_date),
                ];
            }
            writer::with_context($context)->export_data([$component, 'credit_notes'], (object) ['credit_notes' => $rows]);
        }

        // Receipts: one per paid, native order. The refunds so far come from the ledger, as on the receipt itself.
        [$insql, $inparams] = $DB->get_in_or_equal(['paid', 'partial_refund', 'refunded'], SQL_PARAMS_NAMED, 'rcptst');
        $orders = $DB->get_records_select('local_sentientia_cart_history',
            "userid = :uid AND status $insql AND " . \local_sentientia_cart\imported_history::native_only_sql(),
            ['uid' => $userid] + $inparams, 'timecreated ASC, id ASC');
        if ($orders) {
            $rows = [];
            foreach ($orders as $o) {
                $refunds = [];
                foreach (\local_sentientia_cart\receipts::refunds_of($o) as $r) {
                    $refunds[] = ['amount' => $r->refunded, 'refunded_on' => $stamp($r->timecreated)];
                }
                $rows[] = [
                    'order_number' => $o->orderid,
                    'paid_on'      => $stamp($o->timepaid ?: $o->timecreated),
                    'buyer_name'   => $o->billing_name,
                    'buyer_email'  => $o->billing_email,
                    'items'        => json_decode((string) $o->items_json, true),
                    'subtotal'     => $o->subtotal,
                    'discount'     => $o->discount_amount,
                    'gst'          => $o->tax_amount,
                    'total_paid'   => $o->total_amount,
                    'currency'     => $o->currency,
                    'gateway'      => $o->gateway,
                    'gateway_ref'  => $o->gateway_ref,
                    'refunds'      => $refunds,
                ];
            }
            writer::with_context($context)->export_data([$component, 'receipts'], (object) ['receipts' => $rows]);
        }
    }

    /**
     * The time before which a tax document is past its retention period: now minus invoice_retention_years.
     *
     * @return int A timestamp; a document dated before it may have the buyer's details blanked.
     */
    private static function retention_cutoff(): int {
        $years = \local_sentientia_cart\gst_settings::retention_years();
        return (new \DateTimeImmutable('@' . time()))->modify('-' . $years . ' years')->getTimestamp();
    }

    /**
     * Blank the buyer's name, e-mail, phone, address and GSTIN on the tax documents that are past their retention
     * period; documents younger than that are kept unchanged (the law requires the supplier to keep them).
     * Imported ERPNext references carry no buyer details and are left alone.
     *
     * @param int|null $userid One user, or null for every user.
     * @return void
     */
    private static function redact_tax_documents(?int $userid): void {
        global $DB;
        $cutoff = self::retention_cutoff();
        $user = $userid === null ? '' : ' AND userid = :uid';
        $params = ['cutoff' => $cutoff] + ($userid === null ? [] : ['uid' => $userid]);

        $DB->execute("UPDATE {local_sentientia_cart_invoices}
                         SET billing_name = '(redacted)',
                             billing_email = '',
                             billing_phone = '',
                             billing_address = '',
                             billing_gstn = '',
                             signed_qr = NULL
                       WHERE status <> :legacy AND timecreated < :cutoff$user",
            ['legacy' => \local_sentientia_cart\imported_history::INVOICE_STATUS] + $params);
        // The signed QR is the portal's JWT, which carries the buyer's GSTIN; a credit note's reason is free text
        // (review 2026-10-14, should-fix 7). The IRN and the acknowledgement number identify the government record
        // and stay.
        $DB->execute("UPDATE {local_sentientia_cart_creditnotes}
                         SET billing_name = '(redacted)',
                             billing_email = '',
                             billing_phone = '',
                             billing_address = '',
                             billing_gstn = '',
                             signed_qr = NULL,
                             reason = NULL
                       WHERE timecreated < :cutoff$user",
            $params);
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
    /**
     * Blank the buyer details of one order (not an import). The invoicer calls it after issuing the invoice of an
     * order whose buyer has been deleted meanwhile: the order kept the details only for that invoice.
     *
     * @param int $historyid
     * @return void
     */
    public static function redact_order(int $historyid): void {
        global $DB;
        $DB->execute("UPDATE {local_sentientia_cart_history}
                         SET billing_name = '(redacted)',
                             billing_email = '',
                             billing_phone = '',
                             billing_address = '',
                             billing_gstn = '',
                             billing_state = NULL,
                             notes = ''
                       WHERE id = :id AND legacy_source IS NULL AND invoice_due = 0",
            ['id' => $historyid]);
    }

    private static function redact_for_user(int $userid): void {
        global $DB;
        // An order imported from BizLMS carries no billing details and its notes are import facts (legacy ids,
        // never personal), so it is left as it is: it is frozen history (ADR-032).
        // A paid order whose tax invoice is still due: issue it now, while the order still holds the buyer details
        // the invoice needs (review 2026-10-14, should-fix 7). One that still cannot be issued (incomplete settings)
        // keeps them until it is; invoicer::issue_for_order() then blanks the order if the buyer has been deleted.
        // Never inside a caller's transaction: a failed issue would roll the whole erasure back with it.
        if (!$DB->is_transaction_started()) {
            foreach ($DB->get_records('local_sentientia_cart_history', ['userid' => $userid, 'invoice_due' => 1]) as $due) {
                try {
                    \local_sentientia_cart\invoicer::issue_for_order($due);
                } catch (\Throwable $e) {
                    debugging('local_sentientia_cart: the due invoice of order ' . (int) $due->orderid
                        . ' could not be issued before erasure: ' . $e->getMessage(), DEBUG_DEVELOPER);
                }
            }
        }
        $DB->execute("UPDATE {local_sentientia_cart_history}
                         SET billing_name = '(redacted)',
                             billing_email = '',
                             billing_phone = '',
                             billing_address = '',
                             billing_gstn = '',
                             billing_state = NULL,
                             notes = ''
                       WHERE userid = :uid AND legacy_source IS NULL AND invoice_due = 0",
            ['uid' => $userid]);
        // GST tax documents (2026-10-14): kept unchanged while they are inside the retention period (the law requires
        // it), blanked once they are older. Until then the order history above is redacted but the invoice is not.
        self::redact_tax_documents($userid);

        // ADR-032: the ledger and the credit journal are money records. They stay, with the person taken out.
        self::anonymise_ledger_of($userid);
        $DB->execute("UPDATE {local_sentientia_cart_credit_txn} SET userid = 0, reason = NULL WHERE userid = :uid",
            ['uid' => $userid]);
        $DB->execute("UPDATE {local_sentientia_cart_credit_txn} SET initiatedby = 0 WHERE initiatedby = :uid",
            ['uid' => $userid]);

        // The open basket and the credit balance are NOT tax records, so they
        // are deleted outright rather than redacted. Keeping a redacted
        // shopping basket serves no audit purpose and still links a row to a
        // user id. Added 2026-09-22.
        // ADR-032: but a slot that a paid order still refers to is the order's number. Deleting it would let the
        // number be issued again, so it is anonymised, and only a slot no order uses is deleted.
        $DB->execute("UPDATE {local_sentientia_cart_id} SET userid = 0
                       WHERE userid = :uid
                         AND id IN (SELECT h.orderid FROM {local_sentientia_cart_history} h WHERE h.orderid IS NOT NULL)",
            ['uid' => $userid]);
        $DB->execute("DELETE FROM {local_sentientia_cart_id}
                       WHERE userid = :uid
                         AND id NOT IN (SELECT h.orderid FROM {local_sentientia_cart_history} h WHERE h.orderid IS NOT NULL)",
            ['uid' => $userid]);
        $DB->delete_records('local_sentientia_cart_credits', ['userid' => $userid]);
    }

    private static function redact_all(): void {
        global $DB;
        $DB->execute("UPDATE {local_sentientia_cart_history}
                         SET billing_name = '(redacted)',
                             billing_email = '',
                             billing_phone = '',
                             billing_address = '',
                             billing_gstn = '',
                             billing_state = NULL,
                             notes = ''
                       WHERE legacy_source IS NULL");
        // GST tax documents: only those past the retention period (see redact_tax_documents()).
        self::redact_tax_documents(null);

        // ADR-032: the money records stay, with every person taken out.
        $DB->execute("UPDATE {local_sentientia_cart_ledger} SET initiatedby = 0 WHERE initiatedby <> 0");
        self::anonymise_payloads($DB->sql_like_escape('{"userid":') . '%', null);
        $DB->execute("UPDATE {local_sentientia_cart_credit_txn} SET userid = 0, initiatedby = 0, reason = NULL");

        // Same reasoning as redact_for_user(): baskets and balances are not
        // audit records, except a slot an order still refers to.
        $DB->execute("UPDATE {local_sentientia_cart_id} SET userid = 0
                       WHERE id IN (SELECT h.orderid FROM {local_sentientia_cart_history} h WHERE h.orderid IS NOT NULL)");
        $DB->execute("DELETE FROM {local_sentientia_cart_id}
                       WHERE id NOT IN (SELECT h.orderid FROM {local_sentientia_cart_history} h WHERE h.orderid IS NOT NULL)");
        $DB->delete_records('local_sentientia_cart_credits', []);
    }

    /**
     * The ledger rows that name a user: the rows of the user's orders, the rows the user booked, and the imported
     * rows whose payload names the user as buyer (they belong to no order when BizLMS booked them outside one).
     *
     * An imported row's payload_json begins {"userid":N, (the importer writes the buyer first), so a prefix match
     * finds it without parsing every row.
     *
     * @param int $userid
     * @return \stdClass[] ledger rows, by id
     */
    private static function ledger_rows_of(int $userid): array {
        global $DB;
        $like = $DB->sql_like('l.payload_json', ':prefix', true, true, false);
        return $DB->get_records_sql(
            "SELECT l.*
               FROM {local_sentientia_cart_ledger} l
          LEFT JOIN {local_sentientia_cart_history} h ON h.id = l.historyid
              WHERE h.userid = :u1 OR l.initiatedby = :u2 OR $like
           ORDER BY l.timecreated ASC, l.id ASC",
            ['u1' => $userid, 'u2' => $userid, 'prefix' => self::payload_prefix($userid) . '%']);
    }

    /**
     * Take a user out of the ledger and keep the row: initiatedby becomes 0, and an imported payload that names the
     * user as buyer loses the user id and the free-text reason (both can identify a person).
     *
     * @param int $userid
     * @return void
     */
    private static function anonymise_ledger_of(int $userid): void {
        global $DB;
        $DB->execute("UPDATE {local_sentientia_cart_ledger} SET initiatedby = 0 WHERE initiatedby = :uid",
            ['uid' => $userid]);
        self::anonymise_payloads(self::payload_prefix($userid) . '%', $userid);
    }

    /**
     * Take the buyer out of the imported ledger rows whose payload matches a pattern: the user id in the payload
     * becomes 0 and the free-text reason (which can name a person) is cleared. The row, its amount and its time
     * stay.
     *
     * @param string $pattern A LIKE pattern on payload_json.
     * @param int|null $userid Only rows of this buyer, or null for every buyer.
     * @return void
     */
    private static function anonymise_payloads(string $pattern, ?int $userid): void {
        global $DB;
        $like = $DB->sql_like('payload_json', ':prefix', true, true, false);
        $rows = $DB->get_records_select('local_sentientia_cart_ledger', $like, ['prefix' => $pattern],
            'id ASC', 'id, payload_json');
        foreach ($rows as $row) {
            $payload = json_decode((string) $row->payload_json, true);
            if (!is_array($payload) || (int) ($payload['userid'] ?? 0) === 0) {
                continue;
            }
            if ($userid !== null && (int) $payload['userid'] !== $userid) {
                continue;
            }
            $payload['userid'] = 0;
            $DB->update_record('local_sentientia_cart_ledger', (object) [
                'id' => $row->id,
                'payload_json' => json_encode($payload, JSON_INVALID_UTF8_SUBSTITUTE),
                'reason' => null,
            ]);
        }
    }

    /**
     * The start of the payload of an imported ledger row of a user, as a LIKE pattern without its wildcard.
     *
     * @param int $userid
     * @return string
     */
    private static function payload_prefix(int $userid): string {
        global $DB;
        return $DB->sql_like_escape('{"userid":' . $userid . ',');
    }
}
