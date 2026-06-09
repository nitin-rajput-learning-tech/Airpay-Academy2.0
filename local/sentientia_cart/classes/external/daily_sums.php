<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

namespace local_sentientia_cart\external;

defined('MOODLE_INTERNAL') || die();

use core_external\external_api;
use core_external\external_function_parameters;
use core_external\external_single_structure;
use core_external\external_multiple_structure;
use core_external\external_value;

/**
 * Daily payment sums report for finance reconciliation.
 *
 * Reads from the immutable ledger so figures match the bank's settlement
 * report (1-to-1 audit).
 */
class daily_sums extends external_api {

    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'from' => new external_value(PARAM_TEXT, 'ISO date YYYY-MM-DD'),
            'to'   => new external_value(PARAM_TEXT, 'ISO date YYYY-MM-DD'),
        ]);
    }

    public static function execute(string $from, string $to): array {
        global $DB;
        $params = self::validate_parameters(self::execute_parameters(),
            compact('from', 'to'));
        $ctx = \context_system::instance();
        self::validate_context($ctx);
        require_capability('local/sentientia_cart:viewallorders', $ctx);

        $fromts = strtotime($params['from'] . ' 00:00:00');
        $tots   = strtotime($params['to']   . ' 23:59:59');
        if (!$fromts || !$tots || $fromts > $tots) {
            throw new \moodle_exception('error_invalidstate', 'local_sentientia_cart',
                '', 'Invalid date range');
        }

        // ── B1 fix: tenant scoping on the sums query ────────────────────
        // Ledger rows don't carry costcenterid themselves — the parent
        // history row does. JOIN through and apply the tenant filter.
        // Site admins see the global view; tenant-bound managers see
        // only their tenant's ledger.
        [$tnsql, $tnargs] = \local_sentientia_platform\tenant::sql_filter('h');
        $rows = $DB->get_records_sql(
            "SELECT DATE(FROM_UNIXTIME(l.timecreated)) AS day,
                    l.gateway,
                    l.currency,
                    SUM(CASE WHEN l.event_type = 'payment_received' THEN l.amount ELSE 0 END) AS inflow,
                    SUM(CASE WHEN l.event_type IN ('refund_full','refund_partial') THEN l.amount ELSE 0 END) AS outflow,
                    COUNT(CASE WHEN l.event_type = 'payment_received' THEN 1 END) AS payments,
                    COUNT(CASE WHEN l.event_type IN ('refund_full','refund_partial') THEN 1 END) AS refunds
               FROM {local_sentientia_cart_ledger} l
               JOIN {local_sentientia_cart_history} h ON h.id = l.historyid
              WHERE l.timecreated BETWEEN :f AND :t
                AND $tnsql
           GROUP BY day, l.gateway, l.currency
           ORDER BY day DESC, l.gateway, l.currency",
            array_merge(['f' => $fromts, 't' => $tots], $tnargs));

        $out = [];
        foreach ($rows as $r) {
            $out[] = [
                'day'      => $r->day,
                'gateway'  => $r->gateway,
                'currency' => $r->currency,
                'inflow'   => (float) $r->inflow,
                'outflow'  => (float) $r->outflow,
                'net'      => (float) ($r->inflow + $r->outflow),  // outflow is negative
                'payments' => (int) $r->payments,
                'refunds'  => (int) $r->refunds,
            ];
        }
        return ['days' => $out];
    }

    public static function execute_returns(): external_single_structure {
        return new external_single_structure([
            'days' => new external_multiple_structure(
                new external_single_structure([
                    'day'      => new external_value(PARAM_TEXT, ''),
                    'gateway'  => new external_value(PARAM_ALPHANUMEXT, ''),
                    'currency' => new external_value(PARAM_ALPHA, ''),
                    'inflow'   => new external_value(PARAM_FLOAT, ''),
                    'outflow'  => new external_value(PARAM_FLOAT, ''),
                    'net'      => new external_value(PARAM_FLOAT, ''),
                    'payments' => new external_value(PARAM_INT, ''),
                    'refunds'  => new external_value(PARAM_INT, ''),
                ])),
        ]);
    }
}
