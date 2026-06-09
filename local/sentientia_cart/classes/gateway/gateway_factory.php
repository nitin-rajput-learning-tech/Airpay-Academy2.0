<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

namespace local_sentientia_cart\gateway;

defined('MOODLE_INTERNAL') || die();

/**
 * Resolve a gateway implementation by name.
 *
 * Add new gateways here (e.g. stripe, razorpay) — each implements
 * gateway_interface and registers in this switch.
 */
class gateway_factory {

    public static function get(string $name): gateway_interface {
        switch ($name) {
            case 'airpay':
                return new airpay_gateway();
            case 'manual':
                return new manual_gateway();
            default:
                throw new \moodle_exception('error_invalidstate', 'local_sentientia_cart',
                    '', "Unknown gateway: $name");
        }
    }

    /** Gateways currently selectable on the checkout form. */
    public static function available_for_user(\stdClass $user): array {
        $list = ['airpay'];
        // Manual gateway: only enable if customer has billing_gstn set (B2B).
        // For now allow all. Future: per-tenant config.
        $list[] = 'manual';
        return $list;
    }
}
