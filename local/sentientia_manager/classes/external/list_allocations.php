<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

namespace local_sentientia_manager\external;

defined('MOODLE_INTERNAL') || die();

use core_external\external_api;
use core_external\external_function_parameters;
use core_external\external_multiple_structure;
use core_external\external_single_structure;
use core_external\external_value;
use local_sentientia_manager\approval_manager;

class list_allocations extends external_api {

    public static function execute_parameters(): external_function_parameters {
        // Goal A audit Bug #10 (2026-05-22): align with the shared
        // theme_sentientia/datatable client contract — same as list_requests.
        return new external_function_parameters([
            'search'  => new external_value(PARAM_TEXT,     'Free-text search', VALUE_DEFAULT, ''),
            'status'  => new external_value(PARAM_ALPHAEXT, 'all|assigned|in_progress|completed|overdue|cancelled', VALUE_DEFAULT, 'all'),
            'sort'    => new external_value(PARAM_ALPHAEXT, 'Sort col', VALUE_DEFAULT, 'timecreated'),
            'sortdir' => new external_value(PARAM_ALPHA,    'asc|desc', VALUE_DEFAULT, 'desc'),
            'page'    => new external_value(PARAM_INT,      'Page', VALUE_DEFAULT, 0),
            'perpage' => new external_value(PARAM_INT,      'Per-page', VALUE_DEFAULT, 25),
            'filters' => new external_value(PARAM_RAW,      'Reserved', VALUE_DEFAULT, '{}'),
        ]);
    }

    public static function execute(string $search = '', string $status = 'all',
                                    string $sort = 'timecreated', string $sortdir = 'desc',
                                    int $page = 0, int $perpage = 25,
                                    string $filters = '{}'): array {
        global $USER;
        $params = self::validate_parameters(self::execute_parameters(),
            compact('search', 'status', 'sort', 'sortdir', 'page', 'perpage', 'filters'));

        $context = \context_system::instance();
        self::validate_context($context);
        // Bug fix 2026-05-22 (Goal A audit Bug #9b — corollary of Bug #9):
        // use supervisor-aware helper at WS layer; otherwise the page loads
        // (page-level check uses team_manager) but the AJAX rejects.
        \local_sentientia_manager\team_manager::require_manage();

        if (strlen($params['filters']) > 4096) {
            throw new \moodle_exception('filterstoolong', 'local_sentientia_manager');
        }

        $result = approval_manager::list_allocations((int) $USER->id,
            $params['status'], (int) $params['page'], (int) $params['perpage'],
            (string) $params['search']);

        $can_allocate = has_capability('local/sentientia_manager:allocate', $context);
        foreach ($result['rows'] as &$row) {
            $row['actions'] = $can_allocate
                ? '<button type="button" class="btn btn-sm btn-link text-danger p-1" '
                  . 'data-action="delete-allocation" data-allocid="' . (int) $row['id'] . '" '
                  . 'data-name="' . s($row['coursename']) . '" title="Cancel allocation">'
                  . '<i class="fa fa-trash"></i></button>'
                : '';
            $row['statuscss'] = match ($row['status']) {
                'completed'   => 'badge bg-success',
                'in_progress' => 'badge bg-info text-dark',
                'overdue'     => 'badge bg-danger',
                'cancelled'   => 'badge bg-secondary',
                default       => 'badge bg-warning text-dark',
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
                    'id'         => new external_value(PARAM_INT, 'Allocation ID'),
                    'userid'     => new external_value(PARAM_INT, 'User ID'),
                    'username'   => new external_value(PARAM_TEXT, 'User full name'),
                    'courseid'   => new external_value(PARAM_INT, 'Course ID'),
                    'coursename' => new external_value(PARAM_TEXT, 'Course name'),
                    'status'     => new external_value(PARAM_TEXT, 'Status'),
                    'statuscss'  => new external_value(PARAM_TEXT, 'Status badge CSS'),
                    'due_date'   => new external_value(PARAM_INT,  'Due (ts)'),
                    'due_label'  => new external_value(PARAM_TEXT, 'Due (localised)'),
                    'note'       => new external_value(PARAM_RAW,  'Note'),
                    'timecreated' => new external_value(PARAM_INT, 'Created'),
                    'actions'    => new external_value(PARAM_RAW,  'Actions HTML'),
                ])
            ),
            'page'    => new external_value(PARAM_INT, 'Page'),
            'perpage' => new external_value(PARAM_INT, 'Per-page'),
        ]);
    }
}
