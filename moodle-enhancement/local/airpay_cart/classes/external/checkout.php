<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

namespace local_airpay_cart\external;

defined('MOODLE_INTERNAL') || die();

use core_external\external_api;
use core_external\external_function_parameters;
use core_external\external_single_structure;
use core_external\external_value;

class checkout extends external_api {

    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'gateway'         => new external_value(PARAM_ALPHANUMEXT, 'Gateway name'),
            'billing_name'    => new external_value(PARAM_TEXT, ''),
            'billing_email'   => new external_value(PARAM_EMAIL, ''),
            'billing_phone'   => new external_value(PARAM_TEXT, '', VALUE_DEFAULT, ''),
            'billing_address' => new external_value(PARAM_TEXT, '', VALUE_DEFAULT, ''),
            'billing_gstn'    => new external_value(PARAM_TEXT, '', VALUE_DEFAULT, ''),
        ]);
    }

    public static function execute(string $gateway, string $billing_name, string $billing_email,
                                    string $billing_phone = '', string $billing_address = '',
                                    string $billing_gstn = ''): array {
        global $USER;
        $params = self::validate_parameters(self::execute_parameters(), compact(
            'gateway', 'billing_name', 'billing_email', 'billing_phone',
            'billing_address', 'billing_gstn'));

        $ctx = \context_system::instance();
        self::validate_context($ctx);
        require_capability('local/airpay_cart:purchase', $ctx);

        // Validate gateway is available for this user.
        $allowed = \local_airpay_cart\gateway\gateway_factory::available_for_user($USER);
        if (!in_array($params['gateway'], $allowed, true)) {
            throw new \moodle_exception('error_invalidstate', 'local_airpay_cart',
                '', 'Gateway not available: ' . $params['gateway']);
        }

        $cart = \local_airpay_cart\cart_manager::checkout(
            (int) $USER->id,
            [
                'billing_name'    => $params['billing_name'],
                'billing_email'   => $params['billing_email'],
                'billing_phone'   => $params['billing_phone'],
                'billing_address' => $params['billing_address'],
                'billing_gstn'    => $params['billing_gstn'],
            ],
            $params['gateway']
        );

        $gw = \local_airpay_cart\gateway\gateway_factory::get($params['gateway']);
        $init = $gw->initiate_payment($cart);

        return [
            'success'      => true,
            'historyid'    => (int) $cart->id,
            'orderid'      => (int) $cart->orderid,
            'method'       => (string) ($init['method'] ?? 'POST'),
            'redirect_url' => (string) ($init['redirect'] ?? ''),
            'params_json'  => json_encode($init['params'] ?? []),
        ];
    }

    public static function execute_returns(): external_single_structure {
        return new external_single_structure([
            'success'      => new external_value(PARAM_BOOL, ''),
            'historyid'    => new external_value(PARAM_INT, ''),
            'orderid'      => new external_value(PARAM_INT, ''),
            'method'       => new external_value(PARAM_ALPHA, ''),
            'redirect_url' => new external_value(PARAM_URL, ''),
            'params_json'  => new external_value(PARAM_RAW, ''),
        ]);
    }
}
