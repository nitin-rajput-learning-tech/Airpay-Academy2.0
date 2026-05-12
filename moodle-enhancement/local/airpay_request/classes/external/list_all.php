<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

namespace local_airpay_request\external;

defined('MOODLE_INTERNAL') || die();

use core_external\external_api;
use core_external\external_function_parameters;
use core_external\external_single_structure;
use core_external\external_multiple_structure;
use core_external\external_value;

/**
 * All-requests admin view. Returns same shape as list_pending so the
 * admin datatable can re-use the same row template.
 */
class list_all extends external_api {

    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'search'  => new external_value(PARAM_TEXT, '', VALUE_DEFAULT, ''),
            'sort'    => new external_value(PARAM_ALPHAEXT, '', VALUE_DEFAULT, 'timecreated'),
            'sortdir' => new external_value(PARAM_ALPHA, '', VALUE_DEFAULT, 'desc'),
            'page'    => new external_value(PARAM_INT, '', VALUE_DEFAULT, 0),
            'perpage' => new external_value(PARAM_INT, '', VALUE_DEFAULT, 25),
            'filters' => new external_value(PARAM_RAW, '', VALUE_DEFAULT, '{}'),
        ]);
    }

    public static function execute(string $search = '', string $sort = 'timecreated',
                                    string $sortdir = 'desc', int $page = 0,
                                    int $perpage = 25, string $filters = '{}'): array {
        global $DB;
        $params = self::validate_parameters(self::execute_parameters(),
            compact('search', 'sort', 'sortdir', 'page', 'perpage', 'filters'));
        $ctx = \context_system::instance();
        self::validate_context($ctx);
        require_capability('local/airpay_request:viewall', $ctx);

        $allowed = ['timecreated', 'status', 'timedecided', 'timedue'];
        $sort = in_array($params['sort'], $allowed, true) ? $params['sort'] : 'timecreated';
        $sortdir = strtolower($params['sortdir']) === 'asc' ? 'ASC' : 'DESC';

        $where = '1=1';
        $args = [];

        $client = json_decode($params['filters'] ?: '{}', true) ?: [];
        if (!empty($client['status'])) {
            $where .= ' AND r.status = :st';
            $args['st'] = $client['status'];
        }
        if (!empty($client['tenant'])) {
            $where .= ' AND r.costcenterid = :ten';
            $args['ten'] = (int) $client['tenant'];
        }
        if (!empty($params['search'])) {
            $term = '%' . $DB->sql_like_escape($params['search']) . '%';
            $where .= ' AND (' . $DB->sql_like('u.email', ':s1', false) . ' OR '
                . $DB->sql_like('c.fullname', ':s2', false) . ' OR '
                . $DB->sql_like('r.reason', ':s3', false) . ')';
            $args['s1'] = $term; $args['s2'] = $term; $args['s3'] = $term;
        }

        $total = (int) $DB->count_records_sql(
            "SELECT COUNT(*) FROM {local_airpay_request} r
        LEFT JOIN {course} c ON c.id = r.courseid
        LEFT JOIN {user}   u ON u.id = r.userid
             WHERE $where", $args);

        $rows = [];
        if ($total > 0) {
            $records = $DB->get_records_sql(
                "SELECT r.*, c.fullname AS course_name,
                        u.firstname AS req_firstname, u.lastname AS req_lastname,
                        u.email AS req_email
                   FROM {local_airpay_request} r
              LEFT JOIN {course} c ON c.id = r.courseid
              LEFT JOIN {user}   u ON u.id = r.userid
                  WHERE $where
               ORDER BY r.$sort $sortdir, r.id DESC",
                $args,
                $params['page'] * $params['perpage'], $params['perpage']);
            foreach ($records as $r) {
                $shape = list_mine::shape($r);
                $shape['requester_name']  = trim(($r->req_firstname ?? '') . ' '
                                                  . ($r->req_lastname ?? ''));
                $shape['requester_email'] = (string) ($r->req_email ?? '');
                $rows[] = $shape;
            }
        }
        return ['total' => $total, 'rows' => $rows,
                'page' => $params['page'], 'perpage' => $params['perpage']];
    }

    public static function execute_returns(): external_single_structure {
        return new external_single_structure([
            'total' => new external_value(PARAM_INT, ''),
            'rows'  => new external_multiple_structure(new external_single_structure([
                'id'              => new external_value(PARAM_INT, ''),
                'course_name'     => new external_value(PARAM_TEXT, ''),
                'courseid'        => new external_value(PARAM_INT, ''),
                'status'          => new external_value(PARAM_ALPHANUMEXT, ''),
                'route'           => new external_value(PARAM_ALPHANUMEXT, ''),
                'reason'          => new external_value(PARAM_TEXT, ''),
                'decision_note'   => new external_value(PARAM_TEXT, ''),
                'placed_on'       => new external_value(PARAM_TEXT, ''),
                'decided_on'      => new external_value(PARAM_TEXT, ''),
                'due_on'          => new external_value(PARAM_TEXT, ''),
                'is_overdue'      => new external_value(PARAM_BOOL, ''),
                'requester_name'  => new external_value(PARAM_TEXT, ''),
                'requester_email' => new external_value(PARAM_TEXT, ''),
            ])),
            'page'    => new external_value(PARAM_INT, ''),
            'perpage' => new external_value(PARAM_INT, ''),
        ]);
    }
}
