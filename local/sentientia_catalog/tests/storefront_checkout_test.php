<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

namespace local_sentientia_catalog;

defined('MOODLE_INTERNAL') || die();

// Deliberately NO require_once of local_sentientia_cart/lib.php here. cart_manager::add_item() used
// to call the global local_sentientia_cart_get_course_price() from that file, which Moodle loads only
// for a plugin's callbacks, so the bridge fataled on the web while a test that pre-included the file
// passed. add_item() now prices through cart_manager::get_course_price(); the wiring test below holds it.

/**
 * D2 (persona pass 2026-09-30): the storefront basket hands its paid lines to the order cart.
 *
 * The storefront basket (cart.php, in the session) used to end in a disabled "Payment Coming
 * Soon" button for any paid course; nothing carried its lines to the order cart
 * (local_sentientia_cart). checkout_bridge does, behind
 * sentientia.catalog.storefront_checkout.enabled (default OFF).
 *
 * What this locks in:
 *   1. The flag is registered, defaults OFF, and with it OFF nothing can be handed off - whatever
 *      the buyer holds - so cart.php renders as it did.
 *   2. With it ON, only a real login that holds local/sentientia_cart:purchase, in a tenant the cart
 *      is enabled for, can hand off; the flag is per tenant.
 *   3. hand_off() moves PAID lines through cart_manager::add_item(), so the ADR-031 purchase gate
 *      still refuses another tenant's course (whatever the session basket holds), a course with no
 *      order-cart price is refused rather than priced, and refused lines stay in the basket.
 *   4. The result is what cart_manager::checkout() consumes.
 *   5. Review round 1 (2026-09-30): a price that differs between the basket and the order cart is
 *      reported, a Throwable on one line refuses only that line, the free lines a buyer leaves behind
 *      are announced, and add_item() loads its own price lookup.
 *
 * NOTE for the lead: the catalog version was bumped with this (2026093001), so PHPUnit must be
 * re-initialised before it runs.
 *
 * @package    local_sentientia_catalog
 * @category   test
 * @copyright  2026 Airpay Payment Services
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \local_sentientia_catalog\checkout_bridge
 * @group      tenant_isolation
 */
final class storefront_checkout_test extends \advanced_testcase {

    // Provisions {user}.open_path + {course}.open_path on the test DB, as the plugin's other suites do.
    use \local_sentientia_platform\phpunit\open_path_fixture_trait;

    private const PURCHASE = 'local/sentientia_cart:purchase';

    /** Skip when the order cart is not installed; the bridge is a no-op there by design. */
    private function require_order_cart(): void {
        if (!class_exists('\\local_sentientia_cart\\cart_manager')) {
            $this->markTestSkipped('local_sentientia_cart is not installed.');
        }
    }

    private function make_user(int $root): \stdClass {
        global $DB;
        $u = $this->getDataGenerator()->create_user();
        $DB->set_field('user', 'open_path', '/' . $root, ['id' => $u->id]);
        return $DB->get_record('user', ['id' => $u->id], '*', MUST_EXIST);
    }

    /**
     * A course owned by tenant $root. Priced when $fee is set: the enabled enrol_fee instance, which is
     * the one price source (cart.price_source, 2026-10-07: the basket shows and the order cart charges
     * the same cost), plus the config setting course_price_<id>, which is only the fallback for a course
     * with no fee instance. $fee = null leaves it free. $storefrontonly leaves the config setting alone
     * (no fee instance): the order cart has no price for that course. $orderfee sets the enrol_fee cost
     * alone, which the catalogue now follows, so the basket and the order cart still agree.
     */
    private function make_course(int $root, ?float $fee = null, bool $storefrontonly = false,
            ?float $orderfee = null): \stdClass {
        global $DB;
        $c = $this->getDataGenerator()->create_course(['visible' => 1]);
        $DB->set_field('course', 'open_path', '/' . $root, ['id' => $c->id]);
        if ($fee !== null) {
            commerce::set_course_price((int) $c->id, $fee);
            if (!$storefrontonly) {
                $DB->insert_record('enrol', (object) [
                    'enrol' => 'fee', 'status' => ENROL_INSTANCE_ENABLED, 'courseid' => $c->id,
                    'sortorder' => 9, 'cost' => number_format($orderfee ?? $fee, 2, '.', ''), 'currency' => 'INR',
                    'roleid' => (int) $DB->get_field('role', 'id', ['shortname' => 'student']),
                    'timecreated' => time(), 'timemodified' => time(),
                ]);
            }
        }
        return $DB->get_record('course', ['id' => $c->id], '*', MUST_EXIST);
    }

