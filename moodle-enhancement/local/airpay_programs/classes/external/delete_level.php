<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

namespace local_airpay_programs\external;

defined('MOODLE_INTERNAL') || die();

use core_external\external_api;
use core_external\external_function_parameters;
use core_external\external_single_structure;
use core_external\external_value;

class delete_level extends external_api {

    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'levelid' => new external_value(PARAM_INT, 'Level ID'),
        ]);
    }

    public static function execute(int $levelid): array {
        $params = self::validate_parameters(self::execute_parameters(), ['levelid' => $levelid]);

        $context = \context_system::instance();
        self::validate_context($context);
        require_capability('local/airpay_programs:update', $context);

        \local_airpay_programs\program_manager::delete_level($params['levelid']);

        return [
            'levelid' => $params['levelid'],
            'message' => get_string('leveldeleted', 'local_airpay_programs'),
        ];
    }

    public static function execute_returns(): external_single_structure {
        return new external_single_structure([
            'levelid' => new external_value(PARAM_INT,  'Deleted level ID'),
            'message' => new external_value(PARAM_TEXT, 'Confirmation'),
        ]);
    }
}
