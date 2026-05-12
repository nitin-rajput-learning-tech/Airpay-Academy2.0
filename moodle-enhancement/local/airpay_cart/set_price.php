<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

/**
 * Per-course price management.
 *
 * Admins set / update / unset purchase prices. Stored as Moodle
 * enrol_fee instances (single source of truth).
 *
 * @package local_airpay_cart
 */

require_once(__DIR__ . '/../../config.php');
require_login();

global $DB, $OUTPUT, $PAGE;

$ctx = context_system::instance();
$PAGE->set_context($ctx);
$PAGE->set_url(new moodle_url('/local/airpay_cart/set_price.php'));
$PAGE->set_pagelayout('admin');
$PAGE->set_title('Course pricing');
$PAGE->set_heading('Course pricing');
require_capability('local/airpay_cart:manageprices', $ctx);

// Load courses + current price for each (LEFT JOIN enrol_fee).
$rows = $DB->get_records_sql(
    "SELECT c.id, c.fullname, c.shortname,
            e.cost AS price, e.currency, e.status AS fee_status
       FROM {course} c
  LEFT JOIN {enrol} e ON e.courseid = c.id AND e.enrol = 'fee'
      WHERE c.id > 1
      ORDER BY c.fullname ASC
      LIMIT 200");

$tablerows = [];
foreach ($rows as $r) {
    $has_price = !empty($r->price) && ((int) ($r->fee_status ?? 1) === 0);
    $tablerows[] = [
        'id'        => (int) $r->id,
        'fullname'  => format_string($r->fullname),
        'shortname' => format_string($r->shortname),
        'has_price' => $has_price,
        'price'     => $has_price ? number_format((float) $r->price, 2) : '',
        'currency'  => $r->currency ?: 'INR',
    ];
}

$data = [
    'rows' => $tablerows,
    'total' => count($tablerows),
];

echo $OUTPUT->header();
echo $OUTPUT->render_from_template('local_airpay_cart/set_price', $data);
echo $OUTPUT->footer();
