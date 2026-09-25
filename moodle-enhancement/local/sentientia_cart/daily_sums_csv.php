<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

/**
 * Daily sums CSV export — for finance reconciliation downloads.
 *
 * @package local_sentientia_cart
 */

require_once(__DIR__ . '/../../config.php');
require_login();

global $DB;

$ctx = context_system::instance();
require_capability('local/sentientia_cart:viewallorders', $ctx);

$from = required_param('from', PARAM_TEXT);
$to   = required_param('to', PARAM_TEXT);
$fromts = strtotime($from . ' 00:00:00');
$tots   = strtotime($to   . ' 23:59:59');
if (!$fromts || !$tots || $fromts > $tots) {
    throw new \moodle_exception('error_invalidstate', 'local_sentientia_cart');
}

// ADR-031 (2026-09-25): this query used to be a private copy of the web
// service's, without the tenant join, so any :viewallorders holder (every
// tenant admin) downloaded every tenant's daily totals. Both now share
// cart_manager::daily_sums(), which is tenant-scoped and fails closed.
$rows = \local_sentientia_cart\cart_manager::daily_sums($fromts, $tots);

$filename = sprintf('sentientia_cart_daily_sums_%s_to_%s.csv', $from, $to);
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
