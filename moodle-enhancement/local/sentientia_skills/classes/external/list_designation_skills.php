<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

namespace local_sentientia_skills\external;

defined('MOODLE_INTERNAL') || die();

use core_external\external_api;
use core_external\external_function_parameters;
use core_external\external_multiple_structure;
use core_external\external_single_structure;
use core_external\external_value;
use local_sentientia_skills\skills_manager;

class list_designation_skills extends external_api {

    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'designation' => new external_value(PARAM_TEXT, 'Designation name'),
        ]);
    }

    public static function execute(string $designation): array {
        $params = self::validate_parameters(self::execute_parameters(),
            compact('designation'));
        $context = \context_system::instance();
        self::validate_context($context);
        require_capability('local/sentientia_skills:manage', $context);

        return ['rows' => skills_manager::get_designation_skills(
            (string) $params['designation'])];
    }

    public static function execute_returns(): external_single_structure {
        return new external_single_structure([
            'rows' => new external_multiple_structure(
                new external_single_structure([
                    'id'             => new external_value(PARAM_INT,  'Row ID'),
                    'skillid'        => new external_value(PARAM_INT,  'Skill ID'),
                    'skill_name'     => new external_value(PARAM_TEXT, 'Skill name'),
                    'required_level' => new external_value(PARAM_INT,  'Required level'),
                    'max_level'      => new external_value(PARAM_INT,  'Max level for this skill'),
                    'category_name'  => new external_value(PARAM_TEXT, 'Category name'),
                    'category_color' => new external_value(PARAM_TEXT, 'Category color (hex)'),
                ])
            ),
        ]);
    }
}
