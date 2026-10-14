<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

namespace local_sentientia_cart;

defined('MOODLE_INTERNAL') || die();

/**
 * ADR-031 decision 3: a learner may buy only a course the catalogue shows them.
 *
 * Until 2026-09-26 add_item(), checkout() and mark_paid() never looked at the
 * purchased course's tenant. A /1 learner could post a /177 course id to
 * local_sentientia_cart_add_item, pay, and be enrolled in a course their
 * catalogue does not list. Each entry point now asks
 * cart_manager::can_buy_course(), which is the catalogue's own rule
 * (catalog_manager::assert_course_visible_to_viewer()): visible, and owned by
 * the buyer's tenant tree or actively shared to it; a guest is the Public
 * tenant; a buyer with no tenant buys nothing; a cross-tenant buyer (site
 * admin, :crosstenant) is unchanged.
 *
 * 2026-09-29: a line mark_paid() withholds is told to the buyer (not listed as
 * theirs; "cannot be accessed, will be refunded") and to the site admins (a
 * refund-due line naming the order and the course id), and history.notes reach
 * order admins through get_order / list_orders.
 *
 * 2026-10-07 (cart.withheld_line_refund): the admin message and history.notes also
 * state what each withheld line was charged (price, discount, share of the order's
 * GST, paise rounding), labelled "for review". The administrator still decides the
 * refund (a partial refund()); nothing is refunded automatically.
 *
 * @package    local_sentientia_cart
 * @category   test
 * @copyright  2026 Airpay Payment Services
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \local_sentientia_cart\cart_manager::can_buy_course
 * @covers     \local_sentientia_cart\cart_manager::tenant_rule_allows
 * @covers     \local_sentientia_cart\cart_manager::add_item
 * @covers     \local_sentientia_cart\cart_manager::checkout
 * @covers     \local_sentientia_cart\cart_manager::mark_paid
 * @covers     \local_sentientia_cart\cart_manager::withheld_line_amounts
 * @covers     \local_sentientia_cart\notifier::order_paid
 * @covers     \local_sentientia_cart\external\get_order
 * @group      tenant_isolation
 */
final class purchase_gate_test extends \advanced_testcase {

    use \local_sentientia_org\test\bizlms_fixture;

    /** @var array billing details checkout() requires */
    private const BILLING = ['billing_name' => 'Test Buyer', 'billing_email' => 'buyer@example.com'];

    protected function setUp(): void {
        parent::setUp();
        $this->resetAfterTest();
        $this->ensure_bizlms_schema();
    }

    /** A user with the given open_path, re-read so the record carries it. */
    private function user_at(string $path): \stdClass {
        global $DB;
        $u = $this->getDataGenerator()->create_user();
        $DB->set_field('user', 'open_path', $path, ['id' => $u->id]);
        return $DB->get_record('user', ['id' => $u->id], '*', MUST_EXIST);
    }

    /**
     * A course for sale: an enabled enrol_fee instance with a cost, which is
     * what local_sentientia_cart_get_course_price() reads.
     *
     * @param string|null $path open_path, or null to leave it NULL
     * @param float $price
     * @param int $visible
     */
    private function priced_course_at(?string $path, float $price = 1000.00, int $visible = 1): \stdClass {
        global $DB;
        $c = $this->getDataGenerator()->create_course(['visible' => $visible]);
        if ($path !== null) {
            $DB->set_field('course', 'open_path', $path, ['id' => $c->id]);
        }
        $DB->insert_record('enrol', (object) [
            'enrol' => 'fee', 'status' => ENROL_INSTANCE_ENABLED, 'courseid' => $c->id,
            'sortorder' => 9, 'cost' => number_format($price, 2, '.', ''), 'currency' => 'INR',
            'roleid' => (int) $DB->get_field('role', 'id', ['shortname' => 'student']),
            'timecreated' => time(), 'timemodified' => time(),
        ]);
        return $DB->get_record('course', ['id' => $c->id], '*', MUST_EXIST);
    }

