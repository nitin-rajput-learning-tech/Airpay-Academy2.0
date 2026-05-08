<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

namespace local_airpay_skills\external;

defined('MOODLE_INTERNAL') || die();

use core_external\external_api;
use core_external\external_function_parameters;
use core_external\external_single_structure;
use core_external\external_value;
use local_airpay_skills\skills_manager;

class save_course_skill extends external_api {

    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'courseid'      => new external_value(PARAM_INT, 'Course ID'),
            'skillid'       => new external_value(PARAM_INT, 'Skill ID'),
            'teaches_level' => new external_value(PARAM_INT, 'Level granted on completion'),
        ]);
    }

    public static function execute(int $courseid, int $skillid,
                                    int $teaches_level): array {
        $params = self::validate_parameters(self::execute_parameters(),
            compact('courseid', 'skillid', 'teaches_level'));
        $context = \context_system::instance();
        self::validate_context($context);
        require_capability('local/airpay_skills:manage', $context);
        require_sesskey();

        $id = skills_manager::save_course_skill(
            (int) $params['courseid'], (int) $params['skillid'],
            (int) $params['teaches_level']);
        return ['id' => $id];
    }

    public static function execute_returns(): external_single_structure {
        return new external_single_structure([
            'id' => new external_value(PARAM_INT, 'Saved row ID'),
        ]);
    }
}
