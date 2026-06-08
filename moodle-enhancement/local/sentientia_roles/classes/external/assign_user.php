<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

namespace local_sentientia_roles\external;

defined('MOODLE_INTERNAL') || die();

use core_external\external_api;
use core_external\external_function_parameters;
use core_external\external_single_structure;
use core_external\external_value;
use local_sentientia_roles\role_manager;

class assign_user extends external_api {

    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'roleid' => new external_value(PARAM_INT,  'Role ID'),
            'userid' => new external_value(PARAM_INT,  'User ID'),
            'reason' => new external_value(PARAM_TEXT, 'Optional reason', VALUE_DEFAULT, ''),
        ]);
    }

    public static function execute(int $roleid, int $userid, string $reason = ''): array {
        $params = self::validate_parameters(self::execute_parameters(),
            compact('roleid', 'userid', 'reason'));

        $context = \context_system::instance();
        self::validate_context($context);
        require_capability('local/sentientia_roles:assign', $context);
        require_sesskey();

        $id = role_manager::assign_user_to_role(
            (int) $params['roleid'], (int) $params['userid'], (string) $params['reason']);
        return ['assignmentid' => $id];
    }

    public static function execute_returns(): external_single_structure {
        return new external_single_structure([
            'assignmentid' => new external_value(PARAM_INT, 'Assignment ID'),
        ]);
    }
}
