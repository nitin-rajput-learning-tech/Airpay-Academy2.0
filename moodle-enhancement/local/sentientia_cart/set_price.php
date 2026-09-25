<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

/**
 * Per-course price management.
 *
 * Admins set / update / unset purchase prices. Stored as Moodle
 * enrol_fee instances (single source of truth).
 *
 * @package local_sentientia_cart
 */

require_once(__DIR__ . '/../../config.php');
require_login();

global $DB, $OUTPUT, $PAGE;

$ctx = context_system::instance();
$PAGE->set_context($ctx);
$PAGE->set_url(new moodle_url('/local/sentientia_cart/set_price.php'));
$PAGE->set_pagelayout('admin');
$PAGE->set_title('Course pricing');
$PAGE->set_heading('Course pricing');
require_capability('local/sentientia_cart:manageprices', $ctx);

// Load courses + current price for each (LEFT JOIN enrol_fee).
// ADR-031: only the caller's tenant's courses. This listed every tenant's
// courses and prices to any :manageprices holder (every tenant admin).
$rows = \local_sentientia_cart\cart_manager::list_course_prices(200);

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
echo $OUTPUT->render_from_template('local_sentientia_cart/set_price', $data);
echo $OUTPUT->footer();
