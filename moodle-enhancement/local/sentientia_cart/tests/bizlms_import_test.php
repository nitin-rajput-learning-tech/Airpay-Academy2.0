<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

namespace local_sentientia_cart;

defined('MOODLE_INTERNAL') || die();

use local_sentientia_cart\bizlms\importer;
use local_sentientia_org\test\bizlms_fixture;
use local_sentientia_platform\bizlms\decisions;
use local_sentientia_platform\bizlms\importer as importer_interface;
use local_sentientia_platform\bizlms\legacymap;
use local_sentientia_platform\bizlms\registry;
use local_sentientia_platform\bizlms\report;
use local_sentientia_platform\phpunit\importer_contract;
use local_sentientia_platform\phpunit\legacy_schema_fixture;

/**
 * The cart importer (ADR-032, mapping doc section 13): the BizLMS orders, ledger, invoices and credit journal
 * become frozen, admin-only Sentientia cart history.
 *
 * The contract traits give the generic tests (not applicable without tables, a dry run writes nothing, apply
 * reconciles, a second apply is a no-op, resume, a source change is detected, no side effects, privacy declared).
 * The rest pins what is specific to this feature, from the fixture section of the mapping doc:
 *
 *  - statuses: paid, cancelled, part_cancelled and abandoned, and NEVER open, pending, failed, refunded or
 *    partial_refund (the states the native engine acts on);
 *  - order number = the BizLMS identifier, tenant = the buyer's current root, paise-exact totals, the items
 *    snapshot, the gateway reference of the attempt that was paid, a time paid that is not 0 and not the import;
 *  - the ledger one to one with its event types, the cash drawer and credit rows with no order, no row invented;
 *  - the invoices as ERPNEXT- references (never AIRPAY-), with the reasons the others are skipped;
 *  - the credit journal, its balance per user, and a non-INR booking skipped;
 *  - a line with no order number is numbered above the floor, and finalise() records the floor so a native
 *    checkout stays above it;
 *  - mark_paid, mark_failed and refund refuse an imported order.
 *
 * The legacy tables are the fixture biz_cart.install.xml (verbatim copies of the BizLMS install files). Tenant roots
 * 1, 77 and 177 are the registered ones.
 *
 * @package    local_sentientia_cart
 * @category   test
 * @covers     \local_sentientia_cart\bizlms\importer
 * @covers     \local_sentientia_cart\bizlms\history_step
 * @covers     \local_sentientia_cart\bizlms\ledger_step
 * @covers     \local_sentientia_cart\bizlms\invoices_step
 * @covers     \local_sentientia_cart\bizlms\credits_step
 * @covers     \local_sentientia_cart\bizlms\cart_id_step
 * @covers     \local_sentientia_cart\bizlms\order_numbers_step
 * @covers     \local_sentientia_cart\bizlms\order_builder
 * @covers     \local_sentientia_cart\bizlms\evidence
 *
 * @group local_sentientia_cart
 * @group bizlms_import
 * @group tenant_isolation
 */
final class bizlms_import_test extends \advanced_testcase {
    use legacy_schema_fixture;
    use importer_contract;
    use bizlms_fixture;

    /** Timestamp base of the seed. */
    private const T0 = 1700000000;

    /** The BizLMS base of the order numbers (config uniqueidentifier). */
    private const BASE = 1000000;

    /** The owner's signed values for this feature (docs/cutover/bizlms-import-decisions.json, 2026-09-30). */
    private const SIGNED = [
        'tenant.unresolved.cart' => 'pathless',
        'cart.synthesize_ledger' => false,
        'cart.order_tenant' => 'buyer',
        'cart.abandoned' => 'admin_only',
        'cart.imported_visibility' => 'admin_only',
        'cart.admin_refund_imported_orders' => false,
        'cart.cash_drawer_rows_without_order' => 'import_admin_only',
    ];

    /** @var array<string, mixed> The decision values a test runs with; a test may change some. */
    private array $decisionvalues = self::SIGNED;

    /** @var array<string, int> Ids of what the seed created, by name. */
    private array $ids = [];

    protected static function legacy_fixture_definition(): array {
        return ['xml' => __DIR__ . '/fixtures/bizlms/biz_cart.install.xml'];
    }

    protected function contract_importer(): importer_interface {
        return new importer();
    }

    protected function contract_decisions(): decisions {
        return decisions::from_array($this->decisionvalues);
    }

    protected function contract_seed(): void {
        $this->seed_cart();
    }

    protected function contract_mutate_source(): void {
        // A new line changes the count and the max id on every engine, so detection does not depend on the CRC.
        $this->legacy_row('local_biz_cart_history', [
            'id' => 99, 'userid' => $this->ids['a'], 'itemid' => $this->ids['course1'], 'itemname' => 'Late arrival',
            'price' => 1.00, 'currency' => 'INR', 'identifier' => self::BASE + 99, 'payment' => '0',
            'paymentstatus' => 0, 'usermodified' => $this->ids['a'], 'timecreated' => self::T0 + 99,
        ]);
    }

    protected function contract_user_columns(): array {
        // The columns the import added or began to fill that name a person.
        return [
            'local_sentientia_cart_ledger' => ['initiatedby'],
            'local_sentientia_cart_credit_txn' => ['userid', 'initiatedby'],
        ];
    }

    // The seed.

    /**
     * Insert into a legacy table whose real schema this codebase does not own: every NOT NULL column without a
     * default that the caller did not name gets a neutral value, and a column the table lacks is ignored.
     *
     * @param string $table
     * @param array<string, mixed> $data Must carry id.
     * @return int The id.
     */
    private function legacy_row(string $table, array $data): int {
        global $DB;
        $row = [];
        foreach ($DB->get_columns($table, false) as $name => $column) {
            if (array_key_exists($name, $data)) {
                $row[$name] = $data[$name];
            } else if ($name !== 'id' && $column->not_null && !$column->has_default) {
                $row[$name] = in_array($column->meta_type, ['I', 'N', 'F', 'R', 'L'], true) ? 0 : '';
            }
        }
        $DB->import_record($table, (object) $row);
        return (int) $row['id'];
    }

