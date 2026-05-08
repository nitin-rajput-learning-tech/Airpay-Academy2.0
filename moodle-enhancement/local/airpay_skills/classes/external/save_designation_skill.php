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

class save_designation_skill extends external_api {

    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'designation'    => new external_value(PARAM_TEXT, 'Designation'),
            'skillid'        => new external_value(PARAM_INT,  'Skill ID'),
            'required_level' => new external_value(PARAM_INT,  'Required level'),
        ]);
    }

    public static function execute(string $designation, int $skillid,
                                    int $required_level): array {
        $params = self::validate_parameters(self::execute_parameters(),
            compact('designation', 'skillid', 'required_level'));
        $context = \context_system::instance();
        self::validate_context($context);
        require_capability('local/airpay_skills:manage', $context);
        require_sesskey();

        $id = skills_manager::save_designation_skill(
            (string) $params['designation'], (int) $params['skillid'],
            (int) $params['required_level']);
        return ['id' => $id];
    }

    public static function execute_returns(): external_single_structure {
        return new external_single_structure([
            'id' => new external_value(PARAM_INT, 'Saved row ID'),
        ]);
    }
}
