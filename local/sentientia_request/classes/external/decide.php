<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

namespace local_sentientia_request\external;

defined('MOODLE_INTERNAL') || die();

use core_external\external_api;
use core_external\external_function_parameters;
use core_external\external_single_structure;
use core_external\external_value;

class decide extends external_api {

    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'requestid' => new external_value(PARAM_INT, ''),
            'decision'  => new external_value(PARAM_ALPHA, 'approved|rejected'),
            'note'      => new external_value(PARAM_TEXT, '', VALUE_DEFAULT, ''),
        ]);
    }

    public static function execute(int $requestid, string $decision, string $note = ''): array {
        global $USER;
        $params = self::validate_parameters(self::execute_parameters(),
            compact('requestid', 'decision', 'note'));
        $ctx = \context_system::instance();
        self::validate_context($ctx);
        require_capability('local/sentientia_request:approve', $ctx);

        $rec = \local_sentientia_request\request_manager::decide(
            (int) $params['requestid'], (int) $USER->id,
            (string) $params['decision'], (string) $params['note']);

        return [
            'success' => true,
            'status'  => $rec->status,
        ];
    }

    public static function execute_returns(): external_single_structure {
        return new external_single_structure([
            'success' => new external_value(PARAM_BOOL, ''),
            'status'  => new external_value(PARAM_ALPHANUMEXT, ''),
        ]);
    }
}
