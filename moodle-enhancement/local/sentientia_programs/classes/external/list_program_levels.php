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
 * List levels for a program (Levels tab datatable).
 *
 * Returns levels in sortorder with their assigned-course counts.
 *
 * @package    local_sentientia_programs
 */
class list_program_levels extends external_api {

    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'programid' => new external_value(PARAM_INT,      'Program ID'),
            'search'    => new external_value(PARAM_TEXT,     '', VALUE_DEFAULT, ''),
            'sort'      => new external_value(PARAM_ALPHAEXT, '', VALUE_DEFAULT, 'sortorder'),
            'sortdir'   => new external_value(PARAM_ALPHA,    '', VALUE_DEFAULT, 'asc'),
            'page'      => new external_value(PARAM_INT,      '', VALUE_DEFAULT, 0),
            'perpage'   => new external_value(PARAM_INT,      '', VALUE_DEFAULT, 50),
            'filters'   => new external_value(PARAM_RAW,      '', VALUE_DEFAULT, '{}'),
        ]);
    }

    public static function execute(int $programid, string $search = '', string $sort = 'sortorder',
                                    string $sortdir = 'asc', int $page = 0, int $perpage = 50,
                                    string $filters = '{}'): array {
        global $DB;

        $params = self::validate_parameters(self::execute_parameters(),
            compact('programid', 'search', 'sort', 'sortdir', 'page', 'perpage', 'filters'));

        $context = \context_system::instance();
        self::validate_context($context);
        require_capability('local/sentientia_programs:view', $context);

        $can_update = has_capability('local/sentientia_programs:update', $context)
            || has_capability('local/sentientia_programs:manage', $context);

        $DB->get_record('local_sentientia_programs', ['id' => $params['programid']],
            'id', MUST_EXIST);

        $allowed = ['name', 'sortorder', 'timecreated'];
        $sort = in_array($params['sort'], $allowed, true) ? $params['sort'] : 'sortorder';
        $sortdir = strtolower($params['sortdir']) === 'desc' ? 'DESC' : 'ASC';

        $where = ['l.programid = :pid'];
        $sqlparams = ['pid' => $params['programid']];
        if (!empty($params['search'])) {
            $term = '%' . $DB->sql_like_escape($params['search']) . '%';
            $where[] = '(' . $DB->sql_like('l.name', ':s1', false) . ' OR ' .
                $DB->sql_like('l.description', ':s2', false) . ')';
            $sqlparams['s1'] = $sqlparams['s2'] = $term;
        }
        $wheresql = implode(' AND ', $where);

        $total = (int) $DB->count_records_sql(
            "SELECT COUNT(*) FROM {local_sentientia_programs_levels} l WHERE $wheresql",
            $sqlparams);

        $records = [];
        if ($total > 0) {
            $records = $DB->get_records_sql(
                "SELECT l.*,
                        (SELECT COUNT(*) FROM {local_sentientia_programs_courses} lc
                          WHERE lc.levelid = l.id) AS course_count
                   FROM {local_sentientia_programs_levels} l
                  WHERE $wheresql
               ORDER BY l.$sort $sortdir, l.id ASC",
                $sqlparams, $params['page'] * $params['perpage'], $params['perpage']);
        }

        $rows = [];
        foreach ($records as $l) {
            $coursesurl = (new \moodle_url('/local/sentientia_programs/levelcourses.php',
                ['levelid' => (int) $l->id]))->out(false);

            $name_html = '<a href="' . s($coursesurl) . '" class="text-reset fw-semibold text-decoration-none">'
                . s($l->name) . '</a>';

            $required = ((int) $l->completion_required) === 1
                ? '<span class="badge bg-danger-subtle text-danger">Required</span>'
                : '<span class="badge bg-secondary-subtle text-secondary">Optional</span>';

            $actions = [];
            $actions[] = '<a href="' . s($coursesurl) . '" class="btn btn-sm btn-link p-1" '
                . 'title="Manage courses"><i class="fa fa-book"></i></a>';
            if ($can_update) {
                $actions[] = '<a href="#" class="btn btn-sm btn-link text-muted p-1" '
                    . 'data-action="edit-level" data-levelid="' . (int) $l->id . '" '
                    . 'title="Edit"><i class="fa fa-pencil"></i></a>';
                $actions[] = '<a href="#" class="btn btn-sm btn-link text-muted p-1" '
                    . 'data-action="delete-level" data-levelid="' . (int) $l->id . '" '
                    . 'data-name="' . s($l->name) . '" '
                    . 'title="Delete"><i class="fa fa-trash text-danger"></i></a>';
            }

            $rows[] = [
                'id'           => (int) $l->id,
                'position'     => (int) $l->sortorder + 1,   // human-friendly 1-based
                'name'         => $name_html,
                'course_count' => (int) ($l->course_count ?? 0),
                'required'     => $required,
                'actions'      => implode(' ', $actions),
            ];
        }

        return ['total' => $total, 'rows' => $rows,
            'page' => $params['page'], 'perpage' => $params['perpage']];
    }

    public static function execute_returns(): external_single_structure {
        return new external_single_structure([
            'total'   => new external_value(PARAM_INT, ''),
            'rows'    => new external_multiple_structure(
                new external_single_structure([
                    'id'           => new external_value(PARAM_INT,  ''),
                    'position'     => new external_value(PARAM_INT,  ''),
                    'name'         => new external_value(PARAM_RAW,  ''),
                    'course_count' => new external_value(PARAM_INT,  ''),
                    'required'     => new external_value(PARAM_RAW,  ''),
                    'actions'      => new external_value(PARAM_RAW,  ''),
                ])
            ),
            'page'    => new external_value(PARAM_INT, ''),
            'perpage' => new external_value(PARAM_INT, ''),
        ]);
    }
}
