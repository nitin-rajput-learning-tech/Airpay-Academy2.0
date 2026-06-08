<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

namespace local_sentientia_programs\external;

defined('MOODLE_INTERNAL') || die();

use core_external\external_api;
use core_external\external_function_parameters;
use core_external\external_single_structure;
use core_external\external_value;

/**
 * P1 #9 (2026-05-16) — bulk enrol matching users into a program.
 *
 * @package local_sentientia_programs
 */
class bulk_enrol_by_audience extends external_api {

    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'programid' => new external_value(PARAM_INT, 'Program ID'),
            'filters'   => new external_value(PARAM_RAW,
                'JSON filter map (same shape as preview_audience)',
                VALUE_DEFAULT, '{}'),
        ]);
    }

    public static function execute(int $programid, string $filters = '{}'): array {
        global $USER;
        $params = self::validate_parameters(self::execute_parameters(),
            compact('programid', 'filters'));

        $context = \context_system::instance();
        self::validate_context($context);
        require_capability('local/sentientia_programs:enrol', $context);

        $map = self::parse_filters($params['filters']);
        return \local_sentientia_programs\program_audience_enroller::enrol_by_filter(
            (int) $params['programid'], $map, (int) $USER->id);
    }

    private static function parse_filters(string $raw): array {
        if (strlen($raw) > 4096) {
            throw new \moodle_exception('filterstoolong', 'local_sentientia_programs');
        }
        $decoded = json_decode($raw, true, 5);
        if (!is_array($decoded) || json_last_error() !== JSON_ERROR_NONE) {
            return [];
        }
        $allowed = ['designation', 'region', 'location', 'employmenttype',
                    'grade', 'hrmsrole', 'org_path',
                    // P1 #10 (2026-05-16) — cohort filter.
                    'cohortid'];
        return array_intersect_key($decoded, array_flip($allowed));
    }

    public static function execute_returns(): external_single_structure {
        return new external_single_structure([
            'matched'  => new external_value(PARAM_INT, 'Users matched by the filter'),
            'enrolled' => new external_value(PARAM_INT,
                'New enrolments inserted (excludes already-enrolled users)'),
            'capped'   => new external_value(PARAM_BOOL,
                'True if audience size hit MAX_AUDIENCE_SIZE'),
        ]);
    }
}
