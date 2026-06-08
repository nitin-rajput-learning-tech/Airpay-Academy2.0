<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

namespace local_sentientia_cart\gateway;

defined('MOODLE_INTERNAL') || die();

/**
 * Common contract for all payment gateway implementations.
 *
 * Each gateway returns either:
 *   ['redirect' => 'https://gateway.example/...', 'params' => [...]]
 *   for hosted-checkout gateways (Airpay, PayPal classic) where we
 *   redirect the user to the gateway's payment page.
 *
 * Or:
 *   ['client_token' => '...'] for embedded JS gateways (Stripe Elements,
 *   Razorpay JS — to be implemented as Phase-2 extensions).
 *
 * The webhook handler then calls cart_manager::mark_paid() on success.
 */
interface gateway_interface {

    /**
     * Initiate a payment for the given cart.
     *
     * @param \stdClass $cart Row from local_sentientia_cart_history (status='pending')
     * @return array See class docblock for shape
     */
    public function initiate_payment(\stdClass $cart): array;

    /**
     * Verify a webhook callback. Returns true if signature is valid AND
     * the payment is genuine.
     */
    public function verify_callback(array $payload): bool;

    /**
     * Extract the gateway reference (transaction ID) from a callback payload.
     */
    public function extract_reference(array $payload): string;

    /**
     * Was the payment successful per this gateway's payload conventions?
     */
    public function is_success(array $payload): bool;

    /**
     * Refund a payment back to the original instrument. Returns true on
     * accepted-for-refund (final settlement may be async).
     */
    public function refund(\stdClass $cart, float $amount, string $reason): bool;

    /**
     * Gateway identifier (used in DB column `gateway`).
     */
    public function get_name(): string;
}
