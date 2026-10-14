<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

namespace local_sentientia_cart;

defined('MOODLE_INTERNAL') || die();

use local_sentientia_platform\feature_flags;

/**
 * Shared set-up for the GST tax invoice, credit note and receipt tests (spec 2026-10-14, section 11).
 *
 * A trait, not a base class, so it serves both advanced_testcase and the privacy provider_testcase. This file has no
 * _test suffix, so PHPUnit does not run it; each test requires it.
 *
 * What it gives: a complete tax configuration (the supplier is in Maharashtra, state 27, GSTIN
 * 27AAAAA0000A1Z5), the two feature flags, users and tenant administrators at tenant paths, and orders in the shape
 * checkout leaves them: one course line of 1000.00, 18% GST recorded (180.00), total 1180.00.
 *
 * @package    local_sentientia_cart
 * @category   test
 * @copyright  2026 Airpay Payment Services
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
trait gst_helpers {

    use \local_sentientia_org\test\bizlms_fixture;

    /** @var int Counter for order numbers. */
    private static int $nextorder = 5000100;

    /**
     * Reset after the test, add the BizLMS columns and put a complete tax configuration in place. Both flags are
     * left at their registered default, OFF.
     *
     * @return void
     */
    protected function gst_setup(): void {
        $this->resetAfterTest();
        $this->ensure_bizlms_schema();
        $this->configure_tax();
    }

    /**
     * Write the tax settings. Pass overrides to change or blank (with '') any of them.
     *
     * @param array $overrides setting => value
     * @return void
     */
    protected function configure_tax(array $overrides = []): void {
        $settings = $overrides + [
            'company_name'     => 'Airpay Test Pvt Ltd',
            'company_address'  => "Airpay House\nA-wing, Office Building\nMumbai 400063, India",
            'our_gstn'         => '27AAAAA0000A1Z5',
            'sac_code'         => '999293',
            'invoice_prefix'   => 'AIRPAY',
            'credit_note_prefix' => 'AIRCN',
            'gst_rate'         => '18',
            'series_period'    => 'calendar',
            'einvoice_b2b'     => '1',
            'invoice_retention_years' => '8',
            'supplier_state_code' => '',
            'signatory_text'   => 'Authorised signatory',
        ];
        foreach ($settings as $name => $value) {
            set_config($name, $value, 'local_sentientia_cart');
        }
    }

    /**
     * Switch a flag for everybody (tenant 0), or for one tenant root.
     *
     * @param string $key flags::INVOICES or flags::RECEIPTS
     * @param bool $on
     * @param int $tenant 0 for every tenant
     * @return void
     */
    protected function flag(string $key, bool $on, int $tenant = 0): void {
        feature_flags::set($key, $tenant, $on, (int) get_admin()->id);
    }

    /**
     * A user with an open_path, re-read so the record carries it.
     *
     * @param string $path e.g. '/77'
     * @return \stdClass
     */
    protected function user_at(string $path): \stdClass {
        global $DB;
        $u = $this->getDataGenerator()->create_user();
        $DB->set_field('user', 'open_path', $path, ['id' => $u->id]);
        return $DB->get_record('user', ['id' => $u->id], '*', MUST_EXIST);
    }

    /**
     * A user at a tenant path who holds the manager role at system context: a tenant admin, who holds
     * :viewallorders but not :manageinvoices.
     *
     * @param string $path
     * @return \stdClass
     */
    protected function tenant_admin(string $path): \stdClass {
        global $DB;
        $u = $this->user_at($path);
        $managerid = (int) $DB->get_field('role', 'id', ['shortname' => 'manager'], MUST_EXIST);
        role_assign($managerid, $u->id, \context_system::instance()->id);
        accesslib_clear_all_caches_for_unit_testing();
        return $DB->get_record('user', ['id' => $u->id], '*', MUST_EXIST);
    }

    /**
     * A user at a tenant path who holds ONLY :manageinvoices (a Finance role granted nothing else).
     *
     * @param string $path
     * @return \stdClass
     */
    protected function finance_user(string $path): \stdClass {
        $u = $this->user_at($path);
        $roleid = $this->getDataGenerator()->create_role(['shortname' => 'gstfinance' . $u->id]);
        assign_capability('local/sentientia_cart:manageinvoices', CAP_ALLOW, $roleid, \context_system::instance()->id);
        role_assign($roleid, $u->id, \context_system::instance()->id);
        accesslib_clear_all_caches_for_unit_testing();
        return $u;
    }

    /**
     * A course the learners of a tenant may buy (visible, open_path set).
     *
     * @param string $path
     * @return \stdClass
     */
    protected function course_at(string $path): \stdClass {
        global $DB;
        $c = $this->getDataGenerator()->create_course(['visible' => 1]);
        $DB->set_field('course', 'open_path', $path, ['id' => $c->id]);
        return $DB->get_record('course', ['id' => $c->id], '*', MUST_EXIST);
    }

    /**
     * An order in the shape checkout leaves it: pending, with the recorded totals. Defaults: one line of 1000.00,
     * GST 18% (180.00), total 1180.00, buyer state 27 (Maharashtra), no GSTIN. Every column can be overridden.
     *
     * @param \stdClass $buyer
     * @param array $more column => value (items_json, subtotal, discount_amount, tax_amount, total_amount, gst_rate,
     *                    billing_state, billing_gstn, status, ...)
     * @return \stdClass The history row.
     */
    protected function order_for(\stdClass $buyer, array $more = []): \stdClass {
        global $DB;
        $path = (string) $buyer->open_path;
        $parts = explode('/', trim($path, '/'));
        $tenant = isset($parts[0]) && ctype_digit($parts[0]) ? (int) $parts[0] : 0;
        if (!empty($more['__course'])) {
            $course = $more['__course'];
            unset($more['__course']);
        } else {
            $course = $this->course_at($path !== '' ? $path : '/77');
        }
        $now = time();
        $row = (object) ($more + [
            'orderid'         => self::$nextorder++,
            'userid'          => (int) $buyer->id,
            'costcenterid'    => $tenant,
            'items_json'      => json_encode([['courseid' => (int) $course->id, 'name' => 'Course A',
                'price' => 1000.00, 'discount_pct' => 0]]),
            'subtotal'        => 1000.00,
            'discount_amount' => 0,
            'tax_amount'      => 180.00,
            'total_amount'    => 1180.00,
            'currency'        => 'INR',
            'status'          => 'pending',
            'gateway'         => 'airpay',
            'gateway_ref'     => '',
            'billing_name'    => 'Test Buyer',
            'billing_email'   => 'buyer@example.com',
            'billing_phone'   => '9999999999',
            'billing_address' => "1 Test Road\nPune 411001",
            'billing_gstn'    => '',
            'billing_state'   => '27',
            'gst_rate'        => 18.00,
            'notes'           => '',
            'timecreated'     => $now,
            'timepaid'        => 0,
            'timemodified'    => $now,
        ]);
        $row->id = $DB->insert_record('local_sentientia_cart_history', $row);
        return $DB->get_record('local_sentientia_cart_history', ['id' => $row->id], '*', MUST_EXIST);
    }

    /**
     * An order that has already been paid (status paid, timepaid set), without going through mark_paid().
     *
     * @param \stdClass $buyer
     * @param array $more
     * @return \stdClass
     */
    protected function paid_order_for(\stdClass $buyer, array $more = []): \stdClass {
        return $this->order_for($buyer, $more + ['status' => 'paid', 'timepaid' => time(), 'gateway_ref' => 'TXN-1']);
    }

    /**
     * Re-read an order.
     *
     * @param int $historyid
     * @return \stdClass
     */
    protected function order(int $historyid): \stdClass {
        global $DB;
        return $DB->get_record('local_sentientia_cart_history', ['id' => $historyid], '*', MUST_EXIST);
    }

    /**
     * The invoice of an order, or false.
     *
     * @param int $historyid
     * @return \stdClass|false
     */
    protected function invoice_of(int $historyid) {
        global $DB;
        return $DB->get_record('local_sentientia_cart_invoices', ['historyid' => $historyid]);
    }

    /**
     * Assert that a callable throws a moodle_exception with this error code.
     *
     * @param callable $fn
     * @param string $errorcode
     * @param string $why
     * @return void
     */
    protected function assert_refused(callable $fn, string $errorcode, string $why = ''): void {
        try {
            $fn();
            $this->fail($why !== '' ? $why : "Expected $errorcode");
        } catch (\moodle_exception $e) {
            $this->assertSame($errorcode, $e->errorcode, $why);
        }
    }

    /**
     * A UNIX time on a given Indian-time date.
     *
     * @param string $datetime e.g. '2027-04-01 01:30'
     * @return int
     */
    protected function ist(string $datetime): int {
        return (new \DateTimeImmutable($datetime, new \DateTimeZone('Asia/Kolkata')))->getTimestamp();
    }
}
