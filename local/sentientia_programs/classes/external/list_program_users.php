<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

namespace local_sentientia_programs\external;

defined('MOODLE_INTERNAL') || die();

use core_external\external_api;
use core_external\external_function_parameters;
use core_external\external_multiple_structure;
use core_external\external_single_structure;
use core_external\external_value;

class list_program_users extends external_api {

    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'programid' => new external_value(PARAM_INT,      'Program ID'),
            'search'    => new external_value(PARAM_TEXT,     '', VALUE_DEFAULT, ''),
            'sort'      => new external_value(PARAM_ALPHAEXT, '', VALUE_DEFAULT, 'lastname'),
            'sortdir'   => new external_value(PARAM_ALPHA,    '', VALUE_DEFAULT, 'asc'),
            'page'      => new external_value(PARAM_INT,      '', VALUE_DEFAULT, 0),
            'perpage'   => new external_value(PARAM_INT,      '', VALUE_DEFAULT, 25),
            'filters'   => new external_value(PARAM_RAW,      '', VALUE_DEFAULT, '{}'),
        ]);
    }

    public static function execute(int $programid, string $search = '', string $sort = 'lastname',
                                    string $sortdir = 'asc', int $page = 0, int $perpage = 25,
                                    string $filters = '{}'): array {
        global $DB;

        $params = self::validate_parameters(self::execute_parameters(),
            compact('programid', 'search', 'sort', 'sortdir', 'page', 'perpage', 'filters'));

        $context = \context_system::instance();
        self::validate_context($context);
        require_capability('local/sentientia_programs:view', $context);

        $can_update = has_capability('local/sentientia_programs:update', $context)
            || has_capability('local/sentientia_programs:manage', $context);
        $can_enrol = has_capability('local/sentientia_programs:enrol', $context)
            || has_capability('local/sentientia_programs:manage', $context);

        $DB->get_record('local_sentientia_programs', ['id' => $params['programid']],
            'id', MUST_EXIST);

        $total = \local_sentientia_programs\program_manager::count_enrolled_filtered(
            $params['programid'], $params['search']);

        $rows = [];
        if ($total > 0) {
            $records = \local_sentientia_programs\program_manager::get_enrolled_users(
                $params['programid'], $params['search'],
                $params['sort'], $params['sortdir'],
                $params['page'] * $params['perpage'], $params['perpage']);

            $statusmap = [
                \local_sentientia_programs\program_manager::ENROL_NEW         => 'Enrolled',
                \local_sentientia_programs\program_manager::ENROL_INPROGRESS  => 'In progress',
                \local_sentientia_programs\program_manager::ENROL_COMPLETED   => 'Completed',
            ];
            $cssmap = [0 => 'badge-secondary', 1 => 'badge-primary', 2 => 'badge-success'];

            foreach ($records as $r) {
                $fullname = trim(($r->firstname ?? '') . ' ' . ($r->lastname ?? ''));
                if (empty($fullname)) { $fullname = $r->email; }

                $userurl = (new \moodle_url('/user/profile.php', ['id' => (int) $r->userid]))->out(false);
                $name_html = '<a href="' . s($userurl) . '" class="text-reset fw-semibold text-decoration-none">'
                    . s($fullname) . '</a>';

                $employeeid = property_exists($r, 'open_employeeid') ? (string) $r->open_employeeid : '';
                $designation = property_exists($r, 'open_designation') ? (string) $r->open_designation : '';

                $statuslabel = $statusmap[(int) $r->status] ?? 'Enrolled';
                $statuscss   = $cssmap[(int) $r->status] ?? 'badge-secondary';

                $actions = '';
                if ($can_update || $can_enrol) {
                    $actions = '<a href="#" class="btn btn-sm btn-link text-muted p-1" '
                        . 'data-action="unenrol-program-user" '
                        . 'data-userid="' . (int) $r->userid . '" '
                        . 'data-name="' . s($fullname) . '" '
                        . 'title="Remove from program"><i class="fa fa-trash text-danger"></i></a>';
                }

                $rows[] = [
                    'id'           => (int) $r->id,
                    'userid'       => (int) $r->userid,
                    'name'         => $name_html,
                    'email'        => s((string) $r->email),
                    'employeeid'   => s($employeeid),
                    'designation'  => s($designation),
                    'enrolled_at'  => $r->enrolled_at ? userdate((int) $r->enrolled_at, '%d %b %Y') : '—',
                    'statuslabel'  => $statuslabel,
                    'statuscss'    => $statuscss,
                    'actions'      => $actions,
                ];
            }
        }

        return ['total' => $total, 'rows' => $rows,
            'page' => $params['page'], 'perpage' => $params['perpage']];
    }

    public static function execute_returns(): external_single_structure {
        return new external_single_structure([
            'total'   => new external_value(PARAM_INT, ''),
            'rows'    => new external_multiple_structure(
                new external_single_structure([
                    'id'           => new external_value(PARAM_INT, ''),
                    'userid'       => new external_value(PARAM_INT, ''),
                    'name'         => new external_value(PARAM_RAW, ''),
                    'email'        => new external_value(PARAM_TEXT, ''),
                    'employeeid'   => new external_value(PARAM_TEXT, ''),
                    'designation'  => new external_value(PARAM_TEXT, ''),
                    'enrolled_at'  => new external_value(PARAM_TEXT, ''),
                    'statuslabel'  => new external_value(PARAM_TEXT, ''),
                    'statuscss'    => new external_value(PARAM_TEXT, ''),
                    'actions'      => new external_value(PARAM_RAW, ''),
                ])
            ),
            'page'    => new external_value(PARAM_INT, ''),
            'perpage' => new external_value(PARAM_INT, ''),
        ]);
    }
}
