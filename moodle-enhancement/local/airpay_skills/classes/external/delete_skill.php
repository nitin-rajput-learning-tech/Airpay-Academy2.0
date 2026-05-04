<?php
namespace local_airpay_skills\external;

defined('MOODLE_INTERNAL') || die();

use core_external\external_api;
use core_external\external_function_parameters;
use core_external\external_single_structure;
use core_external\external_value;

class delete_skill extends external_api {

    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'skillid' => new external_value(PARAM_INT, 'Skill ID'),
        ]);
    }

    public static function execute(int $skillid): array {
        $params = self::validate_parameters(self::execute_parameters(), ['skillid' => $skillid]);
        $context = \context_system::instance();
        self::validate_context($context);
        require_capability('local/airpay_skills:manage', $context);

        $success = \local_airpay_skills\skills_manager::delete_skill($params['skillid']);
        return ['skillid' => $params['skillid'], 'success' => $success];
    }

    public static function execute_returns(): external_single_structure {
        return new external_single_structure([
            'skillid' => new external_value(PARAM_INT, 'Skill ID'),
            'success' => new external_value(PARAM_BOOL, 'Success'),
        ]);
    }
}
