<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

namespace local_sentientia_classroom\external;

defined('MOODLE_INTERNAL') || die();

use core_external\external_api;
use core_external\external_function_parameters;
use core_external\external_single_structure;
use core_external\external_value;

class waitlist_join extends external_api {
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'classroomid' => new external_value(PARAM_INT, ''),
        ]);
    }
    public static function execute(int $classroomid): array {
        global $USER;
        $params = self::validate_parameters(self::execute_parameters(), compact('classroomid'));
        $ctx = \context_system::instance();
        self::validate_context($ctx);
        require_capability('local/sentientia_classroom:view', $ctx);
        // ADR-036 D9 (review fix 2026-11-20): an external trainer is never a learner, so never joins a
        // waiting list. Their role holds :view, and without this they could join any classroom of the
        // tenant by id, learn that it exists and its queue position, and be auto-promoted into it as a
        // learner, which opens its calendar file, logo and my.php to them. Refused before the classroom
        // is loaded, so every id (missing, another tenant's, this tenant's) gets the same answer.
        if (\local_sentientia_classroom\session_manager::is_external_trainer((int) $USER->id)) {
            throw new \moodle_exception('error_notyourclassroom', 'local_sentientia_classroom');
        }
        // ADR-031: the capability says WHAT; the classroom must also be in the caller's tenant.
        \local_sentientia_classroom\session_manager::require_classroom_access((int) $params['classroomid']);

        $row = \local_sentientia_classroom\waitlist_manager::join(
            (int) $params['classroomid'], (int) $USER->id);
        return [
            'success'  => true,
            'position' => (int) $row->position,
            'status'   => $row->status,
        ];
    }
    public static function execute_returns(): external_single_structure {
        return new external_single_structure([
            'success'  => new external_value(PARAM_BOOL, ''),
            'position' => new external_value(PARAM_INT, ''),
            'status'   => new external_value(PARAM_ALPHANUMEXT, ''),
        ]);
    }
}