    /**
     * A user at a tenant path; null leaves open_path empty.
     *
     * @param string|null $path
     * @return int
     */
    private function user_at(?string $path): int {
        global $DB;
        $user = $this->getDataGenerator()->create_user();
        if ($path !== null) {
            $DB->set_field('user', 'open_path', $path, ['id' => $user->id]);
        }
        return (int) $user->id;
    }

    /**
     * The seed. Numbers a test may rely on (BASE = 1000000, config uniqueidentifier):
     *
     *  local_biz_cart_history  12 lines.
     *    1000001  lines 1, 2   buyer A (/1/2), two course lines paid, gross prices 1180.00 (tax 180) and 900.00
     *                          (discount 100.00, tax 17.994); two paygw attempts (AP-FAIL-1 status 1, AP-OK-2 status
     *                          2); an enrolment-log row with transaction TXN-7770002; NO ledger row
     *    1000002  line 3       buyer B (/77), cancelled, 500.00; a sale ledger row and a cancel ledger row
     *    1000003  lines 4, 5   buyer C (/177/5), one line paid, one cancelled: part_cancelled, cashier
     *    1000004  line 6       buyer A, status 0: abandoned
     *    1000005  line 7       buyer B, status 1: abandoned
     *    1000006  line 8       buyer D (no open_path), paid; a core payments row of gateway paypal
     *    1000007  lines 9, 10  two buyers: skipped, mixed_buyers
     *    1000008  line 11      a buyer who does not exist: skipped, orphan_user
     *    (none)   line 12      buyer C, paid, no order number
     *  local_biz_cart_id       ids 1 to 9 (order numbers 1000001 to 1000009): 1 to 6 fold into their orders, 7 to 9
     *                          are archived (the orders of 7 and 8 were not imported, 9 never had one)
     *  local_biz_cart_ledger   10 rows: 9 import (1 sale, 2 cancel, 3 redeemed, 4 payout, 5 correction, 6 cash in,
     *                          7 cash out, 8 legacy_other, 10 a sale that belongs to no imported order); 9 has a
     *                          currency that is not one and is skipped
     *  local_biz_cart_invoices 5 rows: 1 imports as ERPNEXT-ACC-SINV-2025-00042; 4 repeats that invoice id and is
     *                          merged; 2 has no imported order, 3 no number, 5 a number that is too long
     *  local_biz_cart_credits  7 rows: B has 4 INR bookings (+500, -100, -50, -25) and one EUR booking; A has one
     *                          booking with no currency; 7 belongs to a user who does not exist
     *
     * @return void
     */
    private function seed_cart(): void {
        global $DB;
        $this->ensure_bizlms_schema();
        set_config('uniqueidentifier', self::BASE, 'local_biz_cart');
        set_config('globalcurrency', 'INR', 'local_biz_cart');
        set_config('itempriceisnet', 0, 'local_biz_cart');
        $t = self::T0;

        $this->ids['a'] = $this->user_at('/1/2');
        $this->ids['b'] = $this->user_at('/77');
        $this->ids['c'] = $this->user_at('/177/5');
        $this->ids['d'] = $this->user_at(null);
        $this->ids['course1'] = (int) $this->getDataGenerator()->create_course(['shortname' => 'SAFE101'])->id;
        $this->ids['course2'] = (int) $this->getDataGenerator()->create_course(['shortname' => 'FIN201'])->id;
        [$a, $b, $c, $d] = [$this->ids['a'], $this->ids['b'], $this->ids['c'], $this->ids['d']];
        [$c1, $c2] = [$this->ids['course1'], $this->ids['course2']];
        $base = self::BASE;

        $line = function (int $id, int $user, int $item, int $number, int $status, float $price, array $more = [])
                use ($t, $c1, $c2): void {
            $this->legacy_row('local_biz_cart_history', $more + [
                'id' => $id, 'userid' => $user, 'itemid' => $item, 'itemname' => $item === $c1 ? 'Safety 101' : 'Finance 201',
                'price' => $price, 'currency' => 'INR', 'componentname' => 'local_courses', 'area' => 'option',
                'identifier' => $number, 'payment' => '0', 'paymentstatus' => $status, 'usermodified' => $user,
                'timecreated' => $t + $id, 'timemodified' => $t + 100 + $id,
            ]);
        };
        $line(1, $a, $c1, $base + 1, 2, 1180.00, ['tax' => 180.000, 'taxpercentage' => 0.18, 'taxcategory' => 'GST',
            'discount' => 0, 'timemodified' => $t + 300]);
        $line(2, $a, $c2, $base + 1, 2, 900.00, ['tax' => 17.994, 'taxpercentage' => 0.18, 'taxcategory' => 'GST',
            'discount' => 100.00, 'timemodified' => $t + 310]);
        $line(3, $b, $c1, $base + 2, 3, 500.00, ['timecreated' => $t + 1000, 'timemodified' => $t + 2000]);
        $line(4, $c, $c1, $base + 3, 2, 100.00, ['payment' => '1', 'timecreated' => $t + 3000, 'timemodified' => $t + 3100]);
        $line(5, $c, $c2, $base + 3, 3, 200.00, ['payment' => '1', 'timecreated' => $t + 3001, 'timemodified' => $t + 3200]);
        $line(6, $a, $c1, $base + 4, 0, 50.00);
        $line(7, $b, $c2, $base + 5, 1, 60.00);
        $line(8, $d, $c1, $base + 6, 2, 70.00, ['timemodified' => $t + 800]);
        $line(9, $a, $c1, $base + 7, 2, 10.00);
        $line(10, $b, $c2, $base + 7, 2, 10.00);
        $line(11, 987654, $c1, $base + 8, 2, 10.00);
        $line(12, $c, $c2, 0, 2, 40.00, ['identifier' => null, 'timemodified' => $t + 1200]);
        $this->ids['line3'] = 3;

        for ($id = 1; $id <= 9; $id++) {
            $this->legacy_row('local_biz_cart_id', ['id' => $id, 'timecreated' => $t + $id]);
        }

        // The gateway evidence. Two attempts for order 1000001; the one with status 2 collected the money.
        $this->legacy_row('paygw_airpay', ['id' => 1, 'component' => 'local_biz_cart', 'paymentarea' => 'cart',
            'itemid' => $base + 1, 'userid' => $a, 'accountid' => 1, 'ap_orderid' => 'AP-FAIL-1', 'cost' => 0,
            'status' => 1, 'timecreated' => $t + 200, 'timemodified' => 0]);
        $this->legacy_row('paygw_airpay', ['id' => 2, 'component' => 'local_biz_cart', 'paymentarea' => 'cart',
            'itemid' => $base + 1, 'userid' => $a, 'accountid' => 1, 'ap_orderid' => 'AP-OK-2', 'cost' => 2080,
            'paymentid' => 7770002, 'status' => 2, 'timecreated' => $t + 250, 'timemodified' => 0]);
        $this->legacy_row('paygw_course_enrolmentlog', ['id' => 1, 'courseid' => $c1, 'coursename' => 'Safety 101',
            'userid' => $a, 'username' => 'a', 'transactionid' => 'TXN-7770002', 'ap_orderid' => 'AP-OK-2',
            'amount' => 1180, 'status' => 2, 'timecreated' => $t + 500]);
        $this->legacy_row('paygw_course_enrolmentlog', ['id' => 2, 'courseid' => $c2, 'coursename' => 'Finance 201',
            'userid' => $a, 'username' => 'a', 'transactionid' => '0', 'ap_orderid' => 'AP-OK-2',
            'amount' => 900, 'status' => 0, 'timecreated' => $t + 501]);
        $this->legacy_row('paygw_airpay_errorlog', ['id' => 1, 'airpay_id' => 'AP-OK-2', 'courseid' => $c1,
            'userid' => $a, 'error' => 'ok', 'order_state' => 'Order Successfull', 'paymentarea' => 'cart',
            'timecreated' => $t + 510, 'timemodified' => 0]);
        // Moodle's own payments table: order 1000006 went through a gateway Moodle names.
        $this->ids['payment'] = (int) $DB->insert_record('payments', (object) ['component' => 'local_biz_cart',
            'paymentarea' => 'cart', 'itemid' => $base + 6, 'userid' => $d, 'amount' => '70.00', 'currency' => 'INR',
            'accountid' => 1, 'gateway' => 'paypal', 'timecreated' => $t + 10, 'timemodified' => $t + 10]);

        $ledger = function (int $id, array $data) use ($t): void {
            $this->legacy_row('local_biz_cart_ledger', $data + [
                'id' => $id, 'userid' => 0, 'itemid' => 0, 'currency' => 'INR', 'paymentstatus' => 2,
                'usermodified' => 0, 'timecreated' => $t + $id, 'timemodified' => $t + $id,
            ]);
        };
        $ledger(1, ['userid' => $b, 'itemid' => $c1, 'itemname' => 'Safety 101', 'price' => 500.00,
            'identifier' => $base + 2, 'payment' => '0', 'usermodified' => $b, 'timecreated' => $t + 1000,
            'schistoryid' => 3, 'componentname' => 'local_courses', 'area' => 'option']);
        $ledger(2, ['userid' => $b, 'itemid' => $c1, 'itemname' => 'Safety 101', 'price' => 500.00, 'credits' => 500.00,
            'fee' => 0, 'identifier' => $base + 2, 'payment' => '0', 'paymentstatus' => 3, 'usermodified' => $b,
            'timecreated' => $t + 2000, 'schistoryid' => 3]);
        $ledger(3, ['userid' => $b, 'price' => 0, 'credits' => -100.00, 'payment' => '2', 'usermodified' => $b,
            'timecreated' => $t + 3000]);
        $ledger(4, ['userid' => $b, 'price' => -50.00, 'credits' => -50.00, 'payment' => '6', 'usermodified' => $a,
            'timecreated' => $t + 4000]);
        $ledger(5, ['userid' => $b, 'price' => -25.00, 'credits' => -25.00, 'payment' => '9', 'usermodified' => $a,
            'timecreated' => $t + 5000]);
        $ledger(6, ['itemname' => 'cash', 'price' => 1000.00, 'payment' => '3', 'area' => 'cash', 'identifier' => 0,
            'usermodified' => $a, 'annotation' => 'Opening float', 'timecreated' => $t + 6000]);
        $ledger(7, ['itemname' => 'cash', 'price' => -400.00, 'payment' => '3', 'area' => 'cash', 'identifier' => 0,
            'usermodified' => $a, 'timecreated' => $t + 6001]);
        $ledger(8, ['userid' => $b, 'price' => 0, 'paymentstatus' => 1, 'usermodified' => $b, 'timecreated' => $t + 7000]);
        $ledger(9, ['userid' => $a, 'price' => 0, 'credits' => -5.00, 'payment' => '2', 'currency' => 'EURO',
            'usermodified' => $a, 'timecreated' => $t + 8000]);
        // A sale with no order number and no history line: it belongs to no imported order.
        $ledger(10, ['userid' => $a, 'itemid' => $c1, 'itemname' => 'Orphan sale', 'price' => 30.00, 'payment' => '0',
            'usermodified' => $a, 'timecreated' => $t + 8500]);

        $credit = function (int $id, int $user, float $amount, float $balance, array $more = []) use ($t): void {
            $this->legacy_row('local_biz_cart_credits', $more + [
                'id' => $id, 'userid' => $user, 'credits' => $amount, 'currency' => 'INR', 'balance' => $balance,
                'usermodified' => $user, 'timecreated' => $t + $id, 'timemodified' => $t + $id,
            ]);
        };
        $credit(1, $b, 500.00, 500.00, ['timecreated' => $t + 2000, 'timemodified' => $t + 2000]);
        $credit(2, $b, -100.00, 400.00, ['timecreated' => $t + 3000, 'timemodified' => $t + 3000]);
        $credit(3, $b, -50.00, 350.00, ['timecreated' => $t + 4000, 'timemodified' => $t + 4000]);
        $credit(4, $b, -25.00, 325.00, ['timecreated' => $t + 5001, 'timemodified' => $t + 5001]);
        $credit(5, $b, 10.00, 10.00, ['currency' => 'EUR', 'timecreated' => $t + 6000, 'timemodified' => $t + 6000]);
        $credit(6, $a, 20.00, 20.00, ['currency' => '', 'timecreated' => $t + 7000, 'timemodified' => $t + 7000]);
        $credit(7, 987654, 5.00, 5.00, ['timecreated' => $t + 9000, 'timemodified' => $t + 9000]);

        $invoice = function (int $id, int $number, string $invoiceid, int $when): void {
            $this->legacy_row('local_biz_cart_invoices', ['id' => $id, 'identifier' => $number,
                'invoiceid' => $invoiceid, 'timecreated' => $when]);
        };
        $invoice(1, $base + 1, 'ACC-SINV-2025-00042', $t + 900);
        $invoice(2, $base + 7, 'ACC-SINV-2025-00099', $t + 901);
        $invoice(3, $base + 3, '', $t + 902);
        $invoice(4, $base + 5, 'ACC-SINV-2025-00042', $t + 903);
        $invoice(5, $base + 6, str_repeat('Z', 60), $t + 904);
    }

