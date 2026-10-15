<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

namespace local_sentientia_cart;

defined('MOODLE_INTERNAL') || die();

require_once(__DIR__ . '/gst_helpers.php');

use core_external\external_api;
use local_sentientia_cart\external\list_orders;

/**
 * The order lists (history.php for the buyer, admin_orders.php for the administrator) as the visual pass of
 * 2026-10-14 found them wrong, and the Refund button they were missing (UI pass 2026-10-15).
 *
 * Held here:
 *  - the Refund button: list_orders returns it (escaped HTML, the attributes amd/src/admin_orders.js reads) for the
 *    rows that can be refunded and only to a holder of :refund; the admin page has a column that paints it;
 *  - the Placed column is the payment date, and sorts by it;
 *  - the Status column is the status in words, and the Total column carries the currency symbol.
 *
 * @package    local_sentientia_cart
 * @category   test
 * @covers     \local_sentientia_cart\external\list_orders
 *
 * @group local_sentientia_cart
 * @group tenant_isolation
 */
final class order_list_ui_test extends \advanced_testcase {

    use gst_helpers;

    protected function setUp(): void {
        parent::setUp();
        $this->gst_setup();
    }

    // ── helpers ──────────────────────────────────────────────────────────

    /**
     * What the list service returns to the current user, after the return value passes the schema check.
     *
     * @param string $sort
     * @param string $dir
     * @return array[] The rows, in the order returned.
     */
    private function list_rows(string $sort = 'timecreated', string $dir = 'desc'): array {
        $result = external_api::clean_returnvalue(list_orders::execute_returns(),
            list_orders::execute('', $sort, $dir, 0, 100, '{}'));
        return $result['rows'];
    }

    /**
     * The rows keyed by history id.
     *
     * @param array[] $rows
     * @return array[]
     */
    private function by_id(array $rows): array {
        $keyed = [];
        foreach ($rows as $row) {
            $keyed[$row['id']] = $row;
        }
        return $keyed;
    }

    /**
     * An order administrator of a tenant (the manager role: :viewallorders) who also holds :refund.
     *
     * @param string $path
     * @return \stdClass
     */
    private function refunder(string $path): \stdClass {
        $u = $this->tenant_admin($path);
        $roleid = $this->getDataGenerator()->create_role(['shortname' => 'cartrefunder' . $u->id]);
        assign_capability('local/sentientia_cart:refund', CAP_ALLOW, $roleid, \context_system::instance()->id);
        role_assign($roleid, $u->id, \context_system::instance()->id);
        accesslib_clear_all_caches_for_unit_testing();
        return $u;
    }

    /**
     * Refund an order through the manager, as an administrator would.
     *
     * @param \stdClass $order
     * @param float $amount
     * @return void
     */
    private function refund(\stdClass $order, float $amount): void {
        $sink = $this->redirectMessages();
        $this->assertTrue(cart_manager::refund((int) $order->id, $amount, 'Test', (int) get_admin()->id));
        $sink->close();
    }

    // ── defect (a): the Refund button ────────────────────────────────────

    public function test_a_refunder_is_sent_a_refund_button_on_the_rows_that_can_be_refunded(): void {
        $buyer = $this->user_at('/77');
        $paid = $this->paid_order_for($buyer);
        $part = $this->paid_order_for($buyer);
        $this->refund($part, 500.00);
        $pending = $this->order_for($buyer);
        $failed = $this->order_for($buyer, ['status' => 'failed']);
        $refunded = $this->paid_order_for($buyer);
        $this->refund($refunded, 1180.00);
        $this->assertSame('refunded', $this->order((int) $refunded->id)->status);
        $this->assertSame('partial_refund', $this->order((int) $part->id)->status);

        $this->setUser($this->refunder('/77'));
        $rows = $this->by_id($this->list_rows());

        foreach ([$paid, $part] as $order) {
            $actions = $rows[(int) $order->id]['actions'];
            $this->assertStringContainsString('data-action="refund-order"', $actions);
            $this->assertStringContainsString('data-historyid="' . $order->id . '"', $actions);
            $this->assertStringContainsString('data-orderid="' . $order->orderid . '"', $actions);
            $this->assertStringContainsString('data-total="₹1,180.00"', $actions, 'the dialog shows the order total');
            $this->assertStringContainsString('type="button"', $actions);
        }
        foreach ([$pending, $failed, $refunded] as $order) {
            $this->assertSame('', $rows[(int) $order->id]['actions'],
                'a ' . $order->status . ' order has nothing to refund');
        }
    }

