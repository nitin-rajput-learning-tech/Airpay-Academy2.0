<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

namespace local_sentientia_classroom\external;

defined('MOODLE_INTERNAL') || die();

use core_external\external_api;
use core_external\external_function_parameters;
use core_external\external_single_structure;
use core_external\external_value;

/**
 * Mark a single user's attendance status for a session.
 *
 * Triggered by per-row radio/select clicks in the Attendance UI.
 *
 * @package   local_sentientia_classroom
 */
class mark_session_attendance extends external_api {

    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'sessionid' => new external_value(PARAM_INT,  'Session ID'),
            'userid'    => new external_value(PARAM_INT,  'User ID'),
            'status'    => new external_value(PARAM_INT,  '0=absent 1=present 2=late 3=excused'),
            'notes'     => new external_value(PARAM_TEXT, 'Optional notes', VALUE_DEFAULT, ''),
        ]);
    }

    public static function execute(int $sessionid, int $userid, int $status,
                                    string $notes = ''): array {
        $params = self::validate_parameters(self::execute_parameters(),
            ['sessionid' => $sessionid, 'userid' => $userid,
             'status' => $status, 'notes' => $notes]);

        $context = \context_system::instance();
        self::validate_context($context);
        require_capability('local/sentientia_classroom:attendance', $context);

        $persisted = \local_sentientia_classroom\session_manager::mark_attendance(
            $params['sessionid'], $params['userid'], $params['status'], $params['notes']);

        return [
            'sessionid' => $params['sessionid'],
            'userid'    => $params['userid'],
            'status'    => $persisted,
            'message'   => get_string('attendancemarked', 'local_sentientia_classroom'),
        ];
    }

    public static function execute_returns(): external_single_structure {
        return new external_single_structure([
            'sessionid' => new external_value(PARAM_INT,  'Session ID'),
            'userid'    => new external_value(PARAM_INT,  'User ID'),
            'status'    => new external_value(PARAM_INT,  'Persisted status'),
            'message'   => new external_value(PARAM_TEXT, 'Confirmation'),
        ]);
    }
}
