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

class search_courses extends external_api {

    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'q'     => new external_value(PARAM_RAW, 'Search term', VALUE_DEFAULT, ''),
            'limit' => new external_value(PARAM_INT, 'Result limit', VALUE_DEFAULT, 25),
        ]);
    }

    public static function execute(string $q = '', int $limit = 25): array {
        $params = self::validate_parameters(self::execute_parameters(),
            ['q' => $q, 'limit' => $limit]);
        $context = \context_system::instance();
        self::validate_context($context);
        require_capability('local/sentientia_skills:manage', $context);

        return [
            'rows' => skills_manager::search_courses(
                (string) $params['q'], min(100, max(1, (int) $params['limit']))),
        ];
    }

    public static function execute_returns(): external_single_structure {
        return new external_single_structure([
            'rows' => new external_multiple_structure(new external_single_structure([
                'id'           => new external_value(PARAM_INT,  'Course ID'),
                'fullname'     => new external_value(PARAM_TEXT, 'Course full name'),
                'shortname'    => new external_value(PARAM_TEXT, 'Course shortname'),
                'mapped_count' => new external_value(PARAM_INT,  'Skills already mapped'),
            ])),
        ]);
    }
}