    private function enable_flag(int $root, bool $on = true): void {
        \local_sentientia_platform\feature_flags::set(checkout_bridge::FLAG, $root, $on, null, 'phpunit');
        \local_sentientia_platform\feature_flags::invalidate_caches();
    }

    /** Give (or take) :purchase on the Authenticated user role, the only role a public learner holds. */
    private function set_user_role_purchase(?int $permission): void {
        global $DB;
        $roleid = (int) $DB->get_field('role', 'id', ['shortname' => 'user'], MUST_EXIST);
        $syscontext = \context_system::instance();
        if ($permission === null) {
            unassign_capability(self::PURCHASE, $roleid, $syscontext->id);
        } else {
            assign_capability(self::PURCHASE, $permission, $roleid, $syscontext->id, true);
        }
        accesslib_clear_all_caches_for_unit_testing();
    }

    /** Course ids in the buyer's ORDER cart. */
    private function order_cart_courseids(int $userid): array {
        $cart = \local_sentientia_cart\cart_manager::get_or_open_cart($userid);
        return array_map(fn($i) => (int) $i['courseid'], json_decode($cart->items_json ?: '[]', true) ?: []);
    }

    /** Course ids in the storefront (session) basket. */
    private function basket_courseids(): array {
        return array_map(fn($i) => (int) $i['courseid'], commerce::get_cart());
    }

    /** Put a line in the session basket the way a stale or forged basket can hold one: no gate. */
    private function force_basket_line(\stdClass $course, bool $free = false): void {
        global $SESSION;
        $SESSION->sentientia_cart[] = [
            'courseid' => (int) $course->id, 'fullname' => $course->fullname, 'shortname' => $course->shortname,
            'price' => $free ? 0 : 500.0, 'display' => $free ? 'Free' : '₹500', 'is_free' => $free, 'added' => time(),
        ];
    }

    // ── 1. The flag: registered, default OFF, and OFF means nothing ──────

    public function test_the_flag_is_registered_and_defaults_off(): void {
        $registry = \local_sentientia_platform\feature_flags::load_registry();

        $this->assertArrayHasKey(checkout_bridge::FLAG, $registry);
        $this->assertFalse($registry[checkout_bridge::FLAG]['default'], 'A new visible feature ships OFF.');
        $this->assertNotSame('', trim($registry[checkout_bridge::FLAG]['description']));
        $this->assertSame('sentientia.catalog.storefront_checkout.enabled', checkout_bridge::FLAG);
    }

    public function test_with_the_flag_off_nobody_can_hand_off_even_with_purchase(): void {
        $this->require_order_cart();
        $this->set_user_role_purchase(CAP_ALLOW);
        foreach ([77, 1, 177] as $root) {
            $user = $this->make_user($root);
            $this->assertFalse(checkout_bridge::is_enabled($user), "Default OFF for /$root.");
            $this->assertFalse(checkout_bridge::can_hand_off($user),
                "A buyer with :purchase in /$root still gets the disabled button while the flag is OFF.");
        }
    }

    // ── 2. With it ON: who may hand off ──────────────────────────────────

    public function test_with_the_flag_on_a_real_purchaser_can_hand_off(): void {
        $this->require_order_cart();
        $this->set_user_role_purchase(CAP_ALLOW);
        $this->enable_flag(77);
        $user = $this->make_user(77);

        $this->assertTrue(checkout_bridge::is_enabled($user));
        $this->assertTrue(checkout_bridge::can_hand_off($user));
    }

    public function test_the_flag_is_per_tenant(): void {
        $this->require_order_cart();
        $this->set_user_role_purchase(CAP_ALLOW);
        // The cart's own enabled_tenants setting (settings.php default '77,177', applied by the PHPUnit
        // install) is a second gate: pin it, and use two tenants it is ON for, so the only thing that
        // differs between the two buyers is this flag. (With /1 the assertTrue below would fail on
        // enabled_tenants, not on the flag.)
        set_config('enabled_tenants', '77,177', 'local_sentientia_cart');
        $this->enable_flag(177);

        $this->assertTrue(checkout_bridge::can_hand_off($this->make_user(177)));
        $this->assertFalse(checkout_bridge::can_hand_off($this->make_user(77)),
            'Switched on for /177 only: the Public storefront tenant keeps the disabled button.');
    }

