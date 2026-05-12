<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

/**
 * Checkout — collect billing details, select gateway, place order.
 *
 * On submit:
 * 1. Validate cart not empty
 * 2. Call cart_manager::checkout → cart goes to 'pending'
 * 3. Call gateway → get redirect URL + params
 * 4. Render auto-submit form to gateway (POST) OR redirect (GET)
 *
 * @package local_airpay_cart
 */

require_once(__DIR__ . '/../../config.php');
require_login();

global $USER, $DB, $OUTPUT, $PAGE;

$ctx = context_system::instance();
$PAGE->set_context($ctx);
$PAGE->set_url(new moodle_url('/local/airpay_cart/checkout.php'));
$PAGE->set_pagelayout('standard');
$PAGE->set_title(get_string('checkout', 'local_airpay_cart'));
$PAGE->set_heading(get_string('checkout', 'local_airpay_cart'));

require_capability('local/airpay_cart:purchase', $ctx);

if (!\local_airpay_cart\cart_manager::is_enabled_for_user($USER)) {
    throw new \moodle_exception('error_courseunavailable', 'local_airpay_cart');
}

$cart = \local_airpay_cart\cart_manager::get_or_open_cart((int) $USER->id);
$items = json_decode($cart->items_json ?: '[]', true) ?: [];
if (empty($items)) {
    redirect(new moodle_url('/local/airpay_cart/index.php'),
        get_string('error_emptycart', 'local_airpay_cart'),
        null, \core\output\notification::NOTIFY_WARNING);
}

// Handle POST (place order).
if (data_submitted() && confirm_sesskey()) {
    $billing = [
        'billing_name'    => required_param('billing_name', PARAM_TEXT),
        'billing_email'   => required_param('billing_email', PARAM_EMAIL),
        'billing_phone'   => optional_param('billing_phone', '', PARAM_TEXT),
        'billing_address' => optional_param('billing_address', '', PARAM_TEXT),
        'billing_gstn'    => optional_param('billing_gstn', '', PARAM_TEXT),
    ];
    $gateway_name = required_param('gateway', PARAM_ALPHANUMEXT);

    try {
        $cart = \local_airpay_cart\cart_manager::checkout(
            (int) $USER->id, $billing, $gateway_name);
        $gateway = \local_airpay_cart\gateway\gateway_factory::get($gateway_name);
        $init = $gateway->initiate_payment($cart);

        if (($init['method'] ?? 'POST') === 'POST') {
            // Render an auto-submit form that POSTs to the gateway.
            echo $OUTPUT->header();
            echo $OUTPUT->render_from_template('local_airpay_cart/redirect_to_gateway', [
                'action_url' => $init['redirect'],
                'params'     => array_map(fn($k, $v) => ['key' => $k, 'value' => $v],
                    array_keys($init['params']), $init['params']),
                'gateway'    => $gateway_name,
            ]);
            echo $OUTPUT->footer();
            exit;
        } else {
            // GET method — straight redirect.
            redirect($init['redirect']);
        }
    } catch (\moodle_exception $e) {
        \core\notification::error($e->getMessage());
    }
}

$symbol = \local_airpay_cart\invoicer::currency_symbol($cart->currency);

$rows = [];
foreach ($items as $item) {
    $rows[] = [
        'name'      => format_string($item['name'] ?? ''),
        'price_str' => $symbol . number_format((float) ($item['price'] ?? 0), 2),
    ];
}

$gateways = [];
foreach (\local_airpay_cart\gateway\gateway_factory::available_for_user($USER) as $name) {
    $gateways[] = [
        'name'  => $name,
        'label' => get_string('paymentmethod_' . $name, 'local_airpay_cart'),
        'first' => empty($gateways),  // first gateway is checked by default
    ];
}

$data = [
    'sesskey'        => sesskey(),
    'items'          => $rows,
    'subtotal_str'   => $symbol . number_format((float) $cart->subtotal, 2),
    'tax_str'        => $symbol . number_format((float) $cart->tax_amount, 2),
    'total_str'      => $symbol . number_format((float) $cart->total_amount, 2),
    'currency_symbol' => $symbol,
    'gateways'       => $gateways,
    'default_name'   => fullname($USER),
    'default_email'  => $USER->email,
    'default_phone'  => $USER->phone1 ?? '',
    'back_url'       => (new moodle_url('/local/airpay_cart/index.php'))->out(false),
];

echo $OUTPUT->header();
echo $OUTPUT->render_from_template('local_airpay_cart/checkout', $data);
echo $OUTPUT->footer();
