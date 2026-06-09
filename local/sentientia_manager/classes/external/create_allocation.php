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

class create_allocation extends external_api {

    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'userid'   => new external_value(PARAM_INT, 'Target user ID (must be a direct report)'),
            'courseid' => new external_value(PARAM_INT, 'Course ID'),
            'due_date' => new external_value(PARAM_INT, 'Due date Unix ts (0 = none)', VALUE_DEFAULT, 0),
            'note'     => new external_value(PARAM_TEXT, 'Optional note', VALUE_DEFAULT, ''),
        ]);
    }

    public static function execute(int $userid, int $courseid, int $due_date = 0,
                                    string $note = ''): array {
        global $USER;
        $params = self::validate_parameters(self::execute_parameters(),
            compact('userid', 'courseid', 'due_date', 'note'));

        $context = \context_system::instance();
        self::validate_context($context);
        require_capability('local/sentientia_manager:allocate', $context);
        require_sesskey();

        $id = approval_manager::create_allocation(
            (int) $USER->id, (int) $params['userid'], (int) $params['courseid'],
            !empty($params['due_date']) ? (int) $params['due_date'] : null,
            (string) $params['note']);

        return ['id' => $id];
    }

    public static function execute_returns(): external_single_structure {
        return new external_single_structure([
            'id' => new external_value(PARAM_INT, 'New allocation ID'),
        ]);
    }
}
