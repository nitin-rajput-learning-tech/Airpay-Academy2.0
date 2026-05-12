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

class list_review_queue extends external_api {
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'page'    => new external_value(PARAM_INT, '', VALUE_DEFAULT, 0),
            'perpage' => new external_value(PARAM_INT, '', VALUE_DEFAULT, 25),
        ]);
    }
    public static function execute(int $page = 0, int $perpage = 25): array {
        global $DB;
        $params = self::validate_parameters(self::execute_parameters(),
            compact('page', 'perpage'));
        $ctx = \context_system::instance();
        self::validate_context($ctx);
        require_capability('local/airpay_proctoring:review', $ctx);

        // ── B2 fix: tenant scoping on review queue ──────────────────────
        // The :review cap is granted system-wide. Without this filter
        // a reviewer in one tenant could see + decide on sessions
        // belonging to candidates in another tenant — including
        // identity match scores and biometric provenance.
        [$tnsql, $tnargs] = \local_airpay_core\tenant::sql_filter('s');
        $total = (int) $DB->count_records_sql(
            "SELECT COUNT(*) FROM {local_airpay_proctor_sessions} s
              WHERE s.status = 'flagged' AND $tnsql", $tnargs);
        $rows = [];
        if ($total > 0) {
            $records = $DB->get_records_sql(
                "SELECT s.*, u.firstname, u.lastname, u.email, q.name AS quiz_name
                   FROM {local_airpay_proctor_sessions} s
              LEFT JOIN {user} u ON u.id = s.userid
              LEFT JOIN {quiz} q ON q.id = s.quizid
                  WHERE s.status = 'flagged' AND $tnsql
               ORDER BY s.risk_score DESC, s.timecreated DESC",
                $tnargs, $params['page'] * $params['perpage'], $params['perpage']);
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
