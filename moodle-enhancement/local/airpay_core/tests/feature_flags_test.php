<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

namespace local_airpay_core;

defined('MOODLE_INTERNAL') || die();

/**
 * @covers \local_airpay_core\feature_flags
 *
 * Phase A0 (2026-05-14) — regression suite for the feature-flag
 * resolver. Tests the four-step resolution order
 * (tenant-override → global-override → registered-default → false)
 * plus the override write path, audit log, and unknown-key handling.
 */
class feature_flags_test extends \advanced_testcase {

    public function setUp(): void {
        parent::setUp();
        $this->resetAfterTest(true);
        // Each test starts with a clean cache so registry / overrides
        // reload from disk + DB without prior-test contamination.
        feature_flags::invalidate_caches();
    }

    public function test_registered_default_returns_when_no_override(): void {
        // `ai.assistant.enabled` is declared default = true in
        // local/airpay_core/db/feature_flags.php.
        $this->assertTrue(
            feature_flags::is_enabled_for_tenant('ai.assistant.enabled', 0));
        // `ai.sentientia.enabled` is declared default = false.
        $this->assertFalse(
            feature_flags::is_enabled_for_tenant('ai.sentientia.enabled', 0));
    }

    public function test_unknown_key_returns_false_safely(): void {
        // The resolver logs a debug warning but MUST NOT throw —
        // callers shouldn't have to defend against typos.
        $this->assertFalse(
            feature_flags::is_enabled_for_tenant('does.not.exist', 0));
        $this->assertDebuggingCalled();
    }

    public function test_set_creates_override_row(): void {
        global $DB;
        $admin = get_admin();
        feature_flags::set('ai.assistant.enabled', 0, false, $admin->id, 'test');
        $row = $DB->get_record('local_airpay_feature_flags',
            ['flag_key' => 'ai.assistant.enabled', 'tenant_id' => 0]);
        $this->assertNotEmpty($row);
        $this->assertSame(0, (int) $row->is_enabled);
        // is_enabled now returns the override.
        $this->assertFalse(
            feature_flags::is_enabled_for_tenant('ai.assistant.enabled', 0));
    }

    public function test_tenant_override_wins_over_global(): void {
        $admin = get_admin();
        // Global says OFF.
        feature_flags::set('ai.assistant.enabled', 0, false, $admin->id);
        // Tenant 1 says ON.
        feature_flags::set('ai.assistant.enabled', 1, true, $admin->id);

        $this->assertFalse(
            feature_flags::is_enabled_for_tenant('ai.assistant.enabled', 0));
        $this->assertTrue(
            feature_flags::is_enabled_for_tenant('ai.assistant.enabled', 1));
        // Tenant 77 has no override — inherits the global OFF.
        $this->assertFalse(
            feature_flags::is_enabled_for_tenant('ai.assistant.enabled', 77));
    }

    public function test_null_value_reverts_to_default(): void {
        global $DB;
        $admin = get_admin();
        feature_flags::set('ai.assistant.enabled', 0, false, $admin->id);
        $this->assertFalse(
            feature_flags::is_enabled_for_tenant('ai.assistant.enabled', 0));

        // Revert.
        feature_flags::set('ai.assistant.enabled', 0, null, $admin->id);

        // Row is gone.
        $this->assertFalse($DB->record_exists('local_airpay_feature_flags',
            ['flag_key' => 'ai.assistant.enabled', 'tenant_id' => 0]));
        // Falls back to registered default (true).
        $this->assertTrue(
            feature_flags::is_enabled_for_tenant('ai.assistant.enabled', 0));
    }

