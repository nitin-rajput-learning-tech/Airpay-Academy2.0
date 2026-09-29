<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

/**
 * Admin orders dashboard — list, filter, refund.
 *
 * @package local_sentientia_cart
 */

require_once(__DIR__ . '/../../config.php');
require_login();

global $OUTPUT, $PAGE;

$ctx = context_system::instance();
$PAGE->set_context($ctx);
$PAGE->set_url(new moodle_url('/local/sentientia_cart/admin_orders.php'));
$PAGE->set_pagelayout('admin');
$PAGE->set_title(get_string('allorders', 'local_sentientia_cart'));
$PAGE->set_heading(get_string('allorders', 'local_sentientia_cart'));
require_capability('local/sentientia_cart:viewallorders', $ctx);

$columns = [
    // 2026-09-29: the #, User, Total and Status columns read keys
    // (orderid_link, user_link, total_str, statuslabel) that list_orders never
    // returned, so they rendered empty and a "Refund due" note could not be
    // tied to an order. They now read the fields list_orders returns.
    ['key' => 'orderid',       'label' => '#',       'sortable' => true,  'sortkey' => 'orderid'],
    ['key' => 'placed_on',     'label' => 'Placed',  'sortable' => true,  'sortkey' => 'timecreated'],
    ['key' => 'billing_email', 'label' => 'User',    'sortable' => false],
    ['key' => 'total_amount',  'label' => 'Total',   'sortable' => true,  'sortkey' => 'total_amount'],
    ['key' => 'gateway',       'label' => 'Gateway', 'sortable' => false],
    ['key' => 'status',        'label' => 'Status',  'sortable' => true,  'sortkey' => 'status'],
    // ADR-031 decision 3 (2026-09-29): history.notes, from list_orders, which
    // returns it to :viewallorders holders only. Carries the "Refund due" line
    // mark_paid() writes when it withholds an enrolment, and gateway failure
    // reasons. Plain text: the datatable escapes it.
    ['key' => 'notes',        'label' => get_string('ordernotes', 'local_sentientia_cart'), 'sortable' => false],
];

$data = [
    // Bug fix 2026-05-22 (Goal A audit Bug #12 part 2): see history.php
    // sibling for the long-form explanation of the s(json_encode())
    // double-escape that breaks JSON.parse() at position 2.
    'columns_json'    => json_encode($columns),
    'is_admin'        => true,
    'daily_sums_url'  => (new moodle_url('/local/sentientia_cart/daily_sums.php'))->out(false),
    'set_price_url'   => (new moodle_url('/local/sentientia_cart/set_price.php'))->out(false),
];

echo $OUTPUT->header();
echo $OUTPUT->render_from_template('local_sentientia_cart/admin_orders', $data);
echo $OUTPUT->footer();
