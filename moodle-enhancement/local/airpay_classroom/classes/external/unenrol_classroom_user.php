<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

namespace local_airpay_classroom\external;

defined('MOODLE_INTERNAL') || die();

use core_external\external_api;
use core_external\external_function_parameters;
use core_external\external_single_structure;
use core_external\external_value;

/**
 * Remove a user from a classroom roster.
 *
 * Cascades: also clears any attendance records this user has in this
 * classroom's sessions.
 *
 * @package   local_airpay_classroom
 */
class unenrol_classroom_user extends external_api {

    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'classroomid' => new external_value(PARAM_INT, 'Classroom ID'),
            'userid'      => new external_value(PARAM_INT, 'User ID'),
        ]);
    }

    public static function execute(int $classroomid, int $userid): array {
        $params = self::validate_parameters(self::execute_parameters(),
            ['classroomid' => $classroomid, 'userid' => $userid]);

        $context = \context_system::instance();
        self::validate_context($context);
        require_capability('local/airpay_classroom:update', $context);

        \local_airpay_classroom\session_manager::unenrol_user(
            $params['classroomid'], $params['userid']);

        return [
            'classroomid' => $params['classroomid'],
            'userid'      => $params['userid'],
            'message'     => get_string('userunenrolled', 'local_airpay_classroom'),
        ];
    }

    public static function execute_returns(): external_single_structure {
        return new external_single_structure([
            'classroomid' => new external_value(PARAM_INT,  'Classroom ID'),
            'userid'      => new external_value(PARAM_INT,  'User ID'),
            'message'     => new external_value(PARAM_TEXT, 'Confirmation'),
        ]);
    }
}
