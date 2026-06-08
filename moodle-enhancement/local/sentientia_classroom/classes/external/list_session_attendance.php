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
 * List attendance for a session — every roster member with status.
 *
 * Returns the FULL roster (not paginated) — sessions rarely have >100
 * attendees, and the attendance UI needs all of them at once for the
 * "Mark all present" / bulk actions.
 *
 * @package   local_sentientia_classroom
 */
class list_session_attendance extends external_api {

    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'sessionid' => new external_value(PARAM_INT, 'Session ID'),
        ]);
    }

    public static function execute(int $sessionid): array {
        $params = self::validate_parameters(self::execute_parameters(),
            ['sessionid' => $sessionid]);

        $context = \context_system::instance();
        self::validate_context($context);
        require_capability('local/sentientia_classroom:view', $context);

        $records = \local_sentientia_classroom\session_manager::get_session_attendance(
            $params['sessionid']);

        $rows = [];
        foreach ($records as $r) {
            $fullname = trim(($r->firstname ?? '') . ' ' . ($r->lastname ?? ''));
            if (empty($fullname)) { $fullname = $r->email; }

            $rows[] = [
                'userid'       => (int) $r->userid,
                'name'         => $fullname,
                'email'        => (string) $r->email,
                'status'       => (int) $r->status,
                'status_label' => (string) $r->status_label,
                'marked_at'    => $r->marked_at_human ?? '',
                'notes'        => (string) ($r->notes ?? ''),
            ];
        }

        return ['rows' => $rows, 'total' => count($rows)];
    }

    public static function execute_returns(): external_single_structure {
        return new external_single_structure([
            'total' => new external_value(PARAM_INT, 'Total roster'),
            'rows'  => new external_multiple_structure(
                new external_single_structure([
                    'userid'       => new external_value(PARAM_INT,  'User ID'),
                    'name'         => new external_value(PARAM_TEXT, 'Full name'),
                    'email'        => new external_value(PARAM_TEXT, 'Email'),
                    'status'       => new external_value(PARAM_INT,  '0=absent 1=present 2=late 3=excused'),
                    'status_label' => new external_value(PARAM_TEXT, 'Status label'),
                    'marked_at'    => new external_value(PARAM_TEXT, 'When marked'),
                    'notes'        => new external_value(PARAM_TEXT, 'Notes'),
                ])
            ),
        ]);
    }
}
