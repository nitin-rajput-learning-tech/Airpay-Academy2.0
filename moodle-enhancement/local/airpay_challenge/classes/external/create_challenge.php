<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

namespace local_airpay_challenge\external;

defined('MOODLE_INTERNAL') || die();

use core_external\external_api;
use core_external\external_function_parameters;
use core_external\external_multiple_structure;
use core_external\external_single_structure;
use core_external\external_value;
use local_airpay_challenge\challenge_engine;

class create_challenge extends external_api {

    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'name'         => new external_value(PARAM_TEXT, 'Name'),
            'shortname'    => new external_value(PARAM_ALPHANUMEXT, 'Shortname slug', VALUE_DEFAULT, ''),
            'description'  => new external_value(PARAM_RAW, 'Description', VALUE_DEFAULT, ''),
            'type'         => new external_value(PARAM_ALPHAEXT, 'Type', VALUE_DEFAULT, 'course_completion'),
            'targetcount'  => new external_value(PARAM_INT, 'Target count', VALUE_DEFAULT, 1),
            'courseids'    => new external_multiple_structure(
                new external_value(PARAM_INT, 'Course ID'),
                'Optional course filter', VALUE_DEFAULT, []),
            'pointsreward' => new external_value(PARAM_INT, 'Points', VALUE_DEFAULT, 100),
            'status'       => new external_value(PARAM_INT, 'Status', VALUE_DEFAULT, 0),
            'startdate'    => new external_value(PARAM_INT, 'Start date', VALUE_DEFAULT, 0),
            'enddate'      => new external_value(PARAM_INT, 'End date', VALUE_DEFAULT, 0),
        ]);
    }

    public static function execute(string $name, string $shortname = '', string $description = '',
                                    string $type = 'course_completion', int $targetcount = 1,
                                    array $courseids = [], int $pointsreward = 100,
                                    int $status = 0, int $startdate = 0, int $enddate = 0): array {
        $params = self::validate_parameters(self::execute_parameters(),
            compact('name', 'shortname', 'description', 'type', 'targetcount',
                    'courseids', 'pointsreward', 'status', 'startdate', 'enddate'));

        $context = \context_system::instance();
        self::validate_context($context);
        require_capability('local/airpay_challenge:manage', $context);
        require_sesskey();

        $id = challenge_engine::create_challenge($params);

        return ['id' => $id];
    }

    public static function execute_returns(): external_single_structure {
        return new external_single_structure([
            'id' => new external_value(PARAM_INT, 'New challenge ID'),
        ]);
    }
}