    /** Share $courseid to tenant $tenantid (a local_sentientia_courses_tenant_share row). */
    private function share(int $courseid, int $tenantid, string $status = 'active'): void {
        global $DB;
        $DB->insert_record('local_sentientia_courses_tenant_share', (object) [
            'courseid' => $courseid, 'tenant_id' => $tenantid, 'shared_by' => (int) get_admin()->id,
            'status' => $status, 'timeshared' => time(), 'timemodified' => time(),
        ]);
    }

    /** Course ids in the buyer's open cart. */
    private function cart_courseids(int $userid): array {
        $cart = cart_manager::get_or_open_cart($userid);
        return array_map(fn($i) => (int) $i['courseid'], json_decode($cart->items_json ?: '[]', true) ?: []);
    }

    /**
     * A pending order (as checkout() leaves it) for $buyer.
     *
     * @param \stdClass $buyer
     * @param string[] $lines course id => line name (distinct names, so a test can
     *                        tell which lines a message lists)
     * @return int history id
     */
    private function pending_order(\stdClass $buyer, array $lines): int {
        global $DB;
        $items = [];
        foreach ($lines as $id => $name) {
            $items[] = ['courseid' => (int) $id, 'name' => (string) $name, 'price' => 1000.00];
        }
        $now = time();
        return (int) $DB->insert_record('local_sentientia_cart_history', (object) [
            'orderid' => 900000 + (int) $buyer->id, 'userid' => $buyer->id,
            'costcenterid' => cart_manager::get_tenant_root($buyer),
            'items_json' => json_encode($items), 'subtotal' => 1000.00 * count($items),
            'discount_amount' => 0, 'tax_amount' => 0, 'total_amount' => 1000.00 * count($items),
            'currency' => 'INR', 'status' => 'pending', 'gateway' => 'airpay', 'gateway_ref' => '',
            'billing_name' => 'Test Buyer', 'billing_email' => 'buyer@example.com',
            'billing_phone' => '', 'billing_address' => '', 'billing_gstn' => '', 'notes' => '',
            'timecreated' => $now, 'timepaid' => 0, 'timemodified' => $now,
        ]);
    }

    private function assert_refused(callable $fn, string $errorcode, string $why): void {
        try {
            $fn();
            $this->fail($why);
        } catch (\moodle_exception $e) {
            $this->assertSame($errorcode, $e->errorcode, $why);
        }
    }

    // ── add_item ─────────────────────────────────────────────────────────

    public function test_a_learner_cannot_add_another_tenants_course(): void {
        $learner = $this->user_at('/1/4');
        $theirs = $this->priced_course_at('/177');
        $this->setUser($learner);

        $this->assert_refused(fn() => cart_manager::add_item((int) $learner->id, (int) $theirs->id),
            'error_courseunavailable',
            'A /1 learner must not put a /177 course in their cart by id. Same error as "not for sale", so the '
            . 'probe cannot tell a course in another tenant from a missing one.');
        $this->assertSame([], $this->cart_courseids((int) $learner->id));
    }

    public function test_a_learner_can_add_their_own_course_and_one_shared_to_their_tenant(): void {
        $learner = $this->user_at('/1/4');
        $mine = $this->priced_course_at('/1/9');
        $shared = $this->priced_course_at('/177');
        $this->share((int) $shared->id, 1);
        $this->setUser($learner);

        cart_manager::add_item((int) $learner->id, (int) $mine->id);
        $cart = cart_manager::add_item((int) $learner->id, (int) $shared->id);

        $this->assertEqualsCanonicalizing([(int) $mine->id, (int) $shared->id],
            $this->cart_courseids((int) $learner->id),
            'The in-tenant flow is unchanged, and a course /177 shares to /1 is on sale to /1.');
        $this->assertEqualsWithDelta(2000.00, (float) $cart->subtotal, 0.001);
    }

    public function test_a_withdrawn_share_does_not_sell_the_course(): void {
        $learner = $this->user_at('/1/4');
        $theirs = $this->priced_course_at('/177');
        $this->share((int) $theirs->id, 1, 'withdrawn');
        $this->setUser($learner);

        $this->assert_refused(fn() => cart_manager::add_item((int) $learner->id, (int) $theirs->id),
            'error_courseunavailable', 'Only an ACTIVE share puts a course in another tenant\'s catalogue.');
    }

