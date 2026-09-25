<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

namespace local_sentientia_cart;

defined('MOODLE_INTERNAL') || die();

/**
 * ADR-031: the cart's admin surfaces stay inside the caller's tenant.
 *
 * Tenant admins hold a manager-archetype role at system context, so they hold
 * :viewallorders and :manageprices. Those say WHAT they may do; until
 * 2026-09-25 four surfaces also let them decide WHERE:
 *
 *   - daily_sums_csv.php ran an unscoped copy of the daily-sums query;
 *   - invoice.php let any :viewallorders holder open any tenant's invoice;
 *   - set_price.php listed every tenant's courses and prices;
 *   - set_course_price checked the cap at course context, which a
 *     system-level grant satisfies for every course in every tenant.
 *
 * And tenant::require_access() matched a caller with no tenant against every
 * tenant-0 order. Each case is asserted three ways: a scoped tenant admin is
 * refused / sees nothing of tenant 177, a caller with no tenant gets nothing,
 * and the site admin still sees everything.
 *
 * @package    local_sentientia_cart
 * @category   test
 * @copyright  2026 Airpay Payment Services
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \local_sentientia_cart\cart_manager
 * @covers     \local_sentientia_cart\invoicer::require_view_access
 * @covers     \local_sentientia_cart\external\set_course_price
 * @group      tenant_isolation
 */
final class tenant_scope_test extends \advanced_testcase {

    use \local_sentientia_org\test\bizlms_fixture;

    protected function setUp(): void {
        parent::setUp();
        $this->resetAfterTest();
        $this->ensure_bizlms_schema();
    }

    /** A user with the given open_path. */
    private function user_at(string $path): \stdClass {
        global $DB;
        $u = $this->getDataGenerator()->create_user();
        $DB->set_field('user', 'open_path', $path, ['id' => $u->id]);
        return $DB->get_record('user', ['id' => $u->id], '*', MUST_EXIST);
    }

    /** A tenant admin as UAT has them: manager-archetype role at SYSTEM context. */
    private function tenant_admin_at(string $path): \stdClass {
        global $DB;
        $u = $this->user_at($path);
        $managerid = (int) $DB->get_field('role', 'id', ['shortname' => 'manager'], MUST_EXIST);
        role_assign($managerid, $u->id, \context_system::instance()->id);
        return $u;
    }

    /** A course with the given open_path ('' leaves it NULL). */
    private function course_at(string $path): \stdClass {
        global $DB;
        $c = $this->getDataGenerator()->create_course();
        if ($path !== '') {
            $DB->set_field('course', 'open_path', $path, ['id' => $c->id]);
        }
        return $DB->get_record('course', ['id' => $c->id], '*', MUST_EXIST);
    }

    /** A paid order of tenant $cc with one payment of $amount in the ledger. */
    private function paid_order(int $cc, float $amount): \stdClass {
        global $DB;
        $owner = $this->getDataGenerator()->create_user();
        $now = time();
        $historyid = $DB->insert_record('local_sentientia_cart_history', (object) [
            'userid' => $owner->id, 'costcenterid' => $cc, 'status' => 'paid',
            'total_amount' => $amount, 'currency' => 'INR',
            'timecreated' => $now, 'timemodified' => $now,
        ]);
        $DB->insert_record('local_sentientia_cart_ledger', (object) [
            'historyid' => $historyid, 'event_type' => 'payment_received',
            'amount' => $amount, 'currency' => 'INR', 'gateway' => 'airpay',
            'timecreated' => $now,
        ]);
        return $DB->get_record('local_sentientia_cart_history', ['id' => $historyid], '*', MUST_EXIST);
    }

    /** Total inflow across the rows cart_manager::daily_sums() returns for today. */
    private function inflow_today(): float {
        $rows = cart_manager::daily_sums(strtotime('today'), strtotime('tomorrow') - 1);
        return array_sum(array_map(fn($r) => (float) $r->inflow, $rows));
    }

    /** An invoice row (not persisted: require_view_access() only reads it). */
    private function invoice(int $cc, int $ownerid): \stdClass {
        return (object) ['id' => 1, 'userid' => $ownerid, 'costcenterid' => $cc];
    }

    private function assert_out_of_tenant(callable $fn, string $why): void {
        try {
            $fn();
            $this->fail($why);
        } catch (\moodle_exception $e) {
            $this->assertSame('error_outoftenant', $e->errorcode, $why);
        }
    }

    // ── daily_sums (the CSV export and the web service share it) ─────────

    public function test_daily_sums_are_scoped_to_the_tenant_admins_tenant(): void {
        $this->paid_order(1, 100.00);
        $this->paid_order(177, 5000.00);

        $this->setUser($this->tenant_admin_at('/1'));
        $this->assertEqualsWithDelta(100.00, $this->inflow_today(), 0.001,
            'A /1 tenant admin must not see tenant 177 payments in the daily sums.');
    }

    public function test_daily_sums_give_a_caller_with_no_tenant_nothing(): void {
        $this->paid_order(1, 100.00);
        $this->paid_order(0, 40.00);

        foreach (['', 'garbage'] as $path) {
            $this->setUser($this->tenant_admin_at($path));
            $this->assertSame([], cart_manager::daily_sums(strtotime('today'), strtotime('tomorrow') - 1),
                "open_path '{$path}' must not unlock any tenant's totals.");
        }
    }

