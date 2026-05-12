<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

namespace local_airpay_cart\external;

defined('MOODLE_INTERNAL') || die();

use core_external\external_api;
use core_external\external_function_parameters;
use core_external\external_single_structure;
use core_external\external_value;

class add_item extends external_api {

    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'courseid' => new external_value(PARAM_INT, 'Course to add'),
        ]);
    }

    public static function execute(int $courseid): array {
        global $USER;
        $params = self::validate_parameters(self::execute_parameters(),
            compact('courseid'));

        $ctx = \context_system::instance();
        self::validate_context($ctx);
        require_capability('local/airpay_cart:purchase', $ctx);

        if (!\local_airpay_cart\cart_manager::is_enabled_for_user($USER)) {
            throw new \moodle_exception('error_courseunavailable', 'local_airpay_cart');
        }

        $cart = \local_airpay_cart\cart_manager::add_item(
            (int) $USER->id, (int) $params['courseid']);

        return [
            'success'      => true,
            'item_count'   => count(json_decode($cart->items_json ?: '[]', true) ?: []),
            'subtotal'     => (float) $cart->subtotal,
            'tax_amount'   => (float) $cart->tax_amount,
            'total_amount' => (float) $cart->total_amount,
            'currency'     => $cart->currency,
        ];
    }

    public static function execute_returns(): external_single_structure {
        return new external_single_structure([
            'success'      => new external_value(PARAM_BOOL, 'Success'),
            'item_count'   => new external_value(PARAM_INT, 'Items in cart'),
            'subtotal'     => new external_value(PARAM_FLOAT, 'Subtotal'),
            'tax_amount'   => new external_value(PARAM_FLOAT, 'Tax'),
            'total_amount' => new external_value(PARAM_FLOAT, 'Total'),
            'currency'     => new external_value(PARAM_ALPHA, 'Currency code'),
        ]);
    }
}
