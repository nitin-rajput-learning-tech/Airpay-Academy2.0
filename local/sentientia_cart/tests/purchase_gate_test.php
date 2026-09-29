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
 * @package    local_sentientia_cart
 * @category   test
 * @copyright  2026 Airpay Payment Services
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \local_sentientia_cart\cart_manager::can_buy_course
 * @covers     \local_sentientia_cart\cart_manager::tenant_rule_allows
 * @covers     \local_sentientia_cart\cart_manager::add_item
 * @covers     \local_sentientia_cart\cart_manager::checkout
 * @covers     \local_sentientia_cart\cart_manager::mark_paid
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

    /** A pending order (as checkout() leaves it) holding $courseids, for $buyer. */
    private function pending_order(\stdClass $buyer, array $courseids): int {
        global $DB;
        $items = array_map(fn($id) => ['courseid' => (int) $id, 'name' => 'Course', 'price' => 1000.00],
            $courseids);
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
        $historyid = $this->pending_order($buyer, [(int) $mine->id, (int) $theirs->id]);

        $this->assertTrue(cart_manager::mark_paid($historyid, 'TXN-GATE', []));

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
        $this->assertTrue(cart_manager::mark_paid((int) $order->id, 'TXN-OK', []));

        $this->assertTrue(is_enrolled(\context_course::instance($mine->id), $buyer->id));
        $this->assertTrue(is_enrolled(\context_course::instance($shared->id), $buyer->id));
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
