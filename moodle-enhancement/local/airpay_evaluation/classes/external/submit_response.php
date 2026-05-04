<?php
namespace local_airpay_evaluation\external;

defined('MOODLE_INTERNAL') || die();

use core_external\external_api;
use core_external\external_function_parameters;
use core_external\external_single_structure;
use core_external\external_value;

/**
 * Submit a response to an evaluation form.
 *
 * Accepts a JSON-encoded answers map: {"questionid": "answer", ...}
 * The values vary by question type: int for rating/nps, "yes"|"no" for yesno,
 * the option string for multichoice, free text for text.
 */
class submit_response extends external_api {

    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'evaluationid' => new external_value(PARAM_INT, 'Evaluation ID'),
            'answers'      => new external_value(PARAM_RAW, 'JSON map: {questionid: answer}'),
            'context'      => new external_value(PARAM_RAW,
                'Optional JSON context: {courseid, programid, classroomid}',
                VALUE_DEFAULT, '{}'),
        ]);
    }

    public static function execute(int $evaluationid, string $answers, string $context = '{}'): array {
        global $USER;

        $params = self::validate_parameters(self::execute_parameters(), [
            'evaluationid' => $evaluationid,
            'answers'      => $answers,
            'context'      => $context,
        ]);

        $sysctx = \context_system::instance();
        self::validate_context($sysctx);
        require_capability('local/airpay_evaluation:respond', $sysctx);

        $answers_decoded = json_decode($params['answers'], true);
        if (!is_array($answers_decoded)) {
            $answers_decoded = [];
        }

        $context_decoded = json_decode($params['context'], true);
        if (!is_array($context_decoded)) {
            $context_decoded = [];
        }

        $responseid = \local_airpay_evaluation\evaluation_manager::submit_response(
            $params['evaluationid'], (int) $USER->id, $answers_decoded, $context_decoded);

        return ['responseid' => $responseid, 'success' => true];
    }

    public static function execute_returns(): external_single_structure {
        return new external_single_structure([
            'responseid' => new external_value(PARAM_INT, 'New response ID'),
            'success'    => new external_value(PARAM_BOOL, 'Success'),
        ]);
    }
}
