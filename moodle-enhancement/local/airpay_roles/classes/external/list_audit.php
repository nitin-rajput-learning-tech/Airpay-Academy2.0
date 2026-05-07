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
 * List audit log entries with filters.
 */
class list_audit extends external_api {

    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'roleid'     => new external_value(PARAM_INT, 'Role filter (0 = all)', VALUE_DEFAULT, 0),
            'action'     => new external_value(PARAM_ALPHAEXT, 'Action filter ("" = all)', VALUE_DEFAULT, ''),
            'capability' => new external_value(PARAM_RAW_TRIMMED, 'Capability filter', VALUE_DEFAULT, ''),
            'sort'       => new external_value(PARAM_ALPHAEXT, 'Sort col (ignored — always timecreated DESC)', VALUE_DEFAULT, 'timecreated'),
            'sortdir'    => new external_value(PARAM_ALPHA, 'Ignored', VALUE_DEFAULT, 'desc'),
            'page'       => new external_value(PARAM_INT, 'Page (0-based)', VALUE_DEFAULT, 0),
            'perpage'    => new external_value(PARAM_INT, 'Per-page', VALUE_DEFAULT, 50),
            'filters'    => new external_value(PARAM_RAW, 'Reserved JSON blob', VALUE_DEFAULT, '{}'),
        ]);
    }

    public static function execute(int $roleid = 0, string $action = '', string $capability = '',
                                    string $sort = 'timecreated', string $sortdir = 'desc',
                                    int $page = 0, int $perpage = 50, string $filters = '{}'): array {
        $params = self::validate_parameters(self::execute_parameters(),
            compact('roleid', 'action', 'capability', 'sort', 'sortdir', 'page', 'perpage', 'filters'));

        $context = \context_system::instance();
        self::validate_context($context);
        require_capability('local/airpay_roles:audit', $context);

        if (strlen($params['filters']) > 4096) {
            throw new \moodle_exception('err_filterstoolong', 'local_airpay_roles');
        }

        $result = role_manager::list_audit(
            (int) $params['roleid'],
            $params['action'],
            $params['capability'],
            (int) $params['page'],
            (int) $params['perpage']
        );

        // Build a small "old → new" change cell for the table.
        foreach ($result['rows'] as &$r) {
            if ($r['oldpermission'] !== null && $r['newpermission'] !== null) {
                $r['change'] = s($r['oldlabel']) . ' → ' . s($r['newlabel']);
            } else {
                $r['change'] = '—';
            }
            $r['action_label'] = get_string('audit_action_' . $r['action'], 'local_airpay_roles');
        }
        unset($r);

        return $result;
    }

    public static function execute_returns(): external_single_structure {
        return new external_single_structure([
            'total'   => new external_value(PARAM_INT, 'Total rows'),
            'rows'    => new external_multiple_structure(
                new external_single_structure([
                    'id'             => new external_value(PARAM_INT,  'Entry ID'),
                    'roleid'         => new external_value(PARAM_INT,  'Role ID'),
                    'roleshortname'  => new external_value(PARAM_TEXT, 'Role shortname snapshot'),
                    'action'         => new external_value(PARAM_TEXT, 'Action key'),
                    'action_label'   => new external_value(PARAM_RAW,  'Action localised label'),
                    'capability'     => new external_value(PARAM_TEXT, 'Capability or ""'),
                    'oldpermission'  => new external_value(PARAM_INT,  'Old perm or null', VALUE_OPTIONAL, null, NULL_ALLOWED),
                    'newpermission'  => new external_value(PARAM_INT,  'New perm or null', VALUE_OPTIONAL, null, NULL_ALLOWED),
                    'oldlabel'       => new external_value(PARAM_RAW,  'Old perm label'),
                    'newlabel'       => new external_value(PARAM_RAW,  'New perm label'),
                    'change'         => new external_value(PARAM_RAW,  'Old → new change cell'),
                    'changedby'      => new external_value(PARAM_INT,  'User ID who changed'),
                    'changedby_name' => new external_value(PARAM_TEXT, 'Full name'),
                    'reason'         => new external_value(PARAM_TEXT, 'Reason'),
                    'timecreated'    => new external_value(PARAM_INT,  'Unix timestamp'),
                    'when'           => new external_value(PARAM_TEXT, 'Localised when'),
                ])
            ),
            'page'    => new external_value(PARAM_INT, 'Current page'),
            'perpage' => new external_value(PARAM_INT, 'Per-page'),
        ]);
    }
}