    // Helpers for what the import wrote.

    /**
     * Run the import once, applied, with the signed decisions.
     *
     * @return array{0: array, 1: report}
     */
    private function apply(): array {
        $this->contract_begin();
        $this->contract_seed();
        [$result, $report] = $this->contract_run(true);
        $this->assertContains($result['exit'], [0, 2], implode('; ', $result['blockers']));
        return [$result, $report];
    }

    /**
     * @param int $number The order number.
     * @return \stdClass The imported order.
     */
    private function order(int $number): \stdClass {
        global $DB;
        return $DB->get_record('local_sentientia_cart_history',
            ['orderid' => $number, 'legacy_source' => 'bizlms'], '*', MUST_EXIST);
    }

    /**
     * @param string $sourcetable
     * @param int $sourceid
     * @return \stdClass The primary map row.
     */
    private function map_row(string $sourcetable, int $sourceid): \stdClass {
        global $DB;
        return $DB->get_record(legacymap::TABLE, ['sourcetable' => $sourcetable, 'sourceid' => $sourceid, 'subkey' => ''],
            '*', MUST_EXIST);
    }

    /**
     * The target row a source row became.
     *
     * @param string $sourcetable
     * @param int $sourceid
     * @return \stdClass
     */
    private function target_of(string $sourcetable, int $sourceid): \stdClass {
        global $DB;
        $map = $this->map_row($sourcetable, $sourceid);
        return $DB->get_record($map->targettable, ['id' => $map->targetid], '*', MUST_EXIST);
    }

