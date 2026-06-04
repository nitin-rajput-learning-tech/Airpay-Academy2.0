<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

/**
 * My Cart — current cart contents + checkout button.
 *
 * @package local_airpay_cart
 */

require_once(__DIR__ . '/../../config.php');
require_login();

global $USER, $DB, $OUTPUT, $PAGE;

$ctx = context_system::instance();
$PAGE->set_context($ctx);
$PAGE->set_url(new moodle_url('/local/airpay_cart/index.php'));
$PAGE->set_pagelayout('standard');
$PAGE->set_title(get_string('mycart', 'local_airpay_cart'));
$PAGE->set_heading(get_string('mycart', 'local_airpay_cart'));

require_capability('local/airpay_cart:view', $ctx);

if (!\local_airpay_cart\cart_manager::is_enabled_for_user($USER)) {
    redirect(new moodle_url('/my/dashboard.php'),
        'Cart is not enabled for your tenant.', null,
        \core\output\notification::NOTIFY_INFO);
}

$cart = \local_airpay_cart\cart_manager::get_or_open_cart((int) $USER->id);
$items = json_decode($cart->items_json ?: '[]', true) ?: [];

$symbol = \local_airpay_cart\invoicer::currency_symbol($cart->currency);

$rows = [];
foreach ($items as $item) {
    $rows[] = [
        'courseid'  => (int) $item['courseid'],
        'name'      => format_string($item['name'] ?? ''),
        'shortname' => format_string($item['shortname'] ?? ''),
        'price_str' => $symbol . number_format((float) ($item['price'] ?? 0), 2),
    ];
}

$data = [
    'has_items'      => count($rows) > 0,
    'items'          => $rows,
    'item_count'     => count($rows),
    'currency_symbol' => $symbol,
    'subtotal_str'   => $symbol . number_format((float) $cart->subtotal, 2),
    'tax_str'        => $symbol . number_format((float) $cart->tax_amount, 2),
    'total_str'      => $symbol . number_format((float) $cart->total_amount, 2),
    'checkout_url'   => (new moodle_url('/local/airpay_cart/checkout.php'))->out(false),
    'history_url'    => (new moodle_url('/local/airpay_cart/history.php'))->out(false),
    'catalog_url'    => (new moodle_url('/local/sentientia_catalog/index.php'))->out(false),
];

echo $OUTPUT->header();
echo $OUTPUT->render_from_template('local_airpay_cart/cart', $data);
echo $OUTPUT->footer();