    public function test_a_learner_with_no_tenant_buys_nothing(): void {
        $mine = $this->priced_course_at('/1/9');
        $public = $this->priced_course_at('/77');
        foreach (['', 'garbage'] as $path) {
            $nobody = $this->user_at($path);
            $this->setUser($nobody);
            foreach ([$mine, $public] as $course) {
                $this->assertFalse(cart_manager::can_buy_course((int) $course->id, (int) $nobody->id),
                    "open_path '{$path}' is no tenant, and no tenant sees no catalogue.");
            }
            $this->assert_refused(fn() => cart_manager::add_item((int) $nobody->id, (int) $public->id),
                'error_courseunavailable', "open_path '{$path}' must not buy the Public storefront either.");
        }
    }

    public function test_a_course_with_no_tenant_is_not_sold_to_a_scoped_learner(): void {
        $learner = $this->user_at('/1/4');
        $orphan = $this->priced_course_at(null);

        $this->assertFalse(cart_manager::can_buy_course((int) $orphan->id, (int) $learner->id),
            'The catalogue does not list a course with no open_path to a scoped learner, so the cart does not sell it.');
        $this->assertTrue(cart_manager::can_buy_course((int) $orphan->id, (int) get_admin()->id));
    }

    // ── Public tenant and guest ──────────────────────────────────────────

    public function test_a_public_learner_and_a_guest_get_the_public_catalogue(): void {
        $public = $this->priced_course_at('/77');
        $airpay = $this->priced_course_at('/1/9');
        $sharedtopublic = $this->priced_course_at('/1/9');
        $this->share((int) $sharedtopublic->id, 77);

        $learner = $this->user_at('/77/3');
        $guestid = (int) guest_user()->id;
        foreach (['a /77 learner' => (int) $learner->id, 'the guest user' => $guestid] as $who => $buyerid) {
            $this->assertTrue(cart_manager::can_buy_course((int) $public->id, $buyerid),
                "{$who} may buy a Public course.");
            $this->assertTrue(cart_manager::can_buy_course((int) $sharedtopublic->id, $buyerid),
                "{$who} may buy an Airpay course shared to Public.");
            $this->assertFalse(cart_manager::can_buy_course((int) $airpay->id, $buyerid),
                "{$who} must not buy an Airpay course that is not shared to Public.");
        }

        $this->setUser($learner);
        cart_manager::add_item((int) $learner->id, (int) $public->id);
        $this->assert_refused(fn() => cart_manager::add_item((int) $learner->id, (int) $airpay->id),
            'error_courseunavailable', 'A self-registered Public learner must not buy an internal Airpay course.');
        $this->assertSame([(int) $public->id], $this->cart_courseids((int) $learner->id));
    }

    // ── checkout re-checks every line ────────────────────────────────────

    public function test_checkout_drops_a_line_the_buyer_may_no_longer_buy_and_asks_again(): void {
        $learner = $this->user_at('/1/4');
        $mine = $this->priced_course_at('/1/9');
        $theirs = $this->priced_course_at('/177');
        $this->setUser($learner);

        // A line that got in before the gate shipped (or whose share was
        // withdrawn since): written straight into the open cart.
        $cart = cart_manager::add_item((int) $learner->id, (int) $mine->id);
        $items = json_decode($cart->items_json, true);
        $items[] = ['courseid' => (int) $theirs->id, 'name' => 'Theirs', 'shortname' => 'T',
            'price' => 5000.00, 'discount_pct' => 0];
        $cart->items_json = json_encode($items);
        cart_manager::recompute_totals($cart);

        $this->assert_refused(fn() => cart_manager::checkout((int) $learner->id, self::BILLING, 'manual'),
            'error_itemsunavailable',
            'Checkout must not charge for a /177 line, nor silently charge a basket the buyer did not see.');

        $cart = cart_manager::get_or_open_cart((int) $learner->id);
        $this->assertSame('open', $cart->status, 'The refused checkout leaves the cart open.');
        $this->assertSame([(int) $mine->id], $this->cart_courseids((int) $learner->id));
        $this->assertEqualsWithDelta(1000.00, (float) $cart->subtotal, 0.001, 'Totals are recomputed.');

        // The buyer has now seen the new total; the second attempt goes through.
        $order = cart_manager::checkout((int) $learner->id, self::BILLING, 'manual');
        $this->assertSame('pending', $order->status);
    }

