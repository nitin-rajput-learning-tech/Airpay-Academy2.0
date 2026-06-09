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

/**
 * List courses assigned to a level (per-level courses sub-page datatable).
 */
class list_level_courses extends external_api {

    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'levelid' => new external_value(PARAM_INT,      'Level ID'),
            'search'  => new external_value(PARAM_TEXT,     '', VALUE_DEFAULT, ''),
            'sort'    => new external_value(PARAM_ALPHAEXT, '', VALUE_DEFAULT, 'sortorder'),
            'sortdir' => new external_value(PARAM_ALPHA,    '', VALUE_DEFAULT, 'asc'),
            'page'    => new external_value(PARAM_INT,      '', VALUE_DEFAULT, 0),
            'perpage' => new external_value(PARAM_INT,      '', VALUE_DEFAULT, 50),
            'filters' => new external_value(PARAM_RAW,      '', VALUE_DEFAULT, '{}'),
        ]);
    }

    public static function execute(int $levelid, string $search = '', string $sort = 'sortorder',
                                    string $sortdir = 'asc', int $page = 0, int $perpage = 50,
                                    string $filters = '{}'): array {
        global $DB;

        $params = self::validate_parameters(self::execute_parameters(),
            compact('levelid', 'search', 'sort', 'sortdir', 'page', 'perpage', 'filters'));

        $context = \context_system::instance();
        self::validate_context($context);
        require_capability('local/sentientia_programs:view', $context);

        $can_update = has_capability('local/sentientia_programs:update', $context)
            || has_capability('local/sentientia_programs:manage', $context);

        $DB->get_record('local_sentientia_programs_levels', ['id' => $params['levelid']],
            'id', MUST_EXIST);

        $allowed = ['sortorder', 'fullname', 'shortname'];
        $sort = in_array($params['sort'], $allowed, true) ? $params['sort'] : 'sortorder';
        $sortdir = strtolower($params['sortdir']) === 'desc' ? 'DESC' : 'ASC';
        $sortcol = ($sort === 'sortorder') ? 'lc.sortorder' : "c.{$sort}";

        $where = ['lc.levelid = :lid'];
        $sqlparams = ['lid' => $params['levelid']];
        if (!empty($params['search'])) {
            $term = '%' . $DB->sql_like_escape($params['search']) . '%';
            $where[] = '(' . $DB->sql_like('c.fullname', ':s1', false) . ' OR ' .
                $DB->sql_like('c.shortname', ':s2', false) . ')';
            $sqlparams['s1'] = $sqlparams['s2'] = $term;
        }
        $wheresql = implode(' AND ', $where);

        $total = (int) $DB->count_records_sql(
            "SELECT COUNT(*) FROM {local_sentientia_programs_courses} lc
                JOIN {course} c ON c.id = lc.courseid
              WHERE $wheresql", $sqlparams);

        $rows = [];
        if ($total > 0) {
            $records = $DB->get_records_sql(
                "SELECT lc.id, lc.courseid, lc.sortorder, lc.mandatory,
                        c.fullname, c.shortname, c.visible AS course_visible
                   FROM {local_sentientia_programs_courses} lc
                   JOIN {course} c ON c.id = lc.courseid
                  WHERE $wheresql
               ORDER BY $sortcol $sortdir, lc.id ASC",
                $sqlparams, $params['page'] * $params['perpage'], $params['perpage']);

            foreach ($records as $r) {
                $course_url = (new \moodle_url('/course/view.php',
                    ['id' => (int) $r->courseid]))->out(false);
                $name_html = '<a href="' . s($course_url) . '" class="text-reset fw-semibold text-decoration-none" target="_blank">'
                    . format_string($r->fullname) . '</a>';
                if (!$r->course_visible) {
                    $name_html .= ' <span class="badge bg-warning-subtle text-warning ms-1">Hidden</span>';
                }

                $mandatory = ((int) $r->mandatory) === 1
                    ? '<span class="badge bg-danger-subtle text-danger">Mandatory</span>'
                    : '<span class="badge bg-secondary-subtle text-secondary">Optional</span>';

                $actions = '';
                if ($can_update) {
                    $actions = '<a href="#" class="btn btn-sm btn-link text-muted p-1" '
                        . 'data-action="unassign-level-course" '
                        . 'data-courseid="' . (int) $r->courseid . '" '
                        . 'data-name="' . s($r->fullname) . '" '
                        . 'title="Remove from level"><i class="fa fa-trash text-danger"></i></a>';
                }

                $rows[] = [
                    'id'        => (int) $r->id,
                    'courseid'  => (int) $r->courseid,
                    'position'  => (int) $r->sortorder + 1,
                    'name'      => $name_html,
                    'shortname' => format_string($r->shortname ?? ''),
                    'mandatory' => $mandatory,
                    'actions'   => $actions,
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
                    'id'        => new external_value(PARAM_INT,  ''),
                    'courseid'  => new external_value(PARAM_INT,  ''),
                    'position'  => new external_value(PARAM_INT,  ''),
                    'name'      => new external_value(PARAM_RAW,  ''),
                    'shortname' => new external_value(PARAM_TEXT, ''),
                    'mandatory' => new external_value(PARAM_RAW,  ''),
                    'actions'   => new external_value(PARAM_RAW,  ''),
                ])
            ),
            'page'    => new external_value(PARAM_INT, ''),
            'perpage' => new external_value(PARAM_INT, ''),
        ]);
    }
}
