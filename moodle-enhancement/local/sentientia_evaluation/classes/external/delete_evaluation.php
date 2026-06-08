<?php
namespace local_sentientia_evaluation\external;

defined('MOODLE_INTERNAL') || die();

use core_external\external_api;
use core_external\external_function_parameters;
use core_external\external_single_structure;
use core_external\external_value;

class delete_evaluation extends external_api {

    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'evaluationid' => new external_value(PARAM_INT, 'Evaluation ID'),
        ]);
    }

    public static function execute(int $evaluationid): array {
        $params = self::validate_parameters(self::execute_parameters(), ['evaluationid' => $evaluationid]);
        $context = \context_system::instance();
        self::validate_context($context);
        require_capability('local/sentientia_evaluation:manage', $context);

        $success = \local_sentientia_evaluation\evaluation_manager::delete($params['evaluationid']);
        return ['evaluationid' => $params['evaluationid'], 'success' => $success];
    }

    public static function execute_returns(): external_single_structure {
        return new external_single_structure([
            'evaluationid' => new external_value(PARAM_INT, 'Evaluation ID'),
            'success'      => new external_value(PARAM_BOOL, 'Success'),
        ]);
    }
}