    public function test_a_buyer_without_the_purchase_capability_cannot_hand_off(): void {
        $this->require_order_cart();
        $this->enable_flag(77);
        $this->set_user_role_purchase(null);
        $user = $this->make_user(77);

        $this->assertTrue(checkout_bridge::is_enabled($user));
        $this->assertFalse(checkout_bridge::can_hand_off($user),
            'The order cart would refuse this buyer at add-to-cart, so the storefront must not offer it.');

        $this->set_user_role_purchase(CAP_PROHIBIT);
        $this->assertFalse(checkout_bridge::can_hand_off($user), 'An administrator\'s PROHIBIT stands.');
    }

    public function test_a_tenant_the_cart_is_switched_off_for_cannot_hand_off(): void {
        $this->require_order_cart();
        $this->set_user_role_purchase(CAP_ALLOW);
        $this->enable_flag(77);
        set_config('enabled_tenants', '1,177', 'local_sentientia_cart');
        $user = $this->make_user(77);

        $this->assertFalse(checkout_bridge::can_hand_off($user), 'enabled_tenants still gates the cart per tenant.');
    }

    public function test_a_guest_and_an_anonymous_record_cannot_hand_off(): void {
        $this->require_order_cart();
        $this->set_user_role_purchase(CAP_ALLOW);
        $this->enable_flag(77);
        $this->enable_flag(0);

        $this->assertFalse(checkout_bridge::can_hand_off(guest_user()), 'The guest is sent to log in first.');
        $this->assertFalse(checkout_bridge::can_hand_off((object) ['id' => 0, 'open_path' => '/77']));
    }

    // ── 3. hand_off(): the order cart decides ────────────────────────────

    public function test_paid_lines_move_to_the_order_cart_and_leave_the_basket(): void {
        $this->require_order_cart();
        $buyer = $this->make_user(77);
        $this->setUser($buyer);
        $paid1 = $this->make_course(77, 500.0);
        $paid2 = $this->make_course(77, 750.0);
        $free = $this->make_course(77);
        foreach ([$paid1, $paid2, $free] as $c) {
            $this->assertTrue(commerce::add_to_cart((int) $c->id), 'Precondition: the storefront takes the line.');
        }

        $result = checkout_bridge::hand_off((int) $buyer->id);

        $this->assertEqualsCanonicalizing([(int) $paid1->id, (int) $paid2->id], $result['added']);
        $this->assertSame([], $result['refused']);
        $this->assertSame([], $result['redundant']);
        $this->assertSame([], $result['pricediffers'], 'Both sources say the same, so nothing is flagged.');
        $this->assertEqualsCanonicalizing([(int) $paid1->id, (int) $paid2->id],
            $this->order_cart_courseids((int) $buyer->id));
        $this->assertSame([(int) $free->id], $this->basket_courseids(),
            'The free line stays: it enrols through "Enroll in All (Free)", not the order cart.');
        $cart = \local_sentientia_cart\cart_manager::get_or_open_cart((int) $buyer->id);
        $this->assertEqualsWithDelta(1250.00, (float) $cart->subtotal, 0.001,
            'The order cart charges its own price (the enrol_fee instance).');
    }

    public function test_the_purchase_gate_still_refuses_another_tenants_course(): void {
        $this->require_order_cart();
        $buyer = $this->make_user(77);
        $this->setUser($buyer);
        $mine = $this->make_course(77, 500.0);
        $airpay = $this->make_course(1, 900.0);
        $zeea = $this->make_course(177, 900.0);
        commerce::add_to_cart((int) $mine->id);
        // A /77 basket can hold a /1 or /177 line only if it was forged or carried over from
        // another identity: commerce::add_to_cart() itself refuses it. The order cart must too.
        $this->force_basket_line($airpay);
        $this->force_basket_line($zeea);

        $result = checkout_bridge::hand_off((int) $buyer->id);

        $this->assertSame([(int) $mine->id], $result['added']);
        $this->assertEqualsCanonicalizing([(int) $airpay->id, (int) $zeea->id], $result['refused'],
            'ADR-031: a learner may buy only a course their own catalogue shows.');
        $this->assertSame([(int) $mine->id], $this->order_cart_courseids((int) $buyer->id),
            'Nothing from another tenant reached the order cart.');
        $this->assertEqualsCanonicalizing([(int) $airpay->id, (int) $zeea->id], $this->basket_courseids(),
            'A refused line stays in the basket, untouched.');
    }

