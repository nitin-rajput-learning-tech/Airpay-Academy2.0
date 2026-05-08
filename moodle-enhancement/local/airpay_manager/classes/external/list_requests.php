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

class list_requests extends external_api {

    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'status'  => new external_value(PARAM_ALPHAEXT, 'pending|approved|rejected|cancelled|all', VALUE_DEFAULT, 'pending'),
            'sort'    => new external_value(PARAM_ALPHAEXT, 'Sort col', VALUE_DEFAULT, 'timecreated'),
            'sortdir' => new external_value(PARAM_ALPHA,    'asc|desc', VALUE_DEFAULT, 'desc'),
            'page'    => new external_value(PARAM_INT,      'Page', VALUE_DEFAULT, 0),
            'perpage' => new external_value(PARAM_INT,      'Per-page', VALUE_DEFAULT, 25),
            'filters' => new external_value(PARAM_RAW,      'Reserved', VALUE_DEFAULT, '{}'),
        ]);
    }

    public static function execute(string $status = 'pending', string $sort = 'timecreated',
                                    string $sortdir = 'desc', int $page = 0, int $perpage = 25,
                                    string $filters = '{}'): array {
        global $USER;
        $params = self::validate_parameters(self::execute_parameters(),
            compact('status', 'sort', 'sortdir', 'page', 'perpage', 'filters'));

        $context = \context_system::instance();
        self::validate_context($context);
        require_capability('local/airpay_manager:view', $context);

        if (strlen($params['filters']) > 4096) {
            throw new \moodle_exception('filterstoolong', 'local_airpay_manager');
        }

        $result = approval_manager::list_requests((int) $USER->id,
            $params['status'], (int) $params['page'], (int) $params['perpage']);

        $can_decide = has_capability('local/airpay_manager:approve', $context);
        foreach ($result['rows'] as &$row) {
            $actions = '';
            if ($can_decide && $row['is_pending']) {
                $actions = '<button type="button" class="btn btn-sm btn-success me-1" '
                    . 'data-action="decide-request" data-requestid="' . (int) $row['id'] . '" '
                    . 'data-decision="approved">Approve</button>'
                    . '<button type="button" class="btn btn-sm btn-outline-danger" '
                    . 'data-action="decide-request" data-requestid="' . (int) $row['id'] . '" '
                    . 'data-decision="rejected">Reject</button>';
            }
            $row['actions'] = $actions;
            $row['statuscss'] = match ($row['status']) {
                'approved'  => 'badge bg-success',
                'rejected'  => 'badge bg-danger',
                'cancelled' => 'badge bg-secondary',
                default     => 'badge bg-warning text-dark',
            };
        }
        unset($row);

        return $result;
    }

    public static function execute_returns(): external_single_structure {
        return new external_single_structure([
            'total'   => new external_value(PARAM_INT, 'Total'),
            'rows'    => new external_multiple_structure(
                new external_single_structure([
                    'id'              => new external_value(PARAM_INT, 'Request ID'),
                    'userid'          => new external_value(PARAM_INT, 'Requester'),
                    'username'        => new external_value(PARAM_TEXT, 'Requester full name'),
                    'courseid'        => new external_value(PARAM_INT, 'Course ID'),
                    'coursename'      => new external_value(PARAM_TEXT, 'Course name'),
                    'status'          => new external_value(PARAM_TEXT, 'Status'),
                    'statuscss'       => new external_value(PARAM_TEXT, 'Badge CSS'),
                    'reason'          => new external_value(PARAM_RAW,  'Requester reason'),
                    'decision_reason' => new external_value(PARAM_RAW,  'Manager note'),
                    'timecreated'     => new external_value(PARAM_INT,  'Created (ts)'),
                    'when'            => new external_value(PARAM_TEXT, 'Localised when'),
                    'is_pending'      => new external_value(PARAM_BOOL, 'Pending'),
                    'actions'         => new external_value(PARAM_RAW,  'Actions HTML'),
                ])
            ),
            'page'    => new external_value(PARAM_INT, 'Page'),
            'perpage' => new external_value(PARAM_INT, 'Per-page'),
        ]);
    }
}
