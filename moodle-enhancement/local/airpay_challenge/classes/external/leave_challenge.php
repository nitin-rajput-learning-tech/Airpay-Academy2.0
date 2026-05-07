<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

namespace local_airpay_challenge\external;

defined('MOODLE_INTERNAL') || die();

use core_external\external_api;
use core_external\external_function_parameters;
use core_external\external_single_structure;
use core_external\external_value;
use local_airpay_challenge\challenge_engine;

class leave_challenge extends external_api {

    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'challengeid' => new external_value(PARAM_INT, 'Challenge ID'),
        ]);
    }

    public static function execute(int $challengeid): array {
        global $USER;
        $params = self::validate_parameters(self::execute_parameters(),
            compact('challengeid'));

        $context = \context_system::instance();
        self::validate_context($context);
        require_capability('local/airpay_challenge:participate', $context);
        require_sesskey();

        challenge_engine::leave((int) $params['challengeid'], (int) $USER->id);

        return ['left' => true];
    }

    public static function execute_returns(): external_single_structure {
        return new external_single_structure([
            'left' => new external_value(PARAM_BOOL, 'Successfully left'),
        ]);
    }
}
