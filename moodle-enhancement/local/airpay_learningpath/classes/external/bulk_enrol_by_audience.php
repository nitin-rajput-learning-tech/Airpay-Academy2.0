<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

namespace local_airpay_learningpath\external;

defined('MOODLE_INTERNAL') || die();

use core_external\external_api;
use core_external\external_function_parameters;
use core_external\external_single_structure;
use core_external\external_value;

/**
 * P1 #8 (2026-05-16) — enrol every user matching a target audience into
 * a learning path.
 *
 * Pair with `preview_audience` to let admins sanity-check the resolved
 * count before clicking commit.
 *
 * Idempotent — already-enrolled users are silently skipped via the
 * UNIQUE (pathid, userid) index in `local_airpay_learningpath_users`.
 * The returned `enrolled` count is NEW enrolments only.
 *
 * @package local_airpay_learningpath
 */
class bulk_enrol_by_audience extends external_api {

    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'pathid'  => new external_value(PARAM_INT, 'Learning path ID'),
            'filters' => new external_value(PARAM_RAW,
                'JSON filter map (same shape as preview_audience)',
                VALUE_DEFAULT, '{}'),
        ]);
    }

    public static function execute(int $pathid, string $filters = '{}'): array {
        global $USER;
        $params = self::validate_parameters(self::execute_parameters(),
            compact('pathid', 'filters'));

        $context = \context_system::instance();
        self::validate_context($context);
        require_capability('local/airpay_learningpath:enrol', $context);

        $filter_map = self::parse_filters($params['filters']);
        $result = \local_airpay_learningpath\path_audience_enroller::enrol_by_filter(
            (int) $params['pathid'], $filter_map, (int) $USER->id);
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
        $allowed = ['designation', 'region', 'location', 'employmenttype',
                    'grade', 'hrmsrole', 'org_path'];
        return array_intersect_key($decoded, array_flip($allowed));
    }

    public static function execute_returns(): external_single_structure {
        return new external_single_structure([
            'matched'  => new external_value(PARAM_INT, 'Users matching the filter'),
            'enrolled' => new external_value(PARAM_INT,
                'New enrolments inserted (excludes already-enrolled users)'),
            'capped'   => new external_value(PARAM_BOOL,
                'True if audience was capped at MAX_AUDIENCE_SIZE'),
        ]);
    }
}
