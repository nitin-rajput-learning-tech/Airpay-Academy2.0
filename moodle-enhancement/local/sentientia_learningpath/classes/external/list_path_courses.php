<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

namespace local_sentientia_learningpath\external;

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
 * @package    local_sentientia_learningpath
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
        require_capability('local/sentientia_learningpath:view', $context);

        if (strlen($params['filters']) > 4096) {
            throw new \moodle_exception('filterstoolong', 'local_sentientia_learningpath');
        }

        // Verify the path exists AND is in the caller's tenant (ADR-031). Until
        // 2026-09-25 this deliberately skipped the tenant check, so :view -
        // which every tenant admin holds - listed any tenant's path.
        \local_sentientia_learningpath\path_manager::require_path_tenant($params['pathid']);

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
               FROM {local_sentientia_learningpath_courses} lpc
               JOIN {course} c ON c.id = lpc.courseid
              WHERE $wheresql", $sqlparams);

        $rows = [];
        if ($total > 0) {
            // A row the BizLMS import created cannot be removed (ADR-032, path_manager::unassign_course()). Its row
            // shows a lock that says why instead of the remove action, which only ever ended in an error
            // (2026-11-19, plan 2.4).
            [$nativesql, $nativeparams] = \local_sentientia_platform\bizlms\provenance::not_imported_sql(
                'lpc', 'local_sentientia_learningpath_courses', 'lpi');
            $records = $DB->get_records_sql(
                "SELECT lpc.id AS rowid, lpc.courseid, lpc.sortorder, lpc.mandatory, lpc.timecreated,
                        c.fullname, c.shortname, c.visible,
                        CASE WHEN $nativesql THEN 0 ELSE 1 END AS imported
                   FROM {local_sentientia_learningpath_courses} lpc
                   JOIN {course} c ON c.id = lpc.courseid
                  WHERE $wheresql
               ORDER BY $orderby",
                array_merge($sqlparams, $nativeparams), $params['page'] * $params['perpage'], $params['perpage']);

            $positions = self::positions($params['pathid']);
            $strremove = s(get_string('remove_from_path', 'local_sentientia_learningpath'));
            $strlocked = s(get_string('imported_course_locked', 'local_sentientia_learningpath'));

            foreach ($records as $r) {
                $imported = (bool) (int) $r->imported;
                if ($imported) {
                    $actions = '<button type="button" class="btn btn-sm btn-link p-1 text-muted" '
                        . 'data-action="imported-course-info" '
                        . 'data-courseid="' . (int) $r->courseid . '" '
                        . 'data-name="' . s($r->fullname) . '" '
                        . 'title="' . $strlocked . '" aria-label="' . $strlocked . '">'
                        . '<i class="fa fa-lock" aria-hidden="true"></i></button>';
                } else {
                    $actions = '<a href="#" class="btn btn-sm btn-link p-1 text-danger" '
                        . 'data-action="unassign-course" '
                        . 'data-courseid="' . (int) $r->courseid . '" '
                        . 'data-name="' . s($r->fullname) . '" '
                        . 'title="' . $strremove . '" aria-label="' . $strremove . '">'
                        . '<i class="fa fa-times" aria-hidden="true"></i></a>';
                }
                $rows[] = [
                    'id'          => (int) $r->courseid,
                    'name'        => format_string($r->fullname),
                    'shortname'   => format_string($r->shortname),
                    'sortorder'   => (int) $r->sortorder,
                    'position'    => $positions[(int) $r->rowid] ?? 0,
                    'imported'    => $imported,
                    'mandatory'   => (int) $r->mandatory ? 'Mandatory' : 'Optional',
                    'mandatorycss' => (int) $r->mandatory ? 'badge-primary' : 'badge-secondary',
                    'visible'     => (int) $r->visible ? 'Visible' : 'Hidden',
                    'visiblecss'  => (int) $r->visible ? 'badge-success' : 'badge-warning',
                    'added'       => $r->timecreated ? userdate($r->timecreated, '%d %b %Y') : '—',
                    'actions'     => $actions,
                ];
            }
        }

        return ['total' => $total, 'rows' => $rows, 'page' => $params['page'], 'perpage' => $params['perpage']];
    }

    /**
     * Each course row's place on the path, counted from 1.
     *
     * The "#" column showed the stored sortorder, which starts at 0 and keeps the gaps a removal leaves
     * (2026-11-19, plan 2.4). The place is the row's rank in path order (sortorder, then id) among the rows the
     * table lists, so it stays the same whatever the table is sorted or searched by.
     *
     * @param int $pathid
     * @return array<int, int> Row id => place (1, 2, 3...).
     */
    public static function positions(int $pathid): array {
        global $DB;
        $rowids = $DB->get_fieldset_sql(
            "SELECT lpc.id
               FROM {local_sentientia_learningpath_courses} lpc
               JOIN {course} c ON c.id = lpc.courseid
              WHERE lpc.pathid = :pid
           ORDER BY lpc.sortorder ASC, lpc.id ASC", ['pid' => $pathid]);
        $positions = [];
        foreach (array_values($rowids) as $index => $rowid) {
            $positions[(int) $rowid] = $index + 1;
        }
        return $positions;
    }

    public static function execute_returns(): external_single_structure {
        return new external_single_structure([
            'total'   => new external_value(PARAM_INT, 'Total matching rows'),
            'rows'    => new external_multiple_structure(
                new external_single_structure([
                    'id'           => new external_value(PARAM_INT,  'Course ID'),
                    'name'         => new external_value(PARAM_TEXT, 'Full course name'),
                    'shortname'    => new external_value(PARAM_TEXT, 'Course shortname'),
                    'sortorder'    => new external_value(PARAM_INT,  'Stored sort order (from 0; may have gaps)'),
                    'position'     => new external_value(PARAM_INT,  'Place on the path, counted from 1'),
                    'imported'     => new external_value(PARAM_BOOL, 'True if the BizLMS import created the row (it cannot be removed)'),
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
