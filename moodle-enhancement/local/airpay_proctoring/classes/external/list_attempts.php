<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

namespace local_airpay_proctoring\external;

defined('MOODLE_INTERNAL') || die();

use core_external\external_api;
use core_external\external_function_parameters;
use core_external\external_single_structure;
use core_external\external_multiple_structure;
use core_external\external_value;

class list_attempts extends external_api {
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
        $params = self::validate_parameters(self::execute_parameters(), compact(
            'search', 'sort', 'sortdir', 'page', 'perpage', 'filters'));
        $ctx = \context_system::instance();
        self::validate_context($ctx);
        require_capability('local/airpay_proctoring:viewattempts', $ctx);

        $allowed = ['timecreated', 'risk_score', 'status'];
        $sort = in_array($params['sort'], $allowed, true) ? $params['sort'] : 'timecreated';
        $sortdir = strtolower($params['sortdir']) === 'asc' ? 'ASC' : 'DESC';

        $where = '1=1';
        $args = [];
        $client = json_decode($params['filters'] ?: '{}', true) ?: [];
        if (!empty($client['status'])) {
            $where .= ' AND s.status = :st';
            $args['st'] = $client['status'];
        }
        if (!empty($params['search'])) {
            $term = '%' . $DB->sql_like_escape($params['search']) . '%';
            $where .= ' AND (' . $DB->sql_like('u.email', ':s1', false) . ' OR '
                . $DB->sql_like('q.name', ':s2', false) . ')';
            $args['s1'] = $term; $args['s2'] = $term;
        }
        $total = (int) $DB->count_records_sql(
            "SELECT COUNT(*) FROM {local_airpay_proctor_sessions} s
        LEFT JOIN {user} u ON u.id = s.userid
        LEFT JOIN {quiz} q ON q.id = s.quizid
             WHERE $where", $args);

        $rows = [];
        if ($total > 0) {
            $records = $DB->get_records_sql(
                "SELECT s.*, u.firstname, u.lastname, u.email, q.name AS quiz_name
                   FROM {local_airpay_proctor_sessions} s
              LEFT JOIN {user} u ON u.id = s.userid
              LEFT JOIN {quiz} q ON q.id = s.quizid
                  WHERE $where
               ORDER BY s.$sort $sortdir, s.id DESC",
                $args,
                $params['page'] * $params['perpage'], $params['perpage']);
            foreach ($records as $r) {
                $rows[] = [
                    'id'              => (int) $r->id,
                    'quiz_name'       => format_string($r->quiz_name ?? ''),
                    'user_name'       => trim(($r->firstname ?? '') . ' ' . ($r->lastname ?? '')),
                    'user_email'      => (string) ($r->email ?? ''),
                    'status'          => $r->status,
                    'risk_score'      => (float) ($r->risk_score ?? 0),
                    'auto_decision'   => (string) ($r->auto_decision ?? ''),
                    'human_decision'  => (string) ($r->human_decision ?? ''),
                    'started_on'      => $r->timestarted ? userdate($r->timestarted, '%d %b %Y %H:%M') : '',
                    'finished_on'     => $r->timefinished ? userdate($r->timefinished, '%d %b %Y %H:%M') : '',
                ];
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
                'quiz_name'       => new external_value(PARAM_TEXT, ''),
                'user_name'       => new external_value(PARAM_TEXT, ''),
                'user_email'      => new external_value(PARAM_TEXT, ''),
                'status'          => new external_value(PARAM_ALPHANUMEXT, ''),
                'risk_score'      => new external_value(PARAM_FLOAT, ''),
                'auto_decision'   => new external_value(PARAM_TEXT, ''),
                'human_decision'  => new external_value(PARAM_TEXT, ''),
                'started_on'      => new external_value(PARAM_TEXT, ''),
                'finished_on'     => new external_value(PARAM_TEXT, ''),
            ])),
            'page'    => new external_value(PARAM_INT, ''),
            'perpage' => new external_value(PARAM_INT, ''),
        ]);
    }
}
