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

class update_challenge extends external_api {

    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'id'           => new external_value(PARAM_INT, 'ID'),
            'name'         => new external_value(PARAM_TEXT, 'Name', VALUE_DEFAULT, null, NULL_ALLOWED),
            'shortname'    => new external_value(PARAM_ALPHANUMEXT, 'Shortname', VALUE_DEFAULT, null, NULL_ALLOWED),
            'description'  => new external_value(PARAM_RAW, 'Description', VALUE_DEFAULT, null, NULL_ALLOWED),
            'type'         => new external_value(PARAM_ALPHAEXT, 'Type', VALUE_DEFAULT, null, NULL_ALLOWED),
            'targetcount'  => new external_value(PARAM_INT, 'Target', VALUE_DEFAULT, null, NULL_ALLOWED),
            'courseids'    => new external_multiple_structure(
                new external_value(PARAM_INT, 'Course ID'),
                'Course filter', VALUE_DEFAULT, null, NULL_ALLOWED),
            'pointsreward' => new external_value(PARAM_INT, 'Points', VALUE_DEFAULT, null, NULL_ALLOWED),
            'status'       => new external_value(PARAM_INT, 'Status', VALUE_DEFAULT, null, NULL_ALLOWED),
            'startdate'    => new external_value(PARAM_INT, 'Start', VALUE_DEFAULT, null, NULL_ALLOWED),
            'enddate'      => new external_value(PARAM_INT, 'End', VALUE_DEFAULT, null, NULL_ALLOWED),
        ]);
    }

    public static function execute(int $id, ?string $name = null, ?string $shortname = null,
                                    ?string $description = null, ?string $type = null,
                                    ?int $targetcount = null, ?array $courseids = null,
                                    ?int $pointsreward = null, ?int $status = null,
                                    ?int $startdate = null, ?int $enddate = null): array {
        $params = self::validate_parameters(self::execute_parameters(),
            compact('id', 'name', 'shortname', 'description', 'type', 'targetcount',
                    'courseids', 'pointsreward', 'status', 'startdate', 'enddate'));

        $context = \context_system::instance();
        self::validate_context($context);
        require_capability('local/airpay_challenge:manage', $context);
        require_sesskey();

        // Strip nulls so update_challenge() does a partial update.
        $update = array_filter(
            ['name' => $params['name'], 'shortname' => $params['shortname'],
             'description' => $params['description'], 'type' => $params['type'],
             'targetcount' => $params['targetcount'], 'courseids' => $params['courseids'],
             'pointsreward' => $params['pointsreward'], 'status' => $params['status'],
             'startdate' => $params['startdate'], 'enddate' => $params['enddate']],
            fn($v) => $v !== null);

        challenge_engine::update_challenge((int) $params['id'], $update);

        return ['id' => (int) $params['id']];
    }

    public static function execute_returns(): external_single_structure {
        return new external_single_structure([
            'id' => new external_value(PARAM_INT, 'Updated ID'),
        ]);
    }
}
