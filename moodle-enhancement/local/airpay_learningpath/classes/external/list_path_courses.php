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
 * Web service: list courses assigned to a learning path.
 *
 * Returns the rows for the shared datatable on the path detail page's Courses tab.
 * Sort whitelist matches what's displayable: name, sortorder, mandatory.
 *
 * @package    local_airpay_learningpath
 * @copyright  2026 Airpay Payment Services
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class list_path_courses extends external_api {

    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'pathid'  => new external_value(PARAM_INT, 'Learning path ID'),
            'search'  => new external_value(PARAM_TEXT,     '', VALUE_DEFAULT, ''),
            'sort'    => new external_value(PARAM_ALPHAEXT, '', VALUE_DEFAULT, 'sortorder'),
            'sortdir' => new external_value(PARAM_ALPHA,    '', VALUE_DEFAULT, 'asc'),
            'page'    => new external_value(PARAM_INT,      '', VALUE_DEFAULT, 0),
            'perpage' => new external_value(PARAM_INT,      '', VALUE_DEFAULT, 25),
            'filters' => new external_value(PARAM_RAW,      '', VALUE_DEFAULT, '{}'),
        ]);
    }

    public static function execute(int $pathid, string $search = '', string $sort = 'sortorder',
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

        // Verify the path exists. (No tenant boundary check on read here — listing
        // a path's courses is information the path's :view permission already gates.)
        $DB->get_record('local_airpay_learningpath', ['id' => $params['pathid']], 'id', MUST_EXIST);

        // Sort whitelist — anything outside falls back to sortorder.
        $allowed = ['sortorder', 'fullname', 'mandatory', 'timecreated'];
        $sort = in_array($params['sort'], $allowed, true) ? $params['sort'] : 'sortorder';
        $sortdir = strtolower($params['sortdir']) === 'desc' ? 'DESC' : 'ASC';

        // sortorder is on lpc; fullname is on c.
        $sortcol = ($sort === 'fullname') ? 'c.fullname' : "lpc.$sort";
        $orderby = "$sortcol $sortdir, lpc.id ASC";

        $where = ['lpc.pathid = :pid'];
        $sqlparams = ['pid' => $params['pathid']];

        if (!empty($params['search'])) {
            $term = '%' . $DB->sql_like_escape($params['search']) . '%';
            $where[] = '(' .
                $DB->sql_like('c.fullname',  ':s1', false) . ' OR ' .
                $DB->sql_like('c.shortname', ':s2', false) .
            ')';
            $sqlparams['s1'] = $term;
            $sqlparams['s2'] = $term;
        }
        $wheresql = implode(' AND ', $where);

        $total = (int) $DB->count_records_sql(
            "SELECT COUNT(*)
               FROM {local_airpay_learningpath_courses} lpc
               JOIN {course} c ON c.id = lpc.courseid
              WHERE $wheresql", $sqlparams);

        $rows = [];
        if ($total > 0) {
            $records = $DB->get_records_sql(
                "SELECT lpc.id AS rowid, lpc.courseid, lpc.sortorder, lpc.mandatory, lpc.timecreated,
                        c.fullname, c.shortname, c.visible
                   FROM {local_airpay_learningpath_courses} lpc
                   JOIN {course} c ON c.id = lpc.courseid
                  WHERE $wheresql
               ORDER BY $orderby",
                $sqlparams, $params['page'] * $params['perpage'], $params['perpage']);

            foreach ($records as $r) {
                $rows[] = [
                    'id'          => (int) $r->courseid,
                    'name'        => format_string($r->fullname),
                    'shortname'   => format_string($r->shortname),
                    'sortorder'   => (int) $r->sortorder,
                    'mandatory'   => (int) $r->mandatory ? 'Mandatory' : 'Optional',
                    'mandatorycss' => (int) $r->mandatory ? 'badge-primary' : 'badge-secondary',
                    'visible'     => (int) $r->visible ? 'Visible' : 'Hidden',
                    'visiblecss'  => (int) $r->visible ? 'badge-success' : 'badge-warning',
                    'added'       => userdate($r->timecreated, '%d %b %Y'),
                    'actions'     => '<a href="#" class="btn btn-sm btn-link p-1 text-danger" '
                        . 'data-action="unassign-course" '
                        . 'data-courseid="' . (int) $r->courseid . '" '
                        . 'data-name="' . s($r->fullname) . '" '
                        . 'title="Remove from path"><i class="fa fa-times"></i></a>',
                ];
            }
        }

        return ['total' => $total, 'rows' => $rows, 'page' => $params['page'], 'perpage' => $params['perpage']];
    }

    public static function execute_returns(): external_single_structure {
        return new external_single_structure([
            'total'   => new external_value(PARAM_INT, 'Total matching rows'),
            'rows'    => new external_multiple_structure(
                new external_single_structure([
                    'id'           => new external_value(PARAM_INT,  'Course ID'),
                    'name'         => new external_value(PARAM_TEXT, 'Full course name'),
                    'shortname'    => new external_value(PARAM_TEXT, 'Course shortname'),
                    'sortorder'    => new external_value(PARAM_INT,  'Position in path'),
                    'mandatory'    => new external_value(PARAM_TEXT, 'Mandatory|Optional'),
                    'mandatorycss' => new external_value(PARAM_TEXT, 'Bootstrap badge class'),
                    'visible'      => new external_value(PARAM_TEXT, 'Visible|Hidden'),
                    'visiblecss'   => new external_value(PARAM_TEXT, 'Bootstrap badge class'),
                    'added'        => new external_value(PARAM_TEXT, 'Date added (formatted)'),
                    'actions'      => new external_value(PARAM_RAW,  'Per-row HTML actions'),
                ])
            ),
            'page'    => new external_value(PARAM_INT, 'Echoed back'),
            'perpage' => new external_value(PARAM_INT, 'Echoed back'),
        ]);
    }
}
