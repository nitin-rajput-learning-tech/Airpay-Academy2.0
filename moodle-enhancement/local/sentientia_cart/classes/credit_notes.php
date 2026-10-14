<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

namespace local_sentientia_cart;

defined('MOODLE_INTERNAL') || die();

/**
 * Credit notes for refunds (CGST Act section 34; spec 2026-10-14, section 7).
 *
 * When cart_manager::refund() refunds an order that has an issued (or awaiting_irn) tax invoice and the invoice flag
 * is ON for the order's tenant, a credit note is created after the refund has committed, the same way an invoice is:
 * first as a DUE row (nothing numbered yet), then issued in its own transaction, by the request that refunded or by
 * the 15-minute task. A credit note failure never reaches the refund.
 *
 * Amounts: the refunded amount is gross. taxable = round(amount / (1 + rate / 100), 2) and tax = amount - taxable,
 * split the way the original invoice was; the credit note names the original invoice's number and date. The credit
 * notes of an invoice may not total more than the invoice total, and the credit note that completes the total takes
 * exactly what is left of each of the invoice's figures (gst::credit_note_figures()).
 *
 * A credit note is made only against an invoice issued under the GST layout (it has a place of supply). An invoice
 * from before 1.2.0 never had the figures a credit note must repeat; its refunds are left to Finance, as before.
 *
 * @package    local_sentientia_cart
 * @copyright  2026 Airpay Payment Services
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class credit_notes {

    /** Waiting for a number. */
    public const STATUS_DUE = 'due';

    /**
     * Called by cart_manager::refund() once the refund has committed. Creates the due credit note and tries to
     * issue it at once. Never throws: a failure is logged, and the task retries an unissued credit note.
     *
     * @param \stdClass $cart The refunded order.
     * @param float $amount The gross amount refunded.
     * @param int $ledgerid The refund's ledger row.
     * @param string $reason The reason given for the refund.
     * @return void
     */
    public static function after_refund(\stdClass $cart, float $amount, int $ledgerid, string $reason): void {
        global $DB;
        if ($DB->is_transaction_started()) {
            // Inside a caller's transaction a failure here would roll the refund back with it. issue_due() raises the
            // credit note of every refund that has none, so the task picks this one up.
            return;
        }
        try {
            $cn = self::create_due($cart, $amount, $ledgerid, $reason);
            if ($cn) {
                self::issue((int) $cn->id);
            }
        } catch (\Throwable $e) {
            self::log_failure($cart, $e);
        }
    }

    /**
     * Log a credit note that could not be raised or issued: debugging plus the callback log, with no personal data
     * (as cart_manager does for invoices). The task retries it.
     *
     * @param \stdClass $cart The order.
     * @param \Throwable $e
     * @return void
     */
    private static function log_failure(\stdClass $cart, \Throwable $e): void {
        debugging('local_sentientia_cart: credit note for the refund of order ' . ($cart->orderid ?? '?')
            . ' not issued: ' . get_class($e) . ': ' . $e->getMessage(), DEBUG_DEVELOPER);
        callback_logger::log('credit_note_failed', ['orderid' => (int) ($cart->orderid ?? 0),
            'exception' => get_class($e), 'message' => $e->getMessage()], '');
    }

    /**
     * The paise the credit notes of an invoice already credit (due ones count: they will be issued).
     *
     * @param int $invoiceid
     * @return int[] taxable, cgst, sgst, igst, total (paise)
     */
    public static function credited_so_far(int $invoiceid): array {
        global $DB;
        $sum = ['taxable' => 0, 'cgst' => 0, 'sgst' => 0, 'igst' => 0, 'total' => 0];
        foreach ($DB->get_records('local_sentientia_cart_creditnotes', ['invoiceid' => $invoiceid]) as $cn) {
            foreach (array_keys($sum) as $key) {
                $sum[$key] += gst::to_paise($cn->$key);
            }
        }
        return $sum;
    }

    /**
     * Create the DUE credit note of a refund. Nothing is numbered yet.
     *
     * @param \stdClass $cart The refunded order.
     * @param float $amount The gross amount refunded, in rupees.
     * @param int $ledgerid The refund's ledger row (one credit note per refund; a second call returns the first).
     * @param string $reason
     * @return \stdClass|null The credit note row, or null when there is nothing to credit: the flag is OFF, the
     *                        order has no invoice, or its invoice is of the pre-1.2.0 layout.
     * @throws \moodle_exception error_creditnotelimit when the credit notes would total more than the invoice
     */
    public static function create_due(\stdClass $cart, float $amount, int $ledgerid, string $reason): ?\stdClass {
        global $DB;
        imported_history::refuse_if_imported($cart);
        if (!flags::invoices_for_order($cart)) {
            return null;
        }
        $invoice = $DB->get_record('local_sentientia_cart_invoices', ['historyid' => $cart->id]);
        if (!$invoice || !in_array($invoice->status, [invoicer::STATUS_ISSUED, invoicer::STATUS_AWAITING_IRN], true)) {
            return null;
        }
        if (empty($invoice->place_of_supply)) {
            return null;
        }
        if ($ledgerid > 0) {
            $existing = $DB->get_record('local_sentientia_cart_creditnotes', ['ledgerid' => $ledgerid]);
            if ($existing) {
                return $existing;
            }
        }

        $transaction = $DB->start_delegated_transaction();
        try {
            // Lock the invoice, so two refunds of one order cannot both pass the limit. A no-op UPDATE takes the row
            // lock without writing back the status read before it (Finance may have recorded the IRN meanwhile).
            $DB->execute('UPDATE {local_sentientia_cart_invoices} SET status = status WHERE id = :id', ['id' => $invoice->id]);
            $invoice = $DB->get_record('local_sentientia_cart_invoices', ['id' => $invoice->id], '*', MUST_EXIST);
            if ($ledgerid > 0) {
                // Look again under the lock: the refund request and the task may both have got here for one refund.
                $existing = $DB->get_record('local_sentientia_cart_creditnotes', ['ledgerid' => $ledgerid]);
                if ($existing) {
                    $transaction->allow_commit();
                    return $existing;
                }
            }
            $earlier = self::credited_so_far((int) $invoice->id);
            $remaining = gst::to_paise($invoice->total) - $earlier['total'];
            $amountp = gst::to_paise($amount);
            if ($amountp > $remaining) {
                // A refund may exceed the balance by a paisa (refund() allows 0.01); anything more is refused.
                if ($amountp - $remaining > 1) {
                    throw new \moodle_exception('error_creditnotelimit', 'local_sentientia_cart');
                }
                $amountp = $remaining;
            }
            if ($amountp <= 0) {
                $transaction->allow_commit();
                return null;
            }

            $rate = (float) ($invoice->gst_rate ?? 0);
            $intrastate = (float) $invoice->cgst > 0 || (float) $invoice->sgst > 0;
            $figures = gst::credit_note_figures($invoice, gst::to_rupees($amountp), $rate, $intrastate, $earlier);

            $now = time();
            $cn = (object) [
                'invoiceid'       => (int) $invoice->id,
                'historyid'       => (int) $cart->id,
                'ledgerid'        => $ledgerid > 0 ? $ledgerid : null,
                'cn_number'       => null,
                'userid'          => (int) $invoice->userid,
                'costcenterid'    => (int) $invoice->costcenterid,
                'billing_name'    => (string) $invoice->billing_name,
                'billing_email'   => $invoice->billing_email,
                'billing_phone'   => $invoice->billing_phone,
                'billing_address' => $invoice->billing_address,
                'billing_gstn'    => $invoice->billing_gstn,
                'billing_state'   => $invoice->billing_state,
                'place_of_supply' => $invoice->place_of_supply,
                'sac_code'        => $invoice->sac_code,
                'gst_rate'        => $invoice->gst_rate,
                'taxable'         => $figures['taxable'],
                'cgst'            => $figures['cgst'],
                'sgst'            => $figures['sgst'],
                'igst'            => $figures['igst'],
                'total'           => $figures['total'],
                'currency'        => (string) $invoice->currency,
                'reason'          => $reason,
                'status'          => self::STATUS_DUE,
                'supplier_json'   => $invoice->supplier_json,
                'timecreated'     => $now,
            ];
            $cn->id = $DB->insert_record('local_sentientia_cart_creditnotes', $cn);
            $transaction->allow_commit();
        } catch (\Throwable $e) {
            $transaction->rollback($e);
        }
        return $cn;
    }

    /**
     * Raise the credit notes of refunds that happened before the order's invoice existed (a refund that beat the
     * invoice, which was still due): one due credit note per refund ledger row that has none.
     *
     * @param \stdClass $invoice The invoice just issued.
     * @return int The number of credit notes created.
     */
    public static function create_missing_for_invoice(\stdClass $invoice): int {
        global $DB;
        $rows = $DB->get_records_select('local_sentientia_cart_ledger',
            'historyid = :hid AND ' . $DB->sql_like('event_type', ':ev', true, true),
            ['hid' => $invoice->historyid, 'ev' => $DB->sql_like_escape('refund_') . '%'], 'id ASC');
        if (!$rows) {
            return 0;
        }
        $cart = $DB->get_record('local_sentientia_cart_history', ['id' => $invoice->historyid], '*', MUST_EXIST);
        $created = 0;
        foreach ($rows as $row) {
            if ($DB->record_exists('local_sentientia_cart_creditnotes', ['ledgerid' => $row->id])) {
                continue;
            }
            if (self::create_due($cart, abs((float) $row->amount), (int) $row->id, (string) ($row->reason ?? ''))) {
                $created++;
            }
        }
        return $created;
    }

    /**
     * Issue a due credit note: number it and, for a B2B document, leave it awaiting the IRN. Own transaction.
     * Idempotent: a credit note that is not due is returned as it is.
     *
     * @param int $id The credit note.
     * @return \stdClass|null The credit note, or null while the invoice flag is OFF for its tenant.
     * @throws \moodle_exception error_invoicesettings, error_numbertoolong
     */
    public static function issue(int $id): ?\stdClass {
        global $DB;
        $cn = $DB->get_record('local_sentientia_cart_creditnotes', ['id' => $id], '*', MUST_EXIST);
        if ($cn->status !== self::STATUS_DUE) {
            return $cn;
        }
        if (!flags::invoices_for_order($cn)) {
            return null;
        }
        $problems = gst_settings::problems();
        if ($problems) {
            throw new \moodle_exception('error_invoicesettings', 'local_sentientia_cart', '',
                gst_settings::problem_labels($problems));
        }

        $prefix = gst_settings::credit_note_prefix();
        $now = time();
        $year = docseq::series_year($now, gst_settings::series_period());
        $b2b = gst::normalise_gstin((string) $cn->billing_gstn) !== '' && gst_settings::einvoice_b2b();

        docseq::ensure_series($prefix, $year);

        $transaction = $DB->start_delegated_transaction();
        try {
            // Lock the row, then look again: the other issuer may have numbered it while this one waited. A no-op
            // UPDATE takes the row lock without writing anything; writing 'due' here put an issued note back to due
            // and had it numbered twice, a gap in the series (review 2026-10-14, must-fix 1).
            $DB->execute('UPDATE {local_sentientia_cart_creditnotes} SET status = status WHERE id = :id', ['id' => $cn->id]);
            $cn = $DB->get_record('local_sentientia_cart_creditnotes', ['id' => $id], '*', MUST_EXIST);
            if ($cn->status !== self::STATUS_DUE) {
                $transaction->allow_commit();
                return $cn;
            }
            $cn->cn_number = docseq::allocate($prefix, $year);
            $cn->status = $b2b ? invoicer::STATUS_AWAITING_IRN : invoicer::STATUS_ISSUED;
            $cn->timecreated = $now;
            if (empty($cn->supplier_json)) {
                $cn->supplier_json = doc_view::supplier_snapshot();
            }
            $DB->update_record('local_sentientia_cart_creditnotes', $cn);
            $transaction->allow_commit();
        } catch (\Throwable $e) {
            $transaction->rollback($e);
        }
        return $cn;
    }

    /**
     * Issue every credit note that is still due (the scheduled task).
     *
     * @return array issued (int), skipped (int, flag OFF), failed (int), causes (array[], see invoicer::cause_of())
     */
    public static function issue_due(): array {
        global $DB;
        $result = ['issued' => 0, 'skipped' => 0, 'failed' => 0, 'causes' => []];

        // Refunds of an invoiced order that have no credit note yet: a create_due() that failed, or one skipped
        // because the refund ran inside a caller's transaction. create_due() decides; with the flag OFF it does nothing.
        $sql = "SELECT DISTINCT i.id
                  FROM {local_sentientia_cart_invoices} i
                  JOIN {local_sentientia_cart_ledger} l ON l.historyid = i.historyid
             LEFT JOIN {local_sentientia_cart_creditnotes} c ON c.ledgerid = l.id
                 WHERE c.id IS NULL
                   AND " . $DB->sql_like('l.event_type', ':ev', true, true) . "
                   AND i.status IN (:issued, :awaiting)
                   AND i.place_of_supply IS NOT NULL AND i.place_of_supply <> :empty";
        $params = ['ev' => $DB->sql_like_escape('refund_') . '%', 'issued' => invoicer::STATUS_ISSUED,
            'awaiting' => invoicer::STATUS_AWAITING_IRN, 'empty' => ''];
        foreach ($DB->get_fieldset_sql($sql, $params) as $invoiceid) {
            try {
                $invoice = $DB->get_record('local_sentientia_cart_invoices', ['id' => (int) $invoiceid], '*', MUST_EXIST);
                self::create_missing_for_invoice($invoice);
            } catch (\Throwable $e) {
                $result['failed']++;
                $cause = invoicer::cause_of($e);
                $result['causes'][md5(serialize($cause))] = $cause;
                debugging('local_sentientia_cart: the credit notes of invoice ' . (int) $invoiceid . ' not raised: '
                    . get_class($e) . ': ' . $e->getMessage(), DEBUG_DEVELOPER);
            }
        }

        $ids = $DB->get_fieldset_select('local_sentientia_cart_creditnotes', 'id', 'status = :s', ['s' => self::STATUS_DUE]);
        sort($ids);
        foreach ($ids as $id) {
            try {
                if (self::issue((int) $id)) {
                    $result['issued']++;
                } else {
                    $result['skipped']++;
                }
            } catch (\Throwable $e) {
                $result['failed']++;
                $cause = invoicer::cause_of($e);
                $result['causes'][md5(serialize($cause))] = $cause;
                debugging('local_sentientia_cart: credit note ' . $id . ' not issued: ' . get_class($e) . ': '
                    . $e->getMessage(), DEBUG_DEVELOPER);
            }
        }
        $result['causes'] = array_values($result['causes']);
        return $result;
    }

    /**
     * May $viewerid open this credit note? The owner, unless it awaits its IRN, or an administrator of invoices in
     * its tenant (ADR-031). A credit note that is only due is not a document yet: nobody opens it.
     *
     * @param \stdClass $cn A local_sentientia_cart_creditnotes row.
     * @param int|null $viewerid Defaults to the current user.
     * @throws \moodle_exception error_outoftenant, error_notavailable
     */
    public static function require_view_access(\stdClass $cn, ?int $viewerid = null): void {
        global $USER;
        $viewerid = $viewerid ?? (int) $USER->id;
        if ((string) $cn->status === self::STATUS_DUE) {
            throw new \moodle_exception('error_notavailable', 'local_sentientia_cart');
        }
        invoicer::require_document_access($cn, $viewerid);
    }
}
