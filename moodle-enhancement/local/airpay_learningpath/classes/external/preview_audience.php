<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

namespace local_airpay_learningpath\external;

defined('MOODLE_INTERNAL') || die();

use core_external\external_api;
use core_external\external_function_parameters;
use core_external\external_multiple_structure;
use core_external\external_single_structure;
use core_external\external_value;

/**
 * P1 #8 (2026-05-16) — preview a target audience before enrolling.
 *
 * Admin picks filters → calls this WS → sees the count + first 10 users →
 * decides whether to commit. The actual enrolment is a separate WS
 * (`bulk_enrol_by_audience`) so accidental "huge enrolment" mistakes
 * are caught at preview time.
 *
 * @package local_airpay_learningpath
 */
class preview_audience extends external_api {

    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'filters' => new external_value(PARAM_RAW,
                'JSON map: {designation, region, location, employmenttype, grade, hrmsrole, org_path}',
                VALUE_DEFAULT, '{}'),
        ]);
    }

    public static function execute(string $filters = '{}'): array {
        global $USER;
        $params = self::validate_parameters(self::execute_parameters(),
            compact('filters'));

        $context = \context_system::instance();
        self::validate_context($context);
        require_capability('local/airpay_learningpath:enrol', $context);

        $filter_map = self::parse_filters($params['filters']);
        $result = \local_airpay_learningpath\path_audience_enroller::preview(
            $filter_map, (int) $USER->id);
        return $result;
    }

    private static function parse_filters(string $raw): array {
        if (strlen($raw) > 4096) {
            throw new \moodle_exception('filterstoolong', 'local_airpay_learningpath');
        }
        $decoded = json_decode($raw, true, 5);
        if (!is_array($decoded) || json_last_error() !== JSON_ERROR_NONE) {
            return [];
        }
        // Whitelist allowed keys to prevent injection via unknown filter keys.
        $allowed = ['designation', 'region', 'location', 'employmenttype',
                    'grade', 'hrmsrole', 'org_path'];
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
                'Cap on resolved-audience size. If count == capped_at, results were truncated.'),
        ]);
    }
}
