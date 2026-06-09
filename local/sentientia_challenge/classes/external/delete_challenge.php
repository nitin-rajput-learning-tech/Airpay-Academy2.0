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

class delete_challenge extends external_api {

    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'id' => new external_value(PARAM_INT, 'Challenge ID'),
        ]);
    }

    public static function execute(int $id): array {
        $params = self::validate_parameters(self::execute_parameters(), compact('id'));

        $context = \context_system::instance();
        self::validate_context($context);
        require_capability('local/sentientia_challenge:manage', $context);
        require_sesskey();

        challenge_engine::delete_challenge((int) $params['id']);

        return ['deleted' => true];
    }

    public static function execute_returns(): external_single_structure {
        return new external_single_structure([
            'deleted' => new external_value(PARAM_BOOL, 'Deletion ok'),
        ]);
    }
}
