<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

namespace local_sentientia_programs\external;

defined('MOODLE_INTERNAL') || die();

use core_external\external_api;
use core_external\external_function_parameters;
use core_external\external_single_structure;
use core_external\external_value;

class unassign_level_course extends external_api {

    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'levelid'  => new external_value(PARAM_INT, 'Level ID'),
            'courseid' => new external_value(PARAM_INT, 'Course ID'),
        ]);
    }

    public static function execute(int $levelid, int $courseid): array {
        $params = self::validate_parameters(self::execute_parameters(),
            ['levelid' => $levelid, 'courseid' => $courseid]);

        $context = \context_system::instance();
        self::validate_context($context);
        require_capability('local/sentientia_programs:update', $context);

        \local_sentientia_programs\program_manager::unassign_course_from_level(
            $params['levelid'], $params['courseid']);

        return [
            'levelid'  => $params['levelid'],
            'courseid' => $params['courseid'],
            'message'  => get_string('courseunassigned', 'local_sentientia_programs'),
        ];
    }

    public static function execute_returns(): external_single_structure {
        return new external_single_structure([
            'levelid'  => new external_value(PARAM_INT,  ''),
            'courseid' => new external_value(PARAM_INT,  ''),
            'message'  => new external_value(PARAM_TEXT, ''),
        ]);
    }
}