    public function test_set_writes_audit_row(): void {
        global $DB;
        $admin = get_admin();

        $before = $DB->count_records('local_airpay_feature_flag_audit',
            ['flag_key' => 'ai.assistant.enabled']);

        feature_flags::set('ai.assistant.enabled', 0, false, $admin->id, 'reason A');
        feature_flags::set('ai.assistant.enabled', 0, true,  $admin->id, 'reason B');
        feature_flags::set('ai.assistant.enabled', 0, null,  $admin->id, '');

        $after = $DB->count_records('local_airpay_feature_flag_audit',
            ['flag_key' => 'ai.assistant.enabled']);
        $this->assertSame($before + 3, $after);

        // Verify the trail is in order. Cast int columns explicitly
        // because Moodle's DB layer returns INT(1) values as PHP
        // strings ('0', '1') rather than native ints.
        $rows = $DB->get_records('local_airpay_feature_flag_audit',
            ['flag_key' => 'ai.assistant.enabled'], 'id ASC', '*', 0, 10);
        $values = array_values(array_map(
            fn($r) => [
                $r->old_value === null ? null : (int) $r->old_value,
                $r->new_value === null ? null : (int) $r->new_value,
                $r->reason,
            ],
            $rows));

        // First write: NULL → 0 with "reason A".
        $this->assertSame([null, 0, 'reason A'], $values[count($values) - 3]);
        // Second: 0 → 1 with "reason B".
        $this->assertSame([0, 1, 'reason B'], $values[count($values) - 2]);
        // Third: 1 → NULL with empty reason (stored as null).
        $this->assertSame([1, null, null], $values[count($values) - 1]);
    }

    public function test_set_with_same_value_is_noop(): void {
        global $DB;
        $admin = get_admin();
        $before = $DB->count_records('local_airpay_feature_flag_audit');

        // Set to true (matches registered default), then again. Both should be no-ops.
        feature_flags::set('ai.assistant.enabled', 0, true, $admin->id);
        feature_flags::set('ai.assistant.enabled', 0, true, $admin->id);

        // First call creates an override row + audit row (off-state was "default", new is "true").
        // Second call is a no-op (override already = true).
        $audit_count = $DB->count_records('local_airpay_feature_flag_audit') - $before;
        $this->assertLessThanOrEqual(1, $audit_count,
            'Second set() to same value must not write a duplicate audit row');
    }

    public function test_set_with_unknown_key_throws(): void {
        $admin = get_admin();
        $this->expectException(\moodle_exception::class);
        feature_flags::set('does.not.exist', 0, true, $admin->id);
    }

    public function test_all_returns_every_registered_flag(): void {
        $all = feature_flags::all(0);
        $this->assertNotEmpty($all);
        // The 5 seeded flags from local_airpay_core/db/feature_flags.php
        // must always be present.
        $required = [
            'ai.assistant.enabled',
            'ai.sentientia.enabled',
            'engagement.gamification.enabled',
            'engagement.gamification.confetti',
            'commerce.crossTenantShare.enabled',
            'commerce.crossTenantRequest.enabled',
        ];
        foreach ($required as $key) {
            $this->assertArrayHasKey($key, $all,
                "Seeded flag '$key' missing from registry");
        }
    }

    public function test_all_reflects_tenant_override_in_resolved(): void {
        $admin = get_admin();
        feature_flags::set('ai.assistant.enabled', 1, false, $admin->id);

        $for_global = feature_flags::all(0);
        $for_tenant_1 = feature_flags::all(1);

        // Global view: still ON (registered default).
        $this->assertTrue($for_global['ai.assistant.enabled']['resolved']);
        // Tenant 1 view: OFF (override active).
        $this->assertFalse($for_tenant_1['ai.assistant.enabled']['resolved']);
        $this->assertTrue($for_tenant_1['ai.assistant.enabled']['has_tenant_override']);
    }

    public function test_recent_audit_filters_by_key_prefix(): void {
        $admin = get_admin();
        // Generate audit history for two distinct categories.
        feature_flags::set('ai.assistant.enabled', 0, false, $admin->id);
        feature_flags::set('engagement.gamification.enabled', 0, false, $admin->id);

        // Filter for ai.* prefix should return only ai.* rows.
        $ai_only = feature_flags::recent_audit(100, 'ai.');
        foreach ($ai_only as $row) {
            $this->assertStringStartsWith('ai.', $row->flag_key);
        }
        // At least one row matches the filter.
        $this->assertNotEmpty($ai_only);
    }

