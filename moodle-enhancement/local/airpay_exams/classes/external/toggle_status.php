<?php
namespace local_airpay_exams\external;

defined('MOODLE_INTERNAL') || die();

use core_external\external_api;
use core_external\external_function_parameters;
use core_external\external_single_structure;
use core_external\external_value;

class toggle_status extends external_api {

    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'examid' => new external_value(PARAM_INT, 'Exam ID'),
            'active' => new external_value(PARAM_BOOL, 'true=active, false=inactive'),
        ]);
    }

    public static function execute(int $examid, bool $active): array {
        $params = self::validate_parameters(self::execute_parameters(),
            ['examid' => $examid, 'active' => $active]);
        $context = \context_system::instance();
        self::validate_context($context);
        require_capability('local/airpay_exams:manage', $context);

        $newstate = \local_airpay_exams\exam_manager::toggle_status(
            $params['examid'], $params['active']);
        return ['examid' => $params['examid'], 'active' => $newstate];
    }

    public static function execute_returns(): external_single_structure {
        return new external_single_structure([
            'examid' => new external_value(PARAM_INT, 'Exam ID'),
            'active' => new external_value(PARAM_BOOL, 'New active state'),
        ]);
    }
}
