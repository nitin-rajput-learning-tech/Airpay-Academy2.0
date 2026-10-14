<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

namespace local_sentientia_catalog;

defined('MOODLE_INTERNAL') || die();

/**
 * cart.price_source (owner decision, 2026-10-07): the enabled enrol_fee instance is the one price of a course.
 *
 * The catalogue used to read only the config setting course_price_<id>, which production never sets. Every course
 * priced through enrol_fee (66 in the April 2026 copy, 61 of them Public, INR 100-499) therefore read as Free, the
 * basket stored it as is_free, and the basket's "enrollfree" action (no flag, any logged-in non-guest) enrolled it
 * through enrolment::enrol_now(), whose "never enrol into a paid course" re-check used the same config-only price.
 * A logged-in learner could be enrolled for nothing in a course production sells.
 *
 * What this locks in:
 *   1. A course priced only by an enabled enrol_fee instance is paid, with the instance's cost and currency.
 *   2. The fee beats the config setting; the config setting is only the fallback for a course with no fee
 *      instance; with neither the course is free (nothing that was free becomes paid).
 *   3. A disabled instance, a zero cost and a non-numeric cost are not prices.
 *   4. With several instances the lowest sortorder (then id) with a cost decides, and the catalogue is never FREE
 *      where the order cart has a price (the catalogue may only be stricter).
 *   5. enrol_now() and the basket's enrollfree path refuse a course the order cart would charge for.
 *
 * @package    local_sentientia_catalog
 * @category   test
 * @copyright  2026 Airpay Payment Services
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \local_sentientia_catalog\commerce::get_course_price
 * @covers     \local_sentientia_catalog\commerce::enrol_fee_price
 * @covers     \local_sentientia_catalog\enrolment::enrol_now
 * @group      tenant_isolation
 */
final class price_source_test extends \advanced_testcase {

    // Provisions {user}.open_path + {course}.open_path on the test DB, as the plugin's other suites do.
    use \local_sentientia_platform\phpunit\open_path_fixture_trait;

    /** A course in tenant $root. */
    private function make_course(int $root = 1): \stdClass {
        global $DB;
        $course = $this->getDataGenerator()->create_course(['visible' => 1]);
        $DB->set_field('course', 'open_path', '/' . $root, ['id' => $course->id]);
        return $DB->get_record('course', ['id' => $course->id], '*', MUST_EXIST);
    }

    private function make_user(int $root = 1): \stdClass {
        global $DB;
        $user = $this->getDataGenerator()->create_user();
        $DB->set_field('user', 'open_path', '/' . $root, ['id' => $user->id]);
        return $DB->get_record('user', ['id' => $user->id], '*', MUST_EXIST);
    }

    /** An enrol_fee instance on the course, as Moodle (or the cart's set-price tool) leaves one. */
    private function add_fee(int $courseid, string $cost, int $status = ENROL_INSTANCE_ENABLED,
            string $currency = 'INR', int $sortorder = 9): int {
        global $DB;
        return (int) $DB->insert_record('enrol', (object) [
            'enrol' => 'fee', 'status' => $status, 'courseid' => $courseid, 'sortorder' => $sortorder,
            'cost' => $cost, 'currency' => $currency,
            'roleid' => (int) $DB->get_field('role', 'id', ['shortname' => 'student']),
            'timecreated' => time(), 'timemodified' => time(),
        ]);
    }

    private function require_order_cart(): void {
        if (!class_exists('\\local_sentientia_cart\\cart_manager')) {
            $this->markTestSkipped('local_sentientia_cart is not installed.');
        }
    }

    // ── 1. Priced only by enrol_fee ──────────────────────────────────────

    public function test_a_course_priced_only_by_an_enrol_fee_instance_is_paid(): void {
        $this->resetAfterTest();
        $course = $this->make_course(77);
        $this->add_fee((int) $course->id, '499.00');

        $pricing = commerce::get_course_price((int) $course->id);

        $this->assertFalse($pricing['is_free'], 'The revenue hole: this course used to read as Free.');
        $this->assertEqualsWithDelta(499.0, $pricing['price'], 0.001);
        $this->assertSame('INR', $pricing['currency']);
        $this->assertSame('₹499', $pricing['display']);
        $this->assertSame('paid', $pricing['price_class']);
        $this->assertSame('enrol_fee', $pricing['price_source']);
    }

    public function test_the_basket_stores_a_fee_priced_course_as_paid_not_free(): void {
        $this->resetAfterTest();
        $course = $this->make_course(77);
        $this->add_fee((int) $course->id, '250.00');
        $this->setUser($this->make_user(77));

        $this->assertTrue(commerce::add_to_cart((int) $course->id));
        $line = array_values(commerce::get_cart())[0];

        $this->assertFalse($line['is_free'], 'It used to be stored as is_free, which "enrollfree" then enrolled.');
        $this->assertEqualsWithDelta(250.0, (float) $line['price'], 0.001);
        $totals = commerce::get_cart_total();
        $this->assertFalse($totals['all_free']);
        $this->assertSame(1, $totals['paid_count']);
    }