    // ════════════════════════════════════════════════════════════════
    //  Session 2 / ADR-002 (2026-05-20) — customer-level scope tests
    // ════════════════════════════════════════════════════════════════

    /**
     * The gate flag itself is registered and defaults to FALSE so all
     * pre-Session-2 callers continue to see the legacy 3-level resolution.
     */
    public function test_customer_layer_gate_flag_registered_and_default_off(): void {
        $registry = feature_flags::load_registry();
        $this->assertArrayHasKey(feature_flags::CUSTOMER_LEVEL_FLAG, $registry);
        $this->assertFalse(
            (bool) $registry[feature_flags::CUSTOMER_LEVEL_FLAG]['default']);
    }

    /**
     * When the gate is OFF, is_enabled_for() returns identically to the
     * legacy 3-level resolver — customer-scoped DB rows are inert.
     */
    public function test_customer_scoped_row_inert_when_gate_off(): void {
        global $DB;
        $admin = get_admin();

        // Insert a customer-scoped override row directly via DB so we
        // bypass set()'s gate-off guard. This simulates a row that
        // existed before the gate was turned off again.
        $DB->insert_record('local_airpay_feature_flags', (object) [
            'flag_key'     => 'ai.assistant.enabled',
            'customer_id'  => 1,
            'tenant_id'    => 0,
            'is_enabled'   => 0,  // would set ai.assistant.enabled OFF for customer 1
            'modified_by'  => $admin->id,
            'timecreated'  => time(),
            'timemodified' => time(),
        ]);
        feature_flags::invalidate_caches();

        // Gate is OFF (default). is_enabled_for() should ignore the
        // customer row and return the registered default (true).
        $this->assertTrue(
            feature_flags::is_enabled_for('ai.assistant.enabled', 1, 0));
        $this->assertTrue(
            feature_flags::is_enabled_for('ai.assistant.enabled', 1, 77));
    }

    /**
     * When the gate is ON, a customer-wide override applies to every
     * tenant within that customer, but does not bleed across customers.
     */
    public function test_customer_scoped_override_applies_within_customer_only(): void {
        $admin = get_admin();
        // Enable the gate via the legacy global path (gate has no
        // customer scope itself — global is the legitimate place).
        feature_flags::set(feature_flags::CUSTOMER_LEVEL_FLAG, 0, true, $admin->id);

        // Set a customer-1 (Airpay) wide override to OFF.
        feature_flags::set('ai.assistant.enabled', 0, false, $admin->id,
            'turn off for airpay', customer::AIRPAY);

        // Tenant 1 under customer 1 sees the customer-wide value (OFF).
        $this->assertFalse(
            feature_flags::is_enabled_for('ai.assistant.enabled',
                customer::AIRPAY, 1));
        // Tenant 77 under customer 1 also sees the customer-wide value.
        $this->assertFalse(
            feature_flags::is_enabled_for('ai.assistant.enabled',
                customer::AIRPAY, 77));
        // A view with customer=0 doesn't match the customer-1 row;
        // falls back to the registered default (true).
        $this->assertTrue(
            feature_flags::is_enabled_for('ai.assistant.enabled', 0, 1));
    }

    /**
     * Tenant-within-customer override wins over customer-wide override.
     */
    public function test_tenant_within_customer_wins_over_customer_wide(): void {
        $admin = get_admin();
        feature_flags::set(feature_flags::CUSTOMER_LEVEL_FLAG, 0, true, $admin->id);

        // Customer 1 wide: OFF.
        feature_flags::set('ai.assistant.enabled', 0, false, $admin->id,
            'customer-wide off', customer::AIRPAY);
        // Customer 1, tenant 77 specifically: ON.
        feature_flags::set('ai.assistant.enabled', 77, true, $admin->id,
            'tenant 77 override', customer::AIRPAY);

        // Tenant 77 under customer 1: ON (most specific wins).
        $this->assertTrue(
            feature_flags::is_enabled_for('ai.assistant.enabled',
                customer::AIRPAY, 77));
        // Tenant 1 under customer 1: still OFF (customer-wide).
        $this->assertFalse(
            feature_flags::is_enabled_for('ai.assistant.enabled',
                customer::AIRPAY, 1));
    }

