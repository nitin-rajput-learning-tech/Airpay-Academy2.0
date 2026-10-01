<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

/**
 * Order history — list of past orders for current user.
 *
 * @package local_sentientia_cart
 */

require_once(__DIR__ . '/../../config.php');
require_login();

global $USER, $OUTPUT, $PAGE;

$ctx = context_system::instance();
$PAGE->set_context($ctx);
$PAGE->set_url(new moodle_url('/local/sentientia_cart/history.php'));
$PAGE->set_pagelayout('standard');
$PAGE->set_title(get_string('orderhistory', 'local_sentientia_cart'));
$PAGE->set_heading(get_string('orderhistory', 'local_sentientia_cart'));
require_capability('local/sentientia_cart:view', $ctx);

// ADR-032 (2026-10-01): the # / Total / Status columns read keys (orderid_link, total_str, statuslabel) that
// list_orders never returned, so they rendered empty. They now read the keys it does return: orderid,
// placed_on, total_amount, status. (An order imported from BizLMS never appears here: it is admin-only history.)
$columns = [
    ['key' => 'orderid',      'label' => '#',         'sortable' => true,  'sortkey' => 'orderid'],
    ['key' => 'placed_on',    'label' => 'Placed',    'sortable' => true,  'sortkey' => 'timecreated'],
    ['key' => 'total_amount', 'label' => 'Total',     'sortable' => true,  'sortkey' => 'total_amount'],
    ['key' => 'status',       'label' => 'Status',    'sortable' => true,  'sortkey' => 'status'],
];

$data = [
    // Bug fix 2026-05-22 (Goal A audit Bug #12 part 2): the wrapper
    // `s(json_encode(...))` double-escapes — Mustache's `{{ columns_json }}`
    // already HTML-escapes once on render, the browser auto-unescapes
    // once on dataset read; the extra `s()` makes JSON.parse() choke at
    // position 2 ("Expected property name or '}'"). Same shape as the
    // sentientia_request fix in commit 89fb2e713.
    'columns_json' => json_encode($columns),
    'is_admin'     => false,
    'back_url'     => (new moodle_url('/local/sentientia_cart/index.php'))->out(false),
];

echo $OUTPUT->header();
echo $OUTPUT->render_from_template('local_sentientia_cart/history', $data);
echo $OUTPUT->footer();
