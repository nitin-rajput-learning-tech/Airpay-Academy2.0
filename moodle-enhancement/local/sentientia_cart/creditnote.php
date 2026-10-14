<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

/**
 * Render a GST credit note as HTML (browser print = PDF via Ctrl+P), or download it as a PDF (&download=pdf).
 *
 * Access: the owner (not while the credit note awaits its e-invoice IRN), or a :viewallorders / :manageinvoices holder
 * in the credit note's tenant (ADR-031). A credit note that is only due is not a document yet and cannot be opened.
 *
 * @package local_sentientia_cart
 */

require_once(__DIR__ . '/../../config.php');
require_login();

global $USER, $DB, $OUTPUT, $PAGE;

$id = required_param('id', PARAM_INT);
$download = optional_param('download', '', PARAM_ALPHA);
$cn = $DB->get_record('local_sentientia_cart_creditnotes', ['id' => $id], '*', MUST_EXIST);

$ctx = context_system::instance();
\local_sentientia_cart\credit_notes::require_view_access($cn, (int) $USER->id);

if ($download === 'pdf') {
    $pdf = \local_sentientia_cart\pdf_renderer::credit_note($cn);
    send_file($pdf, \local_sentientia_cart\pdf_renderer::filename('credit-note', (string) $cn->cn_number),
        0, 0, true, true, 'application/pdf');
}

$PAGE->set_context($ctx);
$PAGE->set_url(new moodle_url('/local/sentientia_cart/creditnote.php', ['id' => $id]));
$PAGE->set_pagelayout('embedded');
$title = get_string('doc_creditnote', 'local_sentientia_cart') . ' ' . $cn->cn_number;
$PAGE->set_title($title);
$PAGE->set_heading($title);

$pdfurl = (new moodle_url('/local/sentientia_cart/creditnote.php', ['id' => $id, 'download' => 'pdf']))->out(false);

echo $OUTPUT->header();
echo $OUTPUT->render_from_template('local_sentientia_cart/tax_document',
    \local_sentientia_cart\doc_view::credit_note($cn) + ['pdf_url' => $pdfurl, 'has_pdf' => true]);
echo $OUTPUT->footer();
