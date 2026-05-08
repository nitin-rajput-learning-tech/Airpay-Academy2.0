<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

namespace local_airpay_roles\external;

defined('MOODLE_INTERNAL') || die();

use core_external\external_api;
use core_external\external_function_parameters;
use core_external\external_multiple_structure;
use core_external\external_single_structure;
use core_external\external_value;
use local_airpay_roles\role_manager;

class list_role_assignments extends external_api {

    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'roleid'  => new external_value(PARAM_INT,  'Role ID'),
            'search'  => new external_value(PARAM_TEXT, 'User search', VALUE_DEFAULT, ''),
            'sort'    => new external_value(PARAM_ALPHAEXT, 'Sort col', VALUE_DEFAULT, 'lastname'),
            'sortdir' => new external_value(PARAM_ALPHA, 'asc|desc', VALUE_DEFAULT, 'asc'),
            'page'    => new external_value(PARAM_INT,  'Page', VALUE_DEFAULT, 0),
            'perpage' => new external_value(PARAM_INT,  'Per-page', VALUE_DEFAULT, 25),
            'filters' => new external_value(PARAM_RAW,  'Reserved', VALUE_DEFAULT, '{}'),
        ]);
    }

    public static function execute(int $roleid, string $search = '', string $sort = 'lastname',
                                    string $sortdir = 'asc', int $page = 0, int $perpage = 25,
                                    string $filters = '{}'): array {
        $params = self::validate_parameters(self::execute_parameters(),
            compact('roleid', 'search', 'sort', 'sortdir', 'page', 'perpage', 'filters'));

        $context = \context_system::instance();
        self::validate_context($context);
        require_capability('local/airpay_roles:view', $context);

        if (strlen($params['filters']) > 4096) {
            throw new \moodle_exception('err_filterstoolong', 'local_airpay_roles');
        }

        $result = role_manager::list_role_assignments(
            (int) $params['roleid'], (string) $params['search'],
            (int) $params['page'], (int) $params['perpage']);

        $can_assign = has_capability('local/airpay_roles:assign', $context);
        foreach ($result['rows'] as &$row) {
            $row['actions'] = $can_assign
                ? '<button type="button" class="btn btn-sm btn-link text-danger p-1" '
                  . 'data-action="unassign-user" data-roleid="' . (int) $params['roleid'] . '" '
                  . 'data-userid="' . (int) $row['userid'] . '" '
                  . 'data-name="' . s($row['fullname']) . '">'
                  . '<i class="fa fa-user-times"></i></button>'
                : '';
            $row['statuscss'] = $row['suspended'] ? 'badge bg-secondary' : 'badge bg-success';
            $row['statuslabel'] = $row['suspended'] ? 'Suspended' : 'Active';
        }
        unset($row);

        return $result;
    }

    public static function execute_returns(): external_single_structure {
        return new external_single_structure([
            'total'   => new external_value(PARAM_INT, 'Total'),
            'rows'    => new external_multiple_structure(
                new external_single_structure([
                    'id'          => new external_value(PARAM_INT,  'Assignment ID'),
                    'userid'      => new external_value(PARAM_INT,  'User ID'),
                    'fullname'    => new external_value(PARAM_TEXT, 'Full name'),
                    'email'       => new external_value(PARAM_TEXT, 'Email'),
                    'suspended'   => new external_value(PARAM_BOOL, 'Suspended'),
                    'statuslabel' => new external_value(PARAM_TEXT, 'Status label'),
                    'statuscss'   => new external_value(PARAM_TEXT, 'Status badge CSS'),
                    'assigned_at' => new external_value(PARAM_TEXT, 'When assigned'),
                    'actions'     => new external_value(PARAM_RAW,  'Actions HTML'),
                ])
            ),
            'page'    => new external_value(PARAM_INT, 'Page'),
            'perpage' => new external_value(PARAM_INT, 'Per-page'),
        ]);
    }
}
