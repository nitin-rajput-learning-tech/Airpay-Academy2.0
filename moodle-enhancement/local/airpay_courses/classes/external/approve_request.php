<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

namespace local_airpay_courses\external;

defined('MOODLE_INTERNAL') || die();

use core_external\external_api;
use core_external\external_function_parameters;
use core_external\external_single_structure;
use core_external\external_value;
use local_airpay_courses\request_manager;

/**
 * Sprint D — Airpay Super Admin approves a pending request.
 *
 * Side effect: inserts the active share row via
 * sharing_manager::share_course() so the catalog query immediately
 * picks up the borrowed course for the requesting tenant. Two audit
 * events fire — one for the approval decision and one for the
 * resulting share.
 *
 * @package local_airpay_courses
 */
class approve_request extends external_api {

    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'requestid' => new external_value(PARAM_INT, 'Pending request id'),
        ]);
    }

    public static function execute(int $requestid): array {
        $params = self::validate_parameters(self::execute_parameters(),
            ['requestid' => $requestid]);

        $context = \context_system::instance();
        self::validate_context($context);
        require_capability('local/airpay_courses:approve_request', $context);
        require_sesskey();

        $changed = request_manager::approve_request((int) $params['requestid']);

        return [
            'requestid' => (int) $params['requestid'],
            'changed'   => $changed,
        ];
    }

    public static function execute_returns(): external_single_structure {
        return new external_single_structure([
            'requestid' => new external_value(PARAM_INT, 'Request that was acted on'),
            'changed'   => new external_value(PARAM_BOOL,
                'True if the request was newly approved (false on no-op = already approved)'),
        ]);
    }
}
