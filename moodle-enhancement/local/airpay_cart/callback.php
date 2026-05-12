<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

/**
 * Server-to-server payment callback (webhook).
 *
 * Airpay gateway POSTs here with payment status. We verify signature,
 * then call cart_manager::mark_paid() or mark_failed().
 *
 * Must NOT require login (called by gateway, not user).
 * Must NOT honour CSRF (gateway has its own signature).
 *
 * @package local_airpay_cart
 */

// Disable session.
define('NO_MOODLE_COOKIES', true);
require_once(__DIR__ . '/../../config.php');

// Read POST body. Gateways may post application/x-www-form-urlencoded OR
// application/json — handle both.
$raw = file_get_contents('php://input');
$payload = [];
if (!empty($raw)) {
    $decoded = json_decode($raw, true);
    $payload = is_array($decoded) ? $decoded : $_POST;
} else {
    $payload = $_POST;
}

// Identify which gateway is calling. Airpay uses TRANSACTIONID; later
// gateways have their own identifying fields.
$gateway_name = isset($payload['TRANSACTIONID']) || isset($payload['ap_transactionid'])
    ? 'airpay'
    : 'unknown';

// Log every callback for audit (regardless of outcome).
\local_airpay_cart\callback_logger::log($gateway_name, $payload, $raw);

if ($gateway_name === 'unknown') {
    http_response_code(400);
    echo 'Unknown gateway';
    exit;
}

try {
    $gateway = \local_airpay_cart\gateway\gateway_factory::get($gateway_name);
    if (!$gateway->verify_callback($payload)) {
        http_response_code(400);
        echo 'Invalid signature';
        exit;
    }

    $orderid = (int) ($payload['order_id'] ?? $payload['orderid']
        ?? $payload['ORDERID'] ?? 0);

    global $DB;
    $cart = $DB->get_record('local_airpay_cart_history',
        ['orderid' => $orderid], '*', MUST_EXIST);

    if ($gateway->is_success($payload)) {
        $ref = $gateway->extract_reference($payload);
        \local_airpay_cart\cart_manager::mark_paid((int) $cart->id, $ref, $payload);
        http_response_code(200);
        echo 'OK';
    } else {
        \local_airpay_cart\cart_manager::mark_failed((int) $cart->id,
            'Gateway reported failure: ' . json_encode($payload));
        http_response_code(200);
        echo 'Recorded failure';
    }
} catch (\Throwable $e) {
    http_response_code(500);
    echo 'Error: ' . $e->getMessage();
    debugging('airpay_cart callback error: ' . $e->getMessage(), DEBUG_DEVELOPER);
}
