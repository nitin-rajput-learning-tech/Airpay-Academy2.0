<?php
namespace local_airpay_programs\external;

defined('MOODLE_INTERNAL') || die();

use core_external\external_api;
use core_external\external_function_parameters;
use core_external\external_single_structure;
use core_external\external_value;

class delete_program extends external_api {

    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'programid' => new external_value(PARAM_INT, 'Program ID'),
        ]);
    }

    public static function execute(int $programid): array {
        $params = self::validate_parameters(self::execute_parameters(), ['programid' => $programid]);
        $context = \context_system::instance();
        self::validate_context($context);
        require_capability('local/airpay_programs:delete', $context);

        $success = \local_airpay_programs\program_manager::delete($params['programid']);
        return ['programid' => $params['programid'], 'success' => $success];
    }

    public static function execute_returns(): external_single_structure {
        return new external_single_structure([
            'programid' => new external_value(PARAM_INT, 'Program ID'),
            'success'   => new external_value(PARAM_BOOL, 'Success'),
        ]);
    }
}
