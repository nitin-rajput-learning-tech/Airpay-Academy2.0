<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

/**
 * Daily sums CSV export — for finance reconciliation downloads.
 *
 * @package local_airpay_cart
 */

require_once(__DIR__ . '/../../config.php');
require_login();

global $DB;

$ctx = context_system::instance();
require_capability('local/airpay_cart:viewallorders', $ctx);

$from = required_param('from', PARAM_TEXT);
$to   = required_param('to', PARAM_TEXT);
$fromts = strtotime($from . ' 00:00:00');
$tots   = strtotime($to   . ' 23:59:59');
if (!$fromts || !$tots || $fromts > $tots) {
    throw new \moodle_exception('error_invalidstate', 'local_airpay_cart');
}

$rows = $DB->get_records_sql(
    "SELECT DATE(FROM_UNIXTIME(timecreated)) AS day,
            gateway, currency,
            SUM(CASE WHEN event_type = 'payment_received' THEN amount ELSE 0 END) AS inflow,
            SUM(CASE WHEN event_type IN ('refund_full','refund_partial') THEN amount ELSE 0 END) AS outflow,
            COUNT(CASE WHEN event_type = 'payment_received' THEN 1 END) AS payments,
            COUNT(CASE WHEN event_type IN ('refund_full','refund_partial') THEN 1 END) AS refunds
       FROM {local_airpay_cart_ledger}
      WHERE timecreated BETWEEN :f AND :t
   GROUP BY day, gateway, currency
   ORDER BY day DESC, gateway, currency",
    ['f' => $fromts, 't' => $tots]);

$filename = sprintf('airpay_cart_daily_sums_%s_to_%s.csv', $from, $to);
header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="' . $filename . '"');
header('Cache-Control: no-cache, no-store, must-revalidate');

$out = fopen('php://output', 'w');
fwrite($out, "\xEF\xBB\xBF");  // UTF-8 BOM

fputcsv($out, ['Date', 'Gateway', 'Currency', 'Inflow', 'Outflow', 'Net', 'Payments', 'Refunds']);
foreach ($rows as $r) {
    $net = (float) $r->inflow + (float) $r->outflow;  // outflow is negative
    fputcsv($out, [
        $r->day,
        $r->gateway,
        $r->currency,
        number_format((float) $r->inflow, 2, '.', ''),
        number_format((float) $r->outflow, 2, '.', ''),
        number_format($net, 2, '.', ''),
        (int) $r->payments,
        (int) $r->refunds,
    ]);
}
fclose($out);
exit;
