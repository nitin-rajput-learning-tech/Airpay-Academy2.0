<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

/**
 * Render a GST-compliant invoice as HTML (browser print = PDF via Ctrl+P).
 *
 * @package local_sentientia_cart
 */

require_once(__DIR__ . '/../../config.php');
require_login();

global $USER, $DB, $OUTPUT, $PAGE;

$id = required_param('id', PARAM_INT);
$invoice = $DB->get_record('local_sentientia_cart_invoices',
    ['id' => $id], '*', MUST_EXIST);

// Owner, or a :viewallorders holder in the invoice's tenant (ADR-031).
$ctx = context_system::instance();
\local_sentientia_cart\invoicer::require_view_access($invoice, (int) $USER->id);

$PAGE->set_context($ctx);
$PAGE->set_url(new moodle_url('/local/sentientia_cart/invoice.php', ['id' => $id]));
$PAGE->set_pagelayout('embedded');
$PAGE->set_title('Invoice ' . $invoice->invoice_number);
$PAGE->set_heading('Invoice ' . $invoice->invoice_number);

echo $OUTPUT->header();
echo \local_sentientia_cart\invoicer::render_html($invoice);
echo $OUTPUT->footer();
