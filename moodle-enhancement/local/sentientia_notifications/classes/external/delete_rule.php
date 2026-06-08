<?php
namespace local_sentientia_notifications\external;

defined('MOODLE_INTERNAL') || die();

use core_external\external_api;
use core_external\external_function_parameters;
use core_external\external_single_structure;
use core_external\external_value;

class delete_rule extends external_api {

    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'ruleid' => new external_value(PARAM_INT, 'Rule ID'),
        ]);
    }

    public static function execute(int $ruleid): array {
        $params = self::validate_parameters(self::execute_parameters(), ['ruleid' => $ruleid]);
        $context = \context_system::instance();
        self::validate_context($context);
        require_capability('local/sentientia_notifications:manage', $context);

        $success = \local_sentientia_notifications\rule_manager::delete($params['ruleid']);
        return ['ruleid' => $params['ruleid'], 'success' => $success];
    }

    public static function execute_returns(): external_single_structure {
        return new external_single_structure([
            'ruleid'  => new external_value(PARAM_INT, 'Rule ID'),
            'success' => new external_value(PARAM_BOOL, 'Success'),
        ]);
    }
}
