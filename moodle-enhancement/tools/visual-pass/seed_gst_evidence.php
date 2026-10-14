<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

/**
 * LOCAL-ONLY: seed the orders, tax invoices, credit notes and receipts the GST evidence pass needs
 * (docs/visual-evidence/2026-10-14/gst/README.md; spec GST-TAX-INVOICE-AND-RECEIPT-SPEC-2026-10-14.md).
 * Refuses unless wwwroot is localhost AND $CFG->noemailever is set, so it can never touch UAT/production or mail
 * anyone.
 *
 * Everything goes through the real code paths, not hand-written rows:
 *   cart_manager::add_item -> checkout (billing state and GSTIN checked) -> mark_paid (invoice issued after the
 *   commit by invoicer::issue_for_order) -> refund (credit note issued by credit_notes::after_refund).
 *
 * Tenant: /77 ("Public"), the one local test tenant. The cart is switched on only for tenants 77 and 177 on this box
 * (enabled_tenants), and vp_public77 is the buyer persona there. Both flags are switched ON for tenant 77 only, by
 * gst_evidence_flags.php (run that first); this script refuses if they are not ON for tenant 77.
 *
 * Orders (all by vp_public77, each recognised again by its billing_name, so a re-run adds nothing):
 *   1  B2C intra-state  Maharashtra (27), two lines, odd paisa in the tax, partial refund -> credit note (CGST+SGST)
 *   2  B2C inter-state  Karnataka   (29), one line, partial refund -> credit note (IGST)
 *   3  B2B              buyer GSTIN 27..., one line with 10% discount, invoice AWAITING the IRN, partial refund
 *                       -> credit note AWAITING the IRN
 * and one open cart (a course, nothing else) for the checkout page.
 *
 * Writes .gst-evidence.local.json (ids only, no credentials) for gst_checks.mjs.
 *
 *   php seed_gst_evidence.php     (run with cwd = moodle5/public, after provision_local_personas.php)
 */

define('CLI_SCRIPT', true);
require(getcwd() . '/config.php');
require_once($CFG->dirroot . '/course/lib.php');

if (!preg_match('~^https?://(localhost|127\.0\.0\.1)(:\d+)?(/|$)~', $CFG->wwwroot)) {
    fwrite(STDERR, "Refusing: wwwroot {$CFG->wwwroot} is not a local development host.\n");
    exit(1);
}
if (empty($CFG->noemailever)) {
    fwrite(STDERR, "Refusing: \$CFG->noemailever is not set - these code paths send order messages.\n");
    exit(1);
}

use local_sentientia_cart\cart_manager;
use local_sentientia_cart\flags;

const GST_TENANT = 77;

global $DB;
$buyer = $DB->get_record('user', ['username' => 'vp_public77', 'deleted' => 0], '*', MUST_EXIST);
$admin = $DB->get_record('user', ['username' => 'vp_siteadmin', 'deleted' => 0], '*', MUST_EXIST);
if (cart_manager::get_tenant_root($buyer) !== GST_TENANT) {
    fwrite(STDERR, "Refusing: vp_public77 is not in tenant " . GST_TENANT . ".\n");
    exit(1);
}
foreach ([flags::RECEIPTS, flags::INVOICES] as $key) {
    if (!flags::enabled_for_tenant($key, GST_TENANT)) {
        fwrite(STDERR, "Refusing: flag {$key} is not ON for tenant " . GST_TENANT . " (run gst_evidence_flags.php on).\n");
        exit(1);
    }
}

// 1. LOCAL test settings. The previous values are printed so they can be put back.
$settings = [
    'company_name'    => 'Airpay Payment Services (TEST)',
    'company_address' => "TEST ADDRESS - local evidence only\n1 Test Street, Andheri East\nMumbai 400069, Maharashtra, India",
    'our_gstn'        => '27AAAAA0000A1Z5',
    'sac_code'        => '999293',
];
foreach ($settings as $name => $value) {
    $old = get_config('local_sentientia_cart', $name);
    if ($old !== $value) {
        echo "setting {$name}: was " . json_encode($old) . "\n";
        set_config($name, $value, 'local_sentientia_cart');
    }
}
$problems = \local_sentientia_cart\gst_settings::problems();
if ($problems) {
    fwrite(STDERR, 'Settings still incomplete: ' . implode(', ', $problems) . "\n");
    exit(1);
}

