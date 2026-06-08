<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

namespace local_sentientia_proctoring\external;

defined('MOODLE_INTERNAL') || die();

use core_external\external_api;
use core_external\external_function_parameters;
use core_external\external_single_structure;
use core_external\external_value;

class start_session extends external_api {
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'quizid' => new external_value(PARAM_INT, ''),
        ]);
    }
    public static function execute(int $quizid): array {
        global $USER;
        $params = self::validate_parameters(self::execute_parameters(), compact('quizid'));
        $ctx = \context_system::instance();
        self::validate_context($ctx);
        require_capability('local/sentientia_proctoring:attempt', $ctx);
        $session = \local_sentientia_proctoring\session_manager::start_session(
            (int) $USER->id, (int) $params['quizid']);
        return [
            'sessionid' => (int) $session->id,
            'status'    => $session->status,
        ];
    }
    public static function execute_returns(): external_single_structure {
        return new external_single_structure([
            'sessionid' => new external_value(PARAM_INT, ''),
            'status'    => new external_value(PARAM_ALPHANUMEXT, ''),
        ]);
    }
}
