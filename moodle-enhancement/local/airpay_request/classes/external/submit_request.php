<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

namespace local_airpay_request\external;

defined('MOODLE_INTERNAL') || die();

use core_external\external_api;
use core_external\external_function_parameters;
use core_external\external_single_structure;
use core_external\external_value;

class submit_request extends external_api {

    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'courseid' => new external_value(PARAM_INT, 'Course to request'),
            'reason'   => new external_value(PARAM_TEXT, 'Why you need the course (min 20 chars)'),
        ]);
    }

    public static function execute(int $courseid, string $reason): array {
        global $USER;
        $params = self::validate_parameters(self::execute_parameters(),
            compact('courseid', 'reason'));

        $ctx = \context_system::instance();
        self::validate_context($ctx);
        require_capability('local/airpay_request:request', $ctx);

        $rec = \local_airpay_request\request_manager::submit(
            (int) $USER->id, (int) $params['courseid'], (string) $params['reason']);

        return [
            'success'  => true,
            'requestid' => (int) $rec->id,
            'status'   => $rec->status,
            'route'    => $rec->route,
        ];
    }

    public static function execute_returns(): external_single_structure {
        return new external_single_structure([
            'success'   => new external_value(PARAM_BOOL, ''),
            'requestid' => new external_value(PARAM_INT, ''),
            'status'    => new external_value(PARAM_ALPHANUMEXT, ''),
            'route'     => new external_value(PARAM_ALPHANUMEXT, ''),
        ]);
    }
}