// 2. Priced courses in tenant /77 (an enrol_fee instance, which is what the cart reads).
$studentrole = (int) $DB->get_field('role', 'id', ['shortname' => 'student']);
$category = (int) ($DB->get_field('course_categories', 'id', ['idnumber' => 'external', 'parent' => 0])
    ?: $DB->get_field_sql('SELECT MIN(id) FROM {course_categories} WHERE parent = 0 AND id > 1'));
$priced = function (string $shortname, string $fullname, string $price) use ($DB, $studentrole, $category): stdClass {
    $course = $DB->get_record('course', ['shortname' => $shortname]);
    if (!$course) {
        $course = create_course((object) ['fullname' => $fullname, 'shortname' => $shortname,
            'category' => $category, 'visible' => 1, 'format' => 'topics', 'numsections' => 1]);
    }
    $DB->set_field('course', 'open_path', '/' . GST_TENANT, ['id' => $course->id]);
    $fee = $DB->get_record('enrol', ['enrol' => 'fee', 'courseid' => $course->id]);
    if (!$fee) {
        $DB->insert_record('enrol', (object) [
            'enrol' => 'fee', 'status' => ENROL_INSTANCE_ENABLED, 'courseid' => $course->id,
            'sortorder' => 9, 'cost' => $price, 'currency' => 'INR', 'roleid' => $studentrole,
            'timecreated' => time(), 'timemodified' => time(),
        ]);
    }
    return $DB->get_record('course', ['id' => $course->id], '*', MUST_EXIST);
};
$ca = $priced('vp_gst_a', 'VP GST Course A - Compliance Essentials', '2499.50');
$cb = $priced('vp_gst_b', 'VP GST Course B - Data Privacy Basics', '1001.00');
$cc = $priced('vp_gst_c', 'VP GST Course C - Payments Risk Fundamentals', '4999.00');
$cd = $priced('vp_gst_d', 'VP GST Course D - Leadership Programme', '7500.00');
$ce = $priced('vp_gst_e', 'VP GST Course E - Customer Onboarding', '1499.00');

// 3. One order, through the real path. Recognised again by billing_name.
$emptycart = function () use ($buyer) {
    $cart = cart_manager::get_or_open_cart((int) $buyer->id);
    foreach (json_decode($cart->items_json ?: '[]', true) ?: [] as $item) {
        cart_manager::remove_item((int) $buyer->id, (int) $item['courseid']);
    }
};
$place = function (string $name, array $courses, array $billing, int $discountpct = 0, string $txn = '')
        use ($DB, $buyer, $emptycart): stdClass {
    $existing = $DB->get_record('local_sentientia_cart_history',
        ['userid' => $buyer->id, 'billing_name' => $name], '*', IGNORE_MULTIPLE);
    if ($existing) {
        echo "order for {$name} exists (#{$existing->orderid}, status {$existing->status})\n";
        return $existing;
    }
    $emptycart();
    foreach ($courses as $c) {
        cart_manager::add_item((int) $buyer->id, (int) $c->id);
    }
    $cart = cart_manager::get_or_open_cart((int) $buyer->id);
    if ($discountpct > 0) {
        // The basket supports a per-line discount_pct; nothing in the UI sets it yet, so the seed does.
        $items = json_decode($cart->items_json, true);
        foreach ($items as &$item) {
            $item['discount_pct'] = $discountpct;
        }
        unset($item);
        $cart->items_json = json_encode($items);
        $cart = cart_manager::recompute_totals($cart);
    }
    $order = cart_manager::checkout((int) $buyer->id, $billing + ['billing_name' => $name], 'airpay');
    cart_manager::mark_paid((int) $order->id, $txn, []);
    $order = $DB->get_record('local_sentientia_cart_history', ['id' => $order->id], '*', MUST_EXIST);
    echo "placed #{$order->orderid} for {$name}: total {$order->total_amount}, status {$order->status}\n";
    return $order;
};

