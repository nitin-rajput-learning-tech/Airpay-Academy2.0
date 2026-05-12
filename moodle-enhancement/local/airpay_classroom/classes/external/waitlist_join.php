<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

namespace local_airpay_classroom\external;

defined('MOODLE_INTERNAL') || die();

use core_external\external_api;
use core_external\external_function_parameters;
use core_external\external_single_structure;
use core_external\external_value;

class waitlist_join extends external_api {
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'classroomid' => new external_value(PARAM_INT, ''),
        ]);
    }
    public static function execute(int $classroomid): array {
        global $USER;
        $params = self::validate_parameters(self::execute_parameters(), compact('classroomid'));
        $ctx = \context_system::instance();
        self::validate_context($ctx);
        require_capability('local/airpay_classroom:view', $ctx);

        $row = \local_airpay_classroom\waitlist_manager::join(
            (int) $params['classroomid'], (int) $USER->id);
        return [
            'success'  => true,
            'position' => (int) $row->position,
            'status'   => $row->status,
        ];
    }
    public static function execute_returns(): external_single_structure {
        return new external_single_structure([
            'success'  => new external_value(PARAM_BOOL, ''),
            'position' => new external_value(PARAM_INT, ''),
            'status'   => new external_value(PARAM_ALPHANUMEXT, ''),
        ]);
    }
}
