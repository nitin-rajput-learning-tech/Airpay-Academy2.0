<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

/**
 * Order history — list of past orders for current user.
 *
 * @package local_airpay_cart
 */

require_once(__DIR__ . '/../../config.php');
require_login();

global $USER, $OUTPUT, $PAGE;

$ctx = context_system::instance();
$PAGE->set_context($ctx);
$PAGE->set_url(new moodle_url('/local/airpay_cart/history.php'));
$PAGE->set_pagelayout('standard');
$PAGE->set_title(get_string('orderhistory', 'local_airpay_cart'));
$PAGE->set_heading(get_string('orderhistory', 'local_airpay_cart'));
require_capability('local/airpay_cart:view', $ctx);

$columns = [
    ['key' => 'orderid_link', 'label' => '#',         'sortable' => true,  'sortkey' => 'orderid', 'format' => 'html'],
    ['key' => 'placed_on',    'label' => 'Placed',    'sortable' => true,  'sortkey' => 'timecreated'],
    ['key' => 'total_str',    'label' => 'Total',     'sortable' => true,  'sortkey' => 'total_amount'],
    ['key' => 'statuslabel',  'label' => 'Status',    'sortable' => true,  'sortkey' => 'status', 'format' => 'badge'],
    ['key' => 'actions',      'label' => '',          'sortable' => false, 'format' => 'html'],
];

$data = [
    // Bug fix 2026-05-22 (Goal A audit Bug #12 part 2): the wrapper
    // `s(json_encode(...))` double-escapes — Mustache's `{{ columns_json }}`
    // already HTML-escapes once on render, the browser auto-unescapes
    // once on dataset read; the extra `s()` makes JSON.parse() choke at
    // position 2 ("Expected property name or '}'"). Same shape as the
    // airpay_request fix in commit 89fb2e713.
    'columns_json' => json_encode($columns),
    'is_admin'     => false,
    'back_url'     => (new moodle_url('/local/airpay_cart/index.php'))->out(false),
];

echo $OUTPUT->header();
echo $OUTPUT->render_from_template('local_airpay_cart/history', $data);
echo $OUTPUT->footer();