    public function test_daily_sums_still_give_the_site_admin_every_tenant(): void {
        $this->paid_order(1, 100.00);
        $this->paid_order(177, 5000.00);

        $this->setAdminUser();
        $this->assertEqualsWithDelta(5100.00, $this->inflow_today(), 0.001);
    }

    // ── invoices ─────────────────────────────────────────────────────────

    public function test_a_tenant_admin_cannot_open_another_tenants_invoice(): void {
        $admin = $this->tenant_admin_at('/1');
        $other = $this->user_at('/177/5');
        $this->setUser($admin);

        invoicer::require_view_access($this->invoice(1, (int) $other->id), (int) $admin->id);
        $this->assert_out_of_tenant(
            fn() => invoicer::require_view_access($this->invoice(177, (int) $other->id), (int) $admin->id),
            'A /1 tenant admin must not open a tenant 177 invoice.');
    }

    public function test_a_caller_with_no_tenant_opens_only_their_own_invoice(): void {
        $nobody = $this->tenant_admin_at('');
        $other = $this->user_at('');
        $this->setUser($nobody);

        invoicer::require_view_access($this->invoice(0, (int) $nobody->id), (int) $nobody->id);
        $this->assert_out_of_tenant(
            fn() => invoicer::require_view_access($this->invoice(0, (int) $other->id), (int) $nobody->id),
            'Tenant 0 is nobody\'s tenant: a tenantless admin must not read other tenantless users\' invoices.');
    }

    public function test_the_site_admin_opens_any_invoice(): void {
        $this->setAdminUser();
        $other = $this->user_at('/177/5');
        invoicer::require_view_access($this->invoice(177, (int) $other->id), (int) get_admin()->id);
        $this->assertTrue(true);
    }

    // ── orders (get_order + refund share require_order_tenant) ───────────

    public function test_order_detail_is_refused_across_tenants_and_without_a_tenant(): void {
        $foreign = $this->paid_order(177, 10.00);
        $orphan = $this->paid_order(0, 10.00);

        $admin = $this->tenant_admin_at('/1');
        $this->setUser($admin);
        $this->assert_out_of_tenant(fn() => cart_manager::get_order((int) $foreign->id, (int) $admin->id),
            'A /1 tenant admin must not read a tenant 177 order.');

        $nobody = $this->tenant_admin_at('');
        $this->setUser($nobody);
        $this->assert_out_of_tenant(fn() => cart_manager::get_order((int) $orphan->id, (int) $nobody->id),
            'A tenantless admin used to match every tenant-0 order (0 === 0).');

        $this->setAdminUser();
        $this->assertSame((int) $foreign->id, (int) cart_manager::get_order((int) $foreign->id, (int) get_admin()->id)->id);
    }

    // ── course pricing ───────────────────────────────────────────────────

    public function test_the_pricing_list_shows_only_the_tenant_admins_courses(): void {
        $mine = $this->course_at('/1/4');
        $theirs = $this->course_at('/177');

        $this->setUser($this->tenant_admin_at('/1'));
        $ids = array_map('intval', array_keys(cart_manager::list_course_prices()));
        $this->assertContains((int) $mine->id, $ids);
        $this->assertNotContains((int) $theirs->id, $ids,
            'set_price.php must not list a tenant 177 course to a /1 tenant admin.');

        $this->setUser($this->tenant_admin_at(''));
        $this->assertSame([], cart_manager::list_course_prices(), 'No tenant, no courses.');

        $this->setAdminUser();
        $ids = array_map('intval', array_keys(cart_manager::list_course_prices()));
        $this->assertContains((int) $theirs->id, $ids);
    }

    public function test_a_tenant_admin_cannot_price_another_tenants_course(): void {
        $theirs = $this->course_at('/177');
        $this->setUser($this->tenant_admin_at('/1'));
        $this->assert_out_of_tenant(
            fn() => external\set_course_price::execute((int) $theirs->id, 0.0, 'INR'),
            'A system-level :manageprices grant is inherited by every course; the course must be in the caller\'s tenant.');
    }

    public function test_a_tenant_admin_can_still_price_their_own_course(): void {
        $mine = $this->course_at('/1/4');
        $this->setUser($this->tenant_admin_at('/1'));
        $r = external\set_course_price::execute((int) $mine->id, 0.0, 'INR');
        $this->assertTrue($r['success']);
    }

    public function test_courses_without_a_tenant_and_callers_without_a_tenant_are_refused(): void {
        $this->setUser($this->tenant_admin_at('/1'));
        $this->assert_out_of_tenant(fn() => cart_manager::require_course_in_tenant($this->course_at('')),
            'A course with no open_path cannot be shown to be in the caller\'s tenant.');

        $this->setUser($this->tenant_admin_at(''));
        $this->assert_out_of_tenant(fn() => cart_manager::require_course_in_tenant($this->course_at('/1')),
            'A caller with no tenant prices nothing.');
    }

    public function test_the_site_admin_can_price_any_course(): void {
        $theirs = $this->course_at('/177');
        $this->setAdminUser();
        $r = external\set_course_price::execute((int) $theirs->id, 0.0, 'INR');
        $this->assertTrue($r['success']);
        cart_manager::require_course_in_tenant($this->course_at(''));
    }
}
