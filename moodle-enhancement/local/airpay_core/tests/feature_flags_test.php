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
}
