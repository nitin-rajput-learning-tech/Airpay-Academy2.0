<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

namespace local_sentientia_manager\external;

defined('MOODLE_INTERNAL') || die();

use core_external\external_api;
use core_external\external_function_parameters;
use core_external\external_single_structure;
use core_external\external_value;
use local_sentientia_manager\approval_manager;

class delete_allocation extends external_api {

    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'id' => new external_value(PARAM_INT, 'Allocation ID'),
        ]);
    }

    public static function execute(int $id): array {
        global $USER, $DB;
        $params = self::validate_parameters(self::execute_parameters(), compact('id'));

        $context = \context_system::instance();
        self::validate_context($context);
        require_capability('local/sentientia_manager:allocate', $context);
        require_sesskey();

        // Tenant gate: only owner manager (or siteadmin) can cancel.
        $row = $DB->get_record('local_sentientia_mgr_allocations',
            ['id' => $params['id']], '*', MUST_EXIST);
        if (!is_siteadmin() && (int) $row->managerid !== (int) $USER->id) {
            throw new \required_capability_exception($context,
                'local/sentientia_manager:allocate', 'nopermissions',
                'local_sentientia_manager');
        }

        approval_manager::delete_allocation((int) $params['id']);
        return ['deleted' => true];
    }

    public static function execute_returns(): external_single_structure {
        return new external_single_structure([
            'deleted' => new external_value(PARAM_BOOL, 'Deleted'),
        ]);
    }
}
