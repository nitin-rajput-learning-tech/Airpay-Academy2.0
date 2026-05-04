<?php
namespace local_airpay_evaluation\external;

defined('MOODLE_INTERNAL') || die();

use core_external\external_api;
use core_external\external_function_parameters;
use core_external\external_multiple_structure;
use core_external\external_single_structure;
use core_external\external_value;

/**
 * Reorder questions within an evaluation. Accepts ordered array of question IDs.
 */
class reorder_questions extends external_api {

    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'evaluationid' => new external_value(PARAM_INT, 'Evaluation ID'),
            'questionids'  => new external_multiple_structure(
                new external_value(PARAM_INT, 'Question ID'),
                'Ordered array of question IDs (index = new sortorder)'
            ),
        ]);
    }

    public static function execute(int $evaluationid, array $questionids): array {
        $params = self::validate_parameters(self::execute_parameters(),
            ['evaluationid' => $evaluationid, 'questionids' => $questionids]);
        $context = \context_system::instance();
        self::validate_context($context);
        require_capability('local/airpay_evaluation:manage', $context);

        $success = \local_airpay_evaluation\evaluation_manager::reorder_questions(
            $params['evaluationid'], $params['questionids']);
        return ['evaluationid' => $params['evaluationid'], 'success' => $success];
    }

    public static function execute_returns(): external_single_structure {
        return new external_single_structure([
            'evaluationid' => new external_value(PARAM_INT, 'Evaluation ID'),
            'success'      => new external_value(PARAM_BOOL, 'Success'),
        ]);
    }
}
