<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

namespace local_airpay_cart\external;

defined('MOODLE_INTERNAL') || die();

use core_external\external_api;
use core_external\external_function_parameters;
use core_external\external_single_structure;
use core_external\external_value;

class refund_order extends external_api {

    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'historyid' => new external_value(PARAM_INT, ''),
            'amount'    => new external_value(PARAM_FLOAT, '0 = full remaining', VALUE_DEFAULT, 0),
            'reason'    => new external_value(PARAM_TEXT, ''),
        ]);
    }

    public static function execute(int $historyid, float $amount, string $reason): array {
        global $USER;
        $params = self::validate_parameters(self::execute_parameters(),
            compact('historyid', 'amount', 'reason'));

        $ctx = \context_system::instance();
        self::validate_context($ctx);
        require_capability('local/airpay_cart:refund', $ctx);

        $ok = \local_airpay_cart\cart_manager::refund(
            (int) $params['historyid'],
            (float) $params['amount'],
            (string) $params['reason'],
            (int) $USER->id);

        return ['success' => (bool) $ok];
    }

    public static function execute_returns(): external_single_structure {
        return new external_single_structure([
            'success' => new external_value(PARAM_BOOL, ''),
        ]);
    }
}
