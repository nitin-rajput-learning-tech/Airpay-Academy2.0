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

/**
 * Compliance report: per-day breakdown of clean / flagged / failed sessions.
 *
 * Used by sentientia_compliance_report dashboard widget to show "proctoring
 * health" at-a-glance.
 */
class compliance_report extends external_api {
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'from' => new external_value(PARAM_TEXT, 'YYYY-MM-DD'),
            'to'   => new external_value(PARAM_TEXT, 'YYYY-MM-DD'),
        ]);
    }
    public static function execute(string $from, string $to): array {
        global $DB;
        $params = self::validate_parameters(self::execute_parameters(),
            compact('from', 'to'));
        $ctx = \context_system::instance();
        self::validate_context($ctx);
        require_capability('local/sentientia_proctoring:review', $ctx);

        $fromts = strtotime($params['from'] . ' 00:00:00');
        $tots   = strtotime($params['to']   . ' 23:59:59');
        if (!$fromts || !$tots || $fromts > $tots) {
            throw new \moodle_exception('error_session_state', 'local_sentientia_proctoring');
        }

        // ── B2 fix: tenant scoping on aggregate ──────────────────────────
        // Compliance dashboard for a tenant-bound reviewer must only sum
        // their own tenant's sessions. Site admin sees the global view.
        [$tnsql, $tnargs] = \local_airpay_core\tenant::sql_filter('s');
        $rows = $DB->get_records_sql(
            "SELECT DATE(FROM_UNIXTIME(s.timecreated)) AS day,
                    COUNT(*) AS total,
                    SUM(CASE WHEN s.auto_decision='clean' THEN 1 ELSE 0 END) AS auto_clean,
                    SUM(CASE WHEN s.auto_decision='warn' THEN 1 ELSE 0 END) AS auto_warn,
                    SUM(CASE WHEN s.auto_decision='fail' THEN 1 ELSE 0 END) AS auto_fail,
                    SUM(CASE WHEN s.human_decision='clean' THEN 1 ELSE 0 END) AS rev_clean,
                    SUM(CASE WHEN s.human_decision='warn' THEN 1 ELSE 0 END) AS rev_warn,
                    SUM(CASE WHEN s.human_decision='fail' THEN 1 ELSE 0 END) AS rev_fail,
                    AVG(s.risk_score) AS avg_risk
               FROM {local_sentientia_proctor_sessions} s
              WHERE s.timecreated BETWEEN :f AND :t
                AND $tnsql
           GROUP BY day
           ORDER BY day DESC",
            array_merge(['f' => $fromts, 't' => $tots], $tnargs));

        $out = [];
        foreach ($rows as $r) {
            $out[] = [
                'day'        => $r->day,
                'total'      => (int) $r->total,
                'auto_clean' => (int) $r->auto_clean,
                'auto_warn'  => (int) $r->auto_warn,
                'auto_fail'  => (int) $r->auto_fail,
                'rev_clean'  => (int) $r->rev_clean,
                'rev_warn'   => (int) $r->rev_warn,
                'rev_fail'   => (int) $r->rev_fail,
                'avg_risk'   => (float) ($r->avg_risk ?? 0),
            ];
        }
        return ['days' => $out];
    }
    public static function execute_returns(): external_single_structure {
        return new external_single_structure([
            'days' => new external_multiple_structure(new external_single_structure([
                'day'        => new external_value(PARAM_TEXT, ''),
                'total'      => new external_value(PARAM_INT, ''),
                'auto_clean' => new external_value(PARAM_INT, ''),
                'auto_warn'  => new external_value(PARAM_INT, ''),
                'auto_fail'  => new external_value(PARAM_INT, ''),
                'rev_clean'  => new external_value(PARAM_INT, ''),
                'rev_warn'   => new external_value(PARAM_INT, ''),
                'rev_fail'   => new external_value(PARAM_INT, ''),
                'avg_risk'   => new external_value(PARAM_FLOAT, ''),
            ])),
        ]);
    }
}
