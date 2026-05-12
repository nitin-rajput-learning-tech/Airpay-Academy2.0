<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

namespace local_airpay_cart\external;

defined('MOODLE_INTERNAL') || die();

use core_external\external_api;
use core_external\external_function_parameters;
use core_external\external_single_structure;
use core_external\external_multiple_structure;
use core_external\external_value;

class get_cart extends external_api {

    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([]);
    }

    public static function execute(): array {
        global $USER;
        $ctx = \context_system::instance();
        self::validate_context($ctx);
        require_capability('local/airpay_cart:view', $ctx);

        $cart = \local_airpay_cart\cart_manager::get_or_open_cart((int) $USER->id);
        $items = json_decode($cart->items_json ?: '[]', true) ?: [];
        $rows = [];
        foreach ($items as $item) {
            $rows[] = [
                'courseid'  => (int) $item['courseid'],
                'name'      => (string) $item['name'],
                'shortname' => (string) ($item['shortname'] ?? ''),
                'price'     => (float) $item['price'],
                'discount_pct' => (int) ($item['discount_pct'] ?? 0),
            ];
        }
        return [
            'item_count'      => count($rows),
            'subtotal'        => (float) $cart->subtotal,
            'discount_amount' => (float) $cart->discount_amount,
            'tax_amount'      => (float) $cart->tax_amount,
            'total_amount'    => (float) $cart->total_amount,
            'currency'        => $cart->currency,
            'items'           => $rows,
        ];
    }

    public static function execute_returns(): external_single_structure {
        return new external_single_structure([
            'item_count'      => new external_value(PARAM_INT, ''),
            'subtotal'        => new external_value(PARAM_FLOAT, ''),
            'discount_amount' => new external_value(PARAM_FLOAT, ''),
            'tax_amount'      => new external_value(PARAM_FLOAT, ''),
            'total_amount'    => new external_value(PARAM_FLOAT, ''),
            'currency'        => new external_value(PARAM_ALPHA, ''),
            'items'           => new external_multiple_structure(
                new external_single_structure([
                    'courseid'     => new external_value(PARAM_INT, ''),
                    'name'         => new external_value(PARAM_TEXT, ''),
                    'shortname'    => new external_value(PARAM_TEXT, ''),
                    'price'        => new external_value(PARAM_FLOAT, ''),
                    'discount_pct' => new external_value(PARAM_INT, ''),
                ])),
        ]);
    }
}