    public function test_a_basket_built_as_the_public_guest_is_refused_for_an_internal_learner_who_logs_in(): void {
        $this->require_order_cart();
        $publiccourse = $this->make_course(77, 500.0);
        $this->setGuestUser();
        $this->assertTrue(commerce::add_to_cart((int) $publiccourse->id), 'A guest browses the Public storefront.');

        $internal = $this->make_user(1);
        $this->setUser($internal);
        // The basket a guest built survives the login in $SESSION. Put the same line back directly so
        // the test does not depend on how setUser() treats $SESSION.
        commerce::clear_cart();
        $this->force_basket_line($publiccourse);
        $result = checkout_bridge::hand_off((int) $internal->id);

        $this->assertSame([], $result['added'], 'A /1 employee is not sold a /77 course they cannot see.');
        $this->assertSame([(int) $publiccourse->id], $result['refused']);
        $this->assertSame([], $this->order_cart_courseids((int) $internal->id));
    }

    public function test_a_line_priced_only_on_the_storefront_is_refused_not_priced(): void {
        $this->require_order_cart();
        $buyer = $this->make_user(77);
        $this->setUser($buyer);
        $priced = $this->make_course(77, 500.0);
        $unsellable = $this->make_course(77, 300.0, true);   // storefront price, no enrol_fee instance
        commerce::add_to_cart((int) $priced->id);
        commerce::add_to_cart((int) $unsellable->id);

        $result = checkout_bridge::hand_off((int) $buyer->id);

        $this->assertSame([(int) $priced->id], $result['added']);
        $this->assertSame([(int) $unsellable->id], $result['refused'],
            'The order cart has no price for it, so it is refused rather than given one.');
        $this->assertSame([(int) $unsellable->id], $this->basket_courseids());
    }

    public function test_a_course_the_buyer_is_already_enrolled_in_is_dropped_from_the_basket(): void {
        $this->require_order_cart();
        $buyer = $this->make_user(77);
        $this->setUser($buyer);
        $owned = $this->make_course(77, 500.0);
        $other = $this->make_course(77, 600.0);
        commerce::add_to_cart((int) $owned->id);
        commerce::add_to_cart((int) $other->id);
        $this->getDataGenerator()->enrol_user($buyer->id, $owned->id);

        $result = checkout_bridge::hand_off((int) $buyer->id);

        $this->assertSame([(int) $owned->id], $result['redundant']);
        $this->assertSame([(int) $other->id], $result['added']);
        $this->assertSame([], $this->basket_courseids(), 'An owned course can never be bought, so it leaves the basket.');
        $this->assertSame([(int) $other->id], $this->order_cart_courseids((int) $buyer->id));
    }

    public function test_handing_off_twice_never_duplicates_a_line(): void {
        $this->require_order_cart();
        $buyer = $this->make_user(77);
        $this->setUser($buyer);
        $paid = $this->make_course(77, 500.0);
        commerce::add_to_cart((int) $paid->id);

        checkout_bridge::hand_off((int) $buyer->id);
        // The same basket again (a double click that re-posts a stale form).
        $this->force_basket_line($paid);
        $second = checkout_bridge::hand_off((int) $buyer->id);

        $this->assertSame([(int) $paid->id], $second['added'], 'add_item() is idempotent: the line counts as added.');
        $this->assertSame([(int) $paid->id], $this->order_cart_courseids((int) $buyer->id), 'One line, not two.');
        $this->assertSame([], $this->basket_courseids());
    }

