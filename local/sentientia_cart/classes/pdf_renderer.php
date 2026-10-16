<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

namespace local_sentientia_cart;

defined('MOODLE_INTERNAL') || die();

/**
 * PDFs of the tax invoice, the credit note and the payment receipt (spec 2026-10-14, section 8).
 *
 * Moodle's pdf class (lib/pdflib.php, TCPDF), A4. The PDF is built from the SAME data as the HTML page
 * (doc_view, receipts::context()), so the two cannot disagree on a figure.
 *
 * The words of a PDF are always English, whatever the viewer's language: TCPDF does not shape Devanagari
 * (conjuncts and vowel signs come out in the wrong place), and the tax document is the legal text. The rupee sign
 * and the buyer's own text are drawn with FreeSans, which carries both.
 *
 * A signed e-invoice QR is drawn with TCPDF's own 2D barcode writer, the same engine core_qrcode wraps.
 *
 * @package    local_sentientia_cart
 * @copyright  2026 Airpay Payment Services
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class pdf_renderer {

    /** The language of every PDF. */
    public const LANG = 'en';

    /**
     * The PDF of a tax invoice issued under the GST layout.
     *
     * @param \stdClass $invoice
     * @return string The PDF file's bytes.
     */
    public static function invoice(\stdClass $invoice): string {
        $ctx = doc_view::invoice($invoice, self::LANG);
        return self::build('local_sentientia_cart/tax_document_pdf', $ctx, $ctx['title'] . ' ' . $ctx['number'],
            (string) ($ctx['signed_qr'] ?? ''));
    }

    /**
     * The PDF of a credit note.
     *
     * @param \stdClass $cn
     * @return string
     */
    public static function credit_note(\stdClass $cn): string {
        $ctx = doc_view::credit_note($cn, self::LANG);
        return self::build('local_sentientia_cart/tax_document_pdf', $ctx, $ctx['title'] . ' ' . $ctx['number'],
            (string) ($ctx['signed_qr'] ?? ''));
    }

    /**
     * The PDF of a payment receipt.
     *
     * @param \stdClass $order
     * @return string
     */
    public static function receipt(\stdClass $order): string {
        $ctx = receipts::context($order, self::LANG);
        return self::build('local_sentientia_cart/receipt_pdf', $ctx, $ctx['l']['title'] . ' #' . $ctx['orderid'], '');
    }

    /**
     * A file name for a download: the document number with anything unsafe removed.
     *
     * @param string $stem "invoice", "credit-note" or "receipt"
     * @param string $number The document number or order number.
     * @return string
     */
    public static function filename(string $stem, string $number): string {
        return $stem . '-' . preg_replace('/[^A-Za-z0-9._-]+/', '_', $number) . '.pdf';
    }

    /**
     * Render a template to HTML and lay it out on A4.
     *
     * @param string $template The Mustache template.
     * @param array $ctx Its data.
     * @param string $title The PDF's title property.
     * @param string $qr A signed QR string to draw under the content, or ''.
     * @return string The PDF file's bytes.
     */
    private static function build(string $template, array $ctx, string $title, string $qr): string {
        global $CFG, $OUTPUT;
        require_once($CFG->libdir . '/pdflib.php');

        $html = $OUTPUT->render_from_template($template, $ctx);

        $pdf = new \pdf('P', 'mm', 'A4', true, 'UTF-8');
        $pdf->SetCreator('Sentientia LMS');
        $pdf->SetTitle($title);
        $pdf->setPrintHeader(false);
        $pdf->setPrintFooter(false);
        $pdf->SetMargins(12, 12, 12);
        $pdf->SetAutoPageBreak(true, 12);
        $pdf->SetFont('freesans', '', 9);
        $pdf->AddPage();
        $pdf->writeHTML($html, true, false, true, false, '');

        if ($qr !== '') {
            $y = $pdf->GetY() + 2;
            if ($y + 42 > $pdf->getPageHeight() - 12) {
                $pdf->AddPage();
                $y = 12;
            }
            $pdf->write2DBarcode($qr, 'QRCODE,L', 12, $y, 40, 40,
                ['border' => false, 'padding' => 0, 'fgcolor' => [0, 0, 0], 'bgcolor' => false], 'N');
        }
        return $pdf->Output('document.pdf', 'S');
    }
}
