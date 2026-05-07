<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

namespace local_airpay_roles\external;

defined('MOODLE_INTERNAL') || die();

use core_external\external_api;
use core_external\external_function_parameters;
use core_external\external_single_structure;
use core_external\external_value;
use local_airpay_roles\role_manager;

/**
 * Set a capability permission on a role + write audit log.
 */
class update_capability extends external_api {

    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'roleid'     => new external_value(PARAM_INT,      'Role ID'),
            'capability' => new external_value(PARAM_RAW_TRIMMED, 'Capability name'),
            'permission' => new external_value(PARAM_ALPHAEXT, 'inherit|allow|prevent|prohibit'),
            'reason'     => new external_value(PARAM_TEXT,     'Optional admin justification', VALUE_DEFAULT, ''),
        ]);
    }

    public static function execute(int $roleid, string $capability, string $permission,
                                    string $reason = ''): array {
        $params = self::validate_parameters(self::execute_parameters(),
            compact('roleid', 'capability', 'permission', 'reason'));

        $context = \context_system::instance();
        self::validate_context($context);
        require_capability('local/airpay_roles:manage', $context);
        require_sesskey();

        // Cap reason to 1KB — audit log shouldn't store essays.
        $reason = mb_substr($params['reason'], 0, 1024);

        return role_manager::update_capability(
            (int) $params['roleid'],
            $params['capability'],
            $params['permission'],
            $reason
        );
    }

    public static function execute_returns(): external_single_structure {
        return new external_single_structure([
            'id'             => new external_value(PARAM_INT, 'Audit log entry ID'),
            'roleid'         => new external_value(PARAM_INT, 'Role ID'),
            'capability'     => new external_value(PARAM_TEXT, 'Capability'),
            'oldpermission'  => new external_value(PARAM_INT, 'Old permission as int'),
            'newpermission'  => new external_value(PARAM_INT, 'New permission as int'),
            'oldlabel'       => new external_value(PARAM_ALPHA, 'Old permission label'),
            'newlabel'       => new external_value(PARAM_ALPHA, 'New permission label'),
            'changedby'      => new external_value(PARAM_INT, 'User ID who changed'),
            'timecreated'    => new external_value(PARAM_INT, 'Unix timestamp'),
        ]);
    }
}
