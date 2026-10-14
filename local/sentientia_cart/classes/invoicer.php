<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

namespace local_sentientia_cart;

defined('MOODLE_INTERNAL') || die();

/**
 * Invoicer - issues the GST tax invoice of a paid order (spec 2026-10-14, sections 3 to 6 and 8).
 *
 * What changed, and why (the old invoicer is the list in section 1 of the spec):
 *  - The payment never depends on the invoice. cart_manager::mark_paid() only MARKS the order invoice-due (and only
 *    while the invoice flag is ON for the order's tenant); the invoice is issued after the payment has committed,
 *    in its own transaction, and the 15-minute task issues whatever is still due.
 *  - Numbers come from docseq: gap-free, allocated inside the transaction that inserts the invoice, at most 16
 *    characters. The old COUNT-based number could be handed to two payments at once.
 *  - The tax split is by place of supply: CGST + SGST when it is the supplier's state, IGST otherwise. For B2C the
 *    place of supply is the buyer's state (checkout asks for it), for B2B the state in the GSTIN.
 *  - Every figure comes from what the order recorded when the buyer was charged (gst::compute()): the taxable
 *    value, the tax and the total are not recomputed at today's rate.
 *  - Rule 46 particulars: SAC, place of supply, reverse charge, amount in words, signatory.
 *  - A B2B invoice waits for the e-invoice IRN that Finance records (einvoice.php) and is hidden from the buyer
 *    until then.
 *
 * With the flag OFF nothing new is issued. An invoice that already exists stays viewable exactly as before: a row
 * without a place of supply (the pre-1.2.0 layout) is rendered by the old template, an imported ERPNext reference
 * by its own.
 *
 * @package    local_sentientia_cart
 * @copyright  2026 Airpay Payment Services
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class invoicer {

    /** A tax invoice (or credit note) the buyer may see. */
    public const STATUS_ISSUED = 'issued';

    /** A B2B document that waits for the IRN Finance records; hidden from the buyer. */
    public const STATUS_AWAITING_IRN = 'awaiting_irn';

    /** The most a site administrator is warned: once in this many seconds. */
    public const WARNING_INTERVAL = DAYSECS;

    /**
     * May this viewer act as an administrator of invoices: a site administrator, or a holder of
     * :viewallorders or :manageinvoices?
     *
     * Tenant scoping (cart_manager::require_order_tenant) is a separate question, asked after this one.
     *
     * @param int $viewerid
     * @return bool
     */
    public static function has_admin_access(int $viewerid): bool {
        $ctx = \context_system::instance();
        return is_siteadmin($viewerid)
            || has_capability('local/sentientia_cart:viewallorders', $ctx, $viewerid)
            || has_capability('local/sentientia_cart:manageinvoices', $ctx, $viewerid);
    }

    /**
     * May $viewerid open this invoice? Throws if not.
     *
     * The owner always may, except while the invoice awaits its IRN (the buyer is not shown it until Finance has
     * recorded the IRN). Anyone else needs :viewallorders or :manageinvoices AND the invoice must be in their tenant
     * (ADR-031). invoice.php used to let any :viewallorders holder - every tenant admin - read every tenant's
     * invoices (billing name, email, address, GSTIN, line items) by counting up through the sequential ?id=.
     *
     * @param \stdClass $invoice a local_sentientia_cart_invoices row
     * @param int|null $viewerid defaults to the current user
     * @throws \moodle_exception error_outoftenant, error_notavailable
     */
    public static function require_view_access(\stdClass $invoice, ?int $viewerid = null): void {
        global $USER;
        $viewerid = $viewerid ?? (int) $USER->id;
        $status = (string) ($invoice->status ?? '');
        if ($status === imported_history::INVOICE_STATUS) {
            // ADR-032: an ERPNext reference imported from BizLMS is admin-only history. Its owner does not see it,
            // and an administrator only while the flag is on and only inside their own tenant. The refusal is the
            // one a row of another tenant gets.
            if (!imported_history::orders_enabled()
                    || (!is_siteadmin($viewerid) && !has_capability('local/sentientia_cart:viewallorders',
                        \context_system::instance(), $viewerid))) {
                throw new \moodle_exception('error_outoftenant', 'local_sentientia_cart');
            }
            cart_manager::require_order_tenant((int) $invoice->costcenterid, $viewerid);
            return;
        }
        self::require_document_access($invoice, $viewerid);
    }

    /**
     * The access rule of an invoice or a credit note: the owner (unless the document awaits its IRN), or an
     * administrator of invoices inside the document's tenant.
     *
     * @param \stdClass $doc An invoice or credit note row (userid, costcenterid, status).
     * @param int $viewerid
     * @throws \moodle_exception error_outoftenant, error_notavailable
     */
    public static function require_document_access(\stdClass $doc, int $viewerid): void {
        $awaiting = (string) ($doc->status ?? '') === self::STATUS_AWAITING_IRN;
        if ((int) $doc->userid === $viewerid && !$awaiting) {
            return;
        }
        if (!self::has_admin_access($viewerid)) {
            // The owner of a document that awaits its IRN is refused as "not available", not as another tenant's.
            throw new \moodle_exception((int) $doc->userid === $viewerid ? 'error_notavailable' : 'error_outoftenant',
                'local_sentientia_cart');
        }
        cart_manager::require_order_tenant((int) $doc->costcenterid, $viewerid);
    }

    /**
     * Issue the GST tax invoice of a paid order, in its own transaction. Idempotent: an order that already has an
     * invoice returns it.
     *
     * Returns null, and issues nothing, while the invoice flag is OFF for the order's tenant. Throws (and issues
     * nothing, the number being released by the rollback) when the tax settings are incomplete or the number
     * would be longer than 16 characters; the order stays invoice-due and the task retries.
     *
     * Never call it inside the payment's own transaction: cart_manager::mark_paid() does not.
     *
     * @param \stdClass $cart The order (history row).
     * @return \stdClass|null The invoice row, or null when the flag is OFF.
     * @throws \moodle_exception error_invoicesettings, error_numbertoolong, error_invalidstate
     */
    public static function issue_for_order(\stdClass $cart): ?\stdClass {
        global $DB;

        // ADR-032: an imported order already has its invoice in ERPNext; Sentientia numbers none for it.
        imported_history::refuse_if_imported($cart);

        $existing = $DB->get_record('local_sentientia_cart_invoices', ['historyid' => $cart->id]);
        if ($existing) {
            self::clear_due((int) $cart->id);
            return $existing;
        }

        // Work from the stored order, not from the copy the caller holds.
        $cart = $DB->get_record('local_sentientia_cart_history', ['id' => $cart->id], '*', MUST_EXIST);
        imported_history::refuse_if_imported($cart);

        if (!flags::invoices_for_order($cart)) {
            return null;
        }
        if (!in_array($cart->status, ['paid', 'partial_refund', 'refunded'], true)) {
            throw new \moodle_exception('error_invalidstate', 'local_sentientia_cart', '', 'Only a paid order is invoiced');
        }

        $problems = gst_settings::problems();
        if ($problems) {
            throw new \moodle_exception('error_invoicesettings', 'local_sentientia_cart', '',
                gst_settings::problem_labels($problems));
        }
        $prefix = gst_settings::invoice_prefix();
        if (strtoupper($prefix) === gst_settings::RESERVED_PREFIX) {
            // ADR-032: ERPNEXT-<id> is the number of an invoice imported from BizLMS, and invoice_number is unique.
            throw new \moodle_exception('error_invalidstate', 'local_sentientia_cart',
                '', 'The invoice prefix ERPNEXT is reserved for invoices imported from BizLMS');
        }

        $now = time();
        $year = docseq::series_year($now, gst_settings::series_period());
        $supplierstate = gst_settings::supplier_state();
        $gstin = gst::normalise_gstin((string) ($cart->billing_gstn ?? ''));
        $placeofsupply = gst::place_of_supply((string) ($cart->billing_state ?? ''), $gstin, $supplierstate);
        $calc = gst::compute($cart, $placeofsupply, $supplierstate, gst_settings::sac_code());
        $b2b = $gstin !== '' && gst_settings::einvoice_b2b();

        // Before the transaction: a duplicate-key failure inside one would poison it on PostgreSQL.
        docseq::ensure_series($prefix, $year);

        $transaction = $DB->start_delegated_transaction();
        try {
            // Lock the order row first, so two issuers of the SAME order (the request that took the payment and the
            // task) queue up, and the second finds the first one's invoice instead of issuing another.
            $DB->set_field('local_sentientia_cart_history', 'invoice_due', 1, ['id' => $cart->id]);
            $existing = $DB->get_record('local_sentientia_cart_invoices', ['historyid' => $cart->id]);
            if ($existing) {
                $DB->set_field('local_sentientia_cart_history', 'invoice_due', 0, ['id' => $cart->id]);
                $transaction->allow_commit();
                return $existing;
            }

            $number = docseq::allocate($prefix, $year);
            $invoice = (object) [
                'historyid'       => (int) $cart->id,
                'orderid'         => (int) $cart->orderid,
                'invoice_number'  => $number,
                'userid'          => (int) $cart->userid,
                'costcenterid'    => (int) $cart->costcenterid,
                'billing_name'    => (string) $cart->billing_name,
                'billing_email'   => (string) ($cart->billing_email ?? ''),
                'billing_phone'   => (string) ($cart->billing_phone ?? ''),
                'billing_address' => (string) ($cart->billing_address ?? ''),
                'billing_gstn'    => $gstin,
                'line_items_json' => json_encode($calc['lines'], JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE),
                'subtotal'        => $calc['taxable'],
                'cgst'            => $calc['cgst'],
                'sgst'            => $calc['sgst'],
                'igst'            => $calc['igst'],
                'total'           => $calc['total'],
                'currency'        => (string) $cart->currency,
                'status'          => $b2b ? self::STATUS_AWAITING_IRN : self::STATUS_ISSUED,
                'timecreated'     => $now,
                'billing_state'   => ($cart->billing_state ?? '') !== '' ? (string) $cart->billing_state : null,
                'place_of_supply' => $placeofsupply,
                'sac_code'        => gst_settings::sac_code(),
                'gst_rate'        => $calc['rate'],
                'supplier_json'   => doc_view::supplier_snapshot(),
            ];
            $invoiceid = $DB->insert_record('local_sentientia_cart_invoices', $invoice);
            // The stored row, so every caller sees every column (irn, ack_no, ... are null until recorded).
            $invoice = $DB->get_record('local_sentientia_cart_invoices', ['id' => $invoiceid], '*', MUST_EXIST);
            $DB->set_field('local_sentientia_cart_history', 'invoice_due', 0, ['id' => $cart->id]);
            $transaction->allow_commit();
        } catch (\Throwable $e) {
            $transaction->rollback($e);
        }

        // A refund that came in before the invoice existed has no credit note yet: raise it now.
        try {
            credit_notes::create_missing_for_invoice($invoice);
        } catch (\Throwable $e) {
            debugging('local_sentientia_cart: could not raise the credit notes of invoice ' . $invoice->invoice_number
                . ': ' . get_class($e) . ': ' . $e->getMessage(), DEBUG_DEVELOPER);
        }
        return $invoice;
    }

    /**
     * Take the invoice-due mark off an order.
     *
     * @param int $historyid
     * @return void
     */
    private static function clear_due(int $historyid): void {
        global $DB;
        if ($DB->record_exists('local_sentientia_cart_history', ['id' => $historyid, 'invoice_due' => 1])) {
            $DB->set_field('local_sentientia_cart_history', 'invoice_due', 0, ['id' => $historyid]);
        }
    }

    /**
     * Issue every invoice that is still due (the scheduled task, and nothing else, calls this).
     *
     * One order's failure never stops the others. A cause that stops invoicing (incomplete settings, a number that
     * would be too long) is collected once per distinct cause, for the administrator warning.
     *
     * @return array issued (int), skipped (int, flag OFF), failed (int), causes (array[]: the distinct causes, see cause_of())
     */
    public static function issue_due(): array {
        global $DB;
        $result = ['issued' => 0, 'skipped' => 0, 'failed' => 0, 'causes' => []];
        $ids = $DB->get_fieldset_select('local_sentientia_cart_history', 'id',
            'invoice_due = :due AND ' . imported_history::native_only_sql(), ['due' => 1]);
        sort($ids);
        foreach ($ids as $id) {
            $cart = $DB->get_record('local_sentientia_cart_history', ['id' => $id]);
            if (!$cart) {
                continue;
            }
            try {
                $invoice = self::issue_for_order($cart);
                if ($invoice) {
                    $result['issued']++;
                } else {
                    $result['skipped']++;
                }
            } catch (\Throwable $e) {
                $result['failed']++;
                $cause = self::cause_of($e);
                $result['causes'][md5(serialize($cause))] = $cause;
                debugging('local_sentientia_cart: invoice for order ' . $cart->orderid . ' not issued: '
                    . get_class($e) . ': ' . $e->getMessage(), DEBUG_DEVELOPER);
            }
        }
        $result['causes'] = array_values($result['causes']);
        return $result;
    }

    /**
     * Why a document could not be issued, in a form that can be put into each recipient's own language.
     *
     * @param \Throwable $e What issue_for_order() / credit_notes::issue() threw.
     * @return array code (the lang string id of the cause), and: ids (the settings that are missing, for
     *               error_invoicesettings), a (the number that is too long, for error_numbertoolong), or message
     *               (anything else, as text)
     */
    public static function cause_of(\Throwable $e): array {
        if ($e instanceof \moodle_exception && $e->module === 'local_sentientia_cart') {
            if ($e->errorcode === 'error_invoicesettings') {
                return ['code' => 'error_invoicesettings', 'ids' => gst_settings::problems()];
            }
            if ($e->errorcode === 'error_numbertoolong' && is_object($e->a)) {
                return ['code' => 'error_numbertoolong', 'a' => (object) (array) $e->a];
            }
        }
        return ['code' => 'other', 'message' => get_class($e) . ': ' . $e->getMessage()];
    }

    /**
     * Warn every site administrator that documents cannot be issued, at most once a day.
     *
     * @param array[] $causes What stopped the documents: cause_of() rows (settings missing, number too long, ...).
     * @param int|null $now Current time (for tests).
     * @return bool True when a warning was sent.
     */
    public static function warn_admins_once_a_day(array $causes, ?int $now = null): bool {
        $now = $now ?? time();
        if (!$causes) {
            return false;
        }
        $last = (int) get_config('local_sentientia_cart', 'gst_warning_sent');
        if ($last > 0 && $now - $last < self::WARNING_INTERVAL) {
            return false;
        }
        set_config('gst_warning_sent', $now, 'local_sentientia_cart');
        notifier::gst_admin_warning($causes);
        return true;
    }

    /**
     * Render an invoice as HTML (browser print = PDF via Ctrl+P, or the PDF download).
     *
     * A row issued under the GST layout (it has a place of supply) is drawn by the tax document template, with the
     * words in the viewer's language. An invoice from before 1.2.0 is drawn exactly as it always was.
     *
     * Phase 8.1 B5 fix (kept for the old layout): the old template used `{{{ }}}` (raw output) for `company_address`
     * and `billing_address` because addresses contain embedded line breaks that need to render as `<br/>`. The old
     * code called `nl2br(s($x))` in PHP to pre-escape + insert breaks, but the {{{ }}} pattern is fragile: any
     * future code path that passes raw model data to the template re-introduces XSS. Hardened approach: wrap the
     * address in a `<div class="multiline">` whose CSS uses `white-space: pre-line`, escape the inner text with `s()`
     * only. The new template needs no triple braces at all: it prints the lines of an address as separate rows.
     *
     * @param \stdClass $invoice a local_sentientia_cart_invoices row
     * @param string $pdfurl The PDF download URL of the new layout; '' for none.
     * @return string HTML
     */
    public static function render_html(\stdClass $invoice, string $pdfurl = ''): string {
        global $OUTPUT;
        if (!empty($invoice->place_of_supply)) {
            $data = doc_view::invoice($invoice) + [
                'pdf_url' => $pdfurl,
                'has_pdf' => $pdfurl !== '',
            ];
            return $OUTPUT->render_from_template('local_sentientia_cart/tax_document', $data);
        }
        return self::render_pre_gst_html($invoice);
    }

    /**
     * The invoice layout of before 1.2.0, unchanged, for an invoice that was issued under it.
     *
     * @param \stdClass $invoice
     * @return string HTML
     */
    private static function render_pre_gst_html(\stdClass $invoice): string {
        global $OUTPUT;
        $items = json_decode($invoice->line_items_json ?: '[]', true) ?: [];

        // Pre-build the address wrapper HTML. Inner text is escaped with s(); the wrapper itself is fixed HTML, no
        // interpolation. CSS `white-space: pre-line` makes `\n` render as a visual line break without ever emitting
        // a `<br/>` tag from user data.
        $wrap_multiline = static fn(string $text): string =>
            \html_writer::div(s($text), 'airpay-invoice-multiline',
                ['style' => 'white-space: pre-line;']);

        // Keep plain-text fields RAW - the Mustache `{{ }}` double-brace in the template does HTML-escape on output.
        // Pre-escaping in PHP would double-escape (entities become entity-of-entities).
        $data = [
            'invoice_number'  => $invoice->invoice_number,
            'invoice_date'    => userdate($invoice->timecreated, '%d %b %Y'),
            'company_name'    => (string) get_config('local_sentientia_cart', 'company_name'),
            // Pre-built HTML wrapper - template uses {{{ }}}.
            'company_address' => $wrap_multiline(
                (string) get_config('local_sentientia_cart', 'company_address')),
            'company_gstn'    => (string) get_config('local_sentientia_cart', 'our_gstn'),
            // billing_* are customer-supplied. Plain text - let Mustache {{ }} auto-escape. Don't pre-format.
            'billing_name'    => (string) $invoice->billing_name,
            'billing_email'   => (string) $invoice->billing_email,
            // Pre-built HTML wrapper - template uses {{{ }}}.
            'billing_address' => $wrap_multiline((string) $invoice->billing_address),
            'billing_gstn'    => (string) $invoice->billing_gstn,
            'items'           => array_map(fn($i) => [
                'name'    => (string) ($i['name'] ?? ''),
                'price'   => number_format($i['price'] ?? 0, 2),
                'currency_symbol' => self::currency_symbol($invoice->currency),
            ], $items),
            'subtotal'        => number_format($invoice->subtotal, 2),
            'cgst'            => number_format($invoice->cgst, 2),
            'sgst'            => number_format($invoice->sgst, 2),
            'igst'            => number_format($invoice->igst, 2),
            'has_cgst_sgst'   => $invoice->cgst > 0,
            'has_igst'        => $invoice->igst > 0,
            'total'           => number_format($invoice->total, 2),
            'currency_symbol' => self::currency_symbol($invoice->currency),
        ];
        return $OUTPUT->render_from_template('local_sentientia_cart/invoice', $data);
    }

    /**
     * Render an invoice imported from BizLMS: a REFERENCE, not an invoice. ERPNext issued the invoice when BizLMS
     * posted the order; Sentientia says so, shows the order and the total, and links nowhere (no link-out to
     * ERPNext until finance confirms the ERPNext invoices are the legal tax invoices).
     *
     * @param \stdClass $invoice a local_sentientia_cart_invoices row with status legacy_external
     * @return string HTML
     */
    public static function render_legacy_html(\stdClass $invoice): string {
        global $OUTPUT;
        $number = (string) $invoice->invoice_number;
        $prefix = 'ERPNEXT-';
        if (strncmp($number, $prefix, strlen($prefix)) === 0) {
            $number = substr($number, strlen($prefix));
        }
        $data = [
            'erpnext_number' => $number,
            'orderid'        => (int) $invoice->orderid,
            'invoice_date'   => userdate($invoice->timecreated, '%d %b %Y'),
            'total'          => number_format((float) $invoice->total, 2),
            'currency_symbol' => self::currency_symbol((string) $invoice->currency),
        ];
        return $OUTPUT->render_from_template('local_sentientia_cart/invoice_legacy', $data);
    }

    public static function currency_symbol(string $currency): string {
        return match (strtoupper($currency)) {
            'INR' => '₹',
            'USD' => '$',
            'EUR' => '€',
            'GBP' => '£',
            default => $currency . ' ',
        };
    }
}
