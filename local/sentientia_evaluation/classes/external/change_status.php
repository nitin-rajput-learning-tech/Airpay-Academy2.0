<?php
namespace local_sentientia_evaluation\external;

defined('MOODLE_INTERNAL') || die();

use core_external\external_api;
use core_external\external_function_parameters;
use core_external\external_single_structure;
use core_external\external_value;

class change_status extends external_api {

    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'evaluationid' => new external_value(PARAM_INT, 'Evaluation ID'),
            'status'       => new external_value(PARAM_INT, '0=draft, 1=active, 2=archived'),
        ]);
    }

    public static function execute(int $evaluationid, int $status): array {
        $params = self::validate_parameters(self::execute_parameters(),
            ['evaluationid' => $evaluationid, 'status' => $status]);
        $context = \context_system::instance();
        self::validate_context($context);
        require_capability('local/sentientia_evaluation:manage', $context);

        $newstatus = \local_sentientia_evaluation\evaluation_manager::change_status(
            $params['evaluationid'], $params['status']);
        return ['evaluationid' => $params['evaluationid'], 'status' => $newstatus];
    }

    public static function execute_returns(): external_single_structure {
        return new external_single_structure([
            'evaluationid' => new external_value(PARAM_INT, 'Evaluation ID'),
            'status'       => new external_value(PARAM_INT, 'New status'),
        ]);
    }
}
