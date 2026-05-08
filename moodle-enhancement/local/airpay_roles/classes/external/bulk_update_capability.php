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

class bulk_update_capability extends external_api {

    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'roleids'    => new external_multiple_structure(
                new external_value(PARAM_INT, 'Role ID')),
            'capability' => new external_value(PARAM_RAW_TRIMMED, 'Capability name'),
            'permission' => new external_value(PARAM_ALPHAEXT, 'inherit|allow|prevent|prohibit'),
            'reason'     => new external_value(PARAM_TEXT, 'Optional admin justification', VALUE_DEFAULT, ''),
        ]);
    }

    public static function execute(array $roleids, string $capability,
                                    string $permission, string $reason = ''): array {
        $params = self::validate_parameters(self::execute_parameters(),
            compact('roleids', 'capability', 'permission', 'reason'));

        $context = \context_system::instance();
        self::validate_context($context);
        require_capability('local/airpay_roles:manage', $context);
        require_sesskey();

        $reason = mb_substr($params['reason'], 0, 1024);

        $result = role_manager::bulk_update_capability(
            (array) $params['roleids'], (string) $params['capability'],
            (string) $params['permission'], $reason);

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
            'succeeded_count' => new external_value(PARAM_INT, 'Succeeded'),
            'skipped_count'   => new external_value(PARAM_INT, 'Skipped'),
            'failed_count'    => new external_value(PARAM_INT, 'Failed'),
            'succeeded' => new external_multiple_structure(
                new external_single_structure([
                    'roleid'  => new external_value(PARAM_INT, 'Role ID'),
                    'auditid' => new external_value(PARAM_INT, 'Audit log row ID'),
                ])
            ),
            'skipped' => new external_multiple_structure(
                new external_single_structure([
                    'roleid' => new external_value(PARAM_INT, 'Role ID'),
                    'reason' => new external_value(PARAM_TEXT, 'Reason key'),
                ])
            ),
            'failed' => new external_multiple_structure(
                new external_single_structure([
                    'roleid' => new external_value(PARAM_INT, 'Role ID'),
                    'error'  => new external_value(PARAM_TEXT, 'Error'),
                ])
            ),
        ]);
    }
}
