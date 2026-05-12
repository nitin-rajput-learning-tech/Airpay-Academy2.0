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

class list_mine extends external_api {

    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'sort'    => new external_value(PARAM_ALPHAEXT, '', VALUE_DEFAULT, 'timecreated'),
            'sortdir' => new external_value(PARAM_ALPHA, '', VALUE_DEFAULT, 'desc'),
            'page'    => new external_value(PARAM_INT, '', VALUE_DEFAULT, 0),
            'perpage' => new external_value(PARAM_INT, '', VALUE_DEFAULT, 25),
            'filters' => new external_value(PARAM_RAW, '', VALUE_DEFAULT, '{}'),
        ]);
    }

    public static function execute(string $sort = 'timecreated', string $sortdir = 'desc',
                                    int $page = 0, int $perpage = 25,
                                    string $filters = '{}'): array {
        global $DB, $USER;
        $params = self::validate_parameters(self::execute_parameters(),
            compact('sort', 'sortdir', 'page', 'perpage', 'filters'));
        $ctx = \context_system::instance();
        self::validate_context($ctx);
        require_capability('local/airpay_request:request', $ctx);

        $allowed = ['timecreated', 'status', 'timedecided'];
        $sort = in_array($params['sort'], $allowed, true) ? $params['sort'] : 'timecreated';
        $sortdir = strtolower($params['sortdir']) === 'asc' ? 'ASC' : 'DESC';

        $where = 'r.userid = :uid';
        $args  = ['uid' => (int) $USER->id];

        $client = json_decode($params['filters'] ?: '{}', true) ?: [];
        if (!empty($client['status'])) {
            $where .= ' AND r.status = :st';
            $args['st'] = $client['status'];
        }

        $total = (int) $DB->count_records_sql(
            "SELECT COUNT(*) FROM {local_airpay_request} r WHERE $where", $args);

        $rows = [];
        if ($total > 0) {
            $records = $DB->get_records_sql(
                "SELECT r.*, c.fullname AS course_name
                   FROM {local_airpay_request} r
              LEFT JOIN {course} c ON c.id = r.courseid
                  WHERE $where
               ORDER BY r.$sort $sortdir, r.id DESC",
                $args,
                $params['page'] * $params['perpage'], $params['perpage']);
            foreach ($records as $r) {
                $rows[] = self::shape($r);
            }
        }
        return ['total' => $total, 'rows' => $rows,
                'page' => $params['page'], 'perpage' => $params['perpage']];
    }

    public static function execute_returns(): external_single_structure {
        return new external_single_structure([
            'total' => new external_value(PARAM_INT, ''),
            'rows'  => new external_multiple_structure(
                self::row_structure()),
            'page'    => new external_value(PARAM_INT, ''),
            'perpage' => new external_value(PARAM_INT, ''),
        ]);
    }

    public static function row_structure(): external_single_structure {
        return new external_single_structure([
            'id'            => new external_value(PARAM_INT, ''),
            'course_name'   => new external_value(PARAM_TEXT, ''),
            'courseid'      => new external_value(PARAM_INT, ''),
            'status'        => new external_value(PARAM_ALPHANUMEXT, ''),
            'route'         => new external_value(PARAM_ALPHANUMEXT, ''),
            'reason'        => new external_value(PARAM_TEXT, ''),
            'decision_note' => new external_value(PARAM_TEXT, ''),
            'placed_on'     => new external_value(PARAM_TEXT, ''),
            'decided_on'    => new external_value(PARAM_TEXT, ''),
            'due_on'        => new external_value(PARAM_TEXT, ''),
            'is_overdue'    => new external_value(PARAM_BOOL, ''),
        ]);
    }

    public static function shape(\stdClass $r): array {
        $now = time();
        return [
            'id'            => (int) $r->id,
            'course_name'   => format_string($r->course_name ?? '(deleted course)'),
            'courseid'      => (int) $r->courseid,
            'status'        => $r->status,
            'route'         => $r->route,
            'reason'        => (string) $r->reason,
            'decision_note' => (string) ($r->decision_note ?? ''),
            'placed_on'     => userdate($r->timecreated, '%d %b %Y %H:%M'),
            'decided_on'    => $r->timedecided ? userdate($r->timedecided, '%d %b %Y %H:%M') : '',
            'due_on'        => $r->timedue ? userdate($r->timedue, '%d %b %Y %H:%M') : '',
            'is_overdue'    => $r->status === 'pending' && $r->timedue && $r->timedue < $now,
        ];
    }
}
