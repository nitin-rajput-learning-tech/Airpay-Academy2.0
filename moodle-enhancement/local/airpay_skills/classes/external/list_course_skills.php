<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

namespace local_airpay_skills\external;

defined('MOODLE_INTERNAL') || die();

use core_external\external_api;
use core_external\external_function_parameters;
use core_external\external_multiple_structure;
use core_external\external_single_structure;
use core_external\external_value;
use local_airpay_skills\skills_manager;

class list_course_skills extends external_api {

    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'courseid' => new external_value(PARAM_INT, 'Course ID'),
        ]);
    }

    public static function execute(int $courseid): array {
        $params = self::validate_parameters(self::execute_parameters(),
            ['courseid' => $courseid]);
        $context = \context_system::instance();
        self::validate_context($context);
        require_capability('local/airpay_skills:manage', $context);

        return ['rows' => skills_manager::list_course_skills((int) $params['courseid'])];
    }

    public static function execute_returns(): external_single_structure {
        return new external_single_structure([
            'rows' => new external_multiple_structure(new external_single_structure([
                'id'             => new external_value(PARAM_INT,  'Mapping row ID'),
                'skillid'        => new external_value(PARAM_INT,  'Skill ID'),
                'skill_name'     => new external_value(PARAM_TEXT, 'Skill name'),
                'teaches_level'  => new external_value(PARAM_INT,  'Level taught'),
                'max_level'      => new external_value(PARAM_INT,  'Skill max level'),
                'category_name'  => new external_value(PARAM_TEXT, 'Category name'),
                'category_color' => new external_value(PARAM_TEXT, 'Category hex color'),
            ])),
        ]);
    }
}