    public function test_a_cart_pruned_to_nothing_says_why_not_just_empty(): void {
        global $DB;
        $learner = $this->user_at('/1/4');
        $theirs = $this->priced_course_at('/177');
        $cart = cart_manager::get_or_open_cart((int) $learner->id);
        $DB->set_field('local_sentientia_cart_history', 'items_json',
            json_encode([['courseid' => (int) $theirs->id, 'name' => 'Theirs', 'price' => 5000.00]]),
            ['id' => $cart->id]);

        $this->assert_refused(fn() => cart_manager::checkout((int) $learner->id, self::BILLING, 'manual'),
            'error_itemsunavailable', 'The buyer is told a course was removed, not that their cart is empty.');
        $this->assertSame([], $this->cart_courseids((int) $learner->id));
    }

    // ── payment: no enrolment in what the buyer may not buy ──────────────

    public function test_payment_does_not_enrol_the_buyer_in_a_course_they_may_not_buy(): void {
        global $DB;
        $buyer = $this->user_at('/1/4');
        $mine = $this->priced_course_at('/1/9');
        $theirs = $this->priced_course_at('/177');
        $historyid = $this->pending_order($buyer, [
            (int) $mine->id => 'Payments Basics In Tenant',
            (int) $theirs->id => 'Zeea Onboarding Elsewhere',
        ]);

        $sink = $this->redirectMessages();
        $this->assertTrue(cart_manager::mark_paid($historyid, 'TXN-GATE', []));
        $messages = $sink->get_messages();
        $sink->close();

        $this->assertTrue(is_enrolled(\context_course::instance($mine->id), $buyer->id),
            'The in-tenant line is fulfilled as before.');
        $this->assertFalse(is_enrolled(\context_course::instance($theirs->id), $buyer->id),
            'A payment callback must not enrol a /1 buyer in a /177 course.');

        // The money was taken: the payment is still recorded, and the withheld
        // line is noted for a refund.
        $order = $DB->get_record('local_sentientia_cart_history', ['id' => $historyid], '*', MUST_EXIST);
        $this->assertSame('paid', $order->status);
        $this->assertSame(1, $DB->count_records('local_sentientia_cart_ledger',
            ['historyid' => $historyid, 'event_type' => 'payment_received']));
        $this->assertStringContainsString("course id(s) {$theirs->id} -", (string) $order->notes);

        // The buyer is told the truth: only the granted course is listed as
        // theirs, and the withheld one is said to be inaccessible and refunded.
        $tobuyer = array_values(array_filter($messages, fn($m) =>
            (int) $m->useridto === (int) $buyer->id && $m->eventtype === 'payment_received'));
        $this->assertCount(1, $tobuyer, 'The buyer gets one payment_received message.');
        $body = (string) $tobuyer[0]->fullmessage;
        $this->assertStringContainsString('Payments Basics In Tenant', $body,
            'The course the buyer was enrolled in is listed.');
        $this->assertStringNotContainsString('Zeea Onboarding Elsewhere', $body,
            'The withheld course must not be listed as one the buyer can now access.');
        $this->assertStringContainsString(get_string('paid_withheld', 'local_sentientia_cart', 1), $body,
            'The buyer is told a course in the order cannot be accessed and will be refunded.');

        // The site admins (get_admins(), the admin_new_order recipients) get an
        // explicit refund-due line naming the order and the withheld course id.
        $toadmins = array_values(array_filter($messages, fn($m) => $m->eventtype === 'admin_new_order'));
        $this->assertCount(count(get_admins()), $toadmins, 'Every site admin is told about the order.');
        $refundline = get_string('admin_withheld', 'local_sentientia_cart', (object) [
            'orderid' => (int) $order->orderid, 'courseids' => (string) $theirs->id]);
        foreach ($toadmins as $m) {
            $this->assertStringContainsString($refundline, (string) $m->fullmessage,
                'The admin message names the order and ONLY the withheld course id, with "refund due".');
            $this->assertStringContainsString(get_string('refunddue', 'local_sentientia_cart'), (string) $m->subject);
        }
    }

