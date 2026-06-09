<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

namespace local_sentientia_challenge\external;

defined('MOODLE_INTERNAL') || die();

use core_external\external_api;
use core_external\external_function_parameters;
use core_external\external_single_structure;
use core_external\external_value;
use local_sentientia_challenge\challenge_engine;

class join_challenge extends external_api {

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
        require_capability('local/sentientia_challenge:participate', $context);
        require_sesskey();

        $attemptid = challenge_engine::join((int) $params['challengeid'], (int) $USER->id);

        return ['attemptid' => $attemptid];
    }

    public static function execute_returns(): external_single_structure {
        return new external_single_structure([
            'attemptid' => new external_value(PARAM_INT, 'Created attempt ID'),
        ]);
    }
}
