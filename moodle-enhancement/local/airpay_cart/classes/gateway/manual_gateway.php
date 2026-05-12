<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

namespace local_airpay_cart\gateway;

defined('MOODLE_INTERNAL') || die();

/**
 * Manual gateway — for "pay by bank transfer" / "PO with NET-30" / similar
 * B2B scenarios where the customer pays out-of-band.
 *
 * Order goes to 'pending' state; admin manually clicks "Mark as paid"
 * in the admin orders dashboard once finance confirms receipt.
 */
class manual_gateway implements gateway_interface {

    public function get_name(): string {
        return 'manual';
    }

    public function initiate_payment(\stdClass $cart): array {
        // No external redirect. Return our own confirmation page.
        global $CFG;
        return [
            'method'   => 'GET',
            'redirect' => $CFG->wwwroot . '/local/airpay_cart/return.php?orderid=' . urlencode((string) $cart->orderid) . '&manual=1',
            'params'   => [],
        ];
    }

    public function verify_callback(array $payload): bool {
        // No external callbacks for manual gateway.
        return false;
    }

    public function extract_reference(array $payload): string {
        return (string) ($payload['reference'] ?? '');
    }

    public function is_success(array $payload): bool {
        return false;
    }

    public function refund(\stdClass $cart, float $amount, string $reason): bool {
        // Manual refund — finance issues bank transfer; we just record.
        return true;
    }
}
