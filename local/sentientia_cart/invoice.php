<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

/**
 * Render a GST tax invoice as HTML (browser print = PDF via Ctrl+P), or download it as a PDF (&download=pdf).
 *
 * Access: the owner (not while the invoice awaits its e-invoice IRN), or a :viewallorders / :manageinvoices holder
 * in the invoice's tenant (ADR-031). An invoice imported from BizLMS is an ERPNext reference, and an invoice from
 * before 1.2.0 keeps its old layout; neither has a PDF.
 *
 * @package local_sentientia_cart
 */

require_once(__DIR__ . '/../../config.php');
require_login();

global $USER, $DB, $OUTPUT, $PAGE;

$id = required_param('id', PARAM_INT);
$download = optional_param('download', '', PARAM_ALPHA);
$invoice = $DB->get_record('local_sentientia_cart_invoices',
    ['id' => $id], '*', MUST_EXIST);

// Owner, or a :viewallorders holder in the invoice's tenant (ADR-031).
$ctx = context_system::instance();
\local_sentientia_cart\invoicer::require_view_access($invoice, (int) $USER->id);

$islegacy = $invoice->status === \local_sentientia_cart\imported_history::INVOICE_STATUS;
$hasgstlayout = !$islegacy && !empty($invoice->place_of_supply);

if ($download === 'pdf') {
    if (!$hasgstlayout) {
        throw new \moodle_exception('error_notavailable', 'local_sentientia_cart');
    }
    $pdf = \local_sentientia_cart\pdf_renderer::invoice($invoice);
    send_file($pdf, \local_sentientia_cart\pdf_renderer::filename('invoice', (string) $invoice->invoice_number),
        0, 0, true, true, 'application/pdf');
}

$PAGE->set_context($ctx);
$PAGE->set_url(new moodle_url('/local/sentientia_cart/invoice.php', ['id' => $id]));
$PAGE->set_pagelayout('embedded');
$title = $hasgstlayout
    ? get_string('doc_taxinvoice', 'local_sentientia_cart') . ' ' . $invoice->invoice_number
    : 'Invoice ' . $invoice->invoice_number;
$PAGE->set_title($title);
$PAGE->set_heading($title);

echo $OUTPUT->header();
if ($islegacy) {
    // ADR-032: an invoice imported from BizLMS is a reference to the ERPNext invoice, not a Sentientia invoice.
    echo \local_sentientia_cart\invoicer::render_legacy_html($invoice);
} else {
    $pdfurl = $hasgstlayout
        ? (new moodle_url('/local/sentientia_cart/invoice.php', ['id' => $id, 'download' => 'pdf']))->out(false) : '';
    echo \local_sentientia_cart\invoicer::render_html($invoice, $pdfurl);
}
echo $OUTPUT->footer();
