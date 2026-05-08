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

class get_skill_levels extends external_api {

    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'skillid' => new external_value(PARAM_INT, 'Skill ID'),
        ]);
    }

    public static function execute(int $skillid): array {
        $params = self::validate_parameters(self::execute_parameters(), compact('skillid'));
        $context = \context_system::instance();
        self::validate_context($context);
        require_capability('local/airpay_skills:manage', $context);

        return ['levels' => skills_manager::get_skill_levels((int) $params['skillid'])];
    }

    public static function execute_returns(): external_single_structure {
        return new external_single_structure([
            'levels' => new external_multiple_structure(
                new external_single_structure([
                    'level'       => new external_value(PARAM_INT,  'Level number'),
                    'id'          => new external_value(PARAM_INT,  'DB row ID (0 if unsaved)'),
                    'label'       => new external_value(PARAM_TEXT, 'Display label'),
                    'description' => new external_value(PARAM_RAW,  'Markdown description'),
                    'saved'       => new external_value(PARAM_BOOL, 'Whether a row is persisted'),
                ])
            ),
        ]);
    }
}