    public function test_a_site_administrator_is_sent_the_button_too(): void {
        $order = $this->paid_order_for($this->user_at('/77'));
        $this->setAdminUser();
        $rows = $this->by_id($this->list_rows());
        $this->assertStringContainsString('data-action="refund-order"', $rows[(int) $order->id]['actions']);
    }

    public function test_nobody_without_the_refund_capability_is_sent_a_button(): void {
        $buyer = $this->user_at('/77');
        $order = $this->paid_order_for($buyer);

        // The tenant administrator (manager role) holds :viewallorders but not :refund: it has no archetype.
        $this->setUser($this->tenant_admin('/77'));
        $rows = $this->by_id($this->list_rows());
        $this->assertArrayHasKey((int) $order->id, $rows, 'the administrator does see the order');
        $this->assertSame('', $rows[(int) $order->id]['actions']);

        // The buyer sees their own paid order, and no button.
        $this->setUser($buyer);
        $rows = $this->by_id($this->list_rows());
        $this->assertArrayHasKey((int) $order->id, $rows);
        $this->assertSame('', $rows[(int) $order->id]['actions']);
    }

    public function test_a_refunder_is_sent_buttons_for_their_own_tenant_only(): void {
        $mine = $this->paid_order_for($this->user_at('/77'));
        $other = $this->paid_order_for($this->user_at('/1'));

        $this->setUser($this->refunder('/77'));
        $rows = $this->by_id($this->list_rows());
        $this->assertArrayHasKey((int) $mine->id, $rows);
        $this->assertArrayNotHasKey((int) $other->id, $rows, "another tenant's order is not even listed");
    }

    public function test_no_refund_button_when_nothing_is_left_to_refund(): void {
        // Two refunds that together take the whole total leave the status at partial_refund
        // (cart_manager::refund() calls only a first, whole refund "full"), with no balance left.
        $order = $this->paid_order_for($this->user_at('/77'));
        $this->refund($order, 590.00);
        $this->refund($order, 590.00);
        $this->assertSame('partial_refund', $this->order((int) $order->id)->status);

        $this->setAdminUser();
        $rows = $this->by_id($this->list_rows());
        $this->assertSame('', $rows[(int) $order->id]['actions'], 'a refund dialog here could only record a zero refund');
    }

    public function test_refund_button_is_refused_for_an_imported_order_and_escapes_its_attributes(): void {
        $paid = (object) ['id' => 12, 'orderid' => 345, 'status' => 'paid', 'total_amount' => 1180.00,
            'legacy_source' => null];
        $imported = (object) ['id' => 13, 'orderid' => 346, 'status' => 'paid', 'total_amount' => 1180.00,
            'legacy_source' => 'bizlms'];

        $this->assertSame('', list_orders::refund_button($imported, 0.0, '₹1,180.00'),
            'refund() refuses imported money; so does the list');
        $this->assertSame('', list_orders::refund_button($paid, 1180.00, '₹1,180.00'), 'fully refunded');
        $this->assertSame('', list_orders::refund_button($paid, 1180.004, '₹1,180.00'), 'a rounding trace is nothing left');
        $this->assertNotSame('', list_orders::refund_button($paid, 1179.00, '₹1,180.00'), 'a rupee left');

        $html = list_orders::refund_button($paid, 0.0, '₹1"><script>alert(1)</script>');
        $this->assertStringNotContainsString('<script>', $html, 'the total is an attribute value: escaped');
        $this->assertStringContainsString('&lt;script&gt;', $html);
        $this->assertStringContainsString(get_string('refund_button', 'local_sentientia_cart'), $html);
    }

    public function test_refunded_amounts_sums_the_ledger_for_the_refundable_rows_only(): void {
        $buyer = $this->user_at('/77');
        $a = $this->paid_order_for($buyer);
        $this->refund($a, 300.00);
        $this->refund($a, 200.00);
        $b = $this->paid_order_for($buyer);
        $c = $this->order_for($buyer);

        $sums = list_orders::refunded_amounts([
            (int) $a->id => $this->order((int) $a->id),
            (int) $b->id => $this->order((int) $b->id),
            (int) $c->id => $this->order((int) $c->id),
        ]);
        $this->assertSame([(int) $a->id => 500.0], $sums, 'only the refunded order has an entry');
        $this->assertSame([], list_orders::refunded_amounts([]));
    }

