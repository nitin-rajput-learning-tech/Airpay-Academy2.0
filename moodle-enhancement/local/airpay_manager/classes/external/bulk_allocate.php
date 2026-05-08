<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

namespace local_airpay_manager\external;

defined('MOODLE_INTERNAL') || die();

use core_external\external_api;
use core_external\external_function_parameters;
use core_external\external_multiple_structure;
use core_external\external_single_structure;
use core_external\external_value;
use local_airpay_manager\approval_manager;

class bulk_allocate extends external_api {

    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'userids'  => new external_multiple_structure(
                new external_value(PARAM_INT, 'User ID')),
            'courseid' => new external_value(PARAM_INT, 'Course ID'),
            'due_date' => new external_value(PARAM_INT, 'Due (Unix ts; 0 = none)', VALUE_DEFAULT, 0),
            'note'     => new external_value(PARAM_TEXT, 'Optional note', VALUE_DEFAULT, ''),
        ]);
    }

    public static function execute(array $userids, int $courseid, int $due_date = 0,
                                    string $note = ''): array {
        global $USER;
        $params = self::validate_parameters(self::execute_parameters(),
            compact('userids', 'courseid', 'due_date', 'note'));

        $context = \context_system::instance();
        self::validate_context($context);
        require_capability('local/airpay_manager:allocate', $context);
        require_sesskey();

        $result = approval_manager::bulk_allocate(
            (int) $USER->id, (array) $params['userids'],
            (int) $params['courseid'],
            !empty($params['due_date']) ? (int) $params['due_date'] : null,
            (string) $params['note']);

        return [
            'succeeded_count' => count($result['succeeded']),
            'skipped_count'   => count($result['skipped']),
            'failed_count'    => count($result['failed']),
            'succeeded'       => $result['succeeded'],
            'skipped'         => $result['skipped'],
            'failed'          => $result['failed'],
        ];
    }

    public static function execute_returns(): external_single_structure {
        return new external_single_structure([
            'succeeded_count' => new external_value(PARAM_INT, 'Number succeeded'),
            'skipped_count'   => new external_value(PARAM_INT, 'Number skipped (already had / not direct report)'),
            'failed_count'    => new external_value(PARAM_INT, 'Number with hard failure'),
            'succeeded' => new external_multiple_structure(
                new external_single_structure([
                    'userid'  => new external_value(PARAM_INT, 'User ID'),
                    'allocid' => new external_value(PARAM_INT, 'Allocation row ID'),
                ])
            ),
            'skipped' => new external_multiple_structure(
                new external_single_structure([
                    'userid' => new external_value(PARAM_INT, 'User ID'),
                    'reason' => new external_value(PARAM_TEXT, 'Reason key'),
                ])
            ),
            'failed' => new external_multiple_structure(
                new external_single_structure([
                    'userid' => new external_value(PARAM_INT, 'User ID'),
                    'error'  => new external_value(PARAM_TEXT, 'Error'),
                ])
            ),
        ]);
    }
}
