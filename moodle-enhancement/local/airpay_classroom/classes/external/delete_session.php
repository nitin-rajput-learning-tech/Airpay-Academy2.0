<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

namespace local_airpay_classroom\external;

defined('MOODLE_INTERNAL') || die();

use core_external\external_api;
use core_external\external_function_parameters;
use core_external\external_single_structure;
use core_external\external_value;

/**
 * Delete a single session and its attendance records.
 *
 * @package   local_airpay_classroom
 */
class delete_session extends external_api {

    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'sessionid' => new external_value(PARAM_INT, 'Session ID'),
        ]);
    }

    public static function execute(int $sessionid): array {
        $params = self::validate_parameters(self::execute_parameters(),
            ['sessionid' => $sessionid]);

        $context = \context_system::instance();
        self::validate_context($context);
        require_capability('local/airpay_classroom:update', $context);

        \local_airpay_classroom\session_manager::delete_session($params['sessionid']);

        return [
            'sessionid' => $params['sessionid'],
            'message'   => get_string('sessiondeleted', 'local_airpay_classroom'),
        ];
    }

    public static function execute_returns(): external_single_structure {
        return new external_single_structure([
            'sessionid' => new external_value(PARAM_INT,  'Deleted session ID'),
            'message'   => new external_value(PARAM_TEXT, 'Confirmation message'),
        ]);
    }
}