    public function test_an_empty_or_all_free_basket_hands_off_nothing(): void {
        $this->require_order_cart();
        $buyer = $this->make_user(77);
        $this->setUser($buyer);
        $none = ['added' => [], 'redundant' => [], 'refused' => [], 'pricediffers' => []];
        $this->assertSame($none, checkout_bridge::hand_off((int) $buyer->id));

        $free = $this->make_course(77);
        commerce::add_to_cart((int) $free->id);
        $result = checkout_bridge::hand_off((int) $buyer->id);

        $this->assertSame($none, $result);
        $this->assertSame([(int) $free->id], $this->basket_courseids());
    }

    public function test_what_the_bridge_hands_over_checks_out(): void {
        $this->require_order_cart();
        $buyer = $this->make_user(77);
        $this->setUser($buyer);
        $paid = $this->make_course(77, 500.0);
        commerce::add_to_cart((int) $paid->id);
        checkout_bridge::hand_off((int) $buyer->id);

        $order = \local_sentientia_cart\cart_manager::checkout((int) $buyer->id,
            ['billing_name' => 'Test Buyer', 'billing_email' => 'buyer@example.com'], 'manual');

        $this->assertSame('pending', $order->status);
        $this->assertEqualsWithDelta(500.00, (float) $order->subtotal, 0.001);
        $this->assertSame([(int) $paid->id],
            array_map(fn($i) => (int) $i['courseid'], json_decode($order->items_json, true)));
    }

    public function test_the_basket_shows_the_enrol_fee_cost_the_order_cart_charges(): void {
        $this->require_order_cart();
        $buyer = $this->make_user(77);
        $this->setUser($buyer);
        // Config says 500, the enrol_fee instance 650: the fee is the one source, so the basket line carries 650.
        $course = $this->make_course(77, 500.0, false, 650.0);
        commerce::add_to_cart((int) $course->id);
        $line = commerce::get_cart()[0];
        $this->assertEqualsWithDelta(650.0, (float) $line['price'], 0.001);
        $this->assertFalse($line['is_free']);

        $result = checkout_bridge::hand_off((int) $buyer->id);

        $this->assertSame([(int) $course->id], $result['added']);
        $this->assertSame([], $result['pricediffers'], 'One source: the basket and the order cart cannot disagree.');
        $cart = \local_sentientia_cart\cart_manager::get_or_open_cart((int) $buyer->id);
        $this->assertEqualsWithDelta(650.00, (float) $cart->subtotal, 0.001);
    }

    public function test_a_price_that_differs_between_basket_and_order_cart_is_flagged_but_still_moved(): void {
        global $DB;
        $this->require_order_cart();
        $buyer = $this->make_user(77);
        $this->setUser($buyer);
        $agrees = $this->make_course(77, 500.0);
        $differs = $this->make_course(77, 500.0);
        commerce::add_to_cart((int) $agrees->id);
        commerce::add_to_cart((int) $differs->id);
        // The fee changes after the line is in the basket (the session keeps the price it was added at):
        // the basket says 500, the order cart now charges 650.
        $DB->set_field('enrol', 'cost', '650.00', ['courseid' => $differs->id, 'enrol' => 'fee']);

        $result = checkout_bridge::hand_off((int) $buyer->id);

        $this->assertEqualsCanonicalizing([(int) $agrees->id, (int) $differs->id], $result['added'],
            'A difference is reported, not refused: the checkout page shows what will be charged.');
        $this->assertSame([(int) $differs->id], $result['pricediffers']);
        $cart = \local_sentientia_cart\cart_manager::get_or_open_cart((int) $buyer->id);
        $this->assertEqualsWithDelta(1150.00, (float) $cart->subtotal, 0.001,
            'The order cart charges its own price (the enrol_fee instance) for both lines.');
    }

    public function test_a_throwable_on_one_line_refuses_only_that_line(): void {
        $this->require_order_cart();
        $buyer = $this->make_user(77);
        $this->setUser($buyer);
        $first = $this->make_course(77, 500.0);
        $boom = $this->make_course(77, 600.0);
        $last = $this->make_course(77, 700.0);
        foreach ([$first, $boom, $last] as $c) {
            commerce::add_to_cart((int) $c->id);
        }
        // Not a moodle_exception: a database error or an undefined function on one odd line.
        $adder = function (int $userid, int $courseid) use ($boom) {
            if ($courseid === (int) $boom->id) {
                throw new \RuntimeException('simulated failure');
            }
            return \local_sentientia_cart\cart_manager::add_item($userid, $courseid);
        };

        $result = checkout_bridge::hand_off((int) $buyer->id, $adder);

        $this->assertDebuggingCalled();
        $this->assertEqualsCanonicalizing([(int) $first->id, (int) $last->id], $result['added'],
            'The lines before AND after the failing one are still moved.');
        $this->assertSame([(int) $boom->id], $result['refused']);
        $this->assertSame([(int) $boom->id], $this->basket_courseids(), 'The failing line stays in the basket.');
        $this->assertEqualsCanonicalizing([(int) $first->id, (int) $last->id],
            $this->order_cart_courseids((int) $buyer->id));
    }

