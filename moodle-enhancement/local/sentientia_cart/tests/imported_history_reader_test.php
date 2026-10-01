<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

namespace local_sentientia_cart;

defined('MOODLE_INTERNAL') || die();

use core_external\external_api;
use local_sentientia_cart\external\list_orders;
use local_sentientia_org\test\bizlms_fixture;
use local_sentientia_platform\feature_flags;

/**
 * What the cart shows of the history the BizLMS importer wrote (ADR-032, mapping doc section 13, code fixes 1 to 6).
 *
 * Imported money history is frozen and admin-only (decisions cart.imported_visibility, cart.admin_refund_imported_orders):
 *  - the OWNER of an imported order never sees it, whatever the flag says, and never sees an abandoned checkout;
 *  - an order administrator sees it only while sentientia.cart.imported_orders.enabled is ON, and ADR-031 still
 *    holds: a scoped tenant admin reads only their own tenant, and what has no tenant (costcenterid 0, a ledger row
 *    with historyid 0) is for cross-tenant callers;
 *  - the daily sums leave the import out while the flag is OFF, and count it as BizLMS counted it while it is ON;
 *  - an imported invoice reference is admin-only too, and nothing can refund an imported order.
 *
 * The rows are inserted directly; the importer that writes them is tested in bizlms_import_test.
 *
 * @package    local_sentientia_cart
 * @category   test
 * @covers     \local_sentientia_cart\imported_history
 * @covers     \local_sentientia_cart\cart_manager
 * @covers     \local_sentientia_cart\invoicer
 * @covers     \local_sentientia_cart\external\list_orders
 * @covers     \local_sentientia_cart\external\get_order
 * @covers     \local_sentientia_cart\external\refund_order
 *
 * @group local_sentientia_cart
 * @group bizlms_import
 * @group tenant_isolation
 */
final class imported_history_reader_test extends \advanced_testcase {

    use bizlms_fixture;

    protected function setUp(): void {
        parent::setUp();
        $this->resetAfterTest();
        $this->ensure_bizlms_schema();
    }

    protected function tearDown(): void {
        try {
            feature_flags::set(imported_history::FLAG_ORDERS, 0, null);
            feature_flags::set(imported_history::FLAG_CREDITS, 0, null);
        } catch (\Throwable $e) {
            // The database is reset after the test anyway; a flag left behind cannot outlive it.
            debugging('could not unset the test flags: ' . $e->getMessage(), DEBUG_DEVELOPER);
        }
        parent::tearDown();
    }

    /**
     * @param string $path
     * @return \stdClass A user at a tenant path holding the manager role at system context (a tenant admin).
     */
    private function tenant_admin(string $path): \stdClass {
        global $DB;
        $u = $this->getDataGenerator()->create_user();
        $DB->set_field('user', 'open_path', $path, ['id' => $u->id]);
        $managerid = (int) $DB->get_field('role', 'id', ['shortname' => 'manager'], MUST_EXIST);
        role_assign($managerid, $u->id, \context_system::instance()->id);
        accesslib_clear_all_caches_for_unit_testing();
        return $DB->get_record('user', ['id' => $u->id], '*', MUST_EXIST);
    }

    /**
     * @param string $path
     * @return \stdClass A plain user at a tenant path.
     */
    private function user_at(string $path): \stdClass {
        global $DB;
        $u = $this->getDataGenerator()->create_user();
        $DB->set_field('user', 'open_path', $path, ['id' => $u->id]);
        return $DB->get_record('user', ['id' => $u->id], '*', MUST_EXIST);
    }

    /**
     * An order Sentientia itself wrote.
     *
     * @param int $userid
     * @param int $tenant
     * @param array $more
     * @return int History id.
     */
    private function native(int $userid, int $tenant, array $more = []): int {
        global $DB;
        return (int) $DB->insert_record('local_sentientia_cart_history', (object) ($more + [
            'userid' => $userid, 'costcenterid' => $tenant, 'status' => 'paid', 'total_amount' => 100,
            'currency' => 'INR', 'billing_name' => 'Native buyer', 'billing_email' => 'native@example.com',
            'orderid' => random_int(1, 900000), 'timecreated' => time(), 'timemodified' => time(),
        ]));
    }

    /**
     * An order the importer would have written: no billing details, legacy_source set.
     *
     * @param int $userid
     * @param int $tenant
     * @param array $more
     * @return int History id.
     */
    private function imported(int $userid, int $tenant, array $more = []): int {
        global $DB;
        return (int) $DB->insert_record('local_sentientia_cart_history', (object) ($more + [
            'userid' => $userid, 'costcenterid' => $tenant, 'status' => 'paid', 'total_amount' => 200,
            'currency' => 'INR', 'orderid' => 1000000 + random_int(1, 90000), 'gateway' => 'airpay',
            'legacy_source' => 'bizlms', 'timecreated' => 1700000000, 'timemodified' => 1700000000,
        ]));
    }

