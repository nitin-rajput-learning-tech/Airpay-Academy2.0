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
 * List enrolled users (roster) for a classroom — Users tab datatable.
 *
 * @package   local_sentientia_classroom
 */
class list_classroom_users extends external_api {

    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'classroomid' => new external_value(PARAM_INT,      'Classroom ID'),
            'search'      => new external_value(PARAM_TEXT,     'Search term', VALUE_DEFAULT, ''),
            'sort'        => new external_value(PARAM_ALPHAEXT, 'Sort column', VALUE_DEFAULT, 'lastname'),
            'sortdir'     => new external_value(PARAM_ALPHA,    'asc|desc',    VALUE_DEFAULT, 'asc'),
            'page'        => new external_value(PARAM_INT,      'Page',        VALUE_DEFAULT, 0),
            'perpage'     => new external_value(PARAM_INT,      'Per page',    VALUE_DEFAULT, 25),
            'filters'     => new external_value(PARAM_RAW,      'JSON filters', VALUE_DEFAULT, '{}'),
        ]);
    }

    public static function execute(int $classroomid, string $search = '', string $sort = 'lastname',
                                    string $sortdir = 'asc', int $page = 0, int $perpage = 25,
                                    string $filters = '{}'): array {
        global $DB;

        $params = self::validate_parameters(self::execute_parameters(),
            compact('classroomid', 'search', 'sort', 'sortdir', 'page', 'perpage', 'filters'));

        $context = \context_system::instance();
        self::validate_context($context);
        require_capability('local/sentientia_classroom:view', $context);

        $can_update = has_capability('local/sentientia_classroom:update', $context)
            || has_capability('local/sentientia_classroom:manage', $context);

        $DB->get_record('local_sentientia_classroom', ['id' => $params['classroomid']],
            'id', MUST_EXIST);

        $total = \local_sentientia_classroom\session_manager::count_enrolled_filtered(
            $params['classroomid'], $params['search']);

        $rows = [];
        if ($total > 0) {
            $records = \local_sentientia_classroom\session_manager::get_enrolled_users(
                $params['classroomid'], $params['search'],
                $params['sort'], $params['sortdir'],
                $params['page'] * $params['perpage'], $params['perpage']);

            foreach ($records as $r) {
                $fullname = trim(($r->firstname ?? '') . ' ' . ($r->lastname ?? ''));
                if (empty($fullname)) { $fullname = $r->email; }

                $userurl = (new \moodle_url('/user/profile.php', ['id' => (int) $r->userid]))->out(false);
                $name_html = '<a href="' . s($userurl) . '" class="text-reset fw-semibold text-decoration-none">'
                    . s($fullname) . '</a>';

                $employeeid = property_exists($r, 'open_employeeid') ? (string) $r->open_employeeid : '';
                $designation = property_exists($r, 'open_designation') ? (string) $r->open_designation : '';

                $actions = '';
                if ($can_update) {
                    $actions = '<a href="#" class="btn btn-sm btn-link text-muted p-1" '
                        . 'data-action="unenrol-user" data-userid="' . (int) $r->userid . '" '
                        . 'data-name="' . s($fullname) . '" '
                        . 'title="Remove from classroom"><i class="fa fa-trash text-danger"></i></a>';
                }

                $rows[] = [
                    'id'           => (int) $r->id,
                    'userid'       => (int) $r->userid,
                    'name'         => $name_html,
                    'email'        => s((string) $r->email),
                    'employeeid'   => s($employeeid),
                    'designation'  => s($designation),
                    'enrolled_at'  => $r->enrolled_at ? userdate((int) $r->enrolled_at, '%d %b %Y') : '—',
                    'actions'      => $actions,
                ];
            }
        }

        return [
            'total'   => $total,
            'rows'    => $rows,
            'page'    => $params['page'],
            'perpage' => $params['perpage'],
        ];
    }

    public static function execute_returns(): external_single_structure {
        return new external_single_structure([
            'total'   => new external_value(PARAM_INT, 'Total matches'),
            'rows'    => new external_multiple_structure(
                new external_single_structure([
                    'id'           => new external_value(PARAM_INT, 'Roster row ID'),
                    'userid'       => new external_value(PARAM_INT, 'User ID'),
                    'name'         => new external_value(PARAM_RAW,  'Name (HTML)'),
                    'email'        => new external_value(PARAM_TEXT, 'Email'),
                    'employeeid'   => new external_value(PARAM_TEXT, 'Employee ID'),
                    'designation'  => new external_value(PARAM_TEXT, 'Designation'),
                    'enrolled_at'  => new external_value(PARAM_TEXT, 'Enrolled date'),
                    'actions'      => new external_value(PARAM_RAW,  'Per-row HTML'),
                ])
            ),
            'page'    => new external_value(PARAM_INT, 'Page'),
            'perpage' => new external_value(PARAM_INT, 'Per page'),
        ]);
    }
}
