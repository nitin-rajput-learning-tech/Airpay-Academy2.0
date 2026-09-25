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
        // history row does. cart_manager::daily_sums() joins through and
        // applies the tenant filter; daily_sums_csv.php shares it (ADR-031:
        // the CSV used to run an unscoped copy of this query).
        $rows = \local_sentientia_cart\cart_manager::daily_sums($fromts, $tots);

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