    /**
     * Summed warnings or tenant methods of the cart steps of a run.
     *
     * @param report $report
     * @param string $section warnings or tenant_methods
     * @return array<string, int>
     */
    private function tally(report $report, string $section): array {
        $out = [];
        foreach ($report->to_array()['features']['cart']['steps'] ?? [] as $step) {
            foreach ($step[$section] ?? [] as $code => $n) {
                $out[$code] = ($out[$code] ?? 0) + $n;
            }
        }
        return $out;
    }

    /**
     * A hash per legacy table, to prove the import changed none of them.
     *
     * @return array<string, string>
     */
    private function legacy_snapshot(): array {
        global $DB;
        $out = [];
        foreach (['local_biz_cart_history', 'local_biz_cart_id', 'local_biz_cart_ledger', 'local_biz_cart_invoices',
                  'local_biz_cart_credits', 'paygw_airpay', 'paygw_airpay_errorlog', 'paygw_course_enrolmentlog'] as $table) {
            $rows = array_map(static fn($r) => (array) $r, array_values($DB->get_records($table, null, 'id')));
            $out[$table] = md5(serialize($rows));
        }
        return $out;
    }

    // The registry and the owner's decisions.

    public function test_the_importer_passes_the_registry_rules(): void {
        $importer = $this->contract_begin();
        $loaded = registry::load();
        $this->assertSame(['cart' => $importer], $loaded);
        $this->assertSame([], $importer->depends(), 'the cart depends on no other feature (mapping doc, run order)');
        $this->assertFalse($importer->atomic());
        $this->assertSame([], $importer->tenant_columns(), 'costcenterid is an INT root, not a path');
        $this->assertSame([], $importer->core_writes(), 'the cart importer writes no core table');
        $this->assertSame(['paygw_airpay', 'paygw_airpay_errorlog', 'paygw_course_enrolmentlog'],
            array_keys($importer->declined_tables()), 'the gateway tables are evidence, declined as tables');
    }

    public function test_the_signed_decisions_cover_the_importer_and_the_finance_keys_are_not_declared(): void {
        $this->resetAfterTest();
        $dir = \core_component::get_component_directory('local_sentientia_platform');
        $signed = decisions::load($dir . '/tests/fixtures/bizlms/bizlms-import-decisions.copy.json');
        $importer = new importer();
        $declared = [];
        foreach ($importer->decisions() as $decision) {
            $declared[] = $decision->key;
            $this->assertSame(decisions::ACCEPTED, $signed->status($decision->key), $decision->key . ' is signed');
            $this->assertTrue($signed->has($decision->key));
            $this->assertContains($signed->get($decision->key), (array) $decision->allowed,
                $decision->key . ': the signed value is one the importer implements');
        }
        // The two finance-confirm keys are not accepted, so an importer that declared one would be blocked.
        foreach (['cart.credit_balances', 'cart.erpnext_invoices_legal'] as $key) {
            $this->assertSame('finance-confirm', $signed->status($key));
            $this->assertNotContains($key, $declared, "{$key} must not be declared by the cart importer");
        }
    }