    /**
     * The three places that have to agree for the button to work: the markup the service builds, the dialog script
     * that reads it, and the page whose column paints it. The defect was that the script looked for an element that
     * no page ever rendered, so each end is pinned to the other here.
     */
    public function test_the_button_the_service_builds_is_the_one_the_dialog_script_reads_and_the_page_paints(): void {
        global $CFG;
        $dir = $CFG->dirroot . '/local/sentientia_cart';

        $js = file_get_contents($dir . '/amd/src/admin_orders.js');
        $this->assertStringContainsString("closest('[data-action=\"refund-order\"]')", $js);
        foreach (['historyid', 'total', 'orderid'] as $attribute) {
            $this->assertStringContainsString('btn.dataset.' . $attribute, $js, "the script reads data-$attribute");
        }
        $html = list_orders::refund_button(
            (object) ['id' => 1, 'orderid' => 2, 'status' => 'paid', 'total_amount' => 10.0, 'legacy_source' => null],
            0.0, '₹10.00');
        foreach (['data-action="refund-order"', 'data-historyid="1"', 'data-orderid="2"', 'data-total="₹10.00"'] as $attr) {
            $this->assertStringContainsString($attr, $html);
        }

        // The page: an 'actions' column that paints HTML, added only for a holder of :refund.
        $page = file_get_contents($dir . '/admin_orders.php');
        $this->assertMatchesRegularExpression(
            "/if \\(has_capability\\('local\\/sentientia_cart:refund', \\\$ctx\\)\\) \\{\\s*\\\$columns\\[\\] = \\['key' => 'actions'[^;]*'format' => 'html'\\];/",
            $page, 'admin_orders.php paints the actions column for the holders of :refund');
        $this->assertStringNotContainsString("'key' => 'actions'", file_get_contents($dir . '/history.php'),
            "the buyer's history has no refund column");
    }

    // ── defect (b): Placed, Status, Total ────────────────────────────────

    public function test_placed_is_the_payment_date_not_the_day_the_cart_was_opened(): void {
        $buyer = $this->user_at('/77');
        $opened = $this->ist('2026-09-01 10:00');
        $paidat = $this->ist('2026-09-20 15:30');
        $paid = $this->paid_order_for($buyer, ['timecreated' => $opened, 'timepaid' => $paidat]);
        $failed = $this->order_for($buyer, ['status' => 'failed', 'timecreated' => $opened]);

        $this->setUser($buyer);
        $rows = $this->by_id($this->list_rows());
        $this->assertSame(userdate($paidat, '%d %b %Y'), $rows[(int) $paid->id]['placed_on']);
        $this->assertSame($rows[(int) $paid->id]['paid_on'], $rows[(int) $paid->id]['placed_on']);
        $this->assertNotSame(userdate($opened, '%d %b %Y'), $rows[(int) $paid->id]['placed_on']);
        // An order that was never paid has no payment time: it keeps the time its cart was opened.
        $this->assertSame(userdate($opened, '%d %b %Y'), $rows[(int) $failed->id]['placed_on']);
        $this->assertSame('', $rows[(int) $failed->id]['paid_on']);
    }

    public function test_a_click_on_the_placed_header_sorts_by_the_date_it_shows(): void {
        $buyer = $this->user_at('/77');
        // Opened first, paid last.
        $a = $this->paid_order_for($buyer, ['timecreated' => $this->ist('2026-09-01 10:00'),
            'timepaid' => $this->ist('2026-09-25 10:00')]);
        // Opened second, paid first.
        $b = $this->paid_order_for($buyer, ['timecreated' => $this->ist('2026-09-10 10:00'),
            'timepaid' => $this->ist('2026-09-15 10:00')]);
        // Never paid: placed = the day the cart was opened, between the two payments.
        $c = $this->order_for($buyer, ['timecreated' => $this->ist('2026-09-20 10:00')]);

        $this->setUser($buyer);
        $ids = fn(array $rows): array => array_map(fn($r) => $r['id'], $rows);
        $this->assertSame([(int) $a->id, (int) $c->id, (int) $b->id], $ids($this->list_rows('placed', 'desc')),
            'sorted by the payment date, else the day the cart was opened');
        $this->assertSame([(int) $b->id, (int) $c->id, (int) $a->id], $ids($this->list_rows('placed', 'asc')));
        // The old key still works and still means the day the cart was opened.
        $this->assertSame([(int) $c->id, (int) $b->id, (int) $a->id], $ids($this->list_rows('timecreated', 'desc')));
    }

