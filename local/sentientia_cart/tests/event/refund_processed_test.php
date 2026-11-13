<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

namespace local_sentientia_cart\event;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->dirroot . '/local/sentientia_cart/tests/gst_helpers.php');

use local_sentientia_cart\cart_manager;

/**
 * Decision A5 (2026-11-12): cart_manager::refund() triggers refund_processed, the event
 * \local_sentientia_platform\audit_log::SENSITIVE_EVENTS lists, once per refund and only after it has committed.
 *
 * Each test drives the real refund() with the event sink redirected, so what is asserted is what the standard logstore
 * would have been handed.
 *
 * @package    local_sentientia_cart
 * @category   test
 * @copyright  2026 Airpay Payment Services
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \local_sentientia_cart\event\refund_processed
 * @covers     \local_sentientia_cart\cart_manager::refund
 *
 * @group local_sentientia_cart
 */
final class refund_processed_test extends \advanced_testcase {

    use \local_sentientia_cart\gst_helpers;

    protected function setUp(): void {
        parent::setUp();
        $this->gst_setup();
    }

    /**
     * Run $fn with events and messages redirected and return the refund_processed events it triggered.
     *
     * @param callable $fn
     * @return refund_processed[]
     */
    private function refund_events(callable $fn): array {
        $messages = $this->redirectMessages();
        $sink = $this->redirectEvents();
        try {
            $fn();
        } finally {
            $events = $sink->get_events();
            $sink->close();
            $messages->close();
        }
        return array_values(array_filter($events, fn($e) => $e instanceof refund_processed));
    }

    public function test_a_partial_refund_triggers_refund_processed_once(): void {
        global $DB;
        $buyer = $this->user_at('/77');
        $order = $this->paid_order_for($buyer);
        $admin = (int) get_admin()->id;

        $events = $this->refund_events(fn() => $this->assertTrue(
            cart_manager::refund((int) $order->id, 590.00, 'Customer asked by phone', $admin)));

        $this->assertCount(1, $events, 'one refund, one audit event');
        $event = $events[0];
        $ledger = $DB->get_record('local_sentientia_cart_ledger',
            ['historyid' => $order->id, 'event_type' => 'refund_partial'], '*', MUST_EXIST);

        $this->assertSame((int) $order->id, (int) $event->objectid);
        $this->assertSame('local_sentientia_cart_history', $event->objecttable);
        $this->assertSame((int) $buyer->id, (int) $event->relateduserid);
        $this->assertSame($admin, (int) $event->userid, 'the user the ledger records as having initiated it');
        $this->assertSame(\context_system::instance()->id, (int) $event->contextid);
        $this->assertSame('u', $event->crud);
        $this->assertSame(\core\event\base::LEVEL_OTHER, (int) $event->edulevel);
        $this->assertSame((int) $ledger->id, $event->other['ledgerid']);
        $this->assertSame((int) $order->orderid, $event->other['orderid']);
        $this->assertSame(59000, $event->other['amount'], '590.00 INR in paise, positive');
        $this->assertSame('INR', $event->other['currency']);
        $this->assertSame('partial_refund', $event->other['status']);
        $this->assertSame('partial_refund', $this->order((int) $order->id)->status, 'the event follows the stored refund');

        // Ids and codes only: neither the free-text reason nor the buyer's billing details reach the log.
        $data = json_encode($event->get_data());
        foreach (['Customer asked by phone', 'Test Buyer', 'buyer@example.com', '9999999999'] as $personal) {
            $this->assertStringNotContainsString($personal, $data);
        }
        $this->assertSame(['ledgerid', 'orderid', 'amount', 'currency', 'status'], array_keys($event->other));
        $this->assertStringContainsString("'{$order->id}'", $event->get_description());
        $this->assertSame('Refund processed', refund_processed::get_name());
        $this->assertSame('/local/sentientia_cart/admin_orders.php', $event->get_url()->out_as_local_url(false));
        $this->assertEventContextNotUsed($event);
        $this->assertDebuggingNotCalled();
    }

    public function test_a_full_refund_reports_the_whole_amount_and_the_refunded_status(): void {
        $order = $this->paid_order_for($this->user_at('/77'));

        $events = $this->refund_events(fn() => $this->assertTrue(
            cart_manager::refund((int) $order->id, 0, 'All of it', (int) get_admin()->id)));

        $this->assertCount(1, $events);
        $this->assertSame(118000, $events[0]->other['amount'], '0 means the whole remaining balance, 1180.00');
        $this->assertSame('refunded', $events[0]->other['status']);
    }

    public function test_each_refund_is_its_own_event(): void {
        $order = $this->paid_order_for($this->user_at('/77'));
        $admin = (int) get_admin()->id;

        $events = $this->refund_events(function() use ($order, $admin): void {
            cart_manager::refund((int) $order->id, 300.00, 'Part 1', $admin);
            cart_manager::refund((int) $order->id, 0, 'The rest', $admin);
        });

        $this->assertCount(2, $events);
        $this->assertNotSame($events[0]->other['ledgerid'], $events[1]->other['ledgerid']);
        $this->assertSame(30000, $events[0]->other['amount']);
        $this->assertSame(88000, $events[1]->other['amount'], 'the remaining 880.00');
        $this->assertSame('partial_refund', $events[1]->other['status'], 'a second refund is never called full');
    }

    public function test_a_refused_refund_triggers_nothing(): void {
        global $DB;
        $admin = (int) get_admin()->id;
        $pending = $this->order_for($this->user_at('/77'));
        $paid = $this->paid_order_for($this->user_at('/77'));
        $imported = $this->paid_order_for($this->user_at('/77'), ['legacy_source' => 'bizlms']);

        $events = $this->refund_events(function() use ($pending, $paid, $imported, $admin): void {
            $this->assert_refused(fn() => cart_manager::refund((int) $pending->id, 0, 'x', $admin),
                'error_invalidstate', 'an unpaid order cannot be refunded');
            $this->assert_refused(fn() => cart_manager::refund((int) $paid->id, 5000.00, 'x', $admin),
                'error_invalidstate', 'more than the refundable balance');
            $this->assert_refused(fn() => cart_manager::refund((int) $imported->id, 0, 'x', $admin),
                'error_invalidstate', 'ADR-032: an imported order is frozen history');
        });

        $this->assertSame([], $events);
        [$insql, $params] = $DB->get_in_or_equal([(int) $pending->id, (int) $paid->id, (int) $imported->id]);
        $this->assertSame(0, $DB->count_records_select('local_sentientia_cart_ledger', "historyid $insql", $params),
            'nothing was written, so nothing is logged');
    }
}
