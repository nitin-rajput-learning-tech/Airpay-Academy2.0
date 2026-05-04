<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.

namespace local_airpay_learningpath\external;

defined('MOODLE_INTERNAL') || die();

use core_external\external_api;
use core_external\external_function_parameters;
use core_external\external_single_structure;
use core_external\external_value;

class delete_path extends external_api {

    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'pathid' => new external_value(PARAM_INT, 'Learning path ID'),
        ]);
    }

    public static function execute(int $pathid): array {
        $params = self::validate_parameters(self::execute_parameters(), ['pathid' => $pathid]);
        $context = \context_system::instance();
        self::validate_context($context);
        require_capability('local/airpay_learningpath:delete', $context);

        $success = \local_airpay_learningpath\path_manager::delete($params['pathid']);
        return ['pathid' => $params['pathid'], 'success' => $success];
    }

    public static function execute_returns(): external_single_structure {
        return new external_single_structure([
            'pathid'  => new external_value(PARAM_INT, 'Path ID'),
            'success' => new external_value(PARAM_BOOL, 'Success'),
        ]);
    }
}
