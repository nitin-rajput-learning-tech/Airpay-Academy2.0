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

class list_pending extends external_api {

    public static function execute_parameters(): external_function_parameters {
        // Bug fix 2026-05-22 (Goal A audit Bug #6 final root-cause):
        // Match the shared theme_airpayux/datatable client contract — see
        // sibling list_mine.php for the long-form explanation.
        return new external_function_parameters([
            'search'  => new external_value(PARAM_TEXT, '', VALUE_DEFAULT, ''),
            'sort'    => new external_value(PARAM_ALPHAEXT, '', VALUE_DEFAULT, 'timedue'),
            'sortdir' => new external_value(PARAM_ALPHA, '', VALUE_DEFAULT, 'asc'),
            'page'    => new external_value(PARAM_INT, '', VALUE_DEFAULT, 0),
            'perpage' => new external_value(PARAM_INT, '', VALUE_DEFAULT, 25),
            'filters' => new external_value(PARAM_RAW, '', VALUE_DEFAULT, '{}'),
        ]);
    }

    public static function execute(string $search = '', string $sort = 'timedue',
                                    string $sortdir = 'asc', int $page = 0,
                                    int $perpage = 25, string $filters = '{}'): array {
        global $DB, $USER;
        $params = self::validate_parameters(self::execute_parameters(),
            compact('search', 'sort', 'sortdir', 'page', 'perpage', 'filters'));
        $ctx = \context_system::instance();
        self::validate_context($ctx);
        require_capability('local/airpay_request:approve', $ctx);

        $allowed = ['timecreated', 'timedue', 'status'];
        $sort = in_array($params['sort'], $allowed, true) ? $params['sort'] : 'timedue';
        $sortdir = strtolower($params['sortdir']) === 'desc' ? 'DESC' : 'ASC';

        // Pending requests where I am the assigned approver.
        $where = 'r.status = :s AND r.approver_userid = :uid';
        $args  = ['s' => 'pending', 'uid' => (int) $USER->id];

        // Status filter from client.
        $client = json_decode($params['filters'] ?: '{}', true) ?: [];
        if (!empty($client['status'])) {
            $where .= ' AND r.status = :st';
            $args['st'] = $client['status'];
        }

        // Free-text search across requester name/email + course name + reason.
        // Approvers care most about "who asked for what" so name + course
        // are the high-signal fields.
        if (trim($params['search']) !== '') {
            $term = '%' . $DB->sql_like_escape(trim($params['search'])) . '%';
            $where .= ' AND ('
                . $DB->sql_like('u.firstname', ':s1', false) . ' OR '
                . $DB->sql_like('u.lastname',  ':s2', false) . ' OR '
                . $DB->sql_like('u.email',     ':s3', false) . ' OR '
                . $DB->sql_like('c.fullname',  ':s4', false) . ' OR '
                . $DB->sql_like('r.reason',    ':s5', false)
                . ')';
            $args['s1'] = $term;
            $args['s2'] = $term;
            $args['s3'] = $term;
            $args['s4'] = $term;
            $args['s5'] = $term;
        }

        // Count and list both need the JOIN now (used by search WHERE).
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
        // 2026-05-22 Bug #6 polish: keep parity with list_mine row_structure
        // (status_badge + status_badge_class + actions) so the shared
        // datatable column renderers don't see undefined values.
        return new external_single_structure([
            'total' => new external_value(PARAM_INT, ''),
            'rows'  => new external_multiple_structure(new external_single_structure([
                'id'                 => new external_value(PARAM_INT, ''),
                'course_name'        => new external_value(PARAM_TEXT, ''),
                'courseid'           => new external_value(PARAM_INT, ''),
                'status'             => new external_value(PARAM_ALPHANUMEXT, ''),
                'status_badge'       => new external_value(PARAM_TEXT, ''),
                'status_badge_class' => new external_value(PARAM_ALPHANUMEXT, ''),
                'route'              => new external_value(PARAM_ALPHANUMEXT, ''),
                'reason'             => new external_value(PARAM_TEXT, ''),
                'decision_note'      => new external_value(PARAM_TEXT, ''),
                'placed_on'          => new external_value(PARAM_TEXT, ''),
                'decided_on'         => new external_value(PARAM_TEXT, ''),
                'due_on'             => new external_value(PARAM_TEXT, ''),
                'is_overdue'         => new external_value(PARAM_BOOL, ''),
                'actions'            => new external_value(PARAM_RAW, ''),
                'requester_name'     => new external_value(PARAM_TEXT, ''),
                'requester_email'    => new external_value(PARAM_TEXT, ''),
            ])),
            'page'    => new external_value(PARAM_INT, ''),
            'perpage' => new external_value(PARAM_INT, ''),
        ]);
    }
}