    public function test_an_error_on_one_line_is_also_refused_not_fatal(): void {
        $this->require_order_cart();
        $buyer = $this->make_user(77);
        $this->setUser($buyer);
        $paid = $this->make_course(77, 500.0);
        commerce::add_to_cart((int) $paid->id);
        // An \Error (not an \Exception), which is exactly what an undefined function raises.
        $adder = function (int $userid, int $courseid) {
            throw new \Error('Call to undefined function');
        };

        $result = checkout_bridge::hand_off((int) $buyer->id, $adder);

        $this->assertDebuggingCalled();
        $this->assertSame([(int) $paid->id], $result['refused']);
        $this->assertSame([], $result['added']);
        $this->assertSame([(int) $paid->id], $this->basket_courseids());
    }

    // ── Where the buyer goes and what they are told ──────────────────────

    public function test_next_url_is_checkout_when_something_arrived_and_the_basket_otherwise(): void {
        $moved = checkout_bridge::next_url(['added' => [5], 'redundant' => [], 'refused' => [6]]);
        $stayed = checkout_bridge::next_url(['added' => [], 'redundant' => [], 'refused' => [6]]);

        // out_as_local_url(), not get_path(): PHPUnit's wwwroot is https://www.example.com/moodle, so
        // get_path() carries the '/moodle' prefix; the local URL is relative to wwwroot.
        $this->assertSame('/local/sentientia_cart/checkout.php', $moved->out_as_local_url(false));
        $this->assertSame('/local/sentientia_catalog/cart.php', $stayed->out_as_local_url(false));
    }

    public function test_notify_says_what_moved_what_did_not_and_what_was_dropped(): void {
        \core\notification::fetch();   // Start clean.

        checkout_bridge::notify(['added' => [1, 2], 'redundant' => [3], 'refused' => [4]]);
        $messages = array_map(fn($n) => $n->get_message(), \core\notification::fetch());

        $this->assertContains(get_string('storefront_checkout_moved', 'local_sentientia_catalog', 2), $messages);
        $this->assertContains(get_string('storefront_checkout_redundant', 'local_sentientia_catalog', 1), $messages);
        $this->assertContains(get_string('storefront_checkout_refused', 'local_sentientia_catalog', 1), $messages);
        $this->assertNotContains(get_string('storefront_checkout_nothing', 'local_sentientia_catalog'), $messages,
            'Something arrived, so there is no "nothing" error.');
    }

    public function test_notify_warns_when_a_price_differs(): void {
        \core\notification::fetch();

        checkout_bridge::notify(['added' => [1, 2], 'redundant' => [], 'refused' => [], 'pricediffers' => [2]]);
        $messages = array_map(fn($n) => $n->get_message(), \core\notification::fetch());

        $this->assertContains(get_string('storefront_checkout_pricediffers', 'local_sentientia_catalog', 1), $messages);
    }

    public function test_notify_says_where_the_free_lines_went(): void {
        $buyer = $this->make_user(77);
        $this->setUser($buyer);
        $free1 = $this->make_course(77);
        $free2 = $this->make_course(77);
        commerce::add_to_cart((int) $free1->id);
        commerce::add_to_cart((int) $free2->id);
        $this->assertSame(2, checkout_bridge::free_lines_left());
        \core\notification::fetch();

        checkout_bridge::notify(['added' => [9], 'redundant' => [], 'refused' => [], 'pricediffers' => []]);
        $messages = array_map(fn($n) => $n->get_message(), \core\notification::fetch());

        $this->assertContains(get_string('storefront_checkout_freeleft', 'local_sentientia_catalog', 2), $messages,
            'The buyer lands on the order cart checkout page: tell them their free courses are still in the basket.');
    }

