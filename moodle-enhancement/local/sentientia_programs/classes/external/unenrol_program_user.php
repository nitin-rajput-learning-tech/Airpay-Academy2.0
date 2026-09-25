<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

namespace local_sentientia_programs\external;

defined('MOODLE_INTERNAL') || die();

use core_external\external_api;
use core_external\external_function_parameters;
use core_external\external_single_structure;
use core_external\external_value;

class unenrol_program_user extends external_api {

    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'programid' => new external_value(PARAM_INT, 'Program ID'),
            'userid'    => new external_value(PARAM_INT, 'User ID'),
        ]);
    }

    public static function execute(int $programid, int $userid): array {
        $params = self::validate_parameters(self::execute_parameters(),
            ['programid' => $programid, 'userid' => $userid]);

        $context = \context_system::instance();
        self::validate_context($context);
        require_capability('local/sentientia_programs:enrol', $context);
        // ADR-031: the capability says WHAT; the program must also be in the caller's tenant.
        // The user being removed must be in it too.
        \local_sentientia_programs\program_manager::require_program_access($params['programid']);
        \local_sentientia_platform\tenant::require_same_tenant_user($params['userid']);

        \local_sentientia_programs\program_manager::unenrol_user(
            $params['programid'], $params['userid']);

        return [
            'programid' => $params['programid'],
            'userid'    => $params['userid'],
            'message'   => get_string('userunenrolled', 'local_sentientia_programs'),
        ];
    }

    public static function execute_returns(): external_single_structure {
        return new external_single_structure([
            'programid' => new external_value(PARAM_INT,  ''),
            'userid'    => new external_value(PARAM_INT,  ''),
            'message'   => new external_value(PARAM_TEXT, ''),
        ]);
    }
}