    public function test_the_refund_due_note_reaches_order_admins_not_the_buyer(): void {
        $buyer = $this->user_at('/1/4');
        $theirs = $this->priced_course_at('/177');
        $historyid = $this->pending_order($buyer, [(int) $theirs->id => 'Zeea Onboarding Elsewhere']);
        $sink = $this->redirectMessages();
        cart_manager::mark_paid($historyid, 'TXN-NOTE', []);
        $sink->close();

        $this->setUser($buyer);
        $asbuyer = \core_external\external_api::clean_returnvalue(external\get_order::execute_returns(),
            external\get_order::execute($historyid));
        $this->assertSame('', $asbuyer['notes'], 'history.notes are staff notes; the buyer hears via the message.');

        $this->setAdminUser();
        $asadmin = \core_external\external_api::clean_returnvalue(external\get_order::execute_returns(),
            external\get_order::execute($historyid));
        $this->assertStringContainsString("course id(s) {$theirs->id} -", $asadmin['notes'],
            'An order admin sees the refund-due note (get_order, and the admin_orders.php list via list_orders).');
        $this->assertStringContainsString('Refund due.', $asadmin['notes']);
        $this->assertStringContainsString('order #' . (900000 + (int) $buyer->id) . ':', $asadmin['notes'],
            'The note names the order, so an admin can tie it to a row.');
    }

    public function test_a_gateway_failure_note_with_markup_does_not_break_the_order_lists(): void {
        global $DB;
        $buyer = $this->user_at('/1/4');
        $mine = $this->priced_course_at('/1');
        $historyid = $this->pending_order($buyer, [(int) $mine->id => 'Airpay Onboarding']);
        $note = 'Gateway reported failure: {"MESSAGE":"Declined <br>by bank"}' . "\n"
            . 'ADR-031: order #1: payment recorded, enrolment withheld for course id(s) 5 - x. Refund due.';
        $DB->set_field('local_sentientia_cart_history', 'notes', $note, ['id' => $historyid]);
        $this->setAdminUser();

        $one = \core_external\external_api::clean_returnvalue(external\get_order::execute_returns(),
            external\get_order::execute($historyid));
        $this->assertSame($note, $one['notes'], 'get_order returns the note unchanged (PARAM_RAW).');

        $list = \core_external\external_api::clean_returnvalue(external\list_orders::execute_returns(),
            external\list_orders::execute());
        $row = array_values(array_filter($list['rows'], fn($r) => (int) $r['id'] === $historyid));
        $this->assertCount(1, $row, 'list_orders still returns the order (it used to throw invalid_response).');
        $this->assertSame($note, $row[0]['notes']);
    }

    public function test_the_in_tenant_purchase_flow_is_unchanged_end_to_end(): void {
        $buyer = $this->user_at('/1/4');
        $mine = $this->priced_course_at('/1/9');
        $shared = $this->priced_course_at('/177');
        $this->share((int) $shared->id, 1);
        $this->setUser($buyer);

        cart_manager::add_item((int) $buyer->id, (int) $mine->id);
        cart_manager::add_item((int) $buyer->id, (int) $shared->id);
        $order = cart_manager::checkout((int) $buyer->id, self::BILLING, 'manual');
        $sink = $this->redirectMessages();
        $this->assertTrue(cart_manager::mark_paid((int) $order->id, 'TXN-OK', []));
        $messages = $sink->get_messages();
        $sink->close();

        $this->assertTrue(is_enrolled(\context_course::instance($mine->id), $buyer->id));
        $this->assertTrue(is_enrolled(\context_course::instance($shared->id), $buyer->id));

        // Nothing withheld: the messages read as they always did.
        $tobuyer = array_values(array_filter($messages, fn($m) =>
            (int) $m->useridto === (int) $buyer->id && $m->eventtype === 'payment_received'));
        $this->assertCount(1, $tobuyer);
        $this->assertStringContainsString($mine->fullname, (string) $tobuyer[0]->fullmessage);
        $this->assertStringContainsString($shared->fullname, (string) $tobuyer[0]->fullmessage);
        $this->assertStringNotContainsString(get_string('paid_withheld', 'local_sentientia_cart', 1),
            (string) $tobuyer[0]->fullmessage);
        foreach (array_filter($messages, fn($m) => $m->eventtype === 'admin_new_order') as $m) {
            $this->assertStringNotContainsString(get_string('refunddue', 'local_sentientia_cart'),
                (string) $m->subject . (string) $m->fullmessage, 'No refund is due on a fully granted order.');
        }
    }

