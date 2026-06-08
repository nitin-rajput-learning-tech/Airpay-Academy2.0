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
    ['key' => 'orderid_link', 'label' => '#',       'sortable' => true,  'sortkey' => 'orderid', 'format' => 'html'],
    ['key' => 'placed_on',    'label' => 'Placed',  'sortable' => true,  'sortkey' => 'timecreated'],
    ['key' => 'user_link',    'label' => 'User',    'sortable' => false, 'format' => 'html'],
    ['key' => 'total_str',    'label' => 'Total',   'sortable' => true,  'sortkey' => 'total_amount'],
    ['key' => 'gateway',      'label' => 'Gateway', 'sortable' => false],
    ['key' => 'statuslabel',  'label' => 'Status',  'sortable' => true,  'sortkey' => 'status', 'format' => 'badge'],
    ['key' => 'actions',      'label' => '',        'sortable' => false, 'format' => 'html'],
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
