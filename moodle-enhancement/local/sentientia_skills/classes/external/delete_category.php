<?php
namespace local_sentientia_skills\external;

defined('MOODLE_INTERNAL') || die();

use core_external\external_api;
use core_external\external_function_parameters;
use core_external\external_single_structure;
use core_external\external_value;

class delete_category extends external_api {

    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'categoryid' => new external_value(PARAM_INT, 'Category ID'),
        ]);
    }

    public static function execute(int $categoryid): array {
        $params = self::validate_parameters(self::execute_parameters(), ['categoryid' => $categoryid]);
        $context = \context_system::instance();
        self::validate_context($context);
        require_capability('local/sentientia_skills:manage', $context);

        $success = \local_sentientia_skills\skills_manager::delete_category($params['categoryid']);
        return ['categoryid' => $params['categoryid'], 'success' => $success];
    }

    public static function execute_returns(): external_single_structure {
        return new external_single_structure([
            'categoryid' => new external_value(PARAM_INT, 'Category ID'),
            'success'    => new external_value(PARAM_BOOL, 'Success'),
        ]);
    }
}
