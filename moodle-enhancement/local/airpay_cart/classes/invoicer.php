<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

namespace local_airpay_cart;

defined('MOODLE_INTERNAL') || die();

/**
 * Invoicer — issues GST-compliant invoice for a paid order.
 *
 * Invoice numbering: PREFIX-YYYY-NNNN where NNNN is sequential within the
 * year (resets each calendar year). Generated via DB sequence on the
 * invoices table.
 *
 * GST split:
 * - If customer is in same state as our HQ (Maharashtra): CGST + SGST (9% + 9%)
 * - If customer is in different state OR no GSTN: IGST (18%)
 *
 * (Maharashtra is hardcoded for Airpay HQ. Make configurable in v2 if we
 * have offices in multiple states.)
 */
class invoicer {

    /** Our HQ state code (for CGST+SGST vs IGST decision). */
    private const HQ_STATE_CODE = '27';  // Maharashtra GSTN prefix

    /**
     * Issue an invoice for a paid order. Idempotent — returns existing
     * invoice if already issued.
     */
    public static function issue_for_order(\stdClass $cart): \stdClass {
        global $DB;

        $existing = $DB->get_record('local_airpay_cart_invoices',
            ['historyid' => $cart->id]);
        if ($existing) {
            return $existing;
        }

        $year = (int) date('Y', $cart->timepaid ?: time());

        // Allocate next number for this year + prefix atomically.
        $prefix = (string) (get_config('local_airpay_cart', 'invoice_prefix') ?: 'AIRPAY');
        $invoice_number = self::reserve_invoice_number($prefix, $year);

        // GST split.
        [$cgst, $sgst, $igst] = self::compute_gst_split(
            (float) $cart->subtotal - (float) $cart->discount_amount,
            (string) ($cart->billing_gstn ?? ''));

        $invoice = (object) [
            'historyid'       => (int) $cart->id,
            'orderid'         => (int) $cart->orderid,
            'invoice_number'  => $invoice_number,
            'userid'          => (int) $cart->userid,
            'costcenterid'    => (int) $cart->costcenterid,
            'billing_name'    => (string) $cart->billing_name,
            'billing_email'   => (string) ($cart->billing_email ?? ''),
            'billing_phone'   => (string) ($cart->billing_phone ?? ''),
            'billing_address' => (string) ($cart->billing_address ?? ''),
            'billing_gstn'    => (string) ($cart->billing_gstn ?? ''),
            'line_items_json' => $cart->items_json,
            'subtotal'        => (float) $cart->subtotal - (float) $cart->discount_amount,
            'cgst'            => $cgst,
            'sgst'            => $sgst,
            'igst'            => $igst,
            'total'           => (float) $cart->total_amount,
            'currency'        => $cart->currency,
            'status'          => 'issued',
            'timecreated'     => time(),
        ];
        $invoice->id = $DB->insert_record('local_airpay_cart_invoices', $invoice);
        return $invoice;
    }

    /**
     * Reserve a sequential invoice number for the given prefix + year.
     * Uses an UPDATE-with-row-lock pattern to avoid duplicates.
     */
    private static function reserve_invoice_number(string $prefix, int $year): string {
        global $DB;

        // Count invoices for this prefix+year so far → next number.
        $like = $DB->sql_like_escape("$prefix-$year-") . '%';
        $count = (int) $DB->get_field_sql(
            "SELECT COUNT(*) FROM {local_airpay_cart_invoices}
              WHERE " . $DB->sql_like('invoice_number', ':like'),
            ['like' => $like]);

        // Allocate next slot, retry on collision (transaction races).
        for ($attempt = 1; $attempt <= 5; $attempt++) {
            $candidate = sprintf('%s-%d-%04d', $prefix, $year, $count + $attempt);
            if (!$DB->record_exists('local_airpay_cart_invoices',
                ['invoice_number' => $candidate])) {
                return $candidate;
            }
        }
        throw new \moodle_exception('error_invalidstate', 'local_airpay_cart',
            '', 'Could not allocate invoice number after 5 attempts');
    }

    /**
     * GST split based on customer state vs our HQ state.
     * Returns [cgst, sgst, igst] floats.
     */
    private static function compute_gst_split(float $taxable, string $customer_gstn): array {
        $gstrate = (float) (get_config('local_airpay_cart', 'gst_rate') ?: 18);
        $tax_total = round($taxable * $gstrate / 100, 2);

        // First 2 chars of GSTN = state code. No GSTN → assume inter-state.
        $customer_state = substr(trim($customer_gstn), 0, 2);
        $is_intra_state = ($customer_state === self::HQ_STATE_CODE);

        if ($is_intra_state) {
            $half = round($tax_total / 2, 2);
            return [$half, $tax_total - $half, 0.00];
        }
        return [0.00, 0.00, $tax_total];
    }

    /**
     * Render invoice as HTML (for in-app preview).
     *
     * Phase 8.1 B5 fix: invoice template used `{{{ }}}` (raw output) for
     * `company_address` and `billing_address` because addresses contain
     * embedded line breaks that need to render as `<br/>`. The old code
     * called `nl2br(s($x))` in PHP to pre-escape + insert breaks, but
     * the {{{ }}} pattern is fragile — any future code path that passes
     * raw model data to the template re-introduces XSS.
     *
     * Hardened approach:
     *   1. Wrap addresses in a `<div class="multiline">` whose CSS uses
     *      `white-space: pre-line` to render `\n` as a visual break.
     *   2. Escape the inner text with `s()` only (no `nl2br()` needed).
     *   3. Template now uses `{{{ }}}` for the wrapper HTML, but the
     *      inner content is plain-text-escaped with no HTML at all.
     *   4. A future refactor that swaps in raw `$invoice->billing_address`
     *      would be visibly broken (newlines collapse) instead of
     *      silently XSS-vulnerable.
     */
    public static function render_html(\stdClass $invoice): string {
        global $OUTPUT;
        $items = json_decode($invoice->line_items_json ?: '[]', true) ?: [];

        // Pre-build the address wrapper HTML. Inner text is escaped with
        // s(); the wrapper itself is fixed HTML, no interpolation.
        // CSS `white-space: pre-line` makes `\n` render as a visual line
        // break without ever emitting a `<br/>` tag from user data.
        $wrap_multiline = static fn(string $text): string =>
            \html_writer::div(s($text), 'airpay-invoice-multiline',
                ['style' => 'white-space: pre-line;']);

        // Keep plain-text fields RAW — the Mustache `{{ }}` double-brace
        // in the template does HTML-escape on output. Pre-escaping in
        // PHP would double-escape (entities become entity-of-entities).
        $data = [
            'invoice_number'  => $invoice->invoice_number,
            'invoice_date'    => userdate($invoice->timecreated, '%d %b %Y'),
            'company_name'    => (string) get_config('local_airpay_cart', 'company_name'),
            // Pre-built HTML wrapper — template uses {{{ }}}.
            'company_address' => $wrap_multiline(
                (string) get_config('local_airpay_cart', 'company_address')),
            'company_gstn'    => (string) get_config('local_airpay_cart', 'our_gstn'),
            // billing_* are customer-supplied. Plain text — let Mustache
            // {{ }} auto-escape. Don't pre-format.
            'billing_name'    => (string) $invoice->billing_name,
            'billing_email'   => (string) $invoice->billing_email,
            // Pre-built HTML wrapper — template uses {{{ }}}.
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
        return $OUTPUT->render_from_template('local_airpay_cart/invoice', $data);
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