    public function test_a_decision_value_the_importer_does_not_implement_blocks_the_feature(): void {
        $this->contract_begin();
        $this->contract_seed();
        $this->decisionvalues['cart.synthesize_ledger'] = true;
        [$result] = $this->contract_run(false);
        $this->assertSame(1, $result['exit']);
        $this->assertStringContainsString('decision_value_not_allowed:cart.synthesize_ledger', implode(' ', $result['blockers']));
    }

    // Preflight.

    public function test_preflight_blocks_a_native_order_that_holds_an_imported_number(): void {
        global $DB;
        $this->contract_begin();
        $this->contract_seed();
        $DB->insert_record('local_sentientia_cart_history', (object) ['userid' => $this->ids['a'], 'orderid' => self::BASE + 2,
            'status' => 'paid', 'timecreated' => time(), 'timemodified' => time()]);
        [$result] = $this->contract_run(false);
        $this->assertSame(1, $result['exit']);
        $this->assertStringContainsString('native_order_number_taken:1', implode(' ', $result['blockers']));
    }

    public function test_preflight_blocks_a_credit_balance_the_import_did_not_write(): void {
        global $DB;
        $this->contract_begin();
        $this->contract_seed();
        $DB->insert_record('local_sentientia_cart_credits', (object) ['userid' => $this->ids['b'], 'balance' => 1,
            'currency' => 'INR', 'timemodified' => time()]);
        [$result] = $this->contract_run(false);
        $this->assertSame(1, $result['exit']);
        $this->assertStringContainsString('credit_balance_already_exists:1', implode(' ', $result['blockers']));
    }

    public function test_an_unknown_payment_status_blocks_the_feature(): void {
        $this->contract_begin();
        $this->contract_seed();
        $this->legacy_row('local_biz_cart_history', ['id' => 50, 'userid' => $this->ids['a'], 'itemid' => 1, 'price' => 1,
            'currency' => 'INR', 'identifier' => self::BASE + 50, 'payment' => '0', 'paymentstatus' => 7,
            'usermodified' => $this->ids['a']]);
        [$result] = $this->contract_run(false);
        $this->assertSame(1, $result['exit']);
        $this->assertStringContainsString('unknown_enum:local_biz_cart_history.paymentstatus=7', implode(' ', $result['blockers']));
    }

    // Orders.

    public function test_every_line_has_one_primary_row_and_the_orders_are_what_the_map_says(): void {
        global $DB;
        $this->apply();

        $outcomes = [];
        foreach ($DB->get_records(legacymap::TABLE, ['sourcetable' => 'local_biz_cart_history', 'subkey' => '']) as $row) {
            $outcomes[(int) $row->sourceid] = $row->outcome . ':' . $row->reason;
        }
        $this->assertCount(12, $outcomes, 'one primary map row per history line');
        $this->assertSame('imported:', $outcomes[1]);
        $this->assertSame('merged:order_line', $outcomes[2]);
        $this->assertSame('imported:', $outcomes[3]);
        $this->assertSame('imported:', $outcomes[4]);
        $this->assertSame('merged:order_line', $outcomes[5]);
        $this->assertSame('skipped:mixed_buyers', $outcomes[9]);
        $this->assertSame('skipped:mixed_buyers', $outcomes[10]);
        $this->assertSame('skipped:orphan_user', $outcomes[11]);
        $this->assertSame('imported:', $outcomes[12]);
        // A merged line points at the order its sibling made.
        $this->assertSame($this->map_row('local_biz_cart_history', 1)->targetid, $this->map_row('local_biz_cart_history', 2)->targetid);

        // Never a status the native engine acts on; only the five an import can mean.
        [$insql, $params] = $DB->get_in_or_equal(['open', 'pending', 'failed', 'refunded', 'partial_refund'], SQL_PARAMS_NAMED);
        $this->assertSame(0, $DB->count_records_select('local_sentientia_cart_history', "legacy_source IS NOT NULL AND status $insql", $params));
        $this->assertSame(7, $DB->count_records('local_sentientia_cart_history', ['legacy_source' => 'bizlms']));
        $this->assertSame(0, $DB->count_records('local_sentientia_cart_history', ['legacy_source' => null]),
            'the import wrote only imported rows');
        $this->assertSame('paid', $this->order(self::BASE + 1)->status);
        $this->assertSame('cancelled', $this->order(self::BASE + 2)->status);
        $this->assertSame('part_cancelled', $this->order(self::BASE + 3)->status);
        $this->assertSame('abandoned', $this->order(self::BASE + 4)->status);
        $this->assertSame('abandoned', $this->order(self::BASE + 5)->status);
        $this->assertSame('paid', $this->order(self::BASE + 6)->status);
        // No order of the two groups that were refused.
        $this->assertFalse($DB->record_exists('local_sentientia_cart_history', ['orderid' => self::BASE + 7]));
        $this->assertFalse($DB->record_exists('local_sentientia_cart_history', ['orderid' => self::BASE + 8]));
        // No billing details: BizLMS stored none.
        $this->assertSame(0, $DB->count_records_select('local_sentientia_cart_history',
            'legacy_source IS NOT NULL AND (billing_name IS NOT NULL OR billing_email IS NOT NULL OR billing_gstn IS NOT NULL)'));
    }

