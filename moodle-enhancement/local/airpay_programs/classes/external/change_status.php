<?php
namespace local_airpay_programs\external;

defined('MOODLE_INTERNAL') || die();

use core_external\external_api;
use core_external\external_function_parameters;
use core_external\external_single_structure;
use core_external\external_value;

class change_status extends external_api {

    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'programid' => new external_value(PARAM_INT, 'Program ID'),
            'status'    => new external_value(PARAM_INT, '0=draft, 1=active, 2=archived'),
        ]);
    }

    public static function execute(int $programid, int $status): array {
        $params = self::validate_parameters(self::execute_parameters(),
            ['programid' => $programid, 'status' => $status]);
        $context = \context_system::instance();
        self::validate_context($context);
        require_capability('local/airpay_programs:update', $context);

        $newstatus = \local_airpay_programs\program_manager::change_status(
            $params['programid'], $params['status']);
        return ['programid' => $params['programid'], 'status' => $newstatus];
    }

    public static function execute_returns(): external_single_structure {
        return new external_single_structure([
            'programid' => new external_value(PARAM_INT, 'Program ID'),
            'status'    => new external_value(PARAM_INT, 'New status'),
        ]);
    }
}
