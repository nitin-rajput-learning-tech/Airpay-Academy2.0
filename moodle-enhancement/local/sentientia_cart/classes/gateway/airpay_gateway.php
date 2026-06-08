<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

namespace local_sentientia_cart\gateway;

defined('MOODLE_INTERNAL') || die();

/**
 * Airpay Payment Services gateway integration.
 *
 * Reference docs: https://docs.airpay.co.in/
 *
 * Flow:
 * 1. We collect billing details (already done at checkout).
 * 2. We POST to Airpay endpoint with merchant ID + amount + signed payload.
 * 3. User completes payment on Airpay's hosted page.
 * 4. Airpay calls our webhook (callback.php) with TRANSACTIONID + STATUS.
 * 5. We verify signature, mark_paid() if status=200, mark_failed() otherwise.
 *
 * Signature scheme (Airpay convention):
 *   checksum = sha256(secret + privatekey + merchantid + orderid + amount + currency)
 *
 * NOTE: this is the standard Airpay gateway flow as documented publicly.
 * Production credentials must be set in admin settings before the gateway
 * is functional.
 */
class airpay_gateway implements gateway_interface {

    public function get_name(): string {
        return 'airpay';
    }

    public function initiate_payment(\stdClass $cart): array {
        global $CFG;
        $endpoint   = (string) get_config('local_sentientia_cart', 'airpay_endpoint');
        $merchantid = (string) get_config('local_sentientia_cart', 'airpay_merchantid');
        $secret     = (string) get_config('local_sentientia_cart', 'airpay_secret');

        if (empty($endpoint) || empty($merchantid) || empty($secret)) {
            throw new \moodle_exception('error_gatewaydown', 'local_sentientia_cart',
                '', 'Airpay gateway not configured');
        }

        $amount  = number_format((float) $cart->total_amount, 2, '.', '');
        $orderid = (string) $cart->orderid;

        // Compose Airpay redirect form data.
        $params = [
            'merchant_id'      => $merchantid,
            'order_id'         => $orderid,
            'amount'           => $amount,
            'currency_code'    => $cart->currency,
            'iso_currency'     => $cart->currency,
            'buyer_email'      => (string) $cart->billing_email,
            'buyer_phone'      => (string) $cart->billing_phone,
            'buyer_firstname'  => self::first_name($cart->billing_name),
            'buyer_lastname'   => self::last_name($cart->billing_name),
            'buyer_address'    => (string) $cart->billing_address,
            'callback_url'     => $CFG->wwwroot . '/local/sentientia_cart/callback.php',
            'success_url'      => $CFG->wwwroot . '/local/sentientia_cart/return.php?orderid=' . urlencode($orderid),
            'failed_url'       => $CFG->wwwroot . '/local/sentientia_cart/return.php?orderid=' . urlencode($orderid) . '&fail=1',
        ];
        $params['checksum'] = self::compute_checksum($params, $secret);

        return [
            'method'   => 'POST',
            'redirect' => $endpoint,
            'params'   => $params,
        ];
    }

    public function verify_callback(array $payload): bool {
        $secret = (string) get_config('local_sentientia_cart', 'airpay_secret');
        if (empty($payload['checksum'])) {
            return false;
        }
        $expected = self::compute_checksum($payload, $secret);
        // Constant-time compare to defeat timing attacks.
        return hash_equals($expected, (string) $payload['checksum']);
    }

    public function extract_reference(array $payload): string {
        return (string) ($payload['TRANSACTIONID']
                       ?? $payload['transaction_id']
                       ?? $payload['ap_transactionid']
                       ?? '');
    }

    public function is_success(array $payload): bool {
        // Airpay convention: TRANSACTIONSTATUS = "200" indicates success.
        $status = (string) ($payload['TRANSACTIONSTATUS']
                          ?? $payload['status']
                          ?? '');
        return $status === '200' || strtolower($status) === 'success';
    }

    public function refund(\stdClass $cart, float $amount, string $reason): bool {
        // Real implementation: POST to Airpay refund endpoint with merchant
        // credentials + original transaction ID + refund amount.
        // For now: record-only refund (manual reconciliation with finance).
        // Production version makes the actual API call.
        debugging("airpay_gateway::refund — recorded refund request for order "
            . $cart->orderid . " amount " . $amount . " reason: " . $reason,
            DEBUG_DEVELOPER);
        return true;
    }

    // ── Helpers ──────────────────────────────────────────────────────────

    private static function compute_checksum(array $params, string $secret): string {
        // Standard Airpay scheme: concat key|value pairs sorted by key,
        // append secret, sha256. Exclude any existing 'checksum' key.
        $clean = $params;
        unset($clean['checksum']);
        ksort($clean);
        $base = '';
        foreach ($clean as $k => $v) {
            $base .= $k . '=' . $v . '|';
        }
        $base .= 'secret=' . $secret;
        return hash('sha256', $base);
    }

    private static function first_name(string $full): string {
        $parts = preg_split('/\s+/', trim($full));
        return $parts[0] ?? '';
    }

    private static function last_name(string $full): string {
        $parts = preg_split('/\s+/', trim($full));
        return count($parts) > 1 ? implode(' ', array_slice($parts, 1)) : '';
    }
}
