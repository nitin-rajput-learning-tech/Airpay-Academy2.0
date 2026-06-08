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

class decide_request extends external_api {

    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'requestid'       => new external_value(PARAM_INT, 'Request ID'),
            'decision'        => new external_value(PARAM_ALPHAEXT, 'approved|rejected'),
            'decision_reason' => new external_value(PARAM_TEXT, 'Optional note', VALUE_DEFAULT, ''),
        ]);
    }

    public static function execute(int $requestid, string $decision,
                                    string $decision_reason = ''): array {
        global $USER, $DB;
        $params = self::validate_parameters(self::execute_parameters(),
            compact('requestid', 'decision', 'decision_reason'));

        $context = \context_system::instance();
        self::validate_context($context);
        require_capability('local/sentientia_manager:approve', $context);
        require_sesskey();

        // Tenant gate: only the assigned manager (or siteadmin) can decide.
        $row = $DB->get_record('local_sentientia_mgr_requests',
            ['id' => $params['requestid']], '*', MUST_EXIST);
        if (!is_siteadmin() && (int) $row->managerid !== (int) $USER->id) {
            throw new \required_capability_exception($context,
                'local/sentientia_manager:approve', 'nopermissions',
                'local_sentientia_manager');
        }

        return approval_manager::decide_request(
            (int) $params['requestid'], (string) $params['decision'],
            (string) $params['decision_reason'], (int) $USER->id);
    }

    public static function execute_returns(): external_single_structure {
        return new external_single_structure([
            'requestid'    => new external_value(PARAM_INT, 'Request ID'),
            'decision'     => new external_value(PARAM_TEXT, 'Decision applied'),
            'enrolwarning' => new external_value(PARAM_RAW, 'Enrol warning if any'),
        ]);
    }
}