    /**
     * Customer-wide override wins over a legacy (customer_id=0) tenant
     * override — when the gate is ON. Step 2 > Step 3 in the precedence.
     */
    public function test_customer_wide_wins_over_legacy_tenant_when_gate_on(): void {
        $admin = get_admin();
        feature_flags::set(feature_flags::CUSTOMER_LEVEL_FLAG, 0, true, $admin->id);

        // Legacy tenant override on tenant 1: ON.
        feature_flags::set('ai.assistant.enabled', 1, true, $admin->id);
        // Customer 1 wide: OFF.
        feature_flags::set('ai.assistant.enabled', 0, false, $admin->id,
            'customer-wide off', customer::AIRPAY);

        // Customer 1, tenant 1: customer-wide value (OFF) wins.
        $this->assertFalse(
            feature_flags::is_enabled_for('ai.assistant.enabled',
                customer::AIRPAY, 1));
    }

    /**
     * Customer-scoped writes are rejected when the gate is OFF.
     * Assert on $errorcode (the lang key) rather than the rendered message
     * — Moodle resolves the lang key into the user's locale at exception
     * time, so message-text matching is locale-dependent.
     */
    public function test_customer_scoped_write_rejected_when_gate_off(): void {
        $admin = get_admin();
        $caught = null;
        try {
            feature_flags::set('ai.assistant.enabled', 0, false, $admin->id,
                'should fail', customer::AIRPAY);
        } catch (\moodle_exception $e) {
            $caught = $e;
        }
        $this->assertNotNull($caught,
            'Expected moodle_exception when writing customer-scoped row with gate off');
        $this->assertSame('customer_layer_disabled', $caught->errorcode);
    }

    /**
     * The gate flag itself cannot be set at customer scope — meta-flag
     * semantics: it governs OTHER flags' customer scope, has none itself.
     */
    public function test_gate_flag_rejects_customer_scope_write(): void {
        $admin = get_admin();
        // Turn the gate ON first so the customer-layer-disabled guard
        // doesn't fire first; we want to test the gate-specific guard.
        feature_flags::set(feature_flags::CUSTOMER_LEVEL_FLAG, 0, true, $admin->id);

        $caught = null;
        try {
            feature_flags::set(feature_flags::CUSTOMER_LEVEL_FLAG, 0, true,
                $admin->id, '', customer::AIRPAY);
        } catch (\moodle_exception $e) {
            $caught = $e;
        }
        $this->assertNotNull($caught,
            'Expected moodle_exception when writing gate flag at customer scope');
        $this->assertSame('gateflag_no_customer_scope', $caught->errorcode);
    }

    /**
     * customer::current() returns AIRPAY in Phase 0/1 — verifies the
     * helper contract for code that already calls it.
     */
    public function test_customer_current_returns_airpay_in_phase_one(): void {
        $this->assertSame(customer::AIRPAY, customer::current());
    }

    /**
     * The all() summary surfaces has_customer_override + has_tenant_override
     * separately so the Switchboard can render them with distinct badges.
     */
    public function test_all_distinguishes_customer_vs_tenant_overrides(): void {
        $admin = get_admin();
        feature_flags::set(feature_flags::CUSTOMER_LEVEL_FLAG, 0, true, $admin->id);

        // Set a customer-wide override (customer=1, tenant=0).
        feature_flags::set('ai.assistant.enabled', 0, false, $admin->id,
            'customer-wide', customer::AIRPAY);

        $summary = feature_flags::all(0, customer::AIRPAY);  // viewing customer=1, tenant=0

        $this->assertArrayHasKey('ai.assistant.enabled', $summary);
        $flag = $summary['ai.assistant.enabled'];
        $this->assertTrue($flag['has_customer_override']);
        $this->assertFalse($flag['has_tenant_override']);
        $this->assertFalse($flag['has_legacy_tenant_override']);
    }
}
