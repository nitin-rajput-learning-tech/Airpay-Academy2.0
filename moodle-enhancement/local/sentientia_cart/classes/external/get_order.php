<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

namespace local_sentientia_cart\external;

defined('MOODLE_INTERNAL') || die();

use core_external\external_api;
use core_external\external_function_parameters;
use core_external\external_single_structure;
use core_external\external_value;

class get_order extends external_api {

    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'historyid' => new external_value(PARAM_INT, 'History row ID'),
        ]);
    }

    public static function execute(int $historyid): array {
        global $USER;
        $params = self::validate_parameters(self::execute_parameters(),
            compact('historyid'));
        $ctx = \context_system::instance();
        self::validate_context($ctx);
        require_capability('local/sentientia_cart:view', $ctx);

        $order = \local_sentientia_cart\cart_manager::get_order(
            (int) $params['historyid'], (int) $USER->id);

        return [
            'id'             => (int) $order->id,
            'orderid'        => (int) ($order->orderid ?? 0),
            'status'         => $order->status,
            'gateway'        => $order->gateway ?? '',
            'gateway_ref'    => $order->gateway_ref ?? '',
            'items_json'     => $order->items_json ?: '[]',
            'subtotal'       => (float) $order->subtotal,
            'discount_amount' => (float) $order->discount_amount,
            'tax_amount'     => (float) $order->tax_amount,
            'total_amount'   => (float) $order->total_amount,
            'currency'       => $order->currency,
            'billing_name'   => $order->billing_name ?? '',
            'billing_email'  => $order->billing_email ?? '',
            'billing_phone'  => $order->billing_phone ?? '',
            'billing_address' => $order->billing_address ?? '',
            'billing_gstn'   => $order->billing_gstn ?? '',
            'placed_on'      => userdate($order->timecreated),
            'paid_on'        => $order->timepaid ? userdate($order->timepaid) : '',
        ];
    }

    public static function execute_returns(): external_single_structure {
        return new external_single_structure([
            'id'             => new external_value(PARAM_INT, ''),
            'orderid'        => new external_value(PARAM_INT, ''),
            'status'         => new external_value(PARAM_ALPHANUMEXT, ''),
            'gateway'        => new external_value(PARAM_ALPHANUMEXT, ''),
            'gateway_ref'    => new external_value(PARAM_TEXT, ''),
            'items_json'     => new external_value(PARAM_RAW, ''),
            'subtotal'       => new external_value(PARAM_FLOAT, ''),
            'discount_amount' => new external_value(PARAM_FLOAT, ''),
            'tax_amount'     => new external_value(PARAM_FLOAT, ''),
            'total_amount'   => new external_value(PARAM_FLOAT, ''),
            'currency'       => new external_value(PARAM_ALPHA, ''),
            'billing_name'   => new external_value(PARAM_TEXT, ''),
            'billing_email'  => new external_value(PARAM_TEXT, ''),
            'billing_phone'  => new external_value(PARAM_TEXT, ''),
            'billing_address' => new external_value(PARAM_TEXT, ''),
            'billing_gstn'   => new external_value(PARAM_TEXT, ''),
            'placed_on'      => new external_value(PARAM_TEXT, ''),
            'paid_on'        => new external_value(PARAM_TEXT, ''),
        ]);
    }
}
