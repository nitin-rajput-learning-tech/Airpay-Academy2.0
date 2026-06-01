<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

namespace local_sentientia_core;

defined('MOODLE_INTERNAL') || die();

/**
 * Unit tests for the ADR-021 Wave-4 tenant_registry seam.
 *
 * CI-safe: relies on no production data. The only registry rows are the ones
 * each test inserts itself (resetAfterTest restores between cases). Auto-discovered
 * by the CI phpunit-52 gate for local/sentientia_*.
 *
 * @package    local_sentientia_core
 * @covers     \local_sentientia_core\tenant_registry
 * @copyright  2026 Airpay Payment Services
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class tenant_registry_test extends \advanced_testcase {

    // ── Default-ON legacy flag ────────────────────────────────────────────

    public function test_legacy_is_on_by_default(): void {
        $this->resetAfterTest(true);
        // Unset config must read as ON so production behaviour never changes.
        $this->assertTrue(tenant_registry::use_legacy_registry());
    }

    public function test_legacy_flag_can_be_turned_off(): void {
        $this->resetAfterTest(true);
        set_config('tenant_registry_legacy', 0, 'local_sentientia_core');
        $this->assertFalse(tenant_registry::use_legacy_registry());
    }

    // ── valid_roots() / is_valid() — legacy path ──────────────────────────

    public function test_valid_roots_legacy_matches_allowlist(): void {
        $this->resetAfterTest(true);
        // Sorted, de-duplicated [1, 77, 177].
        $this->assertSame([1, 77, 177], tenant_registry::valid_roots());
    }

    public function test_is_valid_legacy(): void {
        $this->resetAfterTest(true);
        $this->assertTrue(tenant_registry::is_valid(1));
        $this->assertTrue(tenant_registry::is_valid(77));
        $this->assertTrue(tenant_registry::is_valid(177));
        $this->assertFalse(tenant_registry::is_valid(0));
        $this->assertFalse(tenant_registry::is_valid(2));
        $this->assertFalse(tenant_registry::is_valid(999));
    }

    public function test_assert_valid_passes_and_throws(): void {
        $this->resetAfterTest(true);
        // Valid root → no exception.
        tenant_registry::assert_valid(77);
        // Invalid root → moodle_exception.
        $this->expectException(\moodle_exception::class);
        tenant_registry::assert_valid(999);
    }

    // ── customer_of() / roots_for_customer() — legacy path ────────────────

    public function test_customer_of_legacy(): void {
        $this->resetAfterTest(true);
        $this->assertSame(tenant_registry::DEFAULT_CUSTOMER, tenant_registry::customer_of(1));
        $this->assertSame(tenant_registry::DEFAULT_CUSTOMER, tenant_registry::customer_of(177));
        $this->assertSame(tenant_registry::NO_CUSTOMER, tenant_registry::customer_of(999));
    }

    public function test_roots_for_customer_legacy(): void {
        $this->resetAfterTest(true);
        $this->assertSame([1, 77, 177],
            tenant_registry::roots_for_customer(tenant_registry::DEFAULT_CUSTOMER));
        $this->assertSame([], tenant_registry::roots_for_customer(42));
    }

    // ── OFF path with an EMPTY registry → legacy fallback + debug note ─────

    public function test_off_empty_registry_falls_back_to_legacy(): void {
        $this->resetAfterTest(true);
        set_config('tenant_registry_legacy', 0, 'local_sentientia_core');
        // Tables exist (install.xml ran for the test DB) but carry no rows.
        $this->assertSame([1, 77, 177], tenant_registry::valid_roots());
        // The fallback must announce itself for developers.
        $this->assertDebuggingCalled();
    }

    // ── OFF path reading the registry tables ──────────────────────────────

    public function test_off_reads_registry_table(): void {
        global $DB;
        $this->resetAfterTest(true);
        $now = 1700000000; // Fixed stamp — Date.now() is not used in tests.

        $customerid = $DB->insert_record('local_sentientia_customer', (object) [
            'name' => 'Enterprise N', 'shortname' => 'entn', 'status' => 'active',
            'timecreated' => $now, 'timemodified' => $now,
        ]);
        $DB->insert_record('local_sentientia_tenant', (object) [
            'rootid' => 500, 'customerid' => $customerid, 'name' => 'EntN Root',
            'idnumber' => 'EXT-500', 'status' => 'active',
            'timecreated' => $now, 'timemodified' => $now,
        ]);
        // A suspended row must NOT appear in the active allow-list.
        $DB->insert_record('local_sentientia_tenant', (object) [
            'rootid' => 501, 'customerid' => $customerid, 'name' => 'EntN Archived',
            'idnumber' => 'EXT-501', 'status' => 'suspended',
            'timecreated' => $now, 'timemodified' => $now,
        ]);

        set_config('tenant_registry_legacy', 0, 'local_sentientia_core');

        $this->assertSame([500], tenant_registry::valid_roots());
        $this->assertTrue(tenant_registry::is_valid(500));
        $this->assertFalse(tenant_registry::is_valid(501));   // suspended
        $this->assertFalse(tenant_registry::is_valid(1));     // legacy root not in table
        $this->assertSame($customerid, tenant_registry::customer_of(500));
        $this->assertSame([500], tenant_registry::roots_for_customer($customerid));
    }

    // ── Legacy ON must IGNORE table rows (production behaviour unchanged) ──

    public function test_legacy_on_ignores_registry_table(): void {
        global $DB;
        $this->resetAfterTest(true);
        $now = 1700000000;
        $DB->insert_record('local_sentientia_tenant', (object) [
            'rootid' => 500, 'customerid' => 1, 'name' => 'Should be ignored',
            'status' => 'active', 'timecreated' => $now, 'timemodified' => $now,
        ]);
        // Flag is ON (default) → the hardcoded allow-list wins regardless of the table.
        $this->assertSame([1, 77, 177], tenant_registry::valid_roots());
        $this->assertFalse(tenant_registry::is_valid(500));
    }
}
