<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

/**
 * Admin credits page: the credit bookings and balances imported from BizLMS (ADR-032).
 *
 * Frozen history for finance. Nothing in Sentientia pays out, honours or writes off a balance (that is a finance
 * decision that is still open), so this page only shows what was imported. It is behind the default-OFF flag
 * sentientia.cart.imported_credits.enabled, needs :viewallorders, and shows a scoped tenant admin only their own
 * tenant (a booking whose tenant could not be resolved is for cross-tenant administrators only).
 *
 * @package local_sentientia_cart
 */

require_once(__DIR__ . '/../../config.php');
require_login();

global $DB, $OUTPUT, $PAGE;

$ctx = context_system::instance();
$PAGE->set_context($ctx);
$PAGE->set_url(new moodle_url('/local/sentientia_cart/credits.php'));
$PAGE->set_pagelayout('admin');
$PAGE->set_title(get_string('creditsadmin_title', 'local_sentientia_cart'));
$PAGE->set_heading(get_string('creditsadmin_title', 'local_sentientia_cart'));
require_capability('local/sentientia_cart:viewallorders', $ctx);

// With the flag OFF the page answers as if it did not exist.
if (!\local_sentientia_cart\imported_history::credits_enabled()) {
    throw new \moodle_exception('error_notavailable', 'local_sentientia_cart');
}

$page = max(0, optional_param('page', 0, PARAM_INT));
$perpage = 50;

[$tnsql, $tnargs] = \local_sentientia_platform\tenant::sql_filter('t');
$names = \core_user\fields::for_name()->get_sql('u');

$balances = [];
$records = $DB->get_records_sql(
    "SELECT c.id, c.userid, c.balance, c.currency, c.lifetime_earned, c.lifetime_spent{$names->selects}
       FROM {local_sentientia_cart_credits} c
       JOIN {user} u ON u.id = c.userid
      WHERE EXISTS (SELECT 1 FROM {local_sentientia_cart_credit_txn} t WHERE t.userid = c.userid AND $tnsql)
   ORDER BY u.lastname ASC, u.firstname ASC, c.id ASC",
    $tnargs, 0, 500);
foreach ($records as $r) {
    $symbol = \local_sentientia_cart\invoicer::currency_symbol((string) $r->currency);
    $balances[] = [
        'user'    => fullname($r),
        'balance' => $symbol . number_format((float) $r->balance, 2),
        'earned'  => $symbol . number_format((float) $r->lifetime_earned, 2),
        'spent'   => $symbol . number_format((float) $r->lifetime_spent, 2),
    ];
}

$total = (int) $DB->count_records_sql(
    "SELECT COUNT(1) FROM {local_sentientia_cart_credit_txn} t WHERE $tnsql", $tnargs);
$transactions = [];
if ($total > 0) {
    $records = $DB->get_records_sql(
        "SELECT t.id, t.userid, t.event_type, t.amount, t.balance_after, t.currency, t.orderid, t.timecreated,
                u.id AS holderid{$names->selects}
           FROM {local_sentientia_cart_credit_txn} t
      LEFT JOIN {user} u ON u.id = t.userid
          WHERE $tnsql
       ORDER BY t.timecreated DESC, t.id DESC",
        $tnargs, $page * $perpage, $perpage);
    foreach ($records as $r) {
        $symbol = \local_sentientia_cart\invoicer::currency_symbol((string) $r->currency);
        $label = 'credit_event_' . $r->event_type;
        $transactions[] = [
            'when'    => $r->timecreated ? userdate((int) $r->timecreated, '%d %b %Y') : '',
            'user'    => $r->holderid ? fullname($r) : '',
            'event'   => get_string_manager()->string_exists($label, 'local_sentientia_cart')
                ? get_string($label, 'local_sentientia_cart') : (string) $r->event_type,
            'order'   => $r->orderid ? (int) $r->orderid : '',
            'amount'  => $symbol . number_format((float) $r->amount, 2),
            'balance' => $symbol . number_format((float) $r->balance_after, 2),
        ];
    }
}

$data = [
    'hasbalances'     => !empty($balances),
    'balances'        => $balances,
    'hastransactions' => !empty($transactions),
    'transactions'    => $transactions,
    'orders_url'      => (new moodle_url('/local/sentientia_cart/admin_orders.php'))->out(false),
    'paging'          => $total > $perpage
        ? $OUTPUT->paging_bar($total, $page, $perpage, $PAGE->url) : '',
];

echo $OUTPUT->header();
echo $OUTPUT->render_from_template('local_sentientia_cart/credits', $data);
echo $OUTPUT->footer();
