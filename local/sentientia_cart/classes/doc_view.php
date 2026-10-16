<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

namespace local_sentientia_cart;

defined('MOODLE_INTERNAL') || die();

/**
 * The data of a tax invoice, a credit note or a payment receipt, ready for a template (spec 2026-10-14, section 8).
 *
 * One builder feeds both outputs: the HTML page (invoice.php, creditnote.php, receipt.php, in the viewer's language)
 * and the PDF (pdf_renderer, always in English: TCPDF cannot shape Devanagari conjuncts, and the legal document is
 * English). So the page and the PDF cannot disagree on a figure.
 *
 * Every word on a document is a lang string, fetched here per language into `l` (the templates print {{ l.xxx }}),
 * so no English is hard-coded in a template. Names and numbers come from the document row: nothing is recomputed
 * from today's settings. The one exception is the supplier block, which comes from the snapshot the document took
 * at issue (supplier_json) and only falls back to the settings for a row that has none.
 *
 * Every plain-text field is left raw for the template's {{ }} to escape: nothing here is HTML.
 *
 * @package    local_sentientia_cart
 * @copyright  2026 Airpay Payment Services
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class doc_view {

    /** @var string[] The lang strings a document prints, without their prefix. */
    private const DOC_LABELS = [
        'taxinvoice', 'creditnote', 'invoiceno', 'creditnoteno', 'date', 'supplier', 'buyer', 'gstin', 'state',
        'placeofsupply', 'srno', 'description', 'sac', 'taxable', 'rate', 'cgst', 'sgst', 'igst', 'total',
        'totaltaxable', 'totaltax', 'amountinwords', 'reversecharge', 'no', 'irn', 'ackno', 'ackdate', 'qralt',
        'computergenerated_invoice', 'computergenerated_creditnote', 'againstinvoice', 'dated', 'reason', 'print',
        'downloadpdf', 'pendingirn', 'email', 'phone', 'grandtotal', 'tax',
    ];

    /**
     * Lang strings of this plugin in one language.
     *
     * @param string[] $ids The string ids.
     * @param string $prefix Prepended to each id to make the lang string id ('doc_', 'receipt_').
     * @param string|null $lang A language code; null for the current language.
     * @return array<string, string> id (without the prefix) => text
     */
    public static function labels(array $ids, string $prefix, ?string $lang = null): array {
        $sm = get_string_manager();
        $out = [];
        foreach ($ids as $id) {
            $out[$id] = $sm->get_string($prefix . $id, 'local_sentientia_cart', null, $lang);
        }
        return $out;
    }

    /**
     * A date, in Indian time: "14 Oct 2026". English month names when the language is English (the PDF), else the
     * viewer's language.
     *
     * @param int $timestamp
     * @param string|null $lang
     * @param bool $withtime Add the time, "14 Oct 2026, 15:42 IST".
     * @return string
     */
    public static function date(int $timestamp, ?string $lang = null, bool $withtime = false): string {
        if ($timestamp <= 0) {
            return '';
        }
        $tz = new \DateTimeZone(docseq::TIMEZONE);
        if ($lang === 'en') {
            $date = (new \DateTimeImmutable('@' . $timestamp))->setTimezone($tz);
            return $date->format($withtime ? 'd M Y, H:i' : 'd M Y') . ($withtime ? ' IST' : '');
        }
        $text = userdate($timestamp, $withtime ? '%d %b %Y, %H:%M' : '%d %b %Y', docseq::TIMEZONE);
        return $text . ($withtime ? ' IST' : '');
    }

    /**
     * Lines of a multi-line text as template rows, so no HTML is needed to keep the line breaks.
     *
     * @param string $text
     * @return array[] each ['line' => string]
     */
    public static function lines_of(string $text): array {
        $rows = [];
        foreach (preg_split('/\R/', trim($text)) ?: [] as $line) {
            $line = trim($line);
            if ($line !== '') {
                $rows[] = ['line' => $line];
            }
        }
        return $rows;
    }

    /**
     * The supplier as printed on a document, frozen at issue: name, address, GSTIN, state and signatory line.
     *
     * @return string JSON for supplier_json
     */
    public static function supplier_snapshot(): string {
        return json_encode([
            'name'      => gst_settings::company_name(),
            'address'   => gst_settings::company_address(),
            'gstin'     => gst_settings::our_gstn(),
            'state'     => gst_settings::supplier_state(),
            'signatory' => gst_settings::signatory_text('en'),
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE);
    }

    /**
     * The supplier of a document: its snapshot, else (a row without one) the current settings.
     *
     * @param \stdClass $doc An invoice or credit note row.
     * @param string|null $lang
     * @return array name, address (text), gstin, state (code), signatory
     */
    private static function supplier_of(\stdClass $doc, ?string $lang): array {
        $snap = json_decode((string) ($doc->supplier_json ?? ''), true);
        if (is_array($snap) && !empty($snap['name'])) {
            return [
                'name'      => (string) $snap['name'],
                'address'   => (string) ($snap['address'] ?? ''),
                'gstin'     => (string) ($snap['gstin'] ?? ''),
                'state'     => (string) ($snap['state'] ?? ''),
                'signatory' => (string) ($snap['signatory'] ?? gst_settings::signatory_text($lang)),
            ];
        }
        return [
            'name'      => gst_settings::company_name(),
            'address'   => gst_settings::company_address(),
            'gstin'     => gst_settings::our_gstn(),
            'state'     => gst_settings::supplier_state(),
            'signatory' => gst_settings::signatory_text($lang),
        ];
    }

    /**
     * The QR of a signed e-invoice as an inline image, rendered with core_qrcode.
     *
     * @param string $signedqr The signed QR string recorded from the IRP.
     * @return string A data: URI of an SVG, or '' when there is nothing to draw.
     */
    public static function qr_data_uri(string $signedqr): string {
        if ($signedqr === '') {
            return '';
        }
        try {
            $qr = new \core_qrcode($signedqr);
            return 'data:image/svg+xml;base64,' . base64_encode($qr->getBarcodeSVGcode(3, 3, 'black'));
        } catch (\Throwable $e) {
            debugging('local_sentientia_cart: could not draw the e-invoice QR code: ' . $e->getMessage(), DEBUG_DEVELOPER);
            return '';
        }
    }

    /**
     * The fields every tax document shares.
     *
     * @param \stdClass $doc An invoice or credit note row.
     * @param string $number The document number.
     * @param array $figures taxable, cgst, sgst, igst, total (floats) of the whole document.
     * @param array[] $lines Rows: name, sac, taxable, rate, cgst, sgst, igst (floats).
     * @param bool $iscreditnote
     * @param string|null $lang
     * @return array
     */
    private static function common(\stdClass $doc, string $number, array $figures, array $lines,
            bool $iscreditnote, ?string $lang): array {
        $currency = (string) $doc->currency;
        $symbol = invoicer::currency_symbol($currency);
        $fmt = static fn($v): string => $symbol . gst::money($v, $currency);

        $supplier = self::supplier_of($doc, $lang);
        $pos = (string) ($doc->place_of_supply ?? '');
        $intrastate = $pos !== '' && $pos === $supplier['state'];
        $buyerstate = (string) ($doc->billing_state ?: $pos);
        $rate = (float) ($doc->gst_rate ?? 0);

        $rows = [];
        foreach (array_values($lines) as $i => $l) {
            $rows[] = [
                'n'       => $i + 1,
                'name'    => (string) $l['name'],
                'sac'     => (string) ($l['sac'] ?? ''),
                'taxable' => $fmt($l['taxable']),
                'rate'    => rtrim(rtrim(number_format((float) ($l['rate'] ?? $rate), 2, '.', ''), '0'), '.') . '%',
                'cgst'    => $fmt($l['cgst']),
                'sgst'    => $fmt($l['sgst']),
                'igst'    => $fmt($l['igst']),
            ];
        }

        $hasirn = !empty($doc->irn);
        return [
            'l'               => self::labels(self::DOC_LABELS, 'doc_', $lang),
            'is_creditnote'   => $iscreditnote,
            'title'           => get_string_manager()->get_string($iscreditnote ? 'doc_creditnote' : 'doc_taxinvoice',
                'local_sentientia_cart', null, $lang),
            'number_label'    => get_string_manager()->get_string($iscreditnote ? 'doc_creditnoteno' : 'doc_invoiceno',
                'local_sentientia_cart', null, $lang),
            'number'          => $number,
            'date'            => self::date((int) $doc->timecreated, $lang),
            'supplier_name'   => $supplier['name'],
            'supplier_address_lines' => self::lines_of($supplier['address']),
            'supplier_gstin'  => $supplier['gstin'],
            'supplier_state'  => $supplier['state'] !== '' ? gst_states::label($supplier['state'], $lang) : '',
            'buyer_name'      => (string) $doc->billing_name,
            'buyer_email'     => (string) ($doc->billing_email ?? ''),
            'buyer_address_lines' => self::lines_of((string) ($doc->billing_address ?? '')),
            'buyer_gstin'     => (string) ($doc->billing_gstn ?? ''),
            'buyer_state'     => $buyerstate !== '' ? gst_states::label($buyerstate, $lang) : '',
            'place_of_supply' => $pos !== '' ? gst_states::label($pos, $lang) : '',
            'lines'           => $rows,
            'show_cgst_sgst'  => $intrastate,
            'show_igst'       => !$intrastate,
            'total_taxable'   => $fmt($figures['taxable']),
            'total_cgst'      => $fmt($figures['cgst']),
            'total_sgst'      => $fmt($figures['sgst']),
            'total_igst'      => $fmt($figures['igst']),
            'total_tax'       => $fmt($figures['cgst'] + $figures['sgst'] + $figures['igst']),
            'total'           => $fmt($figures['total']),
            'total_words'     => amount_in_words::from_amount($figures['total']),
            'has_irn'         => $hasirn,
            'irn'             => (string) ($doc->irn ?? ''),
            'ack_no'          => (string) ($doc->ack_no ?? ''),
            'ack_date'        => self::date((int) ($doc->ack_date ?? 0), $lang, true),
            'signed_qr'       => (string) ($doc->signed_qr ?? ''),
            'qr_src'          => $hasirn ? self::qr_data_uri((string) ($doc->signed_qr ?? '')) : '',
            'signatory_company' => $supplier['name'],
            'signatory_for'   => get_string_manager()->get_string('doc_for', 'local_sentientia_cart',
                $supplier['name'], $lang),
            'signatory_text'  => $supplier['signatory'],
            'awaiting_irn'    => ($doc->status ?? '') === invoicer::STATUS_AWAITING_IRN,
        ];
    }

    /**
     * The data of a tax invoice.
     *
     * @param \stdClass $invoice A local_sentientia_cart_invoices row issued under the GST layout (place_of_supply set).
     * @param string|null $lang Language of the words; null for the viewer's, 'en' for the PDF.
     * @return array
     */
    public static function invoice(\stdClass $invoice, ?string $lang = null): array {
        $lines = json_decode((string) $invoice->line_items_json, true) ?: [];
        return self::common($invoice, (string) $invoice->invoice_number, [
            'taxable' => (float) $invoice->subtotal,
            'cgst'    => (float) $invoice->cgst,
            'sgst'    => (float) $invoice->sgst,
            'igst'    => (float) $invoice->igst,
            'total'   => (float) $invoice->total,
        ], $lines, false, $lang) + [
            'reverse_charge' => get_string_manager()->get_string('doc_no', 'local_sentientia_cart', null, $lang),
            'computer_generated' => get_string_manager()->get_string('doc_computergenerated_invoice',
                'local_sentientia_cart', null, $lang),
        ];
    }

    /**
     * The data of a credit note: the same layout, with the original invoice's number and date and the reason.
     *
     * @param \stdClass $cn A local_sentientia_cart_creditnotes row.
     * @param string|null $lang
     * @return array
     */
    public static function credit_note(\stdClass $cn, ?string $lang = null): array {
        global $DB;
        $invoice = $DB->get_record('local_sentientia_cart_invoices', ['id' => $cn->invoiceid], '*', MUST_EXIST);
        $order = $DB->get_record('local_sentientia_cart_history', ['id' => $cn->historyid], 'id, orderid', MUST_EXIST);
        $rate = (float) ($cn->gst_rate ?? 0);
        $lines = [[
            'name'    => get_string_manager()->get_string('doc_creditline', 'local_sentientia_cart',
                (int) $order->orderid, $lang),
            'sac'     => (string) ($cn->sac_code ?? ''),
            'taxable' => (float) $cn->taxable,
            'rate'    => $rate,
            'cgst'    => (float) $cn->cgst,
            'sgst'    => (float) $cn->sgst,
            'igst'    => (float) $cn->igst,
        ]];
        return self::common($cn, (string) $cn->cn_number, [
            'taxable' => (float) $cn->taxable,
            'cgst'    => (float) $cn->cgst,
            'sgst'    => (float) $cn->sgst,
            'igst'    => (float) $cn->igst,
            'total'   => (float) $cn->total,
        ], $lines, true, $lang) + [
            'original_number' => (string) $invoice->invoice_number,
            'original_date'   => self::date((int) $invoice->timecreated, $lang),
            'reason'          => trim((string) ($cn->reason ?? '')),
            'has_reason'      => trim((string) ($cn->reason ?? '')) !== '',
            'reverse_charge'  => get_string_manager()->get_string('doc_no', 'local_sentientia_cart', null, $lang),
            'computer_generated' => get_string_manager()->get_string('doc_computergenerated_creditnote',
                'local_sentientia_cart', null, $lang),
        ];
    }
}