    // ── 2. Fee, then config fallback, then free ──────────────────────────

    public function test_the_fee_beats_the_config_setting(): void {
        $this->resetAfterTest();
        $course = $this->make_course();
        commerce::set_course_price((int) $course->id, 100.0);
        $this->add_fee((int) $course->id, '300.00');

        $pricing = commerce::get_course_price((int) $course->id);

        $this->assertEqualsWithDelta(300.0, $pricing['price'], 0.001, 'The cart charges the fee, so the catalogue shows it.');
        $this->assertSame('enrol_fee', $pricing['price_source']);
    }

    public function test_the_config_setting_is_only_a_fallback_for_a_course_with_no_fee_instance(): void {
        $this->resetAfterTest();
        $course = $this->make_course();
        commerce::set_course_price((int) $course->id, 100.0);

        $pricing = commerce::get_course_price((int) $course->id);

        $this->assertFalse($pricing['is_free'], 'Paid stays paid: the setting still marks the course as priced.');
        $this->assertEqualsWithDelta(100.0, $pricing['price'], 0.001);
        $this->assertSame('config', $pricing['price_source']);
    }

    public function test_a_course_with_no_price_anywhere_is_free(): void {
        $this->resetAfterTest();
        $course = $this->make_course();

        $pricing = commerce::get_course_price((int) $course->id);

        $this->assertTrue($pricing['is_free']);
        $this->assertSame('Free', $pricing['display']);
        $this->assertSame('free', $pricing['price_class']);
        $this->assertSame('none', $pricing['price_source']);
        $this->assertEqualsWithDelta(0.0, $pricing['price'], 0.001);
    }

    // ── 3. What is not a price ───────────────────────────────────────────

    public function test_a_disabled_zero_or_non_numeric_fee_is_not_a_price(): void {
        $this->resetAfterTest();
        $disabled = $this->make_course();
        $this->add_fee((int) $disabled->id, '499.00', ENROL_INSTANCE_DISABLED);
        $zero = $this->make_course();
        $this->add_fee((int) $zero->id, '0.00');
        $junk = $this->make_course();
        $this->add_fee((int) $junk->id, 'abc');
        $empty = $this->make_course();
        $this->add_fee((int) $empty->id, '');

        foreach ([$disabled, $zero, $junk, $empty] as $course) {
            $pricing = commerce::get_course_price((int) $course->id);
            $this->assertTrue($pricing['is_free'], 'course ' . $course->id . ': not a price.');
            $this->assertSame('none', $pricing['price_source']);
        }
    }

    public function test_a_fee_in_another_currency_is_shown_with_its_code_and_paise_when_it_has_them(): void {
        $this->resetAfterTest();
        $usd = $this->make_course();
        $this->add_fee((int) $usd->id, '12.50', ENROL_INSTANCE_ENABLED, 'usd');
        $paise = $this->make_course();
        $this->add_fee((int) $paise->id, '499.50');

        $dollars = commerce::get_course_price((int) $usd->id);
        $this->assertSame('USD', $dollars['currency']);
        $this->assertSame('USD 12.50', $dollars['display']);
        $this->assertSame('₹499.50', commerce::get_course_price((int) $paise->id)['display'],
            'A price with paise is not rounded to the rupee.');
    }

    // ── 4. Several instances; never free where the cart has a price ──────

    public function test_the_lowest_sortorder_with_a_cost_decides_and_matches_the_order_cart(): void {
        $this->resetAfterTest();
        $course = $this->make_course();
        $this->add_fee((int) $course->id, '400.00', ENROL_INSTANCE_ENABLED, 'INR', 5);
        $this->add_fee((int) $course->id, '300.00', ENROL_INSTANCE_ENABLED, 'INR', 2);
        $this->add_fee((int) $course->id, '200.00', ENROL_INSTANCE_DISABLED, 'INR', 1);

        $pricing = commerce::get_course_price((int) $course->id);

        $this->assertEqualsWithDelta(300.0, $pricing['price'], 0.001);
        if (class_exists('\\local_sentientia_cart\\cart_manager')) {
            $this->assertEqualsWithDelta(
                \local_sentientia_cart\cart_manager::get_course_price((int) $course->id), $pricing['price'], 0.001,
                'The catalogue shows what the order cart charges.');
        }
    }

