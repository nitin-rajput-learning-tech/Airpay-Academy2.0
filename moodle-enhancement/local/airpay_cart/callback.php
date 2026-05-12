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
 * Phase 8.1 hardening (commit ee9354e7d+1):
 *  - B4 (CVSS 9.1): compare payload.amount to server-side cart.total_amount
 *    BEFORE calling mark_paid(). The gateway's checksum proves payload
 *    integrity from the gateway's perspective but doesn't prove the amount
 *    matches what we billed. Without this check, an attacker who can craft
 *    a valid checksum for a small amount + a target order_id can enrol
 *    themselves in expensive courses.
 *  - B11 (CVSS 5.4): generic 500 response — don't leak PHP error messages.
 *    Plus optional IP allow-list (admin setting `airpay_callback_iplist`)
 *    so we silently drop requests not originating from the gateway IPs.
 *
 * @package local_airpay_cart
 */

// Disable session.
define('NO_MOODLE_COOKIES', true);
require_once(__DIR__ . '/../../config.php');

// B11: optional IP allow-list. If configured, requests from non-listed
// IPs are silently dropped (no 200/4xx response). Gateways re-try, but
// an attacker scanning for the endpoint sees nothing actionable.
$iplist = trim((string) get_config('local_airpay_cart', 'airpay_callback_iplist'));
if ($iplist !== '') {
    $remote = getremoteaddr();
    $allowed = false;
    foreach (explode(',', $iplist) as $cidr) {
        $cidr = trim($cidr);
        if ($cidr === '') continue;
        if (\local_airpay_cart\ip_check::ip_in_cidr($remote, $cidr)) {
            $allowed = true;
            break;
        }
    }
    if (!$allowed) {
        // No body, no telltale. Just drop.
        http_response_code(404);
        exit;
    }
}

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
        // ── B4 fix: amount equality check ────────────────────────────────
        // Gateway payload's amount must equal the server-side total. The
        // checksum verified the payload wasn't tampered after the gateway
        // signed it, but the gateway could in theory accept a "1 INR for
        // orderid 12345" charge from a manipulated initiation step. We
        // own the truth: cart->total_amount.
        $paid_raw = $payload['amount'] ?? $payload['AMOUNT']
            ?? $payload['TRANSACTIONAMOUNT'] ?? null;
        if ($paid_raw === null) {
            \local_airpay_cart\callback_logger::log('amount_missing',
                ['orderid' => $orderid], $raw);
            http_response_code(400);
            echo 'Amount missing';
            exit;
        }
        $paid_amount = (float) $paid_raw;
        $expected    = round((float) $cart->total_amount, 2);
        if (abs($paid_amount - $expected) > 0.01) {
            \local_airpay_cart\callback_logger::log('amount_mismatch',
                ['orderid' => $orderid, 'paid' => $paid_amount,
                 'expected' => $expected], $raw);
            http_response_code(400);
            echo 'Amount mismatch';
            exit;
        }
        // Currency must match too — a gateway that "successfully" charged
        // USD for an INR order would still leak through without this.
        $paid_currency = strtoupper((string) ($payload['currency_code']
            ?? $payload['currency'] ?? $payload['CURRENCYCODE'] ?? ''));
        if ($paid_currency !== '' && $paid_currency !== strtoupper((string) $cart->currency)) {
            \local_airpay_cart\callback_logger::log('currency_mismatch',
                ['orderid' => $orderid, 'paid' => $paid_currency,
                 'expected' => $cart->currency], $raw);
            http_response_code(400);
            echo 'Currency mismatch';
            exit;
        }

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
    // B11 fix: generic response, never echo $e->getMessage() — leaks
    // PHP paths, DB column names, library versions, stack frames.
    http_response_code(500);
    echo 'Error';
    debugging('airpay_cart callback error: ' . $e->getMessage(), DEBUG_DEVELOPER);
    \local_airpay_cart\callback_logger::log('exception',
        ['exception' => get_class($e), 'orderid' => $orderid ?? 0], $raw);
}