    /**
     * A ledger row.
     *
     * @param int $historyid
     * @param string $type
     * @param float $amount
     * @param string $gateway
     * @return void
     */
    private function ledger(int $historyid, string $type, float $amount, string $gateway = 'airpay'): void {
        global $DB;
        $DB->insert_record('local_sentientia_cart_ledger', (object) [
            'historyid' => $historyid, 'orderid' => 0, 'event_type' => $type, 'amount' => $amount, 'currency' => 'INR',
            'gateway' => $gateway, 'timecreated' => time(),
        ]);
    }

    /**
     * @param bool $orders sentientia.cart.imported_orders.enabled
     * @param bool $credits sentientia.cart.imported_credits.enabled
     * @return void
     */
    private function flags(bool $orders, bool $credits = false): void {
        feature_flags::set(imported_history::FLAG_ORDERS, 0, $orders);
        feature_flags::set(imported_history::FLAG_CREDITS, 0, $credits);
    }

    /**
     * The history ids list_orders returns to the current user, after the return value passes the schema check.
     *
     * @return int[]
     */
    private function listed(): array {
        $result = list_orders::execute('', 'timecreated', 'desc', 0, 200, '{}');
        $result = external_api::clean_returnvalue(list_orders::execute_returns(), $result);
        $ids = array_map(static fn($r) => (int) $r['id'], $result['rows']);
        sort($ids);
        return $ids;
    }

    /**
     * @return float Inflow across the rows daily_sums() returns for today.
     */
    private function inflow(): float {
        return array_sum(array_map(static fn($r) => (float) $r->inflow,
            cart_manager::daily_sums(strtotime('today'), strtotime('tomorrow') - 1)));
    }

    /**
     * @return float Outflow (negative) across the rows daily_sums() returns for today.
     */
    private function outflow(): float {
        return array_sum(array_map(static fn($r) => (float) $r->outflow,
            cart_manager::daily_sums(strtotime('today'), strtotime('tomorrow') - 1)));
    }

    // The flags.

    public function test_the_reader_flags_ship_off_and_are_registered(): void {
        $registry = feature_flags::load_registry();
        foreach ([imported_history::FLAG_ORDERS, imported_history::FLAG_CREDITS] as $key) {
            $this->assertArrayHasKey($key, $registry);
            $this->assertFalse($registry[$key]['default'], "{$key} defaults to OFF");
        }
        $this->assertFalse(imported_history::orders_enabled());
        $this->assertFalse(imported_history::credits_enabled());
        $this->flags(true, true);
        $this->assertTrue(imported_history::orders_enabled());
        $this->assertTrue(imported_history::credits_enabled());
    }

    // The order list.

    public function test_the_owner_of_an_imported_order_never_sees_it(): void {
        $owner = $this->user_at('/1/2');
        $native = $this->native($owner->id, 1);
        $imported = $this->imported($owner->id, 1);
        $abandoned = $this->imported($owner->id, 1, ['status' => 'abandoned']);

        $this->setUser($owner);
        $this->assertSame([$native], $this->listed(), 'flag OFF: only the order Sentientia wrote');
        $this->flags(true);
        $this->assertSame([$native], $this->listed(), 'flag ON: the owner still sees none of the import');
        $this->assertNotContains($imported, $this->listed());
        $this->assertNotContains($abandoned, $this->listed());
    }

    public function test_an_order_administrator_sees_the_import_only_while_the_flag_is_on_and_only_in_their_tenant(): void {
        $buyer1 = $this->user_at('/1/2');
        $buyer177 = $this->user_at('/177/5');
        $nopath = $this->user_at('/999');
        $native = $this->native($buyer1->id, 1);
        $import1 = $this->imported($buyer1->id, 1);
        $import177 = $this->imported($buyer177->id, 177);
        $importnone = $this->imported($nopath->id, 0);

        $admin1 = $this->tenant_admin('/1');
        $this->setUser($admin1);
        $this->assertSame([$native], $this->listed(), 'flag OFF: an administrator lists what Sentientia wrote');

        $this->flags(true);
        $this->assertSame([$native, $import1], $this->listed(), 'flag ON: the imported order of their own tenant');
        $this->assertNotContains($import177, $this->listed(), 'never another tenant');
        $this->assertNotContains($importnone, $this->listed(), 'a row with no tenant is not a scoped admin\'s');

        $this->setAdminUser();
        $this->assertSame([$native, $import1, $import177, $importnone], $this->listed(),
            'a cross-tenant administrator sees every imported order, the one with no tenant included');
    }

