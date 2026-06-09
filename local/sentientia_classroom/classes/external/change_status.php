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
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

namespace local_sentientia_classroom\external;

defined('MOODLE_INTERNAL') || die();

use core_external\external_api;
use core_external\external_function_parameters;
use core_external\external_single_structure;
use core_external\external_value;

class change_status extends external_api {

    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'classroomid' => new external_value(PARAM_INT, 'Classroom ID'),
            'status'      => new external_value(PARAM_INT, '0=cancelled, 1=active, 2=completed'),
        ]);
    }

    public static function execute(int $classroomid, int $status): array {
        $params = self::validate_parameters(self::execute_parameters(),
            ['classroomid' => $classroomid, 'status' => $status]);
        $context = \context_system::instance();
        self::validate_context($context);
        require_capability('local/sentientia_classroom:update', $context);

        $newstatus = \local_sentientia_classroom\session_manager::change_status(
            $params['classroomid'], $params['status']);
        return ['classroomid' => $params['classroomid'], 'status' => $newstatus];
    }

    public static function execute_returns(): external_single_structure {
        return new external_single_structure([
            'classroomid' => new external_value(PARAM_INT, 'Classroom ID'),
            'status'      => new external_value(PARAM_INT, 'New status'),
        ]);
    }
}