    // ── cart.withheld_line_refund: the amounts to refund, for review ──────

    /**
     * A pending order with real totals (18 % GST), as checkout() leaves one, so the withheld-line
     * figures are held against what recompute_totals() actually stored.
     *
     * @param \stdClass $buyer
     * @param array $lines course id => [name, price, discount_pct]
     * @return int history id
     */
    private function pending_gst_order(\stdClass $buyer, array $lines): int {
        global $DB;
        set_config('gst_rate', 18, 'local_sentientia_cart');
        $historyid = $this->pending_order($buyer, []);
        $items = [];
        foreach ($lines as $courseid => [$name, $price, $pct]) {
            $items[] = ['courseid' => (int) $courseid, 'name' => $name, 'price' => (float) $price,
                'discount_pct' => (int) $pct];
        }
        $cart = $DB->get_record('local_sentientia_cart_history', ['id' => $historyid], '*', MUST_EXIST);
        $cart->items_json = json_encode($items);
        cart_manager::recompute_totals($cart);
        return $historyid;
    }

    public function test_withheld_line_amounts_are_the_lines_price_discount_and_share_of_the_orders_gst(): void {
        global $DB;
        $buyer = $this->user_at('/1/4');
        $mine = $this->priced_course_at('/1/9');
        $theirs = $this->priced_course_at('/177');
        // 1000.00 + 500.00 less 10 % = 1450.00 taxable; GST 18 % = 261.00; total 1711.00.
        $historyid = $this->pending_gst_order($buyer, [
            (int) $mine->id => ['Payments Basics In Tenant', 1000, 0],
            (int) $theirs->id => ['Zeea Onboarding Elsewhere', 500, 10],
        ]);
        $order = $DB->get_record('local_sentientia_cart_history', ['id' => $historyid], '*', MUST_EXIST);
        $this->assertEqualsWithDelta(1711.00, (float) $order->total_amount, 0.001, 'Precondition: the order totals.');

        $rows = cart_manager::withheld_line_amounts($order, [(int) $theirs->id]);

        $this->assertCount(1, $rows, 'Only the withheld line is stated.');
        $this->assertSame((int) $theirs->id, $rows[0]['courseid']);
        $this->assertSame('Zeea Onboarding Elsewhere', $rows[0]['name']);
        $this->assertEqualsWithDelta(500.00, $rows[0]['price'], 0.0001);
        $this->assertEqualsWithDelta(50.00, $rows[0]['discount'], 0.0001);
        $this->assertEqualsWithDelta(450.00, $rows[0]['net'], 0.0001);
        $this->assertEqualsWithDelta(81.00, $rows[0]['tax'], 0.0001, '450.00 of 1450.00 taxable carries 81.00 of the 261.00 GST.');
        $this->assertEqualsWithDelta(531.00, $rows[0]['total'], 0.0001);
        $this->assertSame([], cart_manager::withheld_line_amounts($order, []), 'Nothing withheld, nothing stated.');
        $this->assertSame([], cart_manager::withheld_line_amounts($order, [987654]), 'A course not in the order is not stated.');
    }

    public function test_the_refund_note_states_the_amounts_for_review(): void {
        global $DB;
        $buyer = $this->user_at('/1/4');
        $mine = $this->priced_course_at('/1/9');
        $theirs = $this->priced_course_at('/177');
        $historyid = $this->pending_gst_order($buyer, [
            (int) $mine->id => ['Payments Basics In Tenant', 1000, 0],
            (int) $theirs->id => ['Zeea Onboarding Elsewhere', 500, 10],
        ]);
        $sink = $this->redirectMessages();
        $this->assertTrue(cart_manager::mark_paid($historyid, 'TXN-AMT', []));
        $sink->close();

        $notes = (string) $DB->get_field('local_sentientia_cart_history', 'notes', ['id' => $historyid]);
        $this->assertStringContainsString("course id(s) {$theirs->id} -", $notes, 'The existing refund-due line is unchanged.');
        $this->assertStringContainsString('Refund due.', $notes);
        $this->assertStringContainsString('For review, not an invoice', $notes);
        $this->assertStringContainsString("course id {$theirs->id}: price 500.00 - discount 50.00 + GST share 81.00 = 531.00 INR",
            $notes);
        $this->assertStringContainsString('Withheld lines total 531.00 INR of the order total 1711.00 INR.', $notes);
        $this->assertStringNotContainsString("course id {$mine->id}:", $notes, 'The granted line is not a refund.');
    }

