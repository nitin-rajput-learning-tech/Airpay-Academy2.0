<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

namespace local_sentientia_cart\event;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->dirroot . '/local/sentientia_cart/tests/gst_helpers.php');

use local_sentientia_cart\cart_manager;

/**
 * Decision A5 (2026-11-12): cart_manager::mark_paid() triggers order_paid, the event
 * \local_sentientia_platform\audit_log::SENSITIVE_EVENTS lists, once per payment and only after it has committed.
 *
 * Each test drives the real mark_paid() with the event sink redirected, so what is asserted is what the standard
 * logstore would have been handed.
 *
 * @package    local_sentientia_cart
 * @category   test
 * @copyright  2026 Airpay Payment Services
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \local_sentientia_cart\event\order_paid
 * @covers     \local_sentientia_cart\cart_manager::mark_paid
 *
 * @group local_sentientia_cart
 */
final class order_paid_test extends \advanced_testcase {

    use \local_sentientia_cart\gst_helpers;

    protected function setUp(): void {
        parent::setUp();
        $this->gst_setup();
    }

    /**
     * A course for sale, the way purchase_gate_test builds one: visible, an open_path, an enabled enrol_fee instance.
     *
     * @param string $path
     * @return \stdClass
     */
    private function priced_course_at(string $path): \stdClass {
        global $DB;
        $c = $this->course_at($path);
        $DB->insert_record('enrol', (object) [
            'enrol' => 'fee', 'status' => ENROL_INSTANCE_ENABLED, 'courseid' => $c->id,
            'sortorder' => 9, 'cost' => '1000.00', 'currency' => 'INR',
            'roleid' => (int) $DB->get_field('role', 'id', ['shortname' => 'student']),
            'timecreated' => time(), 'timemodified' => time(),
        ]);
        return $c;
    }

    /**
     * Run $fn with events and messages redirected and return the order_paid events it triggered.
     *
     * @param callable $fn
     * @return order_paid[]
     */
    private function order_paid_events(callable $fn): array {
        $messages = $this->redirectMessages();
        $sink = $this->redirectEvents();
        try {
            $fn();
        } finally {
            $events = $sink->get_events();
            $sink->close();
            $messages->close();
        }
        return array_values(array_filter($events, fn($e) => $e instanceof order_paid));
    }

    public function test_a_payment_triggers_order_paid_once_with_the_order_and_the_buyer(): void {
        $buyer = $this->user_at('/77');
        $order = $this->order_for($buyer);

        $events = $this->order_paid_events(
            fn() => $this->assertTrue(cart_manager::mark_paid((int) $order->id, 'TXN-A5-1', ['a' => 1])));

        $this->assertCount(1, $events, 'one payment, one audit event');
        $event = $events[0];
        $this->assertSame((int) $order->id, (int) $event->objectid);
        $this->assertSame('local_sentientia_cart_history', $event->objecttable);
        $this->assertSame((int) $buyer->id, (int) $event->relateduserid);
        $this->assertSame(0, (int) $event->userid, 'mark_paid() ran as no one, like the gateway callback');
        $this->assertSame(\context_system::instance()->id, (int) $event->contextid);
        $this->assertSame('u', $event->crud);
        $this->assertSame(\core\event\base::LEVEL_OTHER, (int) $event->edulevel);
        $this->assertSame((int) $order->orderid, $event->other['orderid']);
        $this->assertSame(118000, $event->other['amount'], '1180.00 INR in paise');
        $this->assertSame('INR', $event->other['currency']);
        $this->assertSame('\local_sentientia_cart\event\order_paid', $event->eventname);
        $this->assertSame('paid', $this->order((int) $order->id)->status, 'the event follows the stored payment');

        // Ids and codes only: none of the buyer's billing details reach the log.
        $data = json_encode($event->get_data());
        foreach (['Test Buyer', 'buyer@example.com', '9999999999', 'Test Road'] as $personal) {
            $this->assertStringNotContainsString($personal, $data);
        }
        $this->assertSame(['orderid', 'amount', 'currency', 'withheld'], array_keys($event->other));
        $this->assertStringContainsString("'{$order->id}'", $event->get_description());
        $this->assertSame('Order paid', order_paid::get_name());
        $this->assertSame('/local/sentientia_cart/admin_orders.php', $event->get_url()->out_as_local_url(false));
        $this->assertEventContextNotUsed($event);
        $this->assertDebuggingNotCalled();
    }

    public function test_the_event_counts_the_withheld_lines(): void {
        $buyer = $this->user_at('/1/4');
        $mine = $this->priced_course_at('/1/9');
        $theirs = $this->priced_course_at('/177');
        $order = $this->order_for($buyer, [
            'items_json'   => json_encode([
                ['courseid' => (int) $mine->id, 'name' => 'In tenant', 'price' => 500.00, 'discount_pct' => 0],
                ['courseid' => (int) $theirs->id, 'name' => 'Elsewhere', 'price' => 500.00, 'discount_pct' => 0],
            ]),
        ]);

        $events = $this->order_paid_events(
            fn() => $this->assertTrue(cart_manager::mark_paid((int) $order->id, 'TXN-A5-2')));

        $this->assertCount(1, $events);
        $this->assertSame(1, $events[0]->other['withheld'], 'the /177 line is paid for but not enrolled');
    }

    public function test_a_replayed_payment_triggers_nothing(): void {
        $order = $this->order_for($this->user_at('/77'));
        $messages = $this->redirectMessages();
        $this->assertTrue(cart_manager::mark_paid((int) $order->id, 'TXN-A5-3'));
        $messages->close();

        $events = $this->order_paid_events(
            fn() => $this->assertTrue(cart_manager::mark_paid((int) $order->id, 'TXN-A5-3', ['replay' => true])));

        $this->assertSame([], $events, 'the idempotent replay of a paid order is not a second payment');
    }

    public function test_a_refused_payment_triggers_nothing(): void {
        global $DB;
        $order = $this->order_for($this->user_at('/77'), ['status' => 'refunded']);

        $events = $this->order_paid_events(fn() => $this->assert_refused(
            fn() => cart_manager::mark_paid((int) $order->id, 'TXN-A5-4'), 'error_invalidstate',
            'a refunded order cannot be paid'));

        $this->assertSame([], $events);
        $this->assertSame(0, $DB->count_records('local_sentientia_cart_ledger', ['historyid' => $order->id]),
            'nothing was written, so nothing is logged');
    }

    public function test_an_imported_order_triggers_nothing(): void {
        $order = $this->order_for($this->user_at('/77'), ['legacy_source' => 'bizlms']);

        $events = $this->order_paid_events(fn() => $this->assert_refused(
            fn() => cart_manager::mark_paid((int) $order->id, 'TXN-A5-5'), 'error_invalidstate',
            'ADR-032: an imported order is frozen history'));

        $this->assertSame([], $events);
        $this->assertSame('pending', $this->order((int) $order->id)->status);
    }
}
