<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

namespace local_sentientia_classroom\external;

defined('MOODLE_INTERNAL') || die();

use core_external\external_api;
use core_external\external_function_parameters;
use core_external\external_single_structure;
use core_external\external_value;

class waitlist_leave extends external_api {
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'waitlistid' => new external_value(PARAM_INT, ''),
        ]);
    }
    public static function execute(int $waitlistid): array {
        global $USER;
        $params = self::validate_parameters(self::execute_parameters(), compact('waitlistid'));
        $ctx = \context_system::instance();
        self::validate_context($ctx);
        require_capability('local/sentientia_classroom:view', $ctx);
        $ok = \local_sentientia_classroom\waitlist_manager::leave(
            (int) $params['waitlistid'], (int) $USER->id);
        return ['success' => $ok];
    }
    public static function execute_returns(): external_single_structure {
        return new external_single_structure([
            'success' => new external_value(PARAM_BOOL, ''),
        ]);
    }
}
