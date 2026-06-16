<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

namespace local_sentientia_api\external\v1;

defined('MOODLE_INTERNAL') || die();

use core_external\external_function_parameters;
use core_external\external_single_structure;
use core_external\external_multiple_structure;
use core_external\external_value;

/**
 * v1: GET /courses — list courses in the caller's tenant.
 *
 * @package local_sentientia_api
 */
class list_courses extends base {

    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'search'  => new external_value(PARAM_TEXT, 'Free-text filter on course name', VALUE_DEFAULT, ''),
            'page'    => new external_value(PARAM_INT,  'Zero-based page index', VALUE_DEFAULT, 0),
            'perpage' => new external_value(PARAM_INT,  'Page size (max 100)', VALUE_DEFAULT, 25),
        ]);
    }

    /**
     * @param string $search
     * @param int $page
     * @param int $perpage
     * @return array
     */
    public static function execute(string $search = '', int $page = 0, int $perpage = 25): array {
        global $DB;

        $params = self::validate_parameters(self::execute_parameters(),
            compact('search', 'page', 'perpage'));

        self::open_v1('local_sentientia_api_v1_list_courses', 'local/sentientia_api:read');

        $page = max(0, $params['page']);
        $perpage = min(100, max(1, $params['perpage']));

        [$tenantsql, $tenantargs] = self::course_tenant_filter('c');

        $where = [$tenantsql, 'c.id <> :siteid', 'c.visible = 1'];
        $args = array_merge($tenantargs, ['siteid' => SITEID]);

        if ($params['search'] !== '') {
            $term = '%' . $DB->sql_like_escape($params['search']) . '%';
            $where[] = '(' . $DB->sql_like('c.fullname', ':s1', false) . ' OR '
                . $DB->sql_like('c.shortname', ':s2', false) . ')';
            $args['s1'] = $term;
            $args['s2'] = $term;
        }

        $wheresql = implode(' AND ', $where);

        $total = (int) $DB->count_records_sql(
            "SELECT COUNT(c.id) FROM {course} c WHERE $wheresql", $args);

        $rows = [];
        if ($total > 0) {
            $records = $DB->get_records_sql(
                "SELECT c.id, c.fullname, c.shortname, c.summary, c.startdate, c.enddate, c.visible
                   FROM {course} c
                  WHERE $wheresql
               ORDER BY c.fullname ASC, c.id ASC",
                $args, $page * $perpage, $perpage);
            foreach ($records as $c) {
                $rows[] = [
                    'id'        => (int) $c->id,
                    'fullname'  => format_string($c->fullname),
                    'shortname' => format_string($c->shortname),
                    'summary'   => format_text($c->summary ?? '', FORMAT_HTML, ['context' => \context_course::instance($c->id)]),
                    'startdate' => (int) $c->startdate,
                    'enddate'   => (int) $c->enddate,
                    'visible'   => (bool) $c->visible,
                ];
            }
        }

        return ['total' => $total, 'page' => $page, 'perpage' => $perpage, 'courses' => $rows];
    }

    public static function execute_returns(): external_single_structure {
        return new external_single_structure([
            'total'   => new external_value(PARAM_INT, 'Total matching courses in tenant'),
            'page'    => new external_value(PARAM_INT, 'Page index returned'),
            'perpage' => new external_value(PARAM_INT, 'Page size used'),
            'courses' => new external_multiple_structure(
                new external_single_structure([
                    'id'        => new external_value(PARAM_INT,  'Course id'),
                    'fullname'  => new external_value(PARAM_TEXT, 'Course full name'),
                    'shortname' => new external_value(PARAM_TEXT, 'Course short name'),
                    'summary'   => new external_value(PARAM_RAW,  'Course summary HTML'),
                    'startdate' => new external_value(PARAM_INT,  'Start date (epoch)'),
                    'enddate'   => new external_value(PARAM_INT,  'End date (epoch, 0 if none)'),
                    'visible'   => new external_value(PARAM_BOOL, 'Whether the course is visible'),
                ])
            ),
        ]);
    }
}