    public function test_the_first_order_is_priced_in_paise_and_carries_its_gateway_facts(): void {
        $this->apply();
        $o = $this->order(self::BASE + 1);
        $t = self::T0;

        $this->assertEqualsWithDelta(1982.01, (float) $o->subtotal, 0.0001);
        $this->assertEqualsWithDelta(100.00, (float) $o->discount_amount, 0.0001);
        $this->assertEqualsWithDelta(197.99, (float) $o->tax_amount, 0.0001, 'tax 180.000 + 17.994 rounded to the paisa');
        $this->assertEqualsWithDelta(2080.00, (float) $o->total_amount, 0.0001);
        $this->assertEqualsWithDelta((float) $o->total_amount,
            (float) $o->subtotal - (float) $o->discount_amount + (float) $o->tax_amount, 0.0001,
            'Sentientia total = subtotal - discount + tax');
        $this->assertSame('INR', $o->currency);
        $this->assertSame($this->ids['a'], (int) $o->userid);
        $this->assertSame(1, (int) $o->costcenterid, 'the buyer\'s current root');
        $this->assertSame('airpay', $o->gateway, 'an airpay attempt exists and no core payments row names another');
        $this->assertSame('TXN-7770002', $o->gateway_ref, 'the transaction id of the attempt with status 2, not of the failed one');
        $this->assertSame($t + 1, (int) $o->timecreated, 'the earliest line');
        $this->assertSame($t + 310, (int) $o->timepaid, 'no ledger row: the paid lines were last touched at t+310');
        $this->assertGreaterThan(0, (int) $o->timepaid);
        $this->assertSame($t + 310, (int) $o->timemodified);
        $this->assertStringContainsString('Imported from BizLMS: identifier ' . (self::BASE + 1), $o->notes);

        // The items snapshot: one object per line, facts only.
        $items = json_decode($o->items_json, true);
        $this->assertCount(2, $items);
        $this->assertSame($this->ids['course1'], $items[0]['courseid']);
        $this->assertSame('SAFE101', $items[0]['shortname']);
        $this->assertSame('paid', $items[0]['status']);
        $this->assertEqualsWithDelta(1000.00, $items[0]['price'], 0.0001, 'undiscounted NET price: 1180.00 less 180.000 tax');
        $this->assertEqualsWithDelta(100.00, $items[1]['discount'], 0.0001);
        $this->assertSame(0, $items[1]['discount_pct']);
        $this->assertEqualsWithDelta(17.994, $items[1]['tax'], 0.0001);
        $this->assertSame(1, $items[0]['legacy']['historyid']);
        $this->assertArrayNotHasKey('usermodified', $items[0]['legacy'], 'no actor ids in the snapshot');
        $this->assertArrayNotHasKey('userid', $items[0]['legacy']);
    }

    public function test_the_other_orders(): void {
        $this->apply();
        $t = self::T0;

        $cancelled = $this->order(self::BASE + 2);
        $this->assertSame((int) ($t + 1000), (int) $cancelled->timepaid, 'the earliest sale row of the ledger');
        $this->assertSame(77, (int) $cancelled->costcenterid);
        $this->assertSame('online', $cancelled->gateway, 'no attempt and no payments row: the generic label');
        $this->assertNull($cancelled->gateway_ref);

        $part = $this->order(self::BASE + 3);
        $this->assertSame(177, (int) $part->costcenterid, '/177/5 gives root 177');
        $this->assertSame('cashier', $part->gateway);
        $this->assertEqualsWithDelta(300.00, (float) $part->total_amount, 0.0001, 'paid and cancelled lines both count');
        $lines = array_column(json_decode($part->items_json, true), 'status');
        $this->assertSame(['paid', 'cancelled'], $lines);

        $abandoned = $this->order(self::BASE + 4);
        $this->assertNull($abandoned->timepaid, 'nobody paid it');
        $this->assertSame('not_completed', json_decode($abandoned->items_json, true)[0]['status']);

        $nopath = $this->order(self::BASE + 6);
        $this->assertSame(0, (int) $nopath->costcenterid, 'no open_path: pathless, never a guessed tenant');
        $this->assertSame('paypal', $nopath->gateway, 'the gateway Moodle\'s payments row names');
        $this->assertSame('payments:' . $this->ids['payment'], $nopath->gateway_ref);
    }

    public function test_tenant_methods_are_reported(): void {
        [, $report] = $this->apply();
        $methods = $this->tally($report, 'tenant_methods');
        $this->assertGreaterThan(0, $methods['exact'] ?? 0);
        $this->assertSame(1, $methods['unresolved'] ?? 0, 'only the order of the buyer who has no open_path');
    }

    public function test_an_unresolved_tenant_is_skipped_when_the_decision_says_so(): void {
        global $DB;
        $this->contract_begin();
        $this->contract_seed();
        $this->decisionvalues['tenant.unresolved.cart'] = 'skip';
        [$result] = $this->contract_run(true);
        $this->assertContains($result['exit'], [0, 2]);
        $map = $this->map_row('local_biz_cart_history', 8);
        $this->assertSame('skipped', $map->outcome);
        $this->assertSame('tenant_unresolved', $map->reason);
        $this->assertFalse($DB->record_exists('local_sentientia_cart_history', ['orderid' => self::BASE + 6]));
    }

    public function test_abandoned_checkouts_are_archived_when_the_decision_says_not_to_import_them(): void {
        global $DB;
        $this->contract_begin();
        $this->contract_seed();
        $this->decisionvalues['cart.abandoned'] = 'skip';
        [$result] = $this->contract_run(true);
        $this->assertContains($result['exit'], [0, 2]);
        $this->assertSame('archived', $this->map_row('local_biz_cart_history', 6)->outcome);
        $this->assertSame('abandoned_not_imported', $this->map_row('local_biz_cart_history', 6)->reason);
        $this->assertSame(0, $DB->count_records('local_sentientia_cart_history', ['status' => 'abandoned']));
    }

    public function test_a_line_without_an_order_number_is_numbered_above_the_floor(): void {
        global $DB;
        $this->apply();
        $line = $this->target_of('local_biz_cart_history', 12);
        // The highest number BizLMS could have issued is the base plus the largest generator id: 1000009.
        $this->assertSame(self::BASE + 10, (int) $line->orderid);
        $this->assertStringContainsString('no order number', $line->notes);
        $numbers = $DB->get_fieldset_select('local_sentientia_cart_history', 'orderid', 'legacy_source = :s', ['s' => 'bizlms']);
        $this->assertCount(count(array_unique($numbers)), $numbers, 'no order number is used twice');
    }

    public function test_generator_rows_fold_into_their_orders_and_unused_ones_are_archived(): void {
        $this->apply();
        $one = $this->map_row('local_biz_cart_id', 1);
        $this->assertSame('folded', $one->outcome);
        $this->assertSame('cart_id_of_order', $one->reason);
        $this->assertSame($this->map_row('local_biz_cart_history', 1)->targetid, $one->targetid);
        foreach ([7, 8, 9] as $id) {
            $this->assertSame('archived', $this->map_row('local_biz_cart_id', $id)->outcome);
            $this->assertSame('cart_id_unused', $this->map_row('local_biz_cart_id', $id)->reason);
        }
    }

