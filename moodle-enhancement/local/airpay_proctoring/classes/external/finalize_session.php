<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

namespace local_airpay_proctoring\external;

defined('MOODLE_INTERNAL') || die();

use core_external\external_api;
use core_external\external_function_parameters;
use core_external\external_single_structure;
use core_external\external_value;

class finalize_session extends external_api {
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'sessionid' => new external_value(PARAM_INT, ''),
        ]);
    }
    public static function execute(int $sessionid): array {
        $params = self::validate_parameters(self::execute_parameters(), compact('sessionid'));
        $ctx = \context_system::instance();
        self::validate_context($ctx);
        require_capability('local/airpay_proctoring:attempt', $ctx);
        $s = \local_airpay_proctoring\session_manager::finalize((int) $params['sessionid']);
        return [
            'status'        => $s->status,
            'risk_score'    => (float) ($s->risk_score ?? 0),
            'auto_decision' => (string) ($s->auto_decision ?? ''),
        ];
    }
    public static function execute_returns(): external_single_structure {
        return new external_single_structure([
            'status'        => new external_value(PARAM_ALPHANUMEXT, ''),
            'risk_score'    => new external_value(PARAM_FLOAT, ''),
            'auto_decision' => new external_value(PARAM_TEXT, ''),
        ]);
    }
}
