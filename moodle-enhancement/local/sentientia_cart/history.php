<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

/**
 * Order history — list of past orders for current user.
 *
 * @package local_sentientia_cart
 */

require_once(__DIR__ . '/../../config.php');
require_login();

global $USER, $OUTPUT, $PAGE;

$ctx = context_system::instance();
$PAGE->set_context($ctx);
$PAGE->set_url(new moodle_url('/local/sentientia_cart/history.php'));
$PAGE->set_pagelayout('standard');
$PAGE->set_title(get_string('orderhistory', 'local_sentientia_cart'));
$PAGE->set_heading(get_string('orderhistory', 'local_sentientia_cart'));
require_capability('local/sentientia_cart:view', $ctx);

// ADR-032 (2026-10-01): the # / Total / Status columns read keys (orderid_link, total_str, statuslabel) that
// list_orders never returned, so they rendered empty. They now read the keys it does return: orderid,
// placed_on, total_amount, status. (An order imported from BizLMS never appears here: it is admin-only history.)
// UI pass 2026-10-15: Placed is the payment date (list_orders::placed_time()), and the Total and Status columns show
// the values list_orders formats for the reader (the total with its currency symbol, the status in words) instead of
// the bare number and the raw key (partial_refund). Each still sorts by its own database column.
$columns = [
    ['key' => 'orderid',      'label' => '#',         'sortable' => true,  'sortkey' => 'orderid'],
    ['key' => 'placed_on',    'label' => 'Placed',    'sortable' => true,  'sortkey' => 'placed'],
    ['key' => 'total_str',    'label' => 'Total',     'sortable' => true,  'sortkey' => 'total_amount'],
    ['key' => 'status_label', 'label' => 'Status',    'sortable' => true,  'sortkey' => 'status'],
];
// GST receipts and tax documents (2026-10-14): a Documents column with the Receipt, Tax invoice and Credit note
// links of each order. It exists only while sentientia.cart.purchase_receipts or sentientia.cart.gst_tax_invoices is
// ON for the buyer's tenant; with both OFF the history is exactly what it was. The cell is escaped HTML built on the
// server (list_orders -> receipts::history_links()), which is what the datatable's html format is for.
if (\local_sentientia_cart\receipts::history_column_enabled($USER)) {
    $columns[] = ['key' => 'documents', 'label' => get_string('history_documents', 'local_sentientia_cart'),
        'sortable' => false, 'format' => 'html'];
}

$data = [
    // Bug fix 2026-05-22 (Goal A audit Bug #12 part 2): the wrapper
    // `s(json_encode(...))` double-escapes — Mustache's `{{ columns_json }}`
    // already HTML-escapes once on render, the browser auto-unescapes
    // once on dataset read; the extra `s()` makes JSON.parse() choke at
    // position 2 ("Expected property name or '}'"). Same shape as the
    // sentientia_request fix in commit 89fb2e713.
    'columns_json' => json_encode($columns),
    'is_admin'     => false,
    'back_url'     => (new moodle_url('/local/sentientia_cart/index.php'))->out(false),
];

echo $OUTPUT->header();
echo $OUTPUT->render_from_template('local_sentientia_cart/history', $data);
echo $OUTPUT->footer();
