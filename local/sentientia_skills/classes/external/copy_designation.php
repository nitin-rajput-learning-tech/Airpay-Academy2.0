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

class copy_designation extends external_api {

    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'fromdesignation' => new external_value(PARAM_TEXT, 'Source designation'),
            'todesignation'   => new external_value(PARAM_TEXT, 'Target designation'),
        ]);
    }

    public static function execute(string $fromdesignation, string $todesignation): array {
        $params = self::validate_parameters(self::execute_parameters(),
            compact('fromdesignation', 'todesignation'));
        $context = \context_system::instance();
        self::validate_context($context);
        require_capability('local/sentientia_skills:manage', $context);
        require_sesskey();

        $copied = skills_manager::copy_designation(
            (string) $params['fromdesignation'], (string) $params['todesignation']);
        return ['copied' => $copied];
    }

    public static function execute_returns(): external_single_structure {
        return new external_single_structure([
            'copied' => new external_value(PARAM_INT, 'Number of rows copied'),
        ]);
    }
}
