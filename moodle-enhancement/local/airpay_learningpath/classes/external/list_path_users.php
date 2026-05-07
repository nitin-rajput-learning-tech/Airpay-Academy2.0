<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

namespace local_airpay_learningpath\external;

defined('MOODLE_INTERNAL') || die();

use core_external\external_api;
use core_external\external_function_parameters;
use core_external\external_single_structure;
use core_external\external_multiple_structure;
use core_external\external_value;

/**
 * Web service: list users enrolled in a learning path.
 *
 * Returns rows for the shared datatable on the path detail page's Users tab.
 * Supports search by firstname/lastname/email + pagination. Sort is fixed to
 * lastname/firstname for now (datatable's sort UI on the Users tab is hidden
 * because mixing per-user enrolment status sort with name sort is rarely useful).
 *
 * @package    local_airpay_learningpath
 * @copyright  2026 Airpay Payment Services
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class list_path_users extends external_api {

    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'pathid'  => new external_value(PARAM_INT, 'Learning path ID'),
            'search'  => new external_value(PARAM_TEXT,     '', VALUE_DEFAULT, ''),
            'sort'    => new external_value(PARAM_ALPHAEXT, '', VALUE_DEFAULT, 'lastname'),
            'sortdir' => new external_value(PARAM_ALPHA,    '', VALUE_DEFAULT, 'asc'),
            'page'    => new external_value(PARAM_INT,      '', VALUE_DEFAULT, 0),
            'perpage' => new external_value(PARAM_INT,      '', VALUE_DEFAULT, 25),
            'filters' => new external_value(PARAM_RAW,      '', VALUE_DEFAULT, '{}'),
        ]);
    }

    public static function execute(int $pathid, string $search = '', string $sort = 'lastname',
                                    string $sortdir = 'asc', int $page = 0, int $perpage = 25,
                                    string $filters = '{}'): array {
        global $DB;

        $params = self::validate_parameters(self::execute_parameters(),
            compact('pathid', 'search', 'sort', 'sortdir', 'page', 'perpage', 'filters'));

        $context = \context_system::instance();
        self::validate_context($context);
        require_capability('local/airpay_learningpath:view', $context);

        if (strlen($params['filters']) > 4096) {
            throw new \moodle_exception('filterstoolong', 'local_airpay_learningpath');
        }

        // Verify path exists.
        $DB->get_record('local_airpay_learningpath', ['id' => $params['pathid']], 'id', MUST_EXIST);

        // Delegate to path_manager — it already does the JOIN + status mapping.
        $result = \local_airpay_learningpath\path_manager::get_path_users(
            $params['pathid'], $params['search'], $params['page'], $params['perpage']);

        // Wrap each row with the action button (must be done here because
        // path_manager doesn't know about HTML actions).
        $rows = [];
        foreach ($result['rows'] as $r) {
            $rowarr = (array) $r;
            $rowarr['actions'] = '<a href="#" class="btn btn-sm btn-link p-1 text-danger" '
                . 'data-action="unenrol-user" '
                . 'data-userid="' . (int) $r->id . '" '
                . 'data-name="' . s($r->fullname) . '" '
                . 'title="Unenrol from path"><i class="fa fa-times"></i></a>';
            $rows[] = $rowarr;
        }

        return [
            'total'   => $result['total'],
            'rows'    => $rows,
            'page'    => $params['page'],
            'perpage' => $params['perpage'],
        ];
    }

    public static function execute_returns(): external_single_structure {
        return new external_single_structure([
            'total'   => new external_value(PARAM_INT, 'Total matching rows'),
            'rows'    => new external_multiple_structure(
                new external_single_structure([
                    'id'          => new external_value(PARAM_INT,  'User ID'),
                    'fullname'    => new external_value(PARAM_TEXT, 'Full name'),
                    'employeeid'  => new external_value(PARAM_TEXT, 'Employee ID or em-dash'),
                    'email'       => new external_value(PARAM_TEXT, 'Email'),
                    'designation' => new external_value(PARAM_TEXT, 'Designation or em-dash'),
                    'enrolled'    => new external_value(PARAM_TEXT, 'Date enrolled (formatted)'),
                    'completed'   => new external_value(PARAM_TEXT, 'Date completed or em-dash'),
                    'statuslabel' => new external_value(PARAM_TEXT, 'Enrolled|In Progress|Completed'),
                    'statuscss'   => new external_value(PARAM_TEXT, 'Bootstrap badge class'),
                    'actions'     => new external_value(PARAM_RAW,  'Per-row HTML actions'),
                ])
            ),
            'page'    => new external_value(PARAM_INT, 'Echoed back'),
            'perpage' => new external_value(PARAM_INT, 'Echoed back'),
        ]);
    }
}