    public function test_status_is_shown_in_words_not_as_the_raw_key(): void {
        $buyer = $this->user_at('/77');
        $expected = [
            'pending'        => 'Pending',
            'paid'           => 'Paid',
            'failed'         => 'Failed',
            'refunded'       => 'Refunded',
            'partial_refund' => 'Partial refund',
            'cancelled'      => 'Cancelled',
            'abandoned'      => 'Abandoned',
            'part_cancelled' => 'Partly cancelled',
        ];
        $orders = [];
        foreach (array_keys($expected) as $status) {
            $orders[$status] = $this->order_for($buyer, ['status' => $status]);
        }

        $this->setAdminUser();
        $rows = $this->by_id($this->list_rows());
        foreach ($expected as $status => $label) {
            $row = $rows[(int) $orders[$status]->id];
            $this->assertSame($status, $row['status'], 'the key is still returned');
            $this->assertSame($label, $row['status_label']);
            $this->assertSame(get_string('status_' . $status, 'local_sentientia_cart'), $row['status_label']);
        }
        $this->assertSame('mystery', list_orders::status_label('mystery'), 'a status with no string is shown as it is');
    }

    public function test_total_carries_the_currency_symbol_formatted_as_the_documents_are(): void {
        $buyer = $this->user_at('/77');
        $order = $this->paid_order_for($buyer);

        $this->setUser($buyer);
        $rows = $this->by_id($this->list_rows());
        $this->assertSame('₹1,180.00', $rows[(int) $order->id]['total_str']);
        $this->assertSame(1180.0, $rows[(int) $order->id]['total_amount'], 'the number is still returned, for sorting');

        $label = fn(float $amount, string $currency): string => list_orders::total_label(
            (object) ['total_amount' => $amount, 'currency' => $currency]);
        $this->assertSame('₹1,23,456.50', $label(123456.5, 'INR'), 'rupees group the Indian way, as on the receipt');
        $this->assertSame('$99.50', $label(99.5, 'USD'));
        $this->assertSame('€1,000.00', $label(1000, 'EUR'));
        $this->assertSame('AED 10.00', $label(10, 'AED'), 'a currency without a symbol is named');
    }

    public function test_placed_time_is_the_payment_time_else_the_time_the_cart_was_opened(): void {
        $this->assertSame(200, list_orders::placed_time((object) ['timecreated' => 100, 'timepaid' => 200]));
        $this->assertSame(100, list_orders::placed_time((object) ['timecreated' => 100, 'timepaid' => null]));
        $this->assertSame(100, list_orders::placed_time((object) ['timecreated' => 100, 'timepaid' => 0]));
    }

    public function test_the_list_pages_read_the_formatted_keys_and_sort_by_the_right_columns(): void {
        global $CFG;
        $dir = $CFG->dirroot . '/local/sentientia_cart';
        foreach (['history.php', 'admin_orders.php'] as $file) {
            $page = file_get_contents($dir . '/' . $file);
            $this->assertStringContainsString("['key' => 'placed_on',", $page);
            $this->assertMatchesRegularExpression("/'key' => 'placed_on',[^\\]]*'sortkey' => 'placed'\\]/", $page, $file);
            $this->assertMatchesRegularExpression("/'key' => 'total_str',[^\\]]*'sortkey' => 'total_amount'\\]/", $page, $file);
            $this->assertMatchesRegularExpression("/'key' => 'status_label',[^\\]]*'sortkey' => 'status'\\]/", $page, $file);
            $this->assertStringNotContainsString("['key' => 'total_amount',", $page, "$file shows the bare number");
            $this->assertStringNotContainsString("['key' => 'status',", $page, "$file shows the raw key");
        }
    }
}