    public function test_the_admin_message_states_the_amounts_and_the_buyer_message_does_not(): void {
        $buyer = $this->user_at('/1/4');
        $mine = $this->priced_course_at('/1/9');
        $theirs = $this->priced_course_at('/177');
        $historyid = $this->pending_gst_order($buyer, [
            (int) $mine->id => ['Payments Basics In Tenant', 1000, 0],
            (int) $theirs->id => ['Zeea Onboarding Elsewhere', 500, 10],
        ]);
        $sink = $this->redirectMessages();
        cart_manager::mark_paid($historyid, 'TXN-AMT2', []);
        $messages = $sink->get_messages();
        $sink->close();

        $line = get_string('admin_withheld_line', 'local_sentientia_cart', (object) [
            'courseid' => (int) $theirs->id, 'name' => 'Zeea Onboarding Elsewhere', 'price' => '500.00',
            'discount' => '50.00', 'tax' => '81.00', 'total' => '531.00', 'currency' => 'INR']);
        $total = get_string('admin_withheld_total', 'local_sentientia_cart', (object) [
            'total' => '531.00', 'ordertotal' => '1,711.00', 'currency' => 'INR']);
        $toadmins = array_values(array_filter($messages, fn($m) => $m->eventtype === 'admin_new_order'));
        $this->assertNotEmpty($toadmins);
        foreach ($toadmins as $m) {
            $body = (string) $m->fullmessage;
            $this->assertStringContainsString(get_string('admin_withheld_amounts', 'local_sentientia_cart'), $body);
            $this->assertStringContainsString($line, $body, 'Each withheld line is stated with its amounts.');
            $this->assertStringContainsString($total, $body);
            $this->assertStringNotContainsString('Payments Basics In Tenant', $body, 'The granted line is not a refund.');
        }

        $tobuyer = array_values(array_filter($messages, fn($m) =>
            (int) $m->useridto === (int) $buyer->id && $m->eventtype === 'payment_received'));
        $this->assertCount(1, $tobuyer);
        $this->assertStringNotContainsString('531.00', (string) $tobuyer[0]->fullmessage,
            'The figures to refund are for the administrator; the buyer is told it will be refunded.');
        $this->assertStringNotContainsString('GST', (string) $tobuyer[0]->fullmessage);
    }

    public function test_an_all_withheld_order_states_amounts_that_add_up_to_the_total_within_a_paisa(): void {
        $buyer = $this->user_at('/1/4');
        $a = $this->priced_course_at('/177');
        $b = $this->priced_course_at('/177');
        $c = $this->priced_course_at('/177');
        // Exact: 1000 + 500, GST 270.00, total 1770.00; shares 180.00 + 90.00.
        $exact = $this->pending_gst_order($buyer, [(int) $a->id => ['A', 1000, 0], (int) $b->id => ['B', 500, 0]]);
        $order = $this->order_row($exact);
        $rows = cart_manager::withheld_line_amounts($order, [(int) $a->id, (int) $b->id]);
        $this->assertEqualsWithDelta((float) $order->total_amount, array_sum(array_column($rows, 'total')), 0.0001);

        // 99.99 + 149.99 + 249.99 = 499.97; GST on the whole order is 89.99 (499.97 x 18 %, to the paisa), total 589.96.
        // The lines' shares round to 18.00 + 27.00 + 45.00 = 90.00, so the lines add up to 589.97: a paisa over the
        // order. That is why every figure is labelled "for review" and the administrator decides the refund.
        $buyer2 = $this->user_at('/1/5');
        $paisa = $this->pending_gst_order($buyer2, [
            (int) $a->id => ['A', 99.99, 0], (int) $b->id => ['B', 149.99, 0], (int) $c->id => ['C', 249.99, 0]]);
        $order2 = $this->order_row($paisa);
        $rows2 = cart_manager::withheld_line_amounts($order2, [(int) $a->id, (int) $b->id, (int) $c->id]);
        $this->assertEqualsWithDelta(589.96, (float) $order2->total_amount, 0.0001);
        $this->assertEqualsWithDelta(589.97, array_sum(array_column($rows2, 'total')), 0.0001);
        $this->assertEqualsWithDelta((float) $order2->total_amount, array_sum(array_column($rows2, 'total')), 0.011,
            'Never more than a paisa from the order total.');
    }

