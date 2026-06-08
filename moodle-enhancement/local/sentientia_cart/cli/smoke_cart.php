<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

/**
 * CLI smoke test for sentientia_cart.
 *
 * Exercises end-to-end: set price → add to cart → checkout → mark paid →
 * verify enrolment → invoice issued → refund → verify unenrolment.
 *
 * Usage:
 *   php cli/smoke_cart.php
 *
 * @package local_sentientia_cart
 */

define('CLI_SCRIPT', true);
require_once(__DIR__ . '/../../../config.php');
require_once(__DIR__ . '/../lib.php');  // For local_sentientia_cart_get_course_price()

global $DB;

echo "=== sentientia_cart smoke test ===\n\n";

// Pick a Public-tenant user (the cart's primary audience).
$user = $DB->get_record_sql(
    "SELECT id, username, email, open_path FROM {user}
      WHERE deleted = 0 AND id > 2
        AND open_path LIKE '/77%'
      ORDER BY id ASC LIMIT 1");
if (!$user) {
    echo "FAIL: No Public-tenant user found.\n";
    exit(1);
}
echo "Test user: $user->username (id=$user->id, path=$user->open_path)\n";

// Pick a course that this user is NOT already enrolled in.
$course = $DB->get_record_sql(
    "SELECT c.id, c.fullname, c.shortname FROM {course} c
      WHERE c.id > 1 AND c.visible = 1
        AND c.id NOT IN (
            SELECT e.courseid FROM {enrol} e
            JOIN {user_enrolments} ue ON ue.enrolid = e.id
            WHERE ue.userid = :uid)
      ORDER BY c.id ASC LIMIT 1", ['uid' => $user->id]);
if (!$course) {
    echo "FAIL: No suitable test course.\n";
    exit(1);
}
echo "Test course: $course->fullname (id=$course->id)\n\n";

$test = 0;
$pass = 0;
$check = function(string $name, bool $ok, string $detail = '') use (&$test, &$pass) {
    $test++;
    if ($ok) $pass++;
    printf("  %s [%2d] %s%s\n", $ok ? '✓' : '✗', $test, $name, $detail ? " — $detail" : '');
};

// === 1. Set a price on the test course ===
echo "=== 1. Set course price ===\n";
$enrolplugin = enrol_get_plugin('fee');
if (!$enrolplugin) {
    echo "  ✗ enrol_fee plugin not installed — skipping price setup.\n";
    $check('enrol_fee available', false, 'Cannot continue');
    exit(1);
}
// Use direct DB to avoid full WS plumbing in CLI.
$existing = $DB->get_record('enrol',
    ['courseid' => $course->id, 'enrol' => 'fee']);
if ($existing) {
    $existing->status = 0;
    $existing->cost = 1500.00;
    $existing->currency = 'INR';
    $DB->update_record('enrol', $existing);
} else {
    $enrolplugin->add_instance($course, [
        'name' => 'Smoke test pricing',
        'status' => 0,
        'cost' => 1500.00,
        'currency' => 'INR',
    ]);
}
$price = local_sentientia_cart_get_course_price($course->id);
$check('Course price set to ₹1500', $price === 1500.00, "got $price");

// === 2. Add to cart ===
echo "\n=== 2. Add to cart ===\n";
$cart = \local_sentientia_cart\cart_manager::add_item($user->id, $course->id);
$items = json_decode($cart->items_json, true) ?: [];
$check('Item added', count($items) === 1);
$check('Subtotal = 1500', abs($cart->subtotal - 1500.00) < 0.01, "got {$cart->subtotal}");
$check('GST 18% = 270', abs($cart->tax_amount - 270.00) < 0.01, "got {$cart->tax_amount}");
$check('Total = 1770', abs($cart->total_amount - 1770.00) < 0.01, "got {$cart->total_amount}");

// Idempotency: adding same item should not double.
$cart = \local_sentientia_cart\cart_manager::add_item($user->id, $course->id);
$items = json_decode($cart->items_json, true) ?: [];
$check('Re-add is idempotent', count($items) === 1);

// === 3. Checkout (manual gateway) ===
echo "\n=== 3. Checkout ===\n";
$cart = \local_sentientia_cart\cart_manager::checkout($user->id, [
    'billing_name'    => 'Smoke Test',
    'billing_email'   => $user->email,
    'billing_phone'   => '9999999999',
    'billing_address' => 'Test address',
    'billing_gstn'    => '27AAAAA0000A1Z5',  // Maharashtra → CGST+SGST
], 'manual');
$check('Order number reserved', !empty($cart->orderid), "orderid={$cart->orderid}");
$check('Status = pending', $cart->status === 'pending');
$check('Gateway = manual', $cart->gateway === 'manual');

// === 4. Mark paid ===
echo "\n=== 4. Mark paid ===\n";
$historyid = (int) $cart->id;
$ok = \local_sentientia_cart\cart_manager::mark_paid($historyid, 'SMOKE-REF-' . time());
$check('mark_paid returns true', $ok === true);

$cart_after = $DB->get_record('local_sentientia_cart_history', ['id' => $historyid]);
$check('Status = paid', $cart_after->status === 'paid');
$check('timepaid set', !empty($cart_after->timepaid));

// Ledger row?
$ledger = $DB->get_records('local_sentientia_cart_ledger', ['historyid' => $historyid]);
$check('Ledger has 1 inflow', count($ledger) === 1);
foreach ($ledger as $l) {
    $check('Ledger amount = 1770', abs($l->amount - 1770.00) < 0.01);
    $check('Ledger event = payment_received', $l->event_type === 'payment_received');
}

// User enrolled in course?
$context = \context_course::instance($course->id);
$check('User enrolled in course', is_enrolled($context, $user->id));

// Invoice issued?
$invoice = $DB->get_record('local_sentientia_cart_invoices', ['historyid' => $historyid]);
$check('Invoice issued', !empty($invoice));
if ($invoice) {
    $check('Invoice number format', preg_match('/^AIRPAY-\d{4}-\d{4}$/', $invoice->invoice_number) === 1,
        $invoice->invoice_number);
    // 27 = Maharashtra HQ → intra-state → CGST+SGST split, IGST=0
    $check('Intra-state CGST = 135', abs($invoice->cgst - 135.00) < 0.01, "got {$invoice->cgst}");
    $check('Intra-state SGST = 135', abs($invoice->sgst - 135.00) < 0.01);
    $check('Intra-state IGST = 0',   $invoice->igst == 0);
}

// === 5. Refund (full) ===
echo "\n=== 5. Refund ===\n";
$ok = \local_sentientia_cart\cart_manager::refund($historyid, 0, 'Smoke test refund', $user->id);
$check('refund returns true', $ok === true);
$cart_after = $DB->get_record('local_sentientia_cart_history', ['id' => $historyid]);
$check('Status = refunded', $cart_after->status === 'refunded');
$ledger_count = $DB->count_records('local_sentientia_cart_ledger', ['historyid' => $historyid]);
$check('Ledger has 2 entries (payment + refund)', $ledger_count === 2);
$check('User unenrolled', !is_enrolled($context, $user->id));

// === 6. Audit log integrity ===
echo "\n=== 6. Audit log ===\n";
$ledger_rows = $DB->get_records('local_sentientia_cart_ledger',
    ['historyid' => $historyid], 'timecreated ASC');
$net = 0;
foreach ($ledger_rows as $row) {
    $net += (float) $row->amount;
}
$check('Net ledger = 0 after full refund', abs($net) < 0.01, "got $net");

// === Summary ===
echo "\n" . str_repeat('=', 50) . "\n";
echo sprintf("Smoke result: %d/%d cases pass\n", $pass, $test);
echo str_repeat('=', 50) . "\n";

exit($pass === $test ? 0 : 1);