$o1 = $place('Asha Verma (TEST)', [$ca, $cb], [
    'billing_email' => 'asha.verma@visualpass.localhost', 'billing_phone' => '9800000001',
    'billing_address' => "12 Test Lane, Andheri East\nMumbai 400069, Maharashtra", 'billing_state' => '27',
    'billing_gstn' => ''], 0, 'VP-GST-TXN-1');
$o2 = $place('Ravi Menon (TEST)', [$cc], [
    'billing_email' => 'ravi.menon@visualpass.localhost', 'billing_phone' => '9800000002',
    'billing_address' => "44 Test Road, Indiranagar\nBengaluru 560038, Karnataka", 'billing_state' => '29',
    'billing_gstn' => ''], 0, 'VP-GST-TXN-2');
$o3 = $place('Meridian Test Traders Pvt Ltd (TEST)', [$cd], [
    'billing_email' => 'accounts@meridian-test.visualpass.localhost', 'billing_phone' => '9800000003',
    'billing_address' => "Unit 5, Test Business Park, Hinjewadi\nPune 411057, Maharashtra", 'billing_state' => '27',
    'billing_gstn' => '27BBBBB1111B1Z8'], 10, 'VP-GST-TXN-3');

// 4. A partial refund of each: the credit notes. Gross amounts (tax included).
$refund = function (stdClass $order, float $amount, string $reason) use ($DB, $admin): void {
    $done = $DB->record_exists_select('local_sentientia_cart_ledger',
        "historyid = :h AND event_type IN ('refund_full','refund_partial')", ['h' => $order->id]);
    if ($done) {
        echo "order #{$order->orderid} already refunded once\n";
        return;
    }
    cart_manager::refund((int) $order->id, $amount, $reason, (int) $admin->id);
    echo "refunded {$amount} on order #{$order->orderid}\n";
};
$refund($o1, 1181.18, 'Course B withdrawn at the buyer\'s request (local test)');
$refund($o2, 1000.00, 'Partial refund: duplicate enrolment (local test)');
$refund($o3, 2360.00, 'Partial refund: seats reduced (local test)');

// 5. An open cart for the checkout page: Course E, nothing else.
$emptycart();
cart_manager::add_item((int) $buyer->id, (int) $ce->id);

// 6. The context file for gst_checks.mjs (ids only).
$ctx = ['tenant' => GST_TENANT, 'buyer' => 'vp_public77', 'orders' => []];
foreach (['b2c_intra' => $o1, 'b2c_inter' => $o2, 'b2b' => $o3] as $key => $o) {
    $o = $DB->get_record('local_sentientia_cart_history', ['id' => $o->id], '*', MUST_EXIST);
    $inv = $DB->get_record('local_sentientia_cart_invoices', ['historyid' => $o->id]);
    $cns = $DB->get_records('local_sentientia_cart_creditnotes', ['historyid' => $o->id], 'id ASC');
    $ctx['orders'][$key] = [
        'historyid' => (int) $o->id, 'orderid' => (int) $o->orderid, 'status' => $o->status,
        'total' => (float) $o->total_amount,
        'invoice' => $inv ? ['id' => (int) $inv->id, 'number' => $inv->invoice_number, 'status' => $inv->status,
            'place_of_supply' => $inv->place_of_supply] : null,
        'creditnotes' => array_values(array_map(fn($n) => ['id' => (int) $n->id, 'number' => $n->cn_number,
            'status' => $n->status, 'total' => (float) $n->total], $cns)),
    ];
}
file_put_contents(__DIR__ . '/.gst-evidence.local.json', json_encode($ctx, JSON_PRETTY_PRINT));
echo json_encode($ctx['orders'], JSON_PRETTY_PRINT) . "\n";
