<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

namespace local_sentientia_proctoring\external;

defined('MOODLE_INTERNAL') || die();

use core_external\external_api;
use core_external\external_function_parameters;
use core_external\external_single_structure;
use core_external\external_multiple_structure;
use core_external\external_value;

class list_review_queue extends external_api {
    public static function execute_parameters(): external_function_parameters {
        // Goal A audit Bug #10 (2026-05-22): align with the shared
        // theme_airpayux/datatable client contract — `search`, `sort`,
        // `sortdir`, `filters` are always sent by the client even when
        // the user hasn't typed anything. Strict validator rejects
        // unknown keys → datatable stuck on Loading…
        return new external_function_parameters([
            'search'  => new external_value(PARAM_TEXT, 'Free-text search', VALUE_DEFAULT, ''),
            'sort'    => new external_value(PARAM_ALPHAEXT, 'Sort col', VALUE_DEFAULT, 'risk_score'),
            'sortdir' => new external_value(PARAM_ALPHA, 'asc|desc', VALUE_DEFAULT, 'desc'),
            'page'    => new external_value(PARAM_INT, '', VALUE_DEFAULT, 0),
            'perpage' => new external_value(PARAM_INT, '', VALUE_DEFAULT, 25),
            'filters' => new external_value(PARAM_RAW, 'Reserved', VALUE_DEFAULT, '{}'),
        ]);
    }
    public static function execute(string $search = '', string $sort = 'risk_score',
                                    string $sortdir = 'desc', int $page = 0,
                                    int $perpage = 25, string $filters = '{}'): array {
        global $DB;
        $params = self::validate_parameters(self::execute_parameters(),
            compact('search', 'sort', 'sortdir', 'page', 'perpage', 'filters'));
        $ctx = \context_system::instance();
        self::validate_context($ctx);
        require_capability('local/sentientia_proctoring:review', $ctx);

        // ── B2 fix: tenant scoping on review queue ──────────────────────
        // The :review cap is granted system-wide. Without this filter
        // a reviewer in one tenant could see + decide on sessions
        // belonging to candidates in another tenant — including
        // identity match scores and biometric provenance.
        [$tnsql, $tnargs] = \local_airpay_core\tenant::sql_filter('s');

        $where = "s.status = 'flagged' AND $tnsql";
        $args  = $tnargs;

        // Goal A Bug #10: optional free-text search on candidate name + quiz.
        // Note: q.name lives in the {quiz} table from mod_quiz — we already
        // JOIN to it for display, so reusing the JOIN here costs nothing.
        if (trim($params['search']) !== '') {
            $term = '%' . $DB->sql_like_escape(trim($params['search'])) . '%';
            $where .= ' AND ('
                . $DB->sql_like('u.firstname', ':s1', false) . ' OR '
                . $DB->sql_like('u.lastname',  ':s2', false) . ' OR '
                . $DB->sql_like('u.email',     ':s3', false) . ' OR '
                . $DB->sql_like('q.name',      ':s4', false)
                . ')';
            $args['s1'] = $term;
            $args['s2'] = $term;
            $args['s3'] = $term;
            $args['s4'] = $term;
        }

        // Allow client-side sort on risk_score | timecreated only. Anything
        // else falls back to risk_score DESC to keep highest-risk on top.
        $allowed = ['risk_score', 'timecreated'];
        $sortcol = in_array($params['sort'], $allowed, true)
            ? $params['sort'] : 'risk_score';
        $sortdir = strtolower($params['sortdir']) === 'asc' ? 'ASC' : 'DESC';

        $total = (int) $DB->count_records_sql(
            "SELECT COUNT(*) FROM {local_sentientia_proctor_sessions} s
        LEFT JOIN {user} u ON u.id = s.userid
        LEFT JOIN {quiz} q ON q.id = s.quizid
             WHERE $where", $args);
        $rows = [];
        if ($total > 0) {
            $records = $DB->get_records_sql(
                "SELECT s.*, u.firstname, u.lastname, u.email, q.name AS quiz_name
                   FROM {local_sentientia_proctor_sessions} s
              LEFT JOIN {user} u ON u.id = s.userid
              LEFT JOIN {quiz} q ON q.id = s.quizid
                  WHERE $where
               ORDER BY s.$sortcol $sortdir, s.timecreated DESC",
                $args, $params['page'] * $params['perpage'], $params['perpage']);
            foreach ($records as $r) {
                $rows[] = [
                    'id'           => (int) $r->id,
                    'quiz_name'    => format_string($r->quiz_name ?? ''),
                    'user_name'    => trim(($r->firstname ?? '') . ' ' . ($r->lastname ?? '')),
                    'risk_score'   => (float) ($r->risk_score ?? 0),
                    'auto_decision' => (string) ($r->auto_decision ?? ''),
                    'finished_on'  => $r->timefinished ? userdate($r->timefinished, '%d %b %H:%M') : '',
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
                'id'           => new external_value(PARAM_INT, ''),
                'quiz_name'    => new external_value(PARAM_TEXT, ''),
                'user_name'    => new external_value(PARAM_TEXT, ''),
                'risk_score'   => new external_value(PARAM_FLOAT, ''),
                'auto_decision' => new external_value(PARAM_TEXT, ''),
                'finished_on'  => new external_value(PARAM_TEXT, ''),
            ])),
            'page'    => new external_value(PARAM_INT, ''),
            'perpage' => new external_value(PARAM_INT, ''),
        ]);
    }
}
