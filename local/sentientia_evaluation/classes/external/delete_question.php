<?php
namespace local_sentientia_evaluation\external;

defined('MOODLE_INTERNAL') || die();

use core_external\external_api;
use core_external\external_function_parameters;
use core_external\external_single_structure;
use core_external\external_value;

class delete_question extends external_api {

    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'questionid' => new external_value(PARAM_INT, 'Question ID'),
        ]);
    }

    public static function execute(int $questionid): array {
        $params = self::validate_parameters(self::execute_parameters(), ['questionid' => $questionid]);
        $context = \context_system::instance();
        self::validate_context($context);
        require_capability('local/sentientia_evaluation:manage', $context);

        $success = \local_sentientia_evaluation\evaluation_manager::delete_question($params['questionid']);
        return ['questionid' => $params['questionid'], 'success' => $success];
    }

    public static function execute_returns(): external_single_structure {
        return new external_single_structure([
            'questionid' => new external_value(PARAM_INT, 'Question ID'),
            'success'    => new external_value(PARAM_BOOL, 'Success'),
        ]);
    }
}
