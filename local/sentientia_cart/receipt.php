<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

/**
 * The buyer's payment receipt (receipt.php?id=<history id>), as a page or a PDF (&download=pdf).
 *
 * Behind the flag sentientia.cart.purchase_receipts (for the order's tenant). Access: the order's owner, or a
 * :viewallorders holder inside the order's tenant (ADR-031); an order imported from BizLMS has no receipt. It says
 * it is a payment receipt, not a tax invoice, and points to the tax invoice when one is issued.
 *
 * @package local_sentientia_cart
 */

require_once(__DIR__ . '/../../config.php');
require_login();

global $USER, $DB, $OUTPUT, $PAGE;

$id = required_param('id', PARAM_INT);
$download = optional_param('download', '', PARAM_ALPHA);
$order = $DB->get_record('local_sentientia_cart_history', ['id' => $id], '*', MUST_EXIST);

$ctx = context_system::instance();
\local_sentientia_cart\receipts::require_view_access($order, (int) $USER->id);

if ($download === 'pdf') {
    $pdf = \local_sentientia_cart\pdf_renderer::receipt($order);
    send_file($pdf, \local_sentientia_cart\pdf_renderer::filename('receipt', (string) $order->orderid),
        0, 0, true, true, 'application/pdf');
}

$PAGE->set_context($ctx);
$PAGE->set_url(\local_sentientia_cart\receipts::url($id));
$PAGE->set_pagelayout('standard');
$title = get_string('receipt_page_title', 'local_sentientia_cart', (int) $order->orderid);
$PAGE->set_title($title);
$PAGE->set_heading($title);

echo $OUTPUT->header();
echo $OUTPUT->render_from_template('local_sentientia_cart/receipt',
    \local_sentientia_cart\receipts::context($order) + [
        'pdf_url' => \local_sentientia_cart\receipts::url($id, true)->out(false),
    ]);
echo $OUTPUT->footer();
