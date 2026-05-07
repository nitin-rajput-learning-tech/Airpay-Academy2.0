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

/**
 * Get the capability list for a role with current permissions.
 */
class get_role_caps extends external_api {

    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'roleid'  => new external_value(PARAM_INT,      'Role ID'),
            'search'  => new external_value(PARAM_TEXT,     'Substring filter on capability name', VALUE_DEFAULT, ''),
            'perm'    => new external_value(PARAM_ALPHAEXT, 'Permission filter (all|inherit|allow|prevent|prohibit)', VALUE_DEFAULT, 'all'),
            'sort'    => new external_value(PARAM_ALPHAEXT, 'Sort column', VALUE_DEFAULT, 'capability'),
            'sortdir' => new external_value(PARAM_ALPHA,    'asc|desc', VALUE_DEFAULT, 'asc'),
            'page'    => new external_value(PARAM_INT,      'Page (0-based)', VALUE_DEFAULT, 0),
            'perpage' => new external_value(PARAM_INT,      'Per-page', VALUE_DEFAULT, 50),
            'filters' => new external_value(PARAM_RAW,      'Reserved JSON blob', VALUE_DEFAULT, '{}'),
        ]);
    }

    public static function execute(int $roleid, string $search = '', string $perm = 'all',
                                    string $sort = 'capability', string $sortdir = 'asc',
                                    int $page = 0, int $perpage = 50, string $filters = '{}'): array {
        $params = self::validate_parameters(self::execute_parameters(),
            compact('roleid', 'search', 'perm', 'sort', 'sortdir', 'page', 'perpage', 'filters'));

        $context = \context_system::instance();
        self::validate_context($context);
        require_capability('local/airpay_roles:view', $context);

        if (strlen($params['filters']) > 4096) {
            throw new \moodle_exception('err_filterstoolong', 'local_airpay_roles');
        }

        $result = role_manager::get_role_caps((int) $params['roleid'], $params['search'],
            $params['perm'], (int) $params['page'], (int) $params['perpage']);

        // Build a permission-select cell + reset action for each row.
        $can_manage = has_capability('local/airpay_roles:manage', $context);
        foreach ($result['rows'] as &$row) {
            $perm = (int) $row['permission'];
            $row['perm_inherit']  = $perm === CAP_INHERIT;
            $row['perm_allow']    = $perm === CAP_ALLOW;
            $row['perm_prevent']  = $perm === CAP_PREVENT;
            $row['perm_prohibit'] = $perm === CAP_PROHIBIT;
            // Per-row actions.
            $actions = [];
            if ($can_manage) {
                $actions[] = '<button type="button" class="btn btn-sm btn-link p-1" '
                    . 'data-action="edit-cap" '
                    . 'data-roleid="' . (int) $params['roleid'] . '" '
                    . 'data-capability="' . s($row['capability']) . '" '
                    . 'data-current="' . (int) $row['permission'] . '" '
                    . 'title="Edit"><i class="fa fa-pencil"></i></button>';
                if ($perm !== CAP_INHERIT) {
                    $actions[] = '<button type="button" class="btn btn-sm btn-link text-danger p-1" '
                        . 'data-action="reset-cap" '
                        . 'data-roleid="' . (int) $params['roleid'] . '" '
                        . 'data-capability="' . s($row['capability']) . '" '
                        . 'title="Reset to inherit"><i class="fa fa-undo"></i></button>';
                }
            }
            $row['actions'] = implode(' ', $actions);
            $row['perm_label'] = get_string('cap_perm_' . $row['permission_label'], 'local_airpay_roles');
            $row['perm_css']   = match ((int) $row['permission']) {
                CAP_ALLOW    => 'badge bg-success',
                CAP_PREVENT  => 'badge bg-warning text-dark',
                CAP_PROHIBIT => 'badge bg-danger',
                default      => 'badge bg-secondary',
            };
            $row['risks_html'] = empty($row['risks'])
                ? '—'
                : implode(' ', array_map(fn($r) => '<span class="badge bg-light text-dark border">' . s($r) . '</span>', $row['risks']));
        }
        unset($row);

        return $result;
    }

    public static function execute_returns(): external_single_structure {
        return new external_single_structure([
            'total'   => new external_value(PARAM_INT, 'Total rows'),
            'rows'    => new external_multiple_structure(
                new external_single_structure([
                    'capability'       => new external_value(PARAM_TEXT, 'Capability name'),
                    'component'        => new external_value(PARAM_TEXT, 'Owning component'),
                    'permission'       => new external_value(PARAM_INT,  'Permission as int'),
                    'permission_label' => new external_value(PARAM_ALPHA, 'inherit|allow|prevent|prohibit'),
                    'perm_label'       => new external_value(PARAM_RAW,  'Localised label'),
                    'perm_css'         => new external_value(PARAM_RAW,  'Badge CSS class'),
                    'perm_inherit'     => new external_value(PARAM_BOOL, 'Is inherit'),
                    'perm_allow'       => new external_value(PARAM_BOOL, 'Is allow'),
                    'perm_prevent'     => new external_value(PARAM_BOOL, 'Is prevent'),
                    'perm_prohibit'    => new external_value(PARAM_BOOL, 'Is prohibit'),
                    'risks'            => new external_multiple_structure(
                        new external_value(PARAM_TEXT, 'Risk label')),
                    'risks_text'       => new external_value(PARAM_TEXT, 'Risks comma-list'),
                    'risks_html'       => new external_value(PARAM_RAW,  'Risks badges HTML'),
                    'actions'          => new external_value(PARAM_RAW,  'Per-row action HTML'),
                ])
            ),
            'page'    => new external_value(PARAM_INT, 'Current page'),
            'perpage' => new external_value(PARAM_INT, 'Per-page'),
        ]);
    }
}
