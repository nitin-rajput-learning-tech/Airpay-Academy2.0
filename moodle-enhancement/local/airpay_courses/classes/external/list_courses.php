<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

namespace local_airpay_courses\external;

defined('MOODLE_INTERNAL') || die();

use core_external\external_api;
use core_external\external_function_parameters;
use core_external\external_single_structure;
use core_external\external_value;

/**
 * List courses with server-side search, sort, pagination — for the shared datatable.
 *
 * Contract: matches theme_airpayux/datatable arg shape.
 * Returns: {total, rows[], page, perpage}
 */
class list_courses extends external_api {

    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'search'  => new external_value(PARAM_TEXT, 'Search term', VALUE_DEFAULT, ''),
            'sort'    => new external_value(PARAM_ALPHAEXT, 'Sort column', VALUE_DEFAULT, 'fullname'),
            'sortdir' => new external_value(PARAM_ALPHA, 'asc|desc', VALUE_DEFAULT, 'asc'),
            'page'    => new external_value(PARAM_INT, 'Page', VALUE_DEFAULT, 0),
            'perpage' => new external_value(PARAM_INT, 'Per page', VALUE_DEFAULT, 25),
            'filters' => new external_value(PARAM_RAW, 'JSON filters', VALUE_DEFAULT, '{}'),
        ]);
    }

    public static function execute(string $search = '', string $sort = 'fullname', string $sortdir = 'asc',
                                    int $page = 0, int $perpage = 25, string $filters = '{}'): array {
        global $DB;

        $params = self::validate_parameters(self::execute_parameters(),
            compact('search', 'sort', 'sortdir', 'page', 'perpage', 'filters'));

        $context = \context_system::instance();
        self::validate_context($context);
        require_capability('local/airpay_courses:view', $context);

        $can_edit   = has_capability('local/airpay_courses:update', $context);
        $can_delete = has_capability('local/airpay_courses:delete', $context);
        $can_visibility = has_capability('local/airpay_courses:visibility', $context);
        $can_enrol  = has_capability('local/airpay_courses:enrol', $context);

        // Sort whitelist.
        $allowed_sort = ['fullname', 'shortname', 'timecreated', 'visible', 'category'];
        $sort = in_array($params['sort'], $allowed_sort, true) ? $params['sort'] : 'fullname';
        $sortdir = strtolower($params['sortdir']) === 'desc' ? 'DESC' : 'ASC';
        $orderby = "c.{$sort} {$sortdir}, c.id ASC";

        // Filters from client.
        // M2 fix: bound the JSON filter blob and limit decode depth.
        if (strlen($params['filters']) > 4096) {
            throw new \moodle_exception('filterstoolong', 'local_airpay_courses');
        }
        $client_filters = json_decode($params['filters'], true, 5);
        if (!is_array($client_filters) || json_last_error() !== JSON_ERROR_NONE) {
            $client_filters = [];
        }
        $categoryid = (int) ($client_filters['categoryid'] ?? 0);
        $visibility = (string) ($client_filters['visibility'] ?? 'all');

        $where = ['c.id > 1']; // Exclude site course.
        $sqlparams = [];

        // M3 fix: scope to caller's tenant (was missing entirely — any user
        // with :view could see every course on the platform).
        global $USER;
        if (!is_siteadmin()) {
            $parts = explode('/', trim($USER->open_path ?? '', '/'));
            $top = isset($parts[0]) && ctype_digit($parts[0]) ? (int) $parts[0] : 0;
            if ($top > 0) {
                // Match: course at tenant root, in any descendant of it, OR
                // courses with no open_path set (legacy/unscoped — visible
                // to everyone today; tighten later once data is migrated).
                $where[] = '(c.open_path = :corgexact OR c.open_path LIKE :corgprefix OR c.open_path IS NULL)';
                $sqlparams['corgexact']  = '/' . $top;
                $sqlparams['corgprefix'] =
                    $DB->sql_like_escape('/' . $top . '/') . '%';
            }
        }

        if ($categoryid > 0) {
            $where[] = 'c.category = :catid';
            $sqlparams['catid'] = $categoryid;
        }

        if ($visibility === 'visible') {
            $where[] = 'c.visible = 1';
        } else if ($visibility === 'hidden') {
            $where[] = 'c.visible = 0';
        }

        if (!empty($params['search'])) {
            $term = '%' . $DB->sql_like_escape($params['search']) . '%';
            $where[] = '(' .
                $DB->sql_like('c.fullname',  ':s1', false) . ' OR ' .
                $DB->sql_like('c.shortname', ':s2', false) . ' OR ' .
                $DB->sql_like('c.idnumber',  ':s3', false) .
            ')';
            $sqlparams['s1'] = $term;
            $sqlparams['s2'] = $term;
            $sqlparams['s3'] = $term;
        }

        $wheresql = implode(' AND ', $where);

        // Count.
        $total = (int) $DB->count_records_sql(
            "SELECT COUNT(*) FROM {course} c WHERE $wheresql", $sqlparams);

        // Page.
        $records = [];
        if ($total > 0) {
            $sql = "SELECT c.id, c.fullname, c.shortname, c.idnumber, c.category, c.visible,
                           c.timecreated, cat.name AS catname,
                           (SELECT COUNT(*) FROM {user_enrolments} ue
                              JOIN {enrol} e ON e.id = ue.enrolid
                             WHERE e.courseid = c.id) AS enrolled_count
                      FROM {course} c
                 LEFT JOIN {course_categories} cat ON cat.id = c.category
                     WHERE $wheresql
                  ORDER BY $orderby";
            $records = $DB->get_records_sql($sql, $sqlparams,
                $params['page'] * $params['perpage'], $params['perpage']);
        }

        $rows = [];
        foreach ($records as $c) {
            $statuslabel = $c->visible ? 'Visible' : 'Hidden';
            $statuscss = $c->visible ? 'badge-success' : 'badge-secondary';

            $courseurl = (new \moodle_url('/course/view.php', ['id' => $c->id]))->out(false);
            $fullname_html = '<a href="' . s($courseurl) . '" class="text-reset fw-semibold text-decoration-none">'
                           . s($c->fullname) . '</a>';

            $actions = [];
            // Phase F.5 (2026-05-08) — native enrol modal (replaces G-06
            // deep-link). Opens in-page via local_airpay_courses/enrol_modal.
            // Original deep-link kept as fallback (Shift-click / new-tab).
            if ($can_enrol) {
                $enrolurl = (new \moodle_url('/enrol/users.php', ['id' => (int) $c->id]))->out(false);
                $actions[] = '<a href="' . s($enrolurl) . '" '
                    . 'data-action="enrol-users-modal" '
                    . 'data-courseid="' . (int) $c->id . '" '
                    . 'data-name="' . s($c->fullname) . '" '
                    . 'class="btn btn-sm btn-link text-muted p-1" '
                    . 'title="Enrol users"><i class="fa fa-user-plus"></i></a>';
            }
            if ($can_edit) {
                $actions[] = '<a href="#" class="btn btn-sm btn-link text-muted p-1" '
                    . 'data-action="edit-course" data-courseid="' . (int) $c->id . '" '
                    . 'data-name="' . s($c->fullname) . '" title="Edit"><i class="fa fa-pencil"></i></a>';
            }
            if ($can_visibility) {
                $verb = $c->visible ? 'hide' : 'show';
                $icon = $c->visible ? 'fa-eye-slash text-warning' : 'fa-eye text-success';
                $actions[] = '<a href="#" class="btn btn-sm btn-link text-muted p-1" '
                    . 'data-action="' . $verb . '-course" data-courseid="' . (int) $c->id . '" '
                    . 'data-name="' . s($c->fullname) . '" title="' . ucfirst($verb) . '"><i class="fa ' . $icon . '"></i></a>';
            }
            if ($can_delete) {
                $actions[] = '<a href="#" class="btn btn-sm btn-link text-muted p-1" '
                    . 'data-action="delete-course" data-courseid="' . (int) $c->id . '" '
                    . 'data-name="' . s($c->fullname) . '" title="Delete"><i class="fa fa-trash text-danger"></i></a>';
            }

            $rows[] = [
                'id'           => (int) $c->id,
                'fullname'     => $fullname_html,
                'shortname'    => $c->shortname ?? '',
                'idnumber'     => $c->idnumber ?? '',
                'catname'      => $c->catname ? format_string($c->catname) : '—',
                'enrolled'     => (int) ($c->enrolled_count ?? 0),
                'created'      => $c->timecreated ? userdate($c->timecreated, '%d %b %Y') : '—',
                'statuslabel'  => $statuslabel,
                'statuscss'    => $statuscss,
                'actions'      => implode(' ', $actions),
            ];
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
            'rows'    => new \core_external\external_multiple_structure(
                new external_single_structure([
                    'id'          => new external_value(PARAM_INT, 'Course ID'),
                    'fullname'    => new external_value(PARAM_RAW, 'Full name (HTML linked)'),
                    'shortname'   => new external_value(PARAM_TEXT, 'Short name'),
                    'idnumber'    => new external_value(PARAM_TEXT, 'ID number'),
                    'catname'     => new external_value(PARAM_TEXT, 'Category name'),
                    'enrolled'    => new external_value(PARAM_INT, 'Enrolled users'),
                    'created'     => new external_value(PARAM_TEXT, 'Created date'),
                    'statuslabel' => new external_value(PARAM_TEXT, 'Visible|Hidden'),
                    'statuscss'   => new external_value(PARAM_TEXT, 'Badge class'),
                    'actions'     => new external_value(PARAM_RAW, 'Per-row HTML'),
                ])
            ),
            'page'    => new external_value(PARAM_INT, 'Page'),
            'perpage' => new external_value(PARAM_INT, 'Per page'),
        ]);
    }
}