    public function test_finalise_records_the_floor_and_a_native_order_number_stays_above_it(): void {
        global $DB;
        $this->apply();
        $floor = (int) get_config('local_sentientia_cart', 'bizlms_order_floor');
        $this->assertSame(self::BASE + 10, $floor, 'the highest number an imported order holds');
        $this->assertSame(imported_history::ORDER_FLOOR_CONFIG, \local_sentientia_cart\bizlms\support::FLOOR_CONFIG);

        $reserve = new \ReflectionMethod(cart_manager::class, 'reserve_order_number');
        $first = (int) $reserve->invoke(null, $this->ids['a']);
        $second = (int) $reserve->invoke(null, $this->ids['b']);
        $this->assertGreaterThan($floor, $first, 'a new checkout gets an order number above every imported one');
        $this->assertSame($first + 1, $second);
        $this->assertFalse($DB->record_exists_select('local_sentientia_cart_history', 'orderid = :o AND legacy_source IS NOT NULL',
            ['o' => $first]));
    }

    public function test_without_an_import_the_order_number_is_the_plain_next_one(): void {
        $this->resetAfterTest();
        unset_config('bizlms_order_floor', 'local_sentientia_cart');
        $reserve = new \ReflectionMethod(cart_manager::class, 'reserve_order_number');
        $user = $this->getDataGenerator()->create_user();
        $first = (int) $reserve->invoke(null, $user->id);
        $this->assertSame($first + 1, (int) $reserve->invoke(null, $user->id));
        $this->assertLessThan(1000000, $first);
    }

    // The ledger.

    public function test_the_ledger_is_imported_one_to_one(): void {
        global $DB;
        $this->apply();
        $this->assertSame(9, $DB->count_records(legacymap::TABLE, ['sourcetable' => 'local_biz_cart_ledger', 'outcome' => 'imported']));
        $skipped = $this->map_row('local_biz_cart_ledger', 9);
        $this->assertSame('skipped', $skipped->outcome);
        $this->assertSame('currency_invalid', $skipped->reason);

        $orderhistory = (int) $this->map_row('local_biz_cart_history', 3)->targetid;
        $sale = $this->target_of('local_biz_cart_ledger', 1);
        $this->assertSame('payment_received', $sale->event_type);
        $this->assertSame($orderhistory, (int) $sale->historyid, 'the sale belongs to the order its identifier names');
        $this->assertSame(self::BASE + 2, (int) $sale->orderid);
        $this->assertEqualsWithDelta(500.00, (float) $sale->amount, 0.0001);
        $this->assertSame('online', $sale->gateway);
        $this->assertSame($this->ids['b'], (int) $sale->initiatedby);
        $this->assertSame(self::T0 + 1000, (int) $sale->timecreated, 'the source timestamp, never the import time');
        $payload = json_decode($sale->payload_json, true);
        $this->assertSame($this->ids['b'], $payload['userid']);
        $this->assertStringStartsWith('{"userid":' . $this->ids['b'] . ',', $sale->payload_json,
            'the buyer comes first, so the privacy provider can find the row by a prefix');
        $this->assertSame(3, $payload['schistoryid']);
        $this->assertArrayHasKey('paygw', $payload, 'a sale carries the gateway attempts of its order');

        $types = [2 => 'legacy_cancel_to_credit', 3 => 'legacy_credit_redeemed', 4 => 'legacy_credit_payout',
            5 => 'legacy_credit_correction', 6 => 'legacy_cash_drawer', 7 => 'legacy_cash_drawer', 8 => 'legacy_other',
            // A sale that belongs to no imported order keeps its own type, so it stays on the imported side of the flag.
            10 => 'legacy_sale_without_order'];
        foreach ($types as $id => $type) {
            $this->assertSame($type, $this->target_of('local_biz_cart_ledger', $id)->event_type, "ledger row {$id}");
        }
        $this->assertSame('credits_payback_cash', $this->target_of('local_biz_cart_ledger', 4)->gateway);
        $this->assertSame('credits_correction', $this->target_of('local_biz_cart_ledger', 5)->gateway);

        // The cash drawer belongs to no order: historyid 0, so only cross-tenant administrators see it.
        $cash = $this->target_of('local_biz_cart_ledger', 6);
        $this->assertSame(0, (int) $cash->historyid);
        $this->assertSame(0, (int) $cash->orderid);
        $this->assertSame('cashier_cash', $cash->gateway);
        $this->assertSame('Opening float', $cash->reason);
        $this->assertEqualsWithDelta(-400.00, (float) $this->target_of('local_biz_cart_ledger', 7)->amount, 0.0001);
    }

    public function test_no_ledger_row_is_invented(): void {
        global $DB;
        $this->apply();
        // Order 1000001 has no BizLMS ledger row and gets none (decision cart.synthesize_ledger = false).
        $history = (int) $this->map_row('local_biz_cart_history', 1)->targetid;
        $this->assertSame(0, $DB->count_records('local_sentientia_cart_ledger', ['historyid' => $history]));
        $this->assertSame(9, $DB->count_records('local_sentientia_cart_ledger'));
    }

    // Invoices.

