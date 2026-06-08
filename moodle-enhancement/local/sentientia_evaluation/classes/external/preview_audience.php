<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

namespace local_sentientia_evaluation\external;

defined('MOODLE_INTERNAL') || die();

use core_external\external_api;
use core_external\external_function_parameters;
use core_external\external_multiple_structure;
use core_external\external_single_structure;
use core_external\external_value;

/**
 * P1 #39 (2026-05-20) — preview an evaluation target audience before
 * committing assignments. Parallel-port of P1 #13's classroom WS.
 *
 * @package local_sentientia_evaluation
 */
class preview_audience extends external_api {

    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'filters' => new external_value(PARAM_RAW,
                'JSON map: designation, region, location, employmenttype, grade, hrmsrole, org_path, cohortid',
                VALUE_DEFAULT, '{}'),
        ]);
    }

    public static function execute(string $filters = '{}'): array {
        global $USER;
        $params = self::validate_parameters(self::execute_parameters(),
            compact('filters'));

        $context = \context_system::instance();
        self::validate_context($context);
        require_capability('local/sentientia_evaluation:manage', $context);

        $map = self::parse_filters($params['filters']);
        return \local_sentientia_evaluation\evaluation_audience_assigner::preview(
            $map, (int) $USER->id);
    }

    private static function parse_filters(string $raw): array {
        if (strlen($raw) > 4096) {
            throw new \moodle_exception('filterstoolong', 'local_sentientia_evaluation');
        }
        $decoded = json_decode($raw, true, 5);
        if (!is_array($decoded) || json_last_error() !== JSON_ERROR_NONE) {
            return [];
        }
        $allowed = ['designation', 'region', 'location', 'employmenttype',
                    'grade', 'hrmsrole', 'org_path', 'cohortid'];
        return array_intersect_key($decoded, array_flip($allowed));
    }

    public static function execute_returns(): external_single_structure {
        return new external_single_structure([
            'count'  => new external_value(PARAM_INT, 'Number of users matching the filter'),
            'sample' => new external_multiple_structure(
                new external_single_structure([
                    'id'       => new external_value(PARAM_INT, 'User ID'),
                    'fullname' => new external_value(PARAM_TEXT, 'First + last name'),
                    'email'    => new external_value(PARAM_TEXT, 'Email'),
                ])
            ),
            'capped_at' => new external_value(PARAM_INT,
                'Maximum audience size — if count == capped_at, results were truncated'),
        ]);
    }
}
