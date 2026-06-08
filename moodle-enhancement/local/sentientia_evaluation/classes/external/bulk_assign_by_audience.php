<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

namespace local_sentientia_evaluation\external;

defined('MOODLE_INTERNAL') || die();

use core_external\external_api;
use core_external\external_function_parameters;
use core_external\external_single_structure;
use core_external\external_value;

/**
 * P1 #39 (2026-05-20) — commit a bulk assignment for the previewed audience.
 *
 * Returns counts: matched (audience size), assigned (newly inserted —
 * pre-existing assignments dedupe silently), capped (true if audience
 * hit MAX_AUDIENCE_SIZE).
 *
 * @package local_sentientia_evaluation
 */
class bulk_assign_by_audience extends external_api {

    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'evaluationid' => new external_value(PARAM_INT, 'Evaluation ID'),
            'filters'      => new external_value(PARAM_RAW,
                'JSON map (same shape as preview_audience)',
                VALUE_DEFAULT, '{}'),
            'due_at'       => new external_value(PARAM_INT,
                'Optional deadline timestamp (0 = no due date)',
                VALUE_DEFAULT, 0),
        ]);
    }

    public static function execute(int $evaluationid, string $filters = '{}',
                                     int $due_at = 0): array {
        global $USER;
        $params = self::validate_parameters(self::execute_parameters(),
            compact('evaluationid', 'filters', 'due_at'));

        $context = \context_system::instance();
        self::validate_context($context);
        require_capability('local/sentientia_evaluation:manage', $context);
        require_sesskey();

        $map = self::parse_filters($params['filters']);

        return \local_sentientia_evaluation\evaluation_audience_assigner::assign_by_filter(
            (int) $params['evaluationid'], $map, (int) $USER->id,
            $params['due_at'] > 0 ? (int) $params['due_at'] : null);
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
            'matched'  => new external_value(PARAM_INT,
                'Number of users matched by the audience filter'),
            'assigned' => new external_value(PARAM_INT,
                'Number of NEW assignment rows inserted '
                . '(pre-existing assignments are silently deduped)'),
            'capped'   => new external_value(PARAM_BOOL,
                'True if the audience hit MAX_AUDIENCE_SIZE'),
        ]);
    }
}