    public function test_invoices_are_references_to_erpnext_never_numbers_sentientia_issued(): void {
        global $DB;
        $this->apply();
        $invoice = $this->target_of('local_biz_cart_invoices', 1);
        $this->assertSame('ERPNEXT-ACC-SINV-2025-00042', $invoice->invoice_number);
        $this->assertSame('legacy_external', $invoice->status);
        $this->assertSame(self::BASE + 1, (int) $invoice->orderid);
        $this->assertSame((int) $this->map_row('local_biz_cart_history', 1)->targetid, (int) $invoice->historyid);
        $this->assertSame($this->ids['a'], (int) $invoice->userid);
        $this->assertSame(1, (int) $invoice->costcenterid);
        $this->assertSame('', $invoice->billing_name);
        $this->assertEqualsWithDelta(1882.01, (float) $invoice->subtotal, 0.0001, 'subtotal less discount');
        $this->assertEqualsWithDelta(2080.00, (float) $invoice->total, 0.0001);
        $this->assertEqualsWithDelta(0.0, (float) $invoice->cgst + (float) $invoice->sgst + (float) $invoice->igst, 0.0001,
            'ERPNext worked the tax out');
        $this->assertSame(self::T0 + 900, (int) $invoice->timecreated);
        $this->assertSame(0, $DB->count_records_select('local_sentientia_cart_invoices', $DB->sql_like('invoice_number', ':p'),
            ['p' => 'AIRPAY-%']), 'the import issues no Sentientia invoice number');

        $this->assertSame('merged', $this->map_row('local_biz_cart_invoices', 4)->outcome);
        $this->assertSame('dup_invoice_number', $this->map_row('local_biz_cart_invoices', 4)->reason);
        $this->assertSame('invoice_without_order', $this->map_row('local_biz_cart_invoices', 2)->reason);
        $this->assertSame('invoice_without_number', $this->map_row('local_biz_cart_invoices', 3)->reason);
        $this->assertSame('invoice_number_too_long', $this->map_row('local_biz_cart_invoices', 5)->reason);
        $this->assertSame(1, $DB->count_records('local_sentientia_cart_invoices'));
    }

    // Credits.

    public function test_the_credit_journal_and_the_balance_each_user_held(): void {
        global $DB;
        $this->apply();
        $earned = $this->target_of('local_biz_cart_credits', 1);
        $this->assertSame('earned_cancellation', $earned->event_type, 'matched to the cancel ledger row within five seconds');
        $this->assertEqualsWithDelta(500.00, (float) $earned->amount, 0.0001);
        $this->assertEqualsWithDelta(500.00, (float) $earned->balance_after, 0.0001);
        $this->assertSame((int) $this->map_row('local_biz_cart_ledger', 2)->targetid, (int) $earned->ledgerid);
        $this->assertSame((int) $this->map_row('local_biz_cart_history', 3)->targetid, (int) $earned->historyid);
        $this->assertSame(self::BASE + 2, (int) $earned->orderid);
        $this->assertSame(77, (int) $earned->costcenterid);
        $this->assertSame($this->ids['b'], (int) $earned->initiatedby);
        $this->assertSame('redeemed', $this->target_of('local_biz_cart_credits', 2)->event_type);
        $this->assertSame('payout', $this->target_of('local_biz_cart_credits', 3)->event_type);
        $this->assertSame('correction', $this->target_of('local_biz_cart_credits', 4)->event_type,
            'one second after the ledger row is inside the five-second window');
        $this->assertSame('legacy_unclassified', $this->target_of('local_biz_cart_credits', 6)->event_type);

        $this->assertSame('currency_not_inr', $this->map_row('local_biz_cart_credits', 5)->reason);
        $this->assertSame('orphan_user', $this->map_row('local_biz_cart_credits', 7)->reason);
        $this->assertSame(5, $DB->count_records('local_sentientia_cart_credit_txn'));

        // The balance each user held: the newest booking's, with lifetime earned and spent from the journal.
        $b = $DB->get_record('local_sentientia_cart_credits', ['userid' => $this->ids['b']], '*', MUST_EXIST);
        $this->assertEqualsWithDelta(325.00, (float) $b->balance, 0.0001);
        $this->assertEqualsWithDelta(500.00, (float) $b->lifetime_earned, 0.0001);
        $this->assertEqualsWithDelta(175.00, (float) $b->lifetime_spent, 0.0001, 'payouts and corrections count as spent');
        $this->assertSame('INR', $b->currency);
        $a = $DB->get_record('local_sentientia_cart_credits', ['userid' => $this->ids['a']], '*', MUST_EXIST);
        $this->assertEqualsWithDelta(20.00, (float) $a->balance, 0.0001);
        $this->assertSame(2, $DB->count_records('local_sentientia_cart_credits'));
    }

    // What the import must never do.

    public function test_the_legacy_tables_are_never_changed(): void {
        $this->contract_begin();
        $this->contract_seed();
        $before = $this->legacy_snapshot();
        $this->contract_run(true);
        $this->assertSame($before, $this->legacy_snapshot());
    }

    public function test_mark_paid_mark_failed_and_refund_refuse_an_imported_order(): void {
        global $DB;
        $this->apply();
        foreach ([self::BASE + 1, self::BASE + 2, self::BASE + 4] as $number) {
            $order = $this->order($number);
            $before = [$order->status, $DB->count_records('local_sentientia_cart_ledger')];

            foreach ([
                'mark_paid' => static fn() => cart_manager::mark_paid((int) $order->id, 'ref'),
                'mark_failed' => static fn() => cart_manager::mark_failed((int) $order->id, 'declined'),
                'refund' => static fn() => cart_manager::refund((int) $order->id, 0.0, 'test', 2),
            ] as $what => $call) {
                try {
                    $call();
                    $this->fail("{$what} must refuse the imported order {$number}");
                } catch (\moodle_exception $e) {
                    $this->assertSame('error_invalidstate', $e->errorcode, "{$what} on {$number}");
                }
            }
            $this->assertSame($before, [$DB->get_field('local_sentientia_cart_history', 'status', ['id' => $order->id]),
                $DB->count_records('local_sentientia_cart_ledger')], 'nothing was written');
        }
    }

    public function test_the_reader_flags_ship_off(): void {
        $this->resetAfterTest();
        $registry = \local_sentientia_platform\feature_flags::load_registry();
        foreach ([imported_history::FLAG_ORDERS, imported_history::FLAG_CREDITS] as $key) {
            $this->assertArrayHasKey($key, $registry, "{$key} is registered in db/feature_flags.php");
            $this->assertFalse($registry[$key]['default'], "{$key} defaults to OFF");
        }
        $this->assertFalse(imported_history::orders_enabled());
        $this->assertFalse(imported_history::credits_enabled());
    }
}
