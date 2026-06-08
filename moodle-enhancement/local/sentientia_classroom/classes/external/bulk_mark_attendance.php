<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

namespace local_sentientia_classroom\external;

defined('MOODLE_INTERNAL') || die();

use core_external\external_api;
use core_external\external_function_parameters;
use core_external\external_multiple_structure;
use core_external\external_single_structure;
use core_external\external_value;

/**
 * Bulk-mark attendance for many users in one go.
 *
 * Backs "Mark all present", "Save attendance", and any UI that submits the
 * full attendance grid in one shot. Capped at 1000 marks per call.
 *
 * @package   local_sentientia_classroom
 */
class bulk_mark_attendance extends external_api {

    private const MAX_MARKS = 1000;

    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'sessionid' => new external_value(PARAM_INT, 'Session ID'),
            'marks'     => new external_multiple_structure(
                new external_single_structure([
                    'userid' => new external_value(PARAM_INT, 'User ID'),
                    'status' => new external_value(PARAM_INT, '0=absent 1=present 2=late 3=excused'),
                    'notes'  => new external_value(PARAM_TEXT, 'Notes', VALUE_DEFAULT, ''),
                ]),
                'Attendance marks'
            ),
        ]);
    }

    public static function execute(int $sessionid, array $marks): array {
        $params = self::validate_parameters(self::execute_parameters(),
            ['sessionid' => $sessionid, 'marks' => $marks]);

        $context = \context_system::instance();
        self::validate_context($context);
        require_capability('local/sentientia_classroom:attendance', $context);

        if (count($params['marks']) > self::MAX_MARKS) {
            throw new \moodle_exception('toomanymarks', 'local_sentientia_classroom');
        }

        $count = \local_sentientia_classroom\session_manager::bulk_mark_attendance(
            $params['sessionid'], $params['marks']);

        return [
            'sessionid' => $params['sessionid'],
            'marked'    => $count,
            'message'   => $count . ' ' . ($count === 1 ? 'attendance' : 'attendances') . ' saved.',
        ];
    }

    public static function execute_returns(): external_single_structure {
        return new external_single_structure([
            'sessionid' => new external_value(PARAM_INT,  'Session ID'),
            'marked'    => new external_value(PARAM_INT,  'Rows persisted'),
            'message'   => new external_value(PARAM_TEXT, 'Confirmation'),
        ]);
    }
}