    public function test_the_catalogue_is_never_free_where_the_order_cart_has_a_price(): void {
        $this->require_order_cart();
        $this->resetAfterTest();
        $shapes = [
            'one fee' => [['499.00', ENROL_INSTANCE_ENABLED, 1]],
            'two fees' => [['499.00', ENROL_INSTANCE_ENABLED, 1], ['299.00', ENROL_INSTANCE_ENABLED, 2]],
            'a zero first, a price second' => [['0.00', ENROL_INSTANCE_ENABLED, 1], ['299.00', ENROL_INSTANCE_ENABLED, 2]],
            'a disabled first' => [['499.00', ENROL_INSTANCE_DISABLED, 1], ['299.00', ENROL_INSTANCE_ENABLED, 2]],
            'free' => [],
            'zero only' => [['0.00', ENROL_INSTANCE_ENABLED, 1]],
        ];
        foreach ($shapes as $label => $instances) {
            $course = $this->make_course();
            foreach ($instances as [$cost, $status, $sortorder]) {
                $this->add_fee((int) $course->id, $cost, $status, 'INR', $sortorder);
            }
            $carts = \local_sentientia_cart\cart_manager::get_course_price((int) $course->id);
            $pricing = commerce::get_course_price((int) $course->id);
            if ($carts !== null) {
                $this->assertFalse($pricing['is_free'], "{$label}: the cart charges {$carts}, so the catalogue must not say Free.");
                $this->assertEqualsWithDelta($carts, $pricing['price'], 0.001, "{$label}: the same price.");
            }
        }
    }

    // ── 5. Nothing is enrolled for free ──────────────────────────────────

    public function test_enrol_now_refuses_a_course_priced_only_by_enrol_fee(): void {
        $this->resetAfterTest();
        $course = $this->make_course(1);
        $this->add_fee((int) $course->id, '299.00');
        $user = $this->make_user(1);

        $this->assertFalse(enrolment::enrol_now((int) $course->id, (int) $user->id));
        $this->assertFalse(is_enrolled(\context_course::instance($course->id), $user->id),
            'A paid course must never be enrolled through the free path.');
    }

    public function test_enrol_now_still_enrols_a_free_course_and_one_whose_fee_is_disabled_or_zero(): void {
        $this->resetAfterTest();
        $free = $this->make_course(1);
        $disabled = $this->make_course(1);
        $this->add_fee((int) $disabled->id, '299.00', ENROL_INSTANCE_DISABLED);
        $zero = $this->make_course(1);
        $this->add_fee((int) $zero->id, '0.00');
        $user = $this->make_user(1);

        foreach ([$free, $disabled, $zero] as $course) {
            $this->assertTrue(enrolment::enrol_now((int) $course->id, (int) $user->id),
                'course ' . $course->id . ': free stays free.');
            $this->assertTrue(is_enrolled(\context_course::instance($course->id), $user->id));
        }
    }

    public function test_the_basket_enrollfree_path_cannot_enrol_a_fee_priced_line_even_if_it_carries_is_free(): void {
        global $SESSION;
        $this->resetAfterTest();
        $course = $this->make_course(1);
        $this->add_fee((int) $course->id, '299.00');
        $user = $this->make_user(1);
        $this->setUser($user);
        // A basket line built before the fee was set, or forged: it says free. cart.php's enrollfree action
        // calls enrol_now() for every is_free line, and enrol_now() re-checks the price on the server.
        $SESSION->sentientia_cart = [[
            'courseid' => (int) $course->id, 'fullname' => $course->fullname, 'shortname' => $course->shortname,
            'price' => 0, 'display' => 'Free', 'is_free' => true, 'added' => time(),
        ]];

        foreach (commerce::get_cart() as $item) {
            if (!empty($item['is_free'])) {
                $this->assertFalse(enrolment::enrol_now((int) $item['courseid']));
            }
        }
        $this->assertFalse(is_enrolled(\context_course::instance($course->id), $user->id));
    }

    public function test_enrol_now_also_asks_the_order_cart_for_its_price(): void {
        $this->require_order_cart();
        $source = $this->method_source(enrolment::class, 'enrol_now');
        $this->assertStringContainsString("class_exists('\\\\local_sentientia_cart\\\\cart_manager')", $source);
        $this->assertStringContainsString('cart_manager::get_course_price($courseid) !== null', $source,
            'The guard that stops paid courses being given away does not depend on one price reader being right.');
        $this->assertTrue(method_exists(\local_sentientia_cart\cart_manager::class, 'get_course_price'));
    }

    /** The source of one method, for a wiring check that PHPUnit cannot make by behaviour. */
    private function method_source(string $class, string $method): string {
        $reflection = new \ReflectionMethod($class, $method);
        $lines = file($reflection->getFileName());
        return implode('', array_slice($lines, $reflection->getStartLine() - 1,
            $reflection->getEndLine() - $reflection->getStartLine() + 1));
    }
}