    public function test_an_imported_order_shows_its_buyer_when_it_has_no_billing_details(): void {
        $buyer = $this->user_at('/1/2');
        $this->imported($buyer->id, 1);
        $this->flags(true);
        $this->setUser($this->tenant_admin('/1'));
        $result = list_orders::execute('', 'timecreated', 'desc', 0, 50, '{}');
        $this->assertCount(1, $result['rows']);
        $this->assertSame(fullname($buyer), $result['rows'][0]['billing_name']);
        $this->assertSame($buyer->email, $result['rows'][0]['billing_email']);

        // And an administrator finds it by the buyer's email.
        $found = list_orders::execute($buyer->email, 'timecreated', 'desc', 0, 50, '{}');
        $this->assertCount(1, $found['rows']);
        $none = list_orders::execute('nobody-by-this-name', 'timecreated', 'desc', 0, 50, '{}');
        $this->assertCount(0, $none['rows']);
    }

    // The order detail.

    public function test_get_order_refuses_an_imported_order_to_its_owner_and_to_an_administrator_with_the_flag_off(): void {
        $buyer = $this->user_at('/1/2');
        $historyid = $this->imported($buyer->id, 1);
        $admin = $this->tenant_admin('/1');
        $other = $this->tenant_admin('/177');

        $this->assert_order_refused($historyid, (int) $buyer->id);
        $this->assert_order_refused($historyid, (int) $admin->id);

        $this->flags(true);
        $this->assert_order_refused($historyid, (int) $buyer->id);
        $this->assert_order_refused($historyid, (int) $other->id);
        $this->assertSame($historyid, (int) cart_manager::get_order($historyid, (int) $admin->id)->id);
        $this->assertSame($historyid, (int) cart_manager::get_order($historyid, (int) get_admin()->id)->id);
    }

    /**
     * get_order() refuses the viewer with the one error a row of another tenant gets.
     *
     * @param int $historyid
     * @param int $viewerid
     * @return void
     */
    private function assert_order_refused(int $historyid, int $viewerid): void {
        try {
            cart_manager::get_order($historyid, $viewerid);
            $this->fail('the imported order must be refused');
        } catch (\moodle_exception $e) {
            $this->assertSame('error_outoftenant', $e->errorcode);
        }
    }

    public function test_a_native_order_is_unchanged(): void {
        $buyer = $this->user_at('/1/2');
        $historyid = $this->native($buyer->id, 1);
        $this->assertSame($historyid, (int) cart_manager::get_order($historyid, (int) $buyer->id)->id,
            'its owner still reads it');
        $this->assertSame($historyid, (int) cart_manager::get_order($historyid, (int) $this->tenant_admin('/1')->id)->id);
    }

    // The engine refuses to act on the import.

    public function test_the_engine_and_the_refund_web_service_refuse_an_imported_order(): void {
        global $DB;
        $buyer = $this->user_at('/1/2');
        $historyid = $this->imported($buyer->id, 1, ['status' => 'paid']);
        $order = $DB->get_record('local_sentientia_cart_history', ['id' => $historyid], '*', MUST_EXIST);
        $this->setAdminUser();
        foreach ([
            'refund' => static fn() => cart_manager::refund($historyid, 0.0, 'test', 2),
            'mark_paid' => static fn() => cart_manager::mark_paid($historyid, 'ref'),
            'mark_failed' => static fn() => cart_manager::mark_failed($historyid, 'no'),
            'invoice' => static fn() => invoicer::issue_for_order($order),
        ] as $what => $call) {
            try {
                $call();
                $this->fail("{$what} must refuse an imported order");
            } catch (\moodle_exception $e) {
                $this->assertSame('error_invalidstate', $e->errorcode, $what);
            }
        }
        // The web service goes through the same engine.
        try {
            \local_sentientia_cart\external\refund_order::execute($historyid, 0.0, 'test');
            $this->fail('refund_order must refuse an imported order');
        } catch (\moodle_exception $e) {
            $this->assertSame('error_invalidstate', $e->errorcode);
        }
        $this->assertSame('paid', $DB->get_field('local_sentientia_cart_history', 'status', ['id' => $historyid]));
    }

    // The daily sums.

