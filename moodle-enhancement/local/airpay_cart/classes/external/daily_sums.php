<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

namespace local_airpay_cart\external;

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
        require_capability('local/airpay_cart:viewallorders', $ctx);

        $fromts = strtotime($params['from'] . ' 00:00:00');
        $tots   = strtotime($params['to']   . ' 23:59:59');
        if (!$fromts || !$tots || $fromts > $tots) {
            throw new \moodle_exception('error_invalidstate', 'local_airpay_cart',
                '', 'Invalid date range');
        }

        $rows = $DB->get_records_sql(
            "SELECT DATE(FROM_UNIXTIME(timecreated)) AS day,
                    gateway,
                    currency,
                    SUM(CASE WHEN event_type = 'payment_received' THEN amount ELSE 0 END) AS inflow,
                    SUM(CASE WHEN event_type IN ('refund_full','refund_partial') THEN amount ELSE 0 END) AS outflow,
                    COUNT(CASE WHEN event_type = 'payment_received' THEN 1 END) AS payments,
                    COUNT(CASE WHEN event_type IN ('refund_full','refund_partial') THEN 1 END) AS refunds
               FROM {local_airpay_cart_ledger}
              WHERE timecreated BETWEEN :f AND :t
           GROUP BY day, gateway, currency
           ORDER BY day DESC, gateway, currency",
            ['f' => $fromts, 't' => $tots]);

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