    public function test_notify_does_not_mention_free_lines_when_the_buyer_stays_on_the_basket(): void {
        $buyer = $this->make_user(77);
        $this->setUser($buyer);
        $free = $this->make_course(77);
        commerce::add_to_cart((int) $free->id);
        \core\notification::fetch();

        checkout_bridge::notify(['added' => [], 'redundant' => [], 'refused' => [4], 'pricediffers' => []]);
        $messages = array_map(fn($n) => $n->get_message(), \core\notification::fetch());

        $this->assertNotContains(get_string('storefront_checkout_freeleft', 'local_sentientia_catalog', 1), $messages,
            'Nothing moved, so the buyer is still looking at the basket.');
    }

    public function test_notify_errors_when_nothing_reached_the_order_cart(): void {
        \core\notification::fetch();

        checkout_bridge::notify(['added' => [], 'redundant' => [], 'refused' => [4, 5]]);
        $messages = array_map(fn($n) => $n->get_message(), \core\notification::fetch());

        $this->assertContains(get_string('storefront_checkout_refused', 'local_sentientia_catalog', 2), $messages);
        $this->assertContains(get_string('storefront_checkout_nothing', 'local_sentientia_catalog'), $messages);
    }

    // ── Wiring ───────────────────────────────────────────────────────────

    public function test_cart_php_offers_the_button_only_through_the_bridge(): void {
        global $CFG;
        $source = file_get_contents($CFG->dirroot . '/local/sentientia_catalog/cart.php');

        $this->assertStringContainsString('checkout_bridge::can_hand_off($USER)', $source);
        $this->assertStringContainsString("if (\$action === 'checkout'", $source);
        $this->assertStringContainsString('require_sesskey();', $source);
        // The handler is guarded by can_hand_off() BEFORE it does anything, so the flag off means an
        // unknown action, ignored as it always was.
        $this->assertLessThan(strpos($source, 'checkout_bridge::hand_off('),
            strpos($source, 'checkout_bridge::can_hand_off($USER)'));
        // The flag-off branch is still there, unchanged.
        $this->assertStringContainsString('Payment Coming Soon', $source);
    }

    public function test_the_order_cart_prices_a_line_without_lib_php_being_loaded(): void {
        // Moodle includes a plugin's lib.php only when a callback that plugin defines is looked up, so
        // add_item() must not depend on a function that lives there: through the autoloader alone
        // (the bridge, the local_sentientia_cart_add_item web service) it fataled with "Call to undefined
        // function". A run in one PHPUnit process cannot prove "not loaded" (any earlier callback lookup
        // includes it), so hold the source: add_item() prices through the class.
        $method = new \ReflectionMethod(\local_sentientia_cart\cart_manager::class, 'add_item');
        $lines = file($method->getFileName());
        $body = implode('', array_slice($lines, $method->getStartLine() - 1,
            $method->getEndLine() - $method->getStartLine() + 1));

        $this->assertStringContainsString('self::get_course_price(', $body);
        $this->assertStringNotContainsString('local_sentientia_cart_get_course_price(', $body);
        $this->assertTrue(method_exists(\local_sentientia_cart\cart_manager::class, 'get_course_price'));
    }

    public function test_the_new_strings_exist_in_english_and_hindi(): void {
        global $CFG;
        $keys = ['storefront_checkout_button', 'storefront_checkout_hint', 'storefront_checkout_moved',
            'storefront_checkout_refused', 'storefront_checkout_redundant', 'storefront_checkout_nothing',
            'storefront_checkout_pricediffers', 'storefront_checkout_freeleft'];
        foreach (['en', 'hi'] as $lang) {
            $string = [];
            include($CFG->dirroot . "/local/sentientia_catalog/lang/{$lang}/local_sentientia_catalog.php");
            foreach ($keys as $key) {
                $this->assertArrayHasKey($key, $string, "$lang is missing $key");
                $this->assertNotSame('', trim($string[$key]));
            }
        }
    }

    public function test_the_version_is_bumped(): void {
        global $CFG;
        $plugin = new \stdClass();
        include($CFG->dirroot . '/local/sentientia_catalog/version.php');
        $this->assertGreaterThanOrEqual(2026093001, $plugin->version);
    }
}
