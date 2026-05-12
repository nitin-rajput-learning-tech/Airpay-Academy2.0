<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

namespace local_airpay_proctoring\external;

defined('MOODLE_INTERNAL') || die();

use core_external\external_api;
use core_external\external_function_parameters;
use core_external\external_single_structure;
use core_external\external_value;

class report_event extends external_api {
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'sessionid'    => new external_value(PARAM_INT, ''),
            'event_type'   => new external_value(PARAM_ALPHANUMEXT, ''),
            'severity'     => new external_value(PARAM_ALPHA, '', VALUE_DEFAULT, 'info'),
            'payload_json' => new external_value(PARAM_RAW, '', VALUE_DEFAULT, '{}'),
        ]);
    }
    public static function execute(int $sessionid, string $event_type,
                                    string $severity = 'info',
                                    string $payload_json = '{}'): array {
        $params = self::validate_parameters(self::execute_parameters(), compact(
            'sessionid', 'event_type', 'severity', 'payload_json'));
        $ctx = \context_system::instance();
        self::validate_context($ctx);
        require_capability('local/airpay_proctoring:attempt', $ctx);

        $payload = json_decode($params['payload_json'] ?: '{}', true) ?: [];
        // Bound the JSON payload size.
        if (strlen($params['payload_json']) > 4096) {
            $payload = ['_truncated' => true];
        }

        $id = \local_airpay_proctoring\session_manager::record_event(
            (int) $params['sessionid'], (string) $params['event_type'],
            (string) $params['severity'], $payload);

        return ['eventid' => (int) $id];
    }
    public static function execute_returns(): external_single_structure {
        return new external_single_structure([
            'eventid' => new external_value(PARAM_INT, ''),
        ]);
    }
}