    public function test_an_order_with_no_tax_states_no_gst_share(): void {
        $buyer = $this->user_at('/1/4');
        $theirs = $this->priced_course_at('/177');
        $historyid = $this->pending_order($buyer, [(int) $theirs->id => 'Zeea Onboarding Elsewhere']);   // tax_amount 0
        $rows = cart_manager::withheld_line_amounts($this->order_row($historyid), [(int) $theirs->id]);
        $this->assertCount(1, $rows);
        $this->assertEqualsWithDelta(0.0, $rows[0]['tax'], 0.0001);
        $this->assertEqualsWithDelta(1000.00, $rows[0]['total'], 0.0001);
    }

    private function order_row(int $historyid): \stdClass {
        global $DB;
        return $DB->get_record('local_sentientia_cart_history', ['id' => $historyid], '*', MUST_EXIST);
    }

    // ── cross-tenant callers are unchanged ───────────────────────────────

    public function test_the_site_admin_buys_as_before(): void {
        $theirs = $this->priced_course_at('/177');
        $hidden = $this->priced_course_at('/177', 1000.00, 0);
        $this->setAdminUser();
        $adminid = (int) get_admin()->id;

        $this->assertTrue(cart_manager::can_buy_course((int) $hidden->id, $adminid),
            'The cart never checked visibility for anyone; a cross-tenant buyer keeps that.');
        cart_manager::add_item($adminid, (int) $theirs->id);
        $order = cart_manager::checkout($adminid, self::BILLING, 'manual');
        $this->assertTrue(cart_manager::mark_paid((int) $order->id, 'TXN-ADMIN', []));
        $this->assertTrue(is_enrolled(\context_course::instance($theirs->id), $adminid));
    }

    // ── the fallback rule is the catalogue's rule ────────────────────────

    public function test_the_fallback_rule_gives_the_catalogues_answers(): void {
        $mine = $this->priced_course_at('/1/9');
        $theirs = $this->priced_course_at('/177');
        $sharedto1 = $this->priced_course_at('/177');
        $this->share((int) $sharedto1->id, 1);
        $withdrawn = $this->priced_course_at('/177');
        $this->share((int) $withdrawn->id, 1, 'withdrawn');
        $public = $this->priced_course_at('/77');
        $orphan = $this->priced_course_at(null);
        $hidden = $this->priced_course_at('/1/9', 1000.00, 0);
        $prefix = $this->priced_course_at('/10');   // /10 is not under /1

        $buyers = [
            '/1/4' => (int) $this->user_at('/1/4')->id,
            '/77/3' => (int) $this->user_at('/77/3')->id,
            'no tenant' => (int) $this->user_at('')->id,
            'guest' => (int) guest_user()->id,
        ];
        foreach ($buyers as $who => $buyerid) {
            foreach ([$mine, $theirs, $sharedto1, $withdrawn, $public, $orphan, $hidden, $prefix] as $course) {
                $this->assertSame(cart_manager::can_buy_course((int) $course->id, $buyerid),
                    cart_manager::tenant_rule_allows((int) $course->id, $buyerid),
                    "Without the catalogue plugin, {$who} must be able to buy exactly what they can with it "
                    . "(course {$course->id}, open_path '" . ($course->open_path ?? 'NULL') . "').");
            }
        }
        $this->assertFalse(cart_manager::tenant_rule_allows((int) $prefix->id, $buyers['/1/4']),
            'The tenant match is /-bounded: /10 is not in tenant 1.');
    }
}
