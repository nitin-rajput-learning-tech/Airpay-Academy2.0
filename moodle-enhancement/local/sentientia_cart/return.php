<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

/**
 * Return URL — user lands here after gateway interaction.
 *
 * Shows the order outcome. We DO NOT mark paid here (that's the webhook's
 * job — return.php is just the user-facing thank-you). On the off-chance
 * the webhook hasn't fired yet, we show "pending — refresh in a moment".
 *
 * @package local_sentientia_cart
 */

require_once(__DIR__ . '/../../config.php');
require_login();

global $USER, $DB, $OUTPUT, $PAGE;

$orderid = required_param('orderid', PARAM_INT);
$failed  = optional_param('fail', 0, PARAM_INT);
$manual  = optional_param('manual', 0, PARAM_INT);

$cart = $DB->get_record('local_sentientia_cart_history',
    ['orderid' => $orderid], '*', MUST_EXIST);
if ((int) $cart->userid !== (int) $USER->id) {
    throw new \moodle_exception('error_outoftenant', 'local_sentientia_cart');
}

$ctx = context_system::instance();
$PAGE->set_context($ctx);
$PAGE->set_url(new moodle_url('/local/sentientia_cart/return.php', ['orderid' => $orderid]));
$PAGE->set_pagelayout('standard');
$PAGE->set_title(get_string('orderconfirmation', 'local_sentientia_cart'));
$PAGE->set_heading(get_string('ordernumber', 'local_sentientia_cart', $orderid));

$status_label = get_string('status_' . $cart->status, 'local_sentientia_cart');
$is_paid    = $cart->status === 'paid';
$is_pending = $cart->status === 'pending';
$is_failed  = $cart->status === 'failed' || $failed;

$invoice = null;
if ($is_paid) {
    $invoice = $DB->get_record('local_sentientia_cart_invoices',
        ['historyid' => $cart->id]);
}

$data = [
    'orderid'        => (int) $cart->orderid,
    'status'         => $cart->status,
    'status_label'   => $status_label,
    'is_paid'        => $is_paid,
    'is_pending'     => $is_pending,
    'is_failed'      => $is_failed,
    'is_manual'      => (bool) $manual,
    'total_str'      => \local_sentientia_cart\invoicer::currency_symbol($cart->currency)
                      . number_format((float) $cart->total_amount, 2),
    'invoice_url'    => $invoice
        ? (new moodle_url('/local/sentientia_cart/invoice.php',
            ['id' => $invoice->id]))->out(false)
        : '',
    'history_url'    => (new moodle_url('/local/sentientia_cart/history.php'))->out(false),
    'catalog_url'    => (new moodle_url('/local/sentientia_catalog/index.php'))->out(false),
];

echo $OUTPUT->header();
echo $OUTPUT->render_from_template('local_sentientia_cart/return', $data);
echo $OUTPUT->footer();
