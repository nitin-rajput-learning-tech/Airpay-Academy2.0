<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

namespace local_airpay_request\external;

defined('MOODLE_INTERNAL') || die();

use core_external\external_api;
use core_external\external_function_parameters;
use core_external\external_single_structure;
use core_external\external_value;

class cancel_request extends external_api {

    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'requestid' => new external_value(PARAM_INT, ''),
        ]);
    }

    public static function execute(int $requestid): array {
        global $USER;
        $params = self::validate_parameters(self::execute_parameters(),
            compact('requestid'));
        $ctx = \context_system::instance();
        self::validate_context($ctx);
        require_capability('local/airpay_request:request', $ctx);

        $ok = \local_airpay_request\request_manager::cancel(
            (int) $params['requestid'], (int) $USER->id);
        return ['success' => (bool) $ok];
    }

    public static function execute_returns(): external_single_structure {
        return new external_single_structure([
            'success' => new external_value(PARAM_BOOL, ''),
        ]);
    }
}
