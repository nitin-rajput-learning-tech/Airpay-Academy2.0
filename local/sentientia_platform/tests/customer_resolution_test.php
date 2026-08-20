<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

namespace local_sentientia_platform;

/**
 * Phase 2.1 customer resolution — customer::current() de-hardwired via the
 * tenant registry (ADR-028 / ADR-021 Gate B).
 *
 * Covers the one-way-door carefully: with the registry DORMANT (the default
 * and every production deployment) behaviour must be byte-identical to
 * Phase 0 (AIRPAY, always); with the registry LIVE, the customer follows the
 * caller's tenant root through tenant_registry::customer_of().
 *
 * @package    local_sentientia_platform
 * @covers     \local_sentientia_platform\customer
 */
final class customer_resolution_test extends \advanced_testcase {

    use \local_sentientia_org\test\bizlms_fixture;

    protected function setUp(): void {
        parent::setUp();
        $this->ensure_bizlms_schema();
    }

    /**
     * Seed a registry: customer-zero (airpay, id from insert) owning root 1,
     * plus a second customer owning root 500. Returns [$airpayid, $demoid].
     */
    private function seed_registry(): array {
        global $DB;
        $now = time();
        $airpayid = (int) $DB->insert_record('local_sentientia_customer', (object) [
            'name' => 'Airpay Payment Services', 'shortname' => 'airpay',
            'status' => 'active', 'timecreated' => $now, 'timemodified' => $now,
        ]);
        $demoid = (int) $DB->insert_record('local_sentientia_customer', (object) [
            'name' => '[DEMO] Meridian Financial Services', 'shortname' => 'meridian',
            'status' => 'active', 'timecreated' => $now, 'timemodified' => $now,
        ]);
        foreach ([[1, $airpayid, 'Airpay'], [500, $demoid, 'Meridian HQ']] as [$root, $cid, $name]) {
            $DB->insert_record('local_sentientia_tenant', (object) [
                'rootid' => $root, 'customerid' => $cid, 'name' => $name,
                'idnumber' => '', 'status' => 'active',
                'timecreated' => $now, 'timemodified' => $now,
            ]);
        }
        return [$airpayid, $demoid];
    }

    /**
     * A user with the given open_path, set as the current user.
     */
    private function login_with_path(string $path): \stdClass {
        global $DB;
        $user = $this->getDataGenerator()->create_user();
        $DB->set_field('user', 'open_path', $path, ['id' => $user->id]);
        $user = $DB->get_record('user', ['id' => $user->id]);
        $this->setUser($user);
        return $user;
    }

    /**
     * Registry DORMANT (default): everyone is customer-zero — including a
     * user whose tenant root would map elsewhere if the registry were live.
     */
    public function test_dormant_registry_is_phase0_identical(): void {
        $this->resetAfterTest();
        $this->seed_registry();
        // tenant_registry_legacy defaults ON (unset config treated as legacy).
        $this->login_with_path('/500');
        $this->assertSame(customer::AIRPAY, customer::current());
        $this->login_with_path('/1/2/3');
        $this->assertSame(customer::AIRPAY, customer::current());
    }

    /**
     * Registry LIVE: the customer follows the caller's tenant root.
     */
    public function test_live_registry_resolves_customer_from_tenant(): void {
        $this->resetAfterTest();
        [$airpayid, $demoid] = $this->seed_registry();
        set_config('tenant_registry_legacy', 0, 'local_sentientia_core');

        $this->login_with_path('/500');
        $this->assertSame($demoid, customer::current());

        $this->login_with_path('/1/2/3');
        $this->assertSame($airpayid, customer::current());
    }

    /**
     * Registry LIVE, unscoped callers: site admins and tenantless users
     * stay customer-zero (never an exception, never NO_CUSTOMER).
     */
    public function test_live_registry_unscoped_callers_stay_customer_zero(): void {
        $this->resetAfterTest();
        $this->seed_registry();
        set_config('tenant_registry_legacy', 0, 'local_sentientia_core');

        $this->setAdminUser();
        $this->assertSame(customer::AIRPAY, customer::current());

        $this->login_with_path('');   // No tenant scope.
        $this->assertSame(customer::AIRPAY, customer::current());

        // Unknown root: customer_of() returns NO_CUSTOMER → fall back to zero.
        $this->login_with_path('/999');
        $this->assertSame(customer::AIRPAY, customer::current());
    }

    /**
     * Suspended tenants stop resolving to their customer (status filter).
     */
    public function test_suspended_tenant_falls_back_to_customer_zero(): void {
        global $DB;
        $this->resetAfterTest();
        [, $demoid] = $this->seed_registry();
        set_config('tenant_registry_legacy', 0, 'local_sentientia_core');

        $this->login_with_path('/500');
        $this->assertSame($demoid, customer::current());

        $DB->set_field('local_sentientia_tenant', 'status', 'suspended', ['rootid' => 500]);
        $this->assertSame(customer::AIRPAY, customer::current());
    }
}
