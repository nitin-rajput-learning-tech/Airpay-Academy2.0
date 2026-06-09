<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

namespace local_sentientia_skills\external;

defined('MOODLE_INTERNAL') || die();

use core_external\external_api;
use core_external\external_function_parameters;
use core_external\external_single_structure;
use core_external\external_value;
use local_sentientia_skills\skills_manager;

class save_skill_level extends external_api {

    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'skillid'     => new external_value(PARAM_INT,  'Skill ID'),
            'level'       => new external_value(PARAM_INT,  'Level number (1..max_level)'),
            'label'       => new external_value(PARAM_TEXT, 'Display label'),
            'description' => new external_value(PARAM_RAW,  'Markdown description', VALUE_DEFAULT, ''),
        ]);
    }

    public static function execute(int $skillid, int $level, string $label,
                                    string $description = ''): array {
        $params = self::validate_parameters(self::execute_parameters(),
            compact('skillid', 'level', 'label', 'description'));
        $context = \context_system::instance();
        self::validate_context($context);
        require_capability('local/sentientia_skills:manage', $context);
        require_sesskey();

        $id = skills_manager::save_skill_level(
            (int) $params['skillid'], (int) $params['level'],
            (string) $params['label'], (string) $params['description']);
        return ['id' => $id];
    }

    public static function execute_returns(): external_single_structure {
        return new external_single_structure([
            'id' => new external_value(PARAM_INT, 'Saved row ID'),
        ]);
    }
}