    public function test_the_daily_sums_leave_the_import_out_until_the_flag_is_on(): void {
        $buyer1 = $this->user_at('/1/2');
        $buyer177 = $this->user_at('/177/5');
        $native = $this->native($buyer1->id, 1);
        $this->ledger($native, 'payment_received', 100.00, 'airpay');
        $this->ledger($native, 'a_type_nobody_knows', 999.00, 'airpay');

        $import1 = $this->imported($buyer1->id, 1);
        $this->ledger($import1, 'payment_received', 200.00, 'cashier');
        $this->ledger($import1, 'payment_received', 50.00, 'credits');
        $this->ledger($import1, 'legacy_cancel_to_credit', 60.00, 'online');
        $this->ledger($import1, 'legacy_credit_redeemed', -10.00, 'credits');
        $import177 = $this->imported($buyer177->id, 177);
        $this->ledger($import177, 'payment_received', 1000.00, 'airpay');
        // Booked outside any order: no tenant.
        $this->ledger(0, 'legacy_cash_drawer', 500.00, 'cashier_cash');
        $this->ledger(0, 'legacy_cash_drawer', -100.00, 'cashier_cash');
        $this->ledger(0, 'legacy_credit_payout', -30.00, 'credits_payback_cash');
        $this->ledger(0, 'legacy_sale_without_order', 40.00, 'cashier');
        $this->ledger(0, 'legacy_sale_without_order', 15.00, 'credits');

        $this->setAdminUser();
        $this->assertEqualsWithDelta(100.00, $this->inflow(), 0.001, 'flag OFF: only what Sentientia recorded');
        $this->assertEqualsWithDelta(0.0, $this->outflow(), 0.001);

        $this->flags(true);
        $this->setUser($this->tenant_admin('/1'));
        $this->assertEqualsWithDelta(300.00, $this->inflow(), 0.001,
            'a scoped admin: 100 + 200; paid by credits, a cancellation and a redemption are not money in');
        $this->assertEqualsWithDelta(0.0, $this->outflow(), 0.001, 'the ledger rows with no order are not a scoped admin\'s');

        $this->setAdminUser();
        $this->assertEqualsWithDelta(1840.00, $this->inflow(),
            0.001, '100 + 200 + 1000 + the cash in of 500 + a sale of 40 that belongs to no order; the one paid by credits is not money in');
        $this->assertEqualsWithDelta(-130.00, $this->outflow(), 0.001, 'cash out of 100 and credits paid out of 30');

        // A caller with no tenant gets nothing.
        $this->setUser($this->user_at('/999'));
        $this->assertEqualsWithDelta(0.0, $this->inflow(), 0.001);
    }

    // The invoice reference.

    public function test_an_imported_invoice_reference_is_admin_only_and_behind_the_flag(): void {
        $buyer = $this->user_at('/1/2');
        $invoice = (object) ['id' => 1, 'userid' => $buyer->id, 'costcenterid' => 1, 'status' => 'legacy_external'];
        $admin = $this->tenant_admin('/1');
        $other = $this->tenant_admin('/177');

        foreach ([[$buyer->id, 'owner'], [$admin->id, 'flag off']] as [$viewer, $why]) {
            try {
                invoicer::require_view_access($invoice, (int) $viewer);
                $this->fail("a reference is refused to the {$why}");
            } catch (\moodle_exception $e) {
                $this->assertSame('error_outoftenant', $e->errorcode, $why);
            }
        }
        $this->flags(true);
        try {
            invoicer::require_view_access($invoice, (int) $buyer->id);
            $this->fail('the owner never sees an imported reference');
        } catch (\moodle_exception $e) {
            $this->assertSame('error_outoftenant', $e->errorcode);
        }
        try {
            invoicer::require_view_access($invoice, (int) $other->id);
            $this->fail('another tenant');
        } catch (\moodle_exception $e) {
            $this->assertSame('error_outoftenant', $e->errorcode);
        }
        invoicer::require_view_access($invoice, (int) $admin->id);
        $this->addToAssertionCount(1);
    }

    public function test_the_invoice_prefix_erpnext_is_reserved(): void {
        set_config('invoice_prefix', 'erpnext', 'local_sentientia_cart');
        $buyer = $this->user_at('/1/2');
        $cart = (object) ['id' => 5, 'orderid' => 5, 'userid' => $buyer->id, 'costcenterid' => 1, 'billing_name' => 'x',
            'billing_email' => '', 'billing_phone' => '', 'billing_address' => '', 'billing_gstn' => '', 'items_json' => '[]',
            'subtotal' => 100, 'discount_amount' => 0, 'total_amount' => 118, 'currency' => 'INR', 'timepaid' => time(),
            'legacy_source' => null];
        try {
            invoicer::issue_for_order($cart);
            $this->fail('ERPNEXT is the prefix of an imported invoice');
        } catch (\moodle_exception $e) {
            $this->assertSame('error_invalidstate', $e->errorcode);
        }
    }
}
